<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ? ORDER BY level DESC");
    $stmt->execute([$user_id]);
    $skills = $stmt->fetchAll();
    
    echo json_encode($skills);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>