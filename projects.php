<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUserData();
$theme = getTheme();
$language = getLanguage();

// Обработка закрепления проекта
if (isset($_GET['pin']) && isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);
    $pin = intval($_GET['pin']);
    
    $stmt = $pdo->prepare("UPDATE projects SET pinned = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$pin, $project_id, $_SESSION['user_id']]);
    
    header('Location: projects.php');
    exit;
}

// Обработка удаления проекта
if (isset($_GET['delete_project']) && isset($_GET['project_id'])) {
    $project_id = intval($_GET['project_id']);
    
    // Получаем информацию о проекте
    $stmt = $pdo->prepare("SELECT project_path FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $_SESSION['user_id']]);
    $project = $stmt->fetch();
    
    if ($project) {
        // Удаляем файлы из файловой системы
        if (is_dir($project['project_path'])) {
            deleteDirectory($project['project_path']);
        }
        
        // Удаляем записи из БД
        $stmt = $pdo->prepare("DELETE FROM project_files WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$project_id, $_SESSION['user_id']]);
        
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$project_id, $_SESSION['user_id']]);
        
        $success = "Проект успешно удален";
    }
}

// Получаем проекты пользователя
$stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY pinned DESC, last_updated DESC");
$stmt->execute([$_SESSION['user_id']]);
$projects = $stmt->fetchAll();

// Получаем статистику по языкам
$stmt = $pdo->prepare("
    SELECT 
        language,
        COUNT(*) as file_count,
        SUM(lines_of_code) as total_lines,
        SUM(filesize) as total_size
    FROM project_files 
    WHERE user_id = ?
    GROUP BY language
    ORDER BY total_lines DESC
");
$stmt->execute([$_SESSION['user_id']]);
$language_stats = $stmt->fetchAll();

// Обработка загрузки проекта
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['project_files'])) {
    $project_name = trim($_POST['project_name']);
    $project_description = trim($_POST['project_description']);
    
    // Настройки валидации
    $allowed_extensions = ['php', 'js', 'html', 'css', 'py', 'java', 'cpp', 'c', 'cs', 'jsx', 'tsx', 'ts', 'go', 'rb', 'rs', 'swift', 'kt', 'sql', 'json', 'xml', 'md'];
    $max_file_size = 10 * 1024 * 1024; // 10MB
    $max_total_size = 100 * 1024 * 1024; // 100MB
    
    if (empty($project_name)) {
        $error = "Введите название проекта";
    } elseif (count($_FILES['project_files']['name']) === 0) {
        $error = "Выберите файлы для загрузки";
    } else {
        try {
            // Проверка общего размера файлов
            $total_size = 0;
            for ($i = 0; $i < count($_FILES['project_files']['name']); $i++) {
                if ($_FILES['project_files']['error'][$i] === UPLOAD_ERR_OK) {
                    $total_size += $_FILES['project_files']['size'][$i];
                }
            }
            
            if ($total_size > $max_total_size) {
                $error = "Общий размер файлов превышает максимальный лимит в " . formatFileSize($max_total_size);
            } else {
                // Создаем папку для пользователя, если ее нет
                $user_dir = 'uploads/' . $_SESSION['user_id'] . '/' . time() . '_' . preg_replace('/[^a-zA-Z0-9]/', '_', $project_name);
                if (!is_dir($user_dir)) {
                    mkdir($user_dir, 0775, true);
                }
                
                // Создаем проект в БД
                $stmt = $pdo->prepare("INSERT INTO projects (user_id, name, description, project_path) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $project_name, $project_description, $user_dir]);
                $project_id = $pdo->lastInsertId();
                
                $total_lines = 0;
                $file_count = 0;
                $languages = [];
                
                // Обрабатываем каждый загруженный файл
                for ($i = 0; $i < count($_FILES['project_files']['name']); $i++) {
                    if ($_FILES['project_files']['error'][$i] === UPLOAD_ERR_OK) {
                        $filename = basename($_FILES['project_files']['name'][$i]);
                        
                        // Проверка расширения файла
                        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        if (!in_array($extension, $allowed_extensions)) {
                            $error = "Расширение .$extension не разрешено для файла '$filename'";
                            continue;
                        }
                        
                        // Проверка размера файла
                        if ($_FILES['project_files']['size'][$i] > $max_file_size) {
                            $error = "Файл '$filename' слишком большой. Максимальный размер: " . formatFileSize($max_file_size);
                            continue;
                        }
                        
                        $filepath = $user_dir . '/' . $filename;
                        
                        if (move_uploaded_file($_FILES['project_files']['tmp_name'][$i], $filepath)) {
                            // Определяем язык программирования
                            $language = getLanguageFromExtension($extension);
                            $lines = count(file($filepath));
                            
                            // Сохраняем информацию о файле в БД
                            $stmt = $pdo->prepare("INSERT INTO project_files (project_id, user_id, filename, filepath, filetype, filesize, language, lines_of_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([
                                $project_id,
                                $_SESSION['user_id'],
                                $filename,
                                $filepath,
                                $_FILES['project_files']['type'][$i],
                                $_FILES['project_files']['size'][$i],
                                $language,
                                $lines
                            ]);
                            
                            $total_lines += $lines;
                            $file_count++;
                            
                            // Собираем статистику по языкам
                            if ($language) {
                                if (!isset($languages[$language])) {
                                    $languages[$language] = 0;
                                }
                                $languages[$language] += $lines;
                            }
                        }
                    }
                }
                
                if ($file_count === 0) {
                    $error = "Не удалось загрузить ни одного файла. Проверьте расширения и размеры файлов.";
                } else {
                    // Обновляем статистику проекта
                    $stmt = $pdo->prepare("UPDATE projects SET total_files = ?, total_lines = ? WHERE id = ?");
                    $stmt->execute([$file_count, $total_lines, $project_id]);
                    
                    // Создаем начальный коммит
                    $stmt = $pdo->prepare("INSERT INTO commits (project_id, user_id, message, commit_hash) VALUES (?, ?, ?, ?)");
                    $commit_hash = substr(md5(uniqid()), 0, 8);
                    $stmt->execute([$project_id, $_SESSION['user_id'], "Initial commit", $commit_hash]);
                    
                    // Обновляем статистику навыков
                    updateSkillsStatistics($_SESSION['user_id'], $languages);
                    
                    $success = "Проект '$project_name' успешно загружен! Добавлено $file_count файлов, $total_lines строк кода.";
                    
                    // Обновляем список проектов
                    $stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ? ORDER BY pinned DESC, last_updated DESC");
                    $stmt->execute([$_SESSION['user_id']]);
                    $projects = $stmt->fetchAll();
                }
            }
        } catch (Exception $e) {
            $error = "Ошибка при загрузке проекта: " . $e->getMessage();
        }
    }
}

// Функция для форматирования размера файла
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
}

// Функция для рекурсивного удаления директории
function deleteDirectory($dir) {
    if (!is_dir($dir)) return true;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    return rmdir($dir);
}

// Функция для определения языка по расширению файла
function getLanguageFromExtension($extension) {
    $languages = [
        'php' => 'PHP',
        'js' => 'JavaScript',
        'ts' => 'TypeScript',
        'jsx' => 'React',
        'tsx' => 'React',
        'html' => 'HTML/CSS',
        'htm' => 'HTML/CSS',
        'css' => 'HTML/CSS',
        'scss' => 'HTML/CSS',
        'sass' => 'HTML/CSS',
        'py' => 'Python',
        'java' => 'Java',
        'cpp' => 'C++',
        'c' => 'C',
        'cs' => 'C#',
        'go' => 'Go',
        'rs' => 'Rust',
        'rb' => 'Ruby',
        'swift' => 'Swift',
        'kt' => 'Kotlin',
        'sql' => 'SQL',
        'json' => 'JSON',
        'xml' => 'XML',
        'yml' => 'YAML',
        'yaml' => 'YAML',
        'md' => 'Markdown'
    ];
    
    return $languages[$extension] ?? 'Other';
}

// Функция для обновления статистики навыков
function updateSkillsStatistics($user_id, $languages) {
    global $pdo;
    
    foreach ($languages as $language => $lines) {
        // Проверяем, существует ли уже навык
        $stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ? AND name = ?");
        $stmt->execute([$user_id, $language]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            // Обновляем существующий навык
            $new_lines = $existing['total_lines'] + $lines;
            $level = min(100, floor(($new_lines / 1000) * 10)); // 1 уровень за 100 строк
            
            $stmt = $pdo->prepare("UPDATE skills SET total_lines = ?, level = ? WHERE user_id = ? AND name = ?");
            $stmt->execute([$new_lines, $level, $user_id, $language]);
        } else {
            // Создаем новый навык
            $level = min(100, floor(($lines / 1000) * 10));
            $color = getLanguageColor($language);
            
            $stmt = $pdo->prepare("INSERT INTO skills (user_id, name, level, total_lines, color) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $language, $level, $lines, $color]);
        }
    }
    
    // Пересчитываем уровни всех навыков относительно максимального
    $stmt = $pdo->prepare("SELECT MAX(total_lines) as max_lines FROM skills WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $max = $stmt->fetch();
    
    if ($max['max_lines'] > 0) {
        $stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $all_skills = $stmt->fetchAll();
        
        foreach ($all_skills as $skill) {
            $new_level = min(100, floor(($skill['total_lines'] / $max['max_lines']) * 100));
            $stmt = $pdo->prepare("UPDATE skills SET level = ? WHERE id = ?");
            $stmt->execute([$new_level, $skill['id']]);
        }
    }
}

// Функция для получения цвета языка
function getLanguageColor($language) {
    $colors = [
        'PHP' => '#787cb5',
        'JavaScript' => '#f0db4f',
        'TypeScript' => '#3178c6',
        'React' => '#61dafb',
        'HTML/CSS' => '#e34c26',
        'Python' => '#3572A5',
        'Java' => '#b07219',
        'C++' => '#f34b7d',
        'C' => '#555555',
        'C#' => '#178600',
        'Go' => '#00ADD8',
        'Rust' => '#dea584',
        'Ruby' => '#701516',
        'Swift' => '#ffac45',
        'Kotlin' => '#A97BFF',
        'SQL' => '#e38c00',
        'Other' => '#6e7681'
    ];
    
    return $colors[$language] ?? '#6e7681';
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFolio - Мои проекты</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .projects-page {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .create-project-btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .create-project-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: var(--accent-color);
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .project-card-large {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            transition: all 0.3s;
            position: relative;
        }
        
        .project-card-large:hover {
            border-color: var(--accent-color);
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .project-actions {
            position: absolute;
            top: 15px;
            right: 15px;
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 16px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            background-color: var(--bg-tertiary);
            color: var(--accent-color);
        }
        
        .action-btn.active {
            color: var(--accent-color);
        }
        
        .language-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 15px;
        }
        
        .language-badge {
            background-color: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
        /* Модальное окно */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            font-size: 24px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }
        
        .close-btn:hover {
            background-color: var(--bg-tertiary);
            color: var(--danger-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .file-upload-area {
            border: 2px dashed var(--border-color);
            border-radius: 8px;
            padding: 40px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .file-upload-area:hover, .file-upload-area.dragover {
            border-color: var(--accent-color);
            background-color: var(--bg-tertiary);
        }
        
        .file-list {
            margin-top: 20px;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            background-color: var(--bg-tertiary);
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-icon {
            color: var(--accent-color);
        }
        
        .submit-btn {
            width: 100%;
            padding: 14px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 20px;
        }
        
        .submit-btn:hover {
            opacity: 0.9;
        }
        
        .no-projects {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            grid-column: 1 / -1;
        }
        
        .no-projects i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--border-color);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .error {
            background-color: rgba(248, 81, 73, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        
        .project-links {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .project-link {
            padding: 8px 15px;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s;
        }
        
        .project-link:hover {
            background-color: var(--accent-color);
            color: white;
        }
        
        .file-size-info {
            margin-top: 10px;
            padding: 10px;
            background-color: var(--bg-tertiary);
            border-radius: 6px;
            font-size: 12px;
            color: var(--text-secondary);
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .projects-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .create-project-btn {
                width: 100%;
                justify-content: center;
            }
            
            .project-links {
                flex-direction: column;
            }
        }
    </style>
</head>
<body class="theme-<?php echo $theme; ?>">
    <!-- Навигация -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <i class="fas fa-code"></i>
                <span>DevFolio</span>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-link">Профиль</a>
                <a href="projects.php" class="nav-link active">Проекты</a>
                <a href="index.php#skills" class="nav-link">Навыки</a>
                
                <div class="nav-controls">
                    <!-- Переключатель темы -->
                    <button class="theme-toggle" title="<?php echo $theme === 'dark' ? 'Включить светлую тему' : 'Включить темную тему'; ?>">
                        <i class="fas fa-<?php echo $theme === 'dark' ? 'sun' : 'moon'; ?>"></i>
                    </button>
                    
                    <!-- Переключатель языка -->
                    <div class="language-switch">
                        <button class="lang-btn <?php echo $language === 'ru' ? 'active' : ''; ?>" data-lang="ru">RU</button>
                        <button class="lang-btn <?php echo $language === 'en' ? 'active' : ''; ?>" data-lang="en">EN</button>
                        <button class="lang-btn <?php echo $language === 'es' ? 'active' : ''; ?>" data-lang="es">ES</button>
                    </div>
                    
                    <!-- Профиль пользователя -->
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="index.php" class="dropdown-item">
                                <i class="fas fa-user"></i> Мой профиль
                            </a>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Выйти
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="projects-page">
        <div class="page-header">
            <div>
                <h1>Мои проекты</h1>
                <p class="text-secondary">Здесь вы можете управлять своими проектами</p>
            </div>
            <button class="create-project-btn" onclick="openModal()">
                <i class="fas fa-plus"></i> Создать проект
            </button>
        </div>

        <!-- Сообщения -->
        <?php if (isset($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($projects); ?></div>
                <div class="stat-label">Всего проектов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_files = 0;
                    $total_lines = 0;
                    foreach ($projects as $project) {
                        $total_files += $project['total_files'] ?? 0;
                        $total_lines += $project['total_lines'] ?? 0;
                    }
                    echo number_format($total_files);
                    ?>
                </div>
                <div class="stat-label">Файлов в проектах</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo number_format($total_lines); ?></div>
                <div class="stat-label">Строк кода</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($language_stats); ?></div>
                <div class="stat-label">Языков программирования</div>
            </div>
        </div>

        <!-- Список проектов -->
        <div class="projects-grid">
            <?php if (empty($projects)): ?>
                <div class="no-projects">
                    <i class="fas fa-folder-open"></i>
                    <h3>Проектов пока нет</h3>
                    <p>Создайте свой первый проект, чтобы начать работу</p>
                    <button class="create-project-btn" onclick="openModal()" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i> Создать первый проект
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($projects as $project): ?>
                    <?php 
                    // Получаем языки для этого проекта
                    $stmt = $pdo->prepare("SELECT language, COUNT(*) as count FROM project_files WHERE project_id = ? GROUP BY language ORDER BY count DESC LIMIT 3");
                    $stmt->execute([$project['id']]);
                    $project_languages = $stmt->fetchAll();
                    
                    // Получаем количество коммитов
                    $stmt = $pdo->prepare("SELECT COUNT(*) as commit_count FROM commits WHERE project_id = ?");
                    $stmt->execute([$project['id']]);
                    $commit_count = $stmt->fetch()['commit_count'];
                    ?>
                    
                    <div class="project-card-large">
                        <div class="project-actions">
                            <a href="?pin=<?php echo $project['pinned'] ? 0 : 1; ?>&project_id=<?php echo $project['id']; ?>" 
                               class="action-btn" title="<?php echo $project['pinned'] ? 'Открепить' : 'Закрепить'; ?>">
                                <i class="fas fa-thumbtack <?php echo $project['pinned'] ? 'active' : ''; ?>"></i>
                            </a>
                            <a href="?delete_project=1&project_id=<?php echo $project['id']; ?>" 
                               class="action-btn" title="Удалить" 
                               onclick="return confirm('Вы уверены, что хотите удалить проект \"<?php echo addslashes($project['name']); ?>\"? Все файлы будут удалены безвозвратно.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                        
                        <h3 style="margin-bottom: 10px; color: var(--accent-color);">
                            <?php if ($project['pinned']): ?>
                                <i class="fas fa-thumbtack" style="color: var(--accent-color); margin-right: 8px;"></i>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($project['name']); ?>
                        </h3>
                        
                        <?php if ($project['description']): ?>
                            <p style="color: var(--text-secondary); margin-bottom: 15px; font-size: 14px;">
                                <?php echo htmlspecialchars($project['description']); ?>
                            </p>
                        <?php endif; ?>
                        
                        <div style="display: flex; justify-content: space-between; font-size: 13px; color: var(--text-secondary); margin-bottom: 15px;">
                            <span>
                                <i class="fas fa-file-code"></i> 
                                <?php echo $project['total_files'] ?? 0; ?> файлов
                            </span>
                            <span>
                                <i class="fas fa-code"></i> 
                                <?php echo number_format($project['total_lines'] ?? 0); ?> строк
                            </span>
                            <span>
                                <i class="fas fa-code-branch"></i> 
                                <?php echo $commit_count; ?> коммитов
                            </span>
                        </div>
                        
                        <?php if (!empty($project_languages)): ?>
                            <div class="language-badges">
                                <?php foreach ($project_languages as $lang): ?>
                                    <?php $color = getLanguageColor($lang['language']); ?>
                                    <div class="language-badge">
                                        <span class="badge-dot" style="background-color: <?php echo $color; ?>"></span>
                                        <?php echo htmlspecialchars($lang['language']); ?>
                                        <span style="color: var(--text-secondary);">(<?php echo $lang['count']; ?>)</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="project-links">
                            <a href="project_editor.php?id=<?php echo $project['id']; ?>" class="project-link">
                                <i class="fas fa-edit"></i> Редактировать
                            </a>
                            <a href="commits.php?project_id=<?php echo $project['id']; ?>" class="project-link">
                                <i class="fas fa-history"></i> Коммиты
                            </a>
                            <a href="project_files.php?id=<?php echo $project['id']; ?>" class="project-link">
                                <i class="fas fa-folder-open"></i> Файлы
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Статистика по языкам -->
        <?php if (!empty($language_stats)): ?>
            <div class="stat-card" style="margin-top: 40px;">
                <h3 style="margin-bottom: 20px;">Статистика по языкам</h3>
                <div style="display: grid; gap: 15px;">
                    <?php foreach ($language_stats as $stat): ?>
                        <?php $color = getLanguageColor($stat['language']); ?>
                        <div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>
                                    <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background-color: <?php echo $color; ?>; margin-right: 8px;"></span>
                                    <?php echo htmlspecialchars($stat['language']); ?>
                                </span>
                                <span style="color: var(--text-secondary);">
                                    <?php echo number_format($stat['total_lines']); ?> строк
                                    (<?php echo $stat['file_count']; ?> файлов)
                                </span>
                            </div>
                            <div style="height: 8px; background-color: var(--bg-tertiary); border-radius: 4px; overflow: hidden;">
                                <?php 
                                $total_all_lines = array_sum(array_column($language_stats, 'total_lines'));
                                $percent = $total_all_lines > 0 ? ($stat['total_lines'] / $total_all_lines * 100) : 0;
                                ?>
                                <div style="height: 100%; width: <?php echo $percent; ?>%; background-color: <?php echo $color; ?>; border-radius: 4px;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Модальное окно создания проекта -->
    <div id="projectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Создать новый проект</h2>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="uploadForm" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Название проекта</label>
                        <input type="text" name="project_name" class="form-input" required 
                               placeholder="Например: Мой веб-сайт">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Описание проекта (необязательно)</label>
                        <textarea name="project_description" class="form-textarea" 
                                  placeholder="Краткое описание вашего проекта..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Выберите файлы проекта</label>
                        <div class="file-upload-area" id="dropArea">
                            <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: var(--accent-color); margin-bottom: 15px;"></i>
                            <h3 style="margin-bottom: 10px;">Перетащите файлы сюда</h3>
                            <p style="color: var(--text-secondary); margin-bottom: 20px;">
                                или нажмите для выбора файлов
                            </p>
                            <div class="file-size-info">
                                <i class="fas fa-info-circle"></i> 
                                Максимальный размер файла: 10MB, общий размер: 100MB
                                <br>Разрешенные расширения: .php, .js, .html, .css, .py, .java, .cpp, .c, .cs, и другие
                            </div>
                            <input type="file" id="fileInput" name="project_files[]" multiple 
                                   style="display: none;" onchange="handleFiles(this.files)">
                            <button type="button" class="create-project-btn" onclick="document.getElementById('fileInput').click()">
                                <i class="fas fa-folder-open"></i> Выбрать файлы
                            </button>
                        </div>
                        <div id="fileList" class="file-list"></div>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-upload"></i> Загрузить проект
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Управление модальным окном
        function openModal() {
            document.getElementById('projectModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('projectModal').style.display = 'none';
            resetForm();
        }
        
        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('projectModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Drag and drop для файлов
        const dropArea = document.getElementById('dropArea');
        const fileInput = document.getElementById('fileInput');
        
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, highlight, false);
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, unhighlight, false);
        });
        
        function highlight() {
            dropArea.classList.add('dragover');
        }
        
        function unhighlight() {
            dropArea.classList.remove('dragover');
        }
        
        dropArea.addEventListener('drop', handleDrop, false);
        
        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            handleFiles(files);
        }
        
        // Обработка выбранных файлов
        let selectedFiles = [];
        let totalSize = 0;
        const maxFileSize = 10 * 1024 * 1024; // 10MB
        const maxTotalSize = 100 * 1024 * 1024; // 100MB
        const allowedExtensions = ['php', 'js', 'html', 'css', 'py', 'java', 'cpp', 'c', 'cs', 'jsx', 'tsx', 'ts', 'go', 'rb', 'rs', 'swift', 'kt', 'sql', 'json', 'xml', 'md'];
        
        function handleFiles(files) {
            const newFiles = Array.from(files);
            let errors = [];
            
            newFiles.forEach(file => {
                // Проверка расширения
                const extension = file.name.split('.').pop().toLowerCase();
                if (!allowedExtensions.includes(extension)) {
                    errors.push(`Файл "${file.name}": расширение .${extension} не разрешено`);
                    return;
                }
                
                // Проверка размера файла
                if (file.size > maxFileSize) {
                    errors.push(`Файл "${file.name}": размер превышает 10MB`);
                    return;
                }
                
                // Проверка общего размера
                if (totalSize + file.size > maxTotalSize) {
                    errors.push(`Файл "${file.name}": превышен общий лимит в 100MB`);
                    return;
                }
                
                // Проверка на дубликаты
                const isDuplicate = selectedFiles.some(f => f.name === file.name && f.size === file.size);
                if (isDuplicate) {
                    errors.push(`Файл "${file.name}": уже добавлен`);
                    return;
                }
                
                selectedFiles.push(file);
                totalSize += file.size;
            });
            
            if (errors.length > 0) {
                alert('Ошибки при добавлении файлов:\n' + errors.join('\n'));
            }
            
            updateFileList();
        }
        
        function updateFileList() {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            if (selectedFiles.length === 0) {
                fileList.innerHTML = '<p style="color: var(--text-secondary); text-align: center;">Файлы не выбраны</p>';
                return;
            }
            
            selectedFiles.forEach((file, index) => {
                const div = document.createElement('div');
                div.className = 'file-item';
                
                const extension = file.name.split('.').pop().toLowerCase();
                const icon = getFileIcon(extension);
                
                div.innerHTML = `
                    <div class="file-info">
                        <i class="${icon} file-icon"></i>
                        <span>${file.name}</span>
                        <small style="color: var(--text-secondary);">(${formatFileSize(file.size)})</small>
                    </div>
                    <button type="button" onclick="removeFile(${index})" class="action-btn">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                fileList.appendChild(div);
            });
            
            // Добавляем информацию об общем размере
            const totalSizeInfo = document.createElement('div');
            totalSizeInfo.className = 'file-size-info';
            totalSizeInfo.innerHTML = `
                <i class="fas fa-info-circle"></i>
                Всего файлов: ${selectedFiles.length}, общий размер: ${formatFileSize(totalSize)} / ${formatFileSize(maxTotalSize)}
            `;
            fileList.appendChild(totalSizeInfo);
            
            // Обновляем input с файлами
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
        
        function removeFile(index) {
            totalSize -= selectedFiles[index].size;
            selectedFiles.splice(index, 1);
            updateFileList();
        }
        
        function getFileIcon(extension) {
            const icons = {
                'php': 'fab fa-php',
                'js': 'fab fa-js',
                'jsx': 'fab fa-react',
                'ts': 'fab fa-js-square',
                'tsx': 'fab fa-react',
                'html': 'fab fa-html5',
                'css': 'fab fa-css3-alt',
                'py': 'fab fa-python',
                'java': 'fab fa-java',
                'cpp': 'fas fa-code',
                'c': 'fas fa-code',
                'cs': 'fas fa-code',
                'go': 'fas fa-code',
                'rs': 'fas fa-code',
                'rb': 'fas fa-gem',
                'swift': 'fab fa-swift',
                'kt': 'fas fa-code',
                'sql': 'fas fa-database',
                'json': 'fas fa-file-code',
                'xml': 'fas fa-file-code',
                'md': 'fas fa-file-alt'
            };
            
            return icons[extension] || 'fas fa-file';
        }
        
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        function resetForm() {
            document.getElementById('uploadForm').reset();
            selectedFiles = [];
            totalSize = 0;
            updateFileList();
        }
        
        // Обработка темы и языка
        document.querySelector('.theme-toggle').addEventListener('click', function() {
            const newTheme = document.body.classList.contains('theme-dark') ? 'light' : 'dark';
            fetch('api/update_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    setting: 'theme',
                    value: newTheme
                })
            }).then(() => {
                location.reload();
            });
        });
        
        document.querySelectorAll('.lang-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const lang = this.dataset.lang;
                fetch('api/update_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        setting: 'language',
                        value: lang
                    })
                }).then(() => {
                    location.reload();
                });
            });
        });
    </script>
</body>
</html>