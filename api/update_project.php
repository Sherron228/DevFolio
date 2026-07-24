<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$project_id = $input['projectId'] ?? null;
$pinned = $input['pinned'] ?? false;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("UPDATE projects SET pinned = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$pinned ? 1 : 0, $project_id, $user_id]);
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>