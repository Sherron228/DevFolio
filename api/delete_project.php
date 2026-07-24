<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Получаем информацию о проекте
    $stmt = $pdo->prepare("SELECT project_path FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $user_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'Project not found']);
        exit;
    }
    
    // Удаляем файлы проекта
    if ($project['project_path'] && is_dir($project['project_path'])) {
        deleteDirectory($project['project_path']);
    }
    
    // Удаляем запись из БД (каскадно удалятся и файлы)
    $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ? AND user_id = ?");
    $stmt->execute([$project_id, $user_id]);
    
    // Пересчитываем навыки
    recalculateSkills($user_id);
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

// Функция для удаления директории
function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Функция для пересчета навыков
function recalculateSkills($user_id) {
    global $pdo;
    
    // Получаем общую статистику по всем файлам
    $stmt = $pdo->prepare("
        SELECT 
            language,
            SUM(lines_of_code) as total_lines
        FROM project_files 
        WHERE user_id = ?
        GROUP BY language
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetchAll();
    
    // Удаляем старые навыки
    $stmt = $pdo->prepare("DELETE FROM skills WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    // Добавляем новые навыки
    foreach ($stats as $stat) {
        $level = min(100, floor(($stat['total_lines'] / 1000) * 10));
        $color = getLanguageColor($stat['language']);
        
        $stmt = $pdo->prepare("INSERT INTO skills (user_id, name, level, total_lines, color) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $stat['language'], $level, $stat['total_lines'], $color]);
    }
}

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