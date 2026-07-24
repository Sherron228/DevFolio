<?php
require_once '../config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$setting = $input['setting'] ?? null;
$value = $input['value'] ?? null;

if (!$setting || !$value) {
    http_response_code(400);
    echo json_encode(['error' => 'Setting and value required']);
    exit;
}

// Убедитесь, что этот код есть в файле:
if ($setting === 'theme') {
    $_SESSION['theme'] = $value;
} elseif ($setting === 'language') {
    $_SESSION['language'] = $value;
}

// И обновление в БД:
$column = $setting === 'theme' ? 'theme' : 'language';
$stmt = $pdo->prepare("UPDATE users SET $column = ? WHERE id = ?");
$stmt->execute([$value, $user_id]);
?>