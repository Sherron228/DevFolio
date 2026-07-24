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

// Обработка редактирования файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_file'])) {
    $file_id = intval($_POST['file_id']);
    $content = $_POST['file_content'];
    
    // Получаем информацию о файле
    $stmt = $pdo->prepare("SELECT * FROM project_files WHERE id = ? AND project_id = ? AND user_id = ?");
    $stmt->execute([$file_id, $project_id, $_SESSION['user_id']]);
    $file = $stmt->fetch();
    
    if ($file && file_exists($file['filepath'])) {
        // Сохраняем старую версию для истории
        $old_content = file_get_contents($file['filepath']);
        
        // Сохраняем новое содержимое
        file_put_contents($file['filepath'], $content);
        
        // Обновляем количество строк
        $lines = count(file($file['filepath']));
        
        $stmt = $pdo->prepare("UPDATE project_files SET lines_of_code = ? WHERE id = ?");
        $stmt->execute([$lines, $file_id]);
        
        // Создаем коммит
        $stmt = $pdo->prepare("INSERT INTO commits (project_id, user_id, message, commit_hash) VALUES (?, ?, ?, ?)");
        $commit_hash = substr(md5(uniqid()), 0, 8);
        $stmt->execute([$project_id, $_SESSION['user_id'], "Edited file: " . $file['filename'], $commit_hash]);
        
        // Сохраняем изменения в историю
        $stmt = $pdo->prepare("INSERT INTO file_history (file_id, commit_id, old_content, new_content) VALUES (?, LAST_INSERT_ID(), ?, ?)");
        $stmt->execute([$file_id, $old_content, $content]);
        
        $success = "Файл успешно сохранен!";
    }
}

// Обработка создания нового файла
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_file'])) {
    $filename = trim($_POST['filename']);
    
    if (!empty($filename)) {
        $filepath = $project['project_path'] . '/' . $filename;
        
        // Создаем файл
        file_put_contents($filepath, $_POST['file_content'] ?? '');
        
        // Определяем расширение
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $language = getLanguageFromExtension($extension);
        $lines = count(file($filepath));
        
        // Сохраняем в БД
        $stmt = $pdo->prepare("INSERT INTO project_files (project_id, user_id, filename, filepath, filetype, filesize, language, lines_of_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $project_id,
            $_SESSION['user_id'],
            $filename,
            $filepath,
            mime_content_type($filepath),
            filesize($filepath),
            $language,
            $lines
        ]);
        
        // Создаем коммит
        $stmt = $pdo->prepare("INSERT INTO commits (project_id, user_id, message, commit_hash) VALUES (?, ?, ?, ?)");
        $commit_hash = substr(md5(uniqid()), 0, 8);
        $stmt->execute([$project_id, $_SESSION['user_id'], "Created file: " . $filename, $commit_hash]);
        
        $success = "Файл успешно создан!";
        header("Location: project_editor.php?id=$project_id");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFolio - Редактор проекта: <?php echo htmlspecialchars($project['name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Добавьте стили для редактора -->
</head>
<body class="theme-<?php echo $theme; ?>">
    <!-- Реализация редактора файлов -->
</body>
</html>