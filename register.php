<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    $errors = [];
    
    // Валидация
    if (empty($username)) $errors[] = "Имя пользователя обязательно";
    if (empty($email)) $errors[] = "Email обязателен";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Некорректный email";
    if (empty($password)) $errors[] = "Пароль обязателен";
    if (strlen($password) < 6) $errors[] = "Пароль должен быть не менее 6 символов";
    if ($password !== $confirm_password) $errors[] = "Пароли не совпадают";
    
    // Проверка уникальности
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $errors[] = "Пользователь с таким именем или email уже существует";
        }
    }
    
    // Регистрация
    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$username, $email, $password_hash, $full_name])) {
            // Автоматический вход после регистрации
            $_SESSION['user_id'] = $pdo->lastInsertId();
            // В секции регистрации, после создания пользователя:
$_SESSION['theme'] = 'dark';
$_SESSION['language'] = 'ru';
            
            // Добавляем стандартные навыки
            $default_skills = [
                ['JavaScript', 85, '#f0db4f'],
                ['PHP', 80, '#787cb5'],
                ['HTML/CSS', 90, '#e34c26'],
                ['React', 75, '#61dafb'],
                ['Git', 85, '#f1502f']
            ];
            
            foreach ($default_skills as $skill) {
                $stmt = $pdo->prepare("INSERT INTO skills (user_id, name, level, color) VALUES (?, ?, ?, ?)");
                $stmt->execute([$_SESSION['user_id'], $skill[0], $skill[1], $skill[2]]);
            }
            
            header('Location: index.php');
            exit;
        } else {
            $errors[] = "Ошибка при регистрации. Попробуйте снова.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFolio - Регистрация</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .auth-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 30px;
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
        }
        
        .auth-title {
            text-align: center;
            margin-bottom: 30px;
            font-size: 24px;
        }
        
        .auth-form .form-group {
            margin-bottom: 20px;
        }
        
        .auth-form label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
        }
        
        .auth-form input {
            width: 100%;
            padding: 10px 15px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 16px;
        }
        
        .auth-form input:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .auth-btn {
            width: 100%;
            padding: 12px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .auth-btn:hover {
            opacity: 0.9;
        }
        
        .auth-links {
            text-align: center;
            margin-top: 20px;
            color: var(--text-secondary);
        }
        
        .auth-links a {
            color: var(--accent-color);
            text-decoration: none;
        }
        
        .auth-links a:hover {
            text-decoration: underline;
        }
        
        .error-message {
            background-color: var(--danger-color);
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
    </style>
</head>
<body class="theme-dark">
    <div class="auth-container">
        <h1 class="auth-title">
            <i class="fas fa-code"></i> DevFolio
        </h1>
        
        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" class="auth-form">
            <div class="form-group">
                <label for="full_name">Полное имя</label>
                <input type="text" id="full_name" name="full_name" 
                       value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="username">Имя пользователя</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="password">Пароль (мин. 6 символов)</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Подтвердите пароль</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            
            <button type="submit" class="auth-btn">
                <i class="fas fa-user-plus"></i> Зарегистрироваться
            </button>
        </form>
        
        <div class="auth-links">
            <p>Уже есть аккаунт? <a href="login.php">Войти</a></p>
        </div>
    </div>
</body>
</html>