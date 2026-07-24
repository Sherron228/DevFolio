<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $projects = $stmt->fetchAll();
    
    // Преобразуем JSON поля
    foreach ($projects as &$project) {
        if ($project['tags']) {
            $project['tags'] = json_decode($project['tags'], true);
        } else {
            $project['tags'] = [];
        }
    }
    
    echo json_encode($projects);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>