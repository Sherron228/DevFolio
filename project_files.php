<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$project_id = intval($_GET['id'] ?? 0);
$user = getUserData();
$theme = getTheme();
$language = getLanguage();

// Проверяем доступ к проекту
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
$stmt->execute([$project_id, $_SESSION['user_id']]);
$project = $stmt->fetch();

if (!$project) {
    header('Location: projects.php');
    exit;
}

// Получаем файлы проекта
$stmt = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ? ORDER BY filename");
$stmt->execute([$project_id]);
$files = $stmt->fetchAll();

// Обработка удаления файла
if (isset($_GET['delete_file'])) {
    $file_id = intval($_GET['delete_file']);
    
    // Проверяем доступ к файлу
    $stmt = $pdo->prepare("SELECT * FROM project_files WHERE id = ? AND project_id = ? AND user_id = ?");
    $stmt->execute([$file_id, $project_id, $_SESSION['user_id']]);
    $file = $stmt->fetch();
    
    if ($file) {
        // Удаляем физический файл
        if (file_exists($file['filepath'])) {
            unlink($file['filepath']);
        }
        
        // Удаляем запись из БД
        $stmt = $pdo->prepare("DELETE FROM project_files WHERE id = ?");
        $stmt->execute([$file_id]);
        
        // Обновляем статистику проекта
        $stmt = $pdo->prepare("SELECT COUNT(*) as file_count, SUM(lines_of_code) as total_lines FROM project_files WHERE project_id = ?");
        $stmt->execute([$project_id]);
        $stats = $stmt->fetch();
        
        $stmt = $pdo->prepare("UPDATE projects SET total_files = ?, total_lines = ?, last_updated = NOW() WHERE id = ?");
        $stmt->execute([$stats['file_count'], $stats['total_lines'], $project_id]);
        
        // Создаем коммит
        $stmt = $pdo->prepare("INSERT INTO commits (project_id, user_id, message, commit_hash) VALUES (?, ?, ?, ?)");
        $commit_hash = substr(md5(uniqid()), 0, 8);
        $stmt->execute([$project_id, $_SESSION['user_id'], "Deleted file: " . $file['filename'], $commit_hash]);
        
        $success = "Файл '" . htmlspecialchars($file['filename']) . "' успешно удален!";
        header("Location: project_files.php?id=$project_id&success=" . urlencode($success));
        exit;
    }
}

// Обработка переименования файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_file'])) {
    $file_id = intval($_POST['file_id']);
    $new_name = trim($_POST['new_name']);
    
    // Проверяем доступ к файлу
    $stmt = $pdo->prepare("SELECT * FROM project_files WHERE id = ? AND project_id = ? AND user_id = ?");
    $stmt->execute([$file_id, $project_id, $_SESSION['user_id']]);
    $file = $stmt->fetch();
    
    if ($file && !empty($new_name)) {
        $old_path = $file['filepath'];
        $new_path = dirname($old_path) . '/' . $new_name;
        
        // Переименовываем файл
        if (rename($old_path, $new_path)) {
            // Обновляем запись в БД
            $stmt = $pdo->prepare("UPDATE project_files SET filename = ?, filepath = ? WHERE id = ?");
            $stmt->execute([$new_name, $new_path, $file_id]);
            
            // Создаем коммит
            $stmt = $pdo->prepare("INSERT INTO commits (project_id, user_id, message, commit_hash) VALUES (?, ?, ?, ?)");
            $commit_hash = substr(md5(uniqid()), 0, 8);
            $stmt->execute([$project_id, $_SESSION['user_id'], "Renamed file: " . $file['filename'] . " to " . $new_name, $commit_hash]);
            
            $success = "Файл успешно переименован!";
            header("Location: project_files.php?id=$project_id&success=" . urlencode($success));
            exit;
        }
    }
}

// Обработка загрузки нового файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['new_file'])) {
    $filename = basename($_FILES['new_file']['name']);
    
    if (!empty($filename) && $_FILES['new_file']['error'] === UPLOAD_ERR_OK) {
        // Настройки валидации
        $allowed_extensions = ['php', 'js', 'html', 'css', 'py', 'java', 'cpp', 'c', 'cs', 'jsx', 'tsx', 'ts', 'go', 'rb', 'rs', 'swift', 'kt', 'sql', 'json', 'xml', 'md'];
        $max_file_size = 10 * 1024 * 1024; // 10MB
        
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Проверка расширения
        if (!in_array($extension, $allowed_extensions)) {
            $error = "Расширение .$extension не разрешено";
        } elseif ($_FILES['new_file']['size'] > $max_file_size) {
            $error = "Файл слишком большой. Максимальный размер: 10MB";
        } else {
            $filepath = $project['project_path'] . '/' . $filename;
            
            // Проверяем, не существует ли уже файл с таким именем
            if (file_exists($filepath)) {
                $error = "Файл с именем '$filename' уже существует";
            } else {
                if (move_uploaded_file($_FILES['new_file']['tmp_name'], $filepath)) {
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
                        $_FILES['new_file']['type'],
                        $_FILES['new_file']['size'],
                        $language,
                        $lines
                    ]);
                    
                    // Обновляем статистику проекта
                    $stmt = $pdo->prepare("SELECT COUNT(*) as file_count, SUM(lines_of_code) as total_lines FROM project_files WHERE project_id = ?");
                    $stmt->execute([$project_id]);
                    $stats = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("UPDATE projects SET total_files = ?, total_lines = ?, last_updated = NOW() WHERE id = ?");
                    $stmt->execute([$stats['file_count'], $stats['total_lines'], $project_id]);
                    
                    // Создаем коммит
                    $stmt = $pdo->prepare("INSERT INTO commits (project_id, user_id, message, commit_hash) VALUES (?, ?, ?, ?)");
                    $commit_hash = substr(md5(uniqid()), 0, 8);
                    $stmt->execute([$project_id, $_SESSION['user_id'], "Added file: " . $filename, $commit_hash]);
                    
                    $success = "Файл '$filename' успешно загружен!";
                    header("Location: project_files.php?id=$project_id&success=" . urlencode($success));
                    exit;
                } else {
                    $error = "Ошибка при загрузке файла";
                }
            }
        }
    }
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

// Функция для форматирования размера файла
function formatFileSize($bytes) {
    if ($bytes == 0) return "0 Bytes";
    $k = 1024;
    $sizes = ["Bytes", "KB", "MB", "GB"];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . " " . $sizes[$i];
}

// Функция для получения иконки файла по расширению
function getFileIcon($extension) {
    $icons = [
        'php' => 'fab fa-php',
        'js' => 'fab fa-js',
        'jsx' => 'fab fa-react',
        'ts' => 'fab fa-js-square',
        'tsx' => 'fab fa-react',
        'html' => 'fab fa-html5',
        'css' => 'fab fa-css3-alt',
        'py' => 'fab fa-python',
        'java' => 'fab fa-java',
        'cpp' => 'fas fa-code',
        'c' => 'fas fa-code',
        'cs' => 'fas fa-code',
        'go' => 'fas fa-code',
        'rs' => 'fas fa-code',
        'rb' => 'fas fa-gem',
        'swift' => 'fab fa-swift',
        'kt' => 'fas fa-code',
        'sql' => 'fas fa-database',
        'json' => 'fas fa-file-code',
        'xml' => 'fas fa-file-code',
        'md' => 'fas fa-file-alt'
    ];
    
    return $icons[$extension] ?? 'fas fa-file';
}

// Получаем сообщения об успехе/ошибке из GET параметров
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFolio - Файлы проекта: <?php echo htmlspecialchars($project['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .project-files-page {
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
        
        .back-btn {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
        }
        
        .back-btn:hover {
            background-color: var(--bg-secondary);
            border-color: var(--accent-color);
        }
        
        .files-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .upload-btn, .create-folder-btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .upload-btn:hover, .create-folder-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .files-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 40px;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .files-table th {
            background-color: var(--bg-tertiary);
            padding: 15px;
            text-align: left;
            color: var(--text-secondary);
            font-weight: 500;
            border-bottom: 1px solid var(--border-color);
        }
        
        .files-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .files-table tr:last-child td {
            border-bottom: none;
        }
        
        .files-table tr:hover {
            background-color: var(--bg-tertiary);
        }
        
        .file-icon-cell {
            width: 40px;
            text-align: center;
        }
        
        .file-icon {
            font-size: 20px;
            color: var(--accent-color);
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .file-action-btn {
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .file-action-btn:hover {
            background-color: var(--bg-tertiary);
            color: var(--accent-color);
        }
        
        .language-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 8px;
            background-color: var(--bg-tertiary);
            border-radius: 12px;
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        
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
            max-width: 500px;
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
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-color);
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
        
        .no-files {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            grid-column: 1 / -1;
        }
        
        .no-files i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--border-color);
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .files-table {
                display: block;
                overflow-x: auto;
            }
            
            .file-actions {
                flex-direction: column;
                gap: 5px;
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
                <a href="projects.php" class="nav-link">Проекты</a>
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
    <div class="project-files-page">
        <div class="page-header">
            <div>
                <h1>
                    <a href="projects.php" class="back-btn" style="margin-right: 15px;">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    Файлы проекта: <?php echo htmlspecialchars($project['name']); ?>
                </h1>
                <?php if ($project['description']): ?>
                    <p class="text-secondary"><?php echo htmlspecialchars($project['description']); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <a href="project_editor.php?id=<?php echo $project_id; ?>" class="back-btn">
                    <i class="fas fa-edit"></i> Редактор
                </a>
                <a href="commits.php?project_id=<?php echo $project_id; ?>" class="back-btn" style="margin-left: 10px;">
                    <i class="fas fa-history"></i> Коммиты
                </a>
            </div>
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

        <!-- Действия с файлами -->
        <div class="files-actions">
            <button class="upload-btn" onclick="openUploadModal()">
                <i class="fas fa-upload"></i> Загрузить файл
            </button>
            <a href="project_editor.php?id=<?php echo $project_id; ?>&action=create" class="create-folder-btn">
                <i class="fas fa-plus"></i> Создать файл
            </a>
        </div>

        <!-- Список файлов -->
        <?php if (empty($files)): ?>
            <div class="no-files">
                <i class="fas fa-folder-open"></i>
                <h3>Файлов пока нет</h3>
                <p>Загрузите или создайте свой первый файл</p>
                <button class="upload-btn" onclick="openUploadModal()" style="margin-top: 20px;">
                    <i class="fas fa-upload"></i> Загрузить первый файл
                </button>
            </div>
        <?php else: ?>
            <table class="files-table">
                <thead>
                    <tr>
                        <th class="file-icon-cell"></th>
                        <th>Имя файла</th>
                        <th>Язык</th>
                        <th>Размер</th>
                        <th>Строк кода</th>
                        <th>Дата изменения</th>
                        <th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($files as $file): ?>
                        <?php $color = getLanguageColor($file['language']); ?>
                        <tr>
                            <td class="file-icon-cell">
                                <?php
                                $extension = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
                                $icon = getFileIcon($extension);
                                ?>
                                <i class="<?php echo $icon; ?> file-icon"></i>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($file['filename']); ?></strong>
                            </td>
                            <td>
                                <span class="language-badge">
                                    <span class="badge-dot" style="background-color: <?php echo $color; ?>"></span>
                                    <?php echo htmlspecialchars($file['language']); ?>
                                </span>
                            </td>
                            <td><?php echo formatFileSize($file['filesize']); ?></td>
                            <td><?php echo number_format($file['lines_of_code']); ?></td>
                            <td>
                                <?php 
                                if (file_exists($file['filepath'])) {
                                    echo date('d.m.Y H:i', filemtime($file['filepath']));
                                } else {
                                    echo 'Не найден';
                                }
                                ?>
                            </td>
                            <td>
                                <div class="file-actions">
                                    <a href="project_editor.php?id=<?php echo $project_id; ?>&file_id=<?php echo $file['id']; ?>" 
                                       class="file-action-btn" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="#" onclick="renameFile(<?php echo $file['id']; ?>, '<?php echo htmlspecialchars(addslashes($file['filename'])); ?>')" 
                                       class="file-action-btn" title="Переименовать">
                                        <i class="fas fa-i-cursor"></i>
                                    </a>
                                    <a href="?id=<?php echo $project_id; ?>&delete_file=<?php echo $file['id']; ?>" 
                                       class="file-action-btn" title="Удалить"
                                       onclick="return confirm('Вы уверены, что хотите удалить файл \"<?php echo htmlspecialchars(addslashes($file['filename'])); ?>\"?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <a href="<?php echo htmlspecialchars($file['filepath']); ?>" 
                                       class="file-action-btn" title="Скачать" download>
                                        <i class="fas fa-download"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Статистика проекта -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($files); ?></div>
                <div class="stat-label">Всего файлов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_lines = 0;
                    foreach ($files as $file) {
                        $total_lines += $file['lines_of_code'];
                    }
                    echo number_format($total_lines);
                    ?>
                </div>
                <div class="stat-label">Строк кода</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $total_size = 0;
                    foreach ($files as $file) {
                        $total_size += $file['filesize'];
                    }
                    echo formatFileSize($total_size);
                    ?>
                </div>
                <div class="stat-label">Общий размер</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <?php 
                    $languages = [];
                    foreach ($files as $file) {
                        if (!isset($languages[$file['language']])) {
                            $languages[$file['language']] = 0;
                        }
                        $languages[$file['language']]++;
                    }
                    echo count($languages);
                    ?>
                </div>
                <div class="stat-label">Языков</div>
            </div>
        </div>
    </div>

    <!-- Модальное окно загрузки файла -->
    <div id="uploadModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Загрузить файл</h2>
                <button class="close-btn" onclick="closeUploadModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Выберите файл</label>
                        <input type="file" name="new_file" class="form-input" required>
                        <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                            Максимальный размер: 10MB. Разрешенные расширения: .php, .js, .html, .css, .py, .java, .cpp, .c, .cs и другие
                        </small>
                    </div>
                    
                    <button type="submit" class="submit-btn">
                        <i class="fas fa-upload"></i> Загрузить
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Модальное окно переименования файла -->
    <div id="renameModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Переименовать файл</h2>
                <button class="close-btn" onclick="closeRenameModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="renameForm">
                    <input type="hidden" name="file_id" id="renameFileId">
                    <div class="form-group">
                        <label class="form-label">Новое имя файла</label>
                        <input type="text" name="new_name" id="renameFileName" class="form-input" required>
                        <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                            Укажите имя файла с расширением (например: script.js)
                        </small>
                    </div>
                    
                    <button type="submit" name="rename_file" class="submit-btn">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Функция для получения иконки файла по расширению
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
        
        // Управление модальными окнами
        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
        }
        
        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
        }
        
        function renameFile(fileId, currentName) {
            document.getElementById('renameFileId').value = fileId;
            document.getElementById('renameFileName').value = currentName;
            document.getElementById('renameModal').style.display = 'flex';
        }
        
        function closeRenameModal() {
            document.getElementById('renameModal').style.display = 'none';
        }
        
        // Закрытие модальных окон при клике вне их
        window.onclick = function(event) {
            const uploadModal = document.getElementById('uploadModal');
            const renameModal = document.getElementById('renameModal');
            
            if (event.target === uploadModal) {
                closeUploadModal();
            }
            if (event.target === renameModal) {
                closeRenameModal();
            }
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