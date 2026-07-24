<?php
session_start();

// Конфигурация базы данных
define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // измените на своего пользователя
define('DB_PASS', ''); // измените на свой пароль
define('DB_NAME', 'devfolio');

// Подключение к БД
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

// Проверка авторизации
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserData() {
    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch();
    }
    return null;
}
// Получение текущей темы
function getTheme() {
    if (isset($_SESSION['theme'])) {
        return $_SESSION['theme'];
    }
    return 'dark'; // тема по умолчанию
}

// Получение текущего языка
function getLanguage() {
    if (isset($_SESSION['language'])) {
        return $_SESSION['language'];
    }
    return 'ru'; // язык по умолчанию
}
?>