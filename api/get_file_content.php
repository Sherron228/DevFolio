<?php
require_once '../config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$file_id = intval($_GET['file_id'] ?? 0);
$commit_id = intval($_GET['commit_id'] ?? 0);

if ($file_id && $commit_id) {
    // Получаем содержимое файла из истории коммита
    $stmt = $pdo->prepare("
        SELECT cf.content 
        FROM commit_files cf 
        WHERE cf.file_id = ? AND cf.commit_id = ?
    ");
    $stmt->execute([$file_id, $commit_id]);
    $file = $stmt->fetch();
    
    if ($file) {
        echo json_encode(['success' => true, 'content' => $file['content']]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Файл не найден в истории']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Не указаны параметры']);
}
?>