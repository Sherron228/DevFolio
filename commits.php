<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$project_id = intval($_GET['project_id'] ?? 0);
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

// Получаем коммиты проекта
$stmt = $pdo->prepare("
    SELECT c.*, u.username, u.avatar_url 
    FROM commits c 
    LEFT JOIN users u ON c.user_id = u.id 
    WHERE c.project_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$project_id]);
$commits = $stmt->fetchAll();

// Получаем статистику коммитов
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_commits,
        DATE(created_at) as commit_date
    FROM commits 
    WHERE project_id = ? 
    GROUP BY DATE(created_at)
    ORDER BY commit_date DESC
    LIMIT 7
");
$stmt->execute([$project_id]);
$commit_stats = $stmt->fetchAll();

// Обработка создания нового коммита
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_commit'])) {
    $commit_message = trim($_POST['commit_message']);
    
    if (!empty($commit_message)) {
        try {
            // Создаем коммит
            $commit_hash = substr(md5(uniqid()), 0, 8);
            $stmt = $pdo->prepare("INSERT INTO commits (project_id, user_id, message, commit_hash) VALUES (?, ?, ?, ?)");
            $stmt->execute([$project_id, $_SESSION['user_id'], $commit_message, $commit_hash]);
            
            // Получаем ID созданного коммита
            $commit_id = $pdo->lastInsertId();
            
            // Получаем текущие файлы проекта
            $stmt = $pdo->prepare("SELECT * FROM project_files WHERE project_id = ?");
            $stmt->execute([$project_id]);
            $files = $stmt->fetchAll();
            
            // Сохраняем снимок файлов для этого коммита
            foreach ($files as $file) {
                if (file_exists($file['filepath'])) {
                    $content = file_get_contents($file['filepath']);
                    $stmt = $pdo->prepare("INSERT INTO commit_files (commit_id, file_id, content) VALUES (?, ?, ?)");
                    $stmt->execute([$commit_id, $file['id'], $content]);
                }
            }
            
            // Обновляем дату последнего изменения проекта
            $stmt = $pdo->prepare("UPDATE projects SET last_updated = NOW() WHERE id = ?");
            $stmt->execute([$project_id]);
            
            $success = "Коммит успешно создан!";
            header("Location: commits.php?project_id=$project_id&success=" . urlencode($success));
            exit;
            
        } catch (Exception $e) {
            $error = "Ошибка при создании коммита: " . $e->getMessage();
        }
    } else {
        $error = "Введите сообщение коммита";
    }
}

// Обработка просмотра деталей коммита
if (isset($_GET['view_commit'])) {
    $commit_id = intval($_GET['view_commit']);
    
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar_url 
        FROM commits c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.id = ? AND c.project_id = ?
    ");
    $stmt->execute([$commit_id, $project_id]);
    $commit_details = $stmt->fetch();
    
    if ($commit_details) {
        // Получаем файлы этого коммита
        $stmt = $pdo->prepare("
            SELECT cf.*, pf.filename, pf.language 
            FROM commit_files cf 
            LEFT JOIN project_files pf ON cf.file_id = pf.id 
            WHERE cf.commit_id = ?
        ");
        $stmt->execute([$commit_id]);
        $commit_files = $stmt->fetchAll();
    }
}

// Получаем сообщения об успехе/ошибке из GET параметров
if (isset($_GET['success'])) {
    $success = urldecode($_GET['success']);
}
if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
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

// Функция для форматирования времени
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'только что';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' ' . plural($minutes, ['минуту', 'минуты', 'минут']) . ' назад';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' ' . plural($hours, ['час', 'часа', 'часов']) . ' назад';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' ' . plural($days, ['день', 'дня', 'дней']) . ' назад';
    } else {
        return date('d.m.Y H:i', $time);
    }
}

// Функция для склонения слов
function plural($number, $titles) {
    $cases = array(2, 0, 1, 1, 1, 2);
    return $titles[($number % 100 > 4 && $number % 100 < 20) ? 2 : $cases[min($number % 10, 5)]];
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFolio - Коммиты проекта: <?php echo htmlspecialchars($project['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .commits-page {
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
        
        .commits-section {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }
        
        @media (min-width: 992px) {
            .commits-section {
                grid-template-columns: 1fr 350px;
            }
        }
        
        .create-commit-card {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .create-commit-card h3 {
            margin-bottom: 20px;
            color: var(--accent-color);
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
        
        .form-textarea {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 16px;
            min-height: 100px;
            resize: vertical;
            transition: border-color 0.3s;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .submit-btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .submit-btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .commits-list {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .commit-item {
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
            transition: background-color 0.3s;
        }
        
        .commit-item:last-child {
            border-bottom: none;
        }
        
        .commit-item:hover {
            background-color: var(--bg-tertiary);
        }
        
        .commit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .commit-hash {
            font-family: monospace;
            background-color: var(--bg-tertiary);
            color: var(--accent-color);
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .commit-time {
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .commit-message {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 15px;
            color: var(--text-primary);
        }
        
        .commit-author {
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .author-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .commit-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .commit-action-btn {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .commit-action-btn:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--accent-color);
            color: var(--accent-color);
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
        
        .no-commits {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .no-commits i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--border-color);
        }
        
        /* Модальное окно деталей коммита */
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
            padding: 20px;
        }
        
        .modal-content {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            width: 100%;
            max-width: 800px;
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
        
        .commit-details {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .files-list {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .file-item {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-icon {
            color: var(--accent-color);
            font-size: 16px;
        }
        
        .file-language {
            background-color: var(--bg-tertiary);
            color: var(--text-secondary);
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .file-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .view-file-btn {
            background: none;
            border: 1px solid var(--border-color);
            color: var(--text-secondary);
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .view-file-btn:hover {
            background-color: var(--bg-tertiary);
            border-color: var(--accent-color);
            color: var(--accent-color);
        }
        
        /* График коммитов */
        .activity-chart {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 20px;
        }
        
        .chart-title {
            margin-bottom: 20px;
            color: var(--accent-color);
        }
        
        .chart-bars {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            height: 150px;
            padding: 20px 0;
        }
        
        .chart-bar {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .bar {
            width: 20px;
            background-color: var(--accent-color);
            border-radius: 4px 4px 0 0;
            transition: all 0.3s;
        }
        
        .bar:hover {
            opacity: 0.8;
        }
        
        .bar-label {
            font-size: 11px;
            color: var(--text-secondary);
            text-align: center;
        }
        
        .bar-value {
            font-size: 12px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .commit-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .chart-bars {
                gap: 5px;
            }
            
            .bar {
                width: 15px;
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
    <div class="commits-page">
        <div class="page-header">
            <div>
                <h1>
                    <a href="projects.php" class="back-btn" style="margin-right: 15px;">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    Коммиты проекта: <?php echo htmlspecialchars($project['name']); ?>
                </h1>
                <?php if ($project['description']): ?>
                    <p class="text-secondary"><?php echo htmlspecialchars($project['description']); ?></p>
                <?php endif; ?>
            </div>
            <div>
                <a href="project_files.php?id=<?php echo $project_id; ?>" class="back-btn">
                    <i class="fas fa-folder-open"></i> Файлы
                </a>
                <a href="project_editor.php?id=<?php echo $project_id; ?>" class="back-btn" style="margin-left: 10px;">
                    <i class="fas fa-edit"></i> Редактор
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

        <!-- Создание коммита -->
        <div class="create-commit-card">
            <h3><i class="fas fa-code-branch"></i> Создать новый коммит</h3>
            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Сообщение коммита</label>
                    <textarea name="commit_message" class="form-textarea" 
                              placeholder="Опишите изменения, которые вы сделали..." required></textarea>
                    <small style="color: var(--text-secondary); display: block; margin-top: 5px;">
                        Хорошее сообщение коммита описывает, какие изменения были сделаны и почему
                    </small>
                </div>
                
                <button type="submit" name="create_commit" class="submit-btn">
                    <i class="fas fa-plus"></i> Создать коммит
                </button>
            </form>
        </div>

        <div class="commits-section">
            <!-- Список коммитов -->
            <div class="commits-list">
                <h3 style="padding: 20px; border-bottom: 1px solid var(--border-color); margin: 0; color: var(--accent-color);">
                    <i class="fas fa-history"></i> История коммитов
                </h3>
                
                <?php if (empty($commits)): ?>
                    <div class="no-commits">
                        <i class="fas fa-code-branch"></i>
                        <h3>Коммитов пока нет</h3>
                        <p>Создайте первый коммит, чтобы начать отслеживать изменения</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($commits as $commit): ?>
                        <div class="commit-item">
                            <div class="commit-header">
                                <div>
                                    <span class="commit-hash">
                                        <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($commit['commit_hash']); ?>
                                    </span>
                                </div>
                                <div class="commit-time" title="<?php echo date('d.m.Y H:i', strtotime($commit['created_at'])); ?>">
                                    <i class="far fa-clock"></i> <?php echo timeAgo($commit['created_at']); ?>
                                </div>
                            </div>
                            
                            <div class="commit-message">
                                <?php echo nl2br(htmlspecialchars($commit['message'])); ?>
                            </div>
                            
                            <div class="commit-author">
                                <?php if ($commit['avatar_url']): ?>
                                    <img src="<?php echo htmlspecialchars($commit['avatar_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($commit['username']); ?>" 
                                         class="author-avatar">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($commit['username']); ?></span>
                            </div>
                            
                            <div class="commit-actions">
                                <a href="?project_id=<?php echo $project_id; ?>&view_commit=<?php echo $commit['id']; ?>" 
                                   class="commit-action-btn" onclick="event.preventDefault(); viewCommitDetails(<?php echo $commit['id']; ?>)">
                                    <i class="fas fa-eye"></i> Подробности
                                </a>
                                <a href="#" class="commit-action-btn">
                                    <i class="fas fa-code-branch"></i> Ветка: main
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Статистика и активность -->
            <div>
                <!-- Статистика -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($commits); ?></div>
                        <div class="stat-label">Всего коммитов</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">
                            <?php 
                            $last_commit = reset($commits);
                            echo $last_commit ? timeAgo($last_commit['created_at']) : 'Нет';
                            ?>
                        </div>
                        <div class="stat-label">Последний коммит</div>
                    </div>
                </div>

                <!-- График активности -->
                <?php if (!empty($commit_stats)): ?>
                    <div class="activity-chart">
                        <h3 class="chart-title"><i class="fas fa-chart-line"></i> Активность коммитов</h3>
                        <div class="chart-bars">
                            <?php 
                            // Создаем массив для последних 7 дней
                            $last_7_days = [];
                            for ($i = 6; $i >= 0; $i--) {
                                $date = date('Y-m-d', strtotime("-$i days"));
                                $last_7_days[$date] = 0;
                            }
                            
                            // Заполняем данные
                            foreach ($commit_stats as $stat) {
                                $last_7_days[$stat['commit_date']] = $stat['total_commits'];
                            }
                            
                            // Находим максимальное значение для масштабирования
                            $max_commits = max($last_7_days);
                            if ($max_commits == 0) $max_commits = 1;
                            
                            foreach ($last_7_days as $date => $count):
                                $height = $max_commits > 0 ? ($count / $max_commits * 100) : 0;
                                $day_name = date('D', strtotime($date));
                                $day_number = date('d', strtotime($date));
                            ?>
                                <div class="chart-bar">
                                    <div class="bar-value"><?php echo $count; ?></div>
                                    <div class="bar" style="height: <?php echo $height; ?>%;"></div>
                                    <div class="bar-label">
                                        <?php echo $day_name; ?><br>
                                        <?php echo $day_number; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Информация о проекте -->
                <div class="stat-card" style="margin-top: 20px;">
                    <h4 style="margin-bottom: 15px; color: var(--accent-color);">
                        <i class="fas fa-info-circle"></i> Информация о проекте
                    </h4>
                    <div style="font-size: 14px; color: var(--text-secondary);">
                        <p><i class="far fa-calendar"></i> Создан: <?php echo date('d.m.Y', strtotime($project['created_at'])); ?></p>
                        <p><i class="fas fa-sync-alt"></i> Обновлен: <?php echo date('d.m.Y H:i', strtotime($project['last_updated'])); ?></p>
                        <p><i class="fas fa-folder"></i> Путь: <?php echo htmlspecialchars($project['project_path']); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Модальное окно деталей коммита -->
    <div id="commitDetailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Детали коммита</h2>
                <button class="close-btn" onclick="closeCommitDetails()">&times;</button>
            </div>
            <div class="modal-body">
                <?php if (isset($commit_details)): ?>
                    <div class="commit-details">
                        <div style="margin-bottom: 15px;">
                            <span class="commit-hash" style="font-size: 14px;">
                                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($commit_details['commit_hash']); ?>
                            </span>
                        </div>
                        
                        <div class="commit-message" style="font-size: 18px; margin-bottom: 20px;">
                            <?php echo nl2br(htmlspecialchars($commit_details['message'])); ?>
                        </div>
                        
                        <div style="display: flex; justify-content: space-between; color: var(--text-secondary); font-size: 14px;">
                            <div class="commit-author" style="margin: 0;">
                                <?php if ($commit_details['avatar_url']): ?>
                                    <img src="<?php echo htmlspecialchars($commit_details['avatar_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($commit_details['username']); ?>" 
                                         class="author-avatar">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                                <span><?php echo htmlspecialchars($commit_details['username']); ?></span>
                            </div>
                            
                            <div>
                                <i class="far fa-clock"></i> 
                                <?php echo date('d.m.Y H:i', strtotime($commit_details['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($commit_files)): ?>
                        <h3 style="margin-bottom: 15px; color: var(--accent-color);">
                            <i class="fas fa-file-code"></i> Файлы в этом коммите
                        </h3>
                        <div class="files-list">
                            <?php foreach ($commit_files as $file): ?>
                                <?php $color = getLanguageColor($file['language']); ?>
                                <div class="file-item">
                                    <div class="file-info">
                                        <i class="fas fa-file file-icon"></i>
                                        <span style="font-weight: 500;"><?php echo htmlspecialchars($file['filename']); ?></span>
                                        <span class="file-language">
                                            <span class="file-dot" style="background-color: <?php echo $color; ?>"></span>
                                            <?php echo htmlspecialchars($file['language']); ?>
                                        </span>
                                    </div>
                                    <div class="file-actions">
                                        <button class="view-file-btn" onclick="viewFileContent(<?php echo $file['id']; ?>)">
                                            <i class="fas fa-eye"></i> Просмотр
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-commits" style="padding: 30px 20px;">
                            <i class="fas fa-file-alt"></i>
                            <p>Нет информации о файлах для этого коммита</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Модальное окно просмотра файла -->
    <div id="fileContentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Содержимое файла</h2>
                <button class="close-btn" onclick="closeFileContent()">&times;</button>
            </div>
            <div class="modal-body">
                <pre id="fileContent" style="background-color: var(--bg-tertiary); padding: 20px; border-radius: 6px; overflow: auto; font-family: monospace; font-size: 14px; white-space: pre-wrap; word-wrap: break-word; max-height: 500px;"></pre>
            </div>
        </div>
    </div>

    <script>
        // Функция для просмотра деталей коммита
        function viewCommitDetails(commitId) {
            // Загружаем детали коммита через AJAX
            fetch(`api/get_commit_details.php?commit_id=${commitId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Здесь можно обновить модальное окно с данными
                        // В данном случае просто открываем страницу с GET параметром
                        window.location.href = `?project_id=<?php echo $project_id; ?>&view_commit=${commitId}`;
                    }
                });
            
            // Показываем модальное окно (если данные уже загружены через PHP)
            document.getElementById('commitDetailsModal').style.display = 'flex';
        }
        
        function closeCommitDetails() {
            document.getElementById('commitDetailsModal').style.display = 'none';
            // Убираем GET параметр из URL
            history.replaceState(null, null, window.location.pathname + '?project_id=<?php echo $project_id; ?>');
        }
        
        // Функция для просмотра содержимого файла
        function viewFileContent(fileId) {
            fetch(`api/get_file_content.php?file_id=${fileId}&commit_id=<?php echo isset($commit_details) ? $commit_details['id'] : 0; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const fileContent = document.getElementById('fileContent');
                        fileContent.textContent = data.content;
                        document.getElementById('fileContentModal').style.display = 'flex';
                    } else {
                        alert('Ошибка при загрузке содержимого файла: ' + data.error);
                    }
                })
                .catch(error => {
                    alert('Ошибка при загрузке содержимого файла');
                });
        }
        
        function closeFileContent() {
            document.getElementById('fileContentModal').style.display = 'none';
        }
        
        // Закрытие модальных окон при клике вне их
        window.onclick = function(event) {
            const commitModal = document.getElementById('commitDetailsModal');
            const fileModal = document.getElementById('fileContentModal');
            
            if (event.target === commitModal) {
                closeCommitDetails();
            }
            if (event.target === fileModal) {
                closeFileContent();
            }
        }
        
        // Автоматическое открытие модального окна при наличии GET параметра view_commit
        <?php if (isset($_GET['view_commit'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                document.getElementById('commitDetailsModal').style.display = 'flex';
            });
        <?php endif; ?>
        
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