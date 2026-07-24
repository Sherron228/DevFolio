<?php
require_once '../config.php';

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}

$commit_id = intval($_GET['commit_id'] ?? 0);

if ($commit_id) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.avatar_url 
        FROM commits c 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$commit_id]);
    $commit = $stmt->fetch();
    
    if ($commit) {
        // Проверяем доступ к проекту
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND user_id = ?");
        $stmt->execute([$commit['project_id'], $_SESSION['user_id']]);
        $project = $stmt->fetch();
        
        if ($project) {
            echo json_encode(['success' => true, 'commit' => $commit]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Коммит не найден']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Не указан ID коммита']);
}
?>