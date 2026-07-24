<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUserData();
$theme = getTheme();
$language = getLanguage();

$errors = [];
$success = '';

// Обработка обновления профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $bio = trim($_POST['bio']);
    $location = trim($_POST['location']);
    $github_url = trim($_POST['github_url']);
    $linkedin_url = trim($_POST['linkedin_url']);
    $telegram_url = trim($_POST['telegram_url']);
    $website_url = trim($_POST['website_url']);
    
    // Валидация
    if (empty($full_name)) {
        $errors[] = "Полное имя обязательно";
    }
    
    if (strlen($bio) > 500) {
        $errors[] = "Биография не должна превышать 500 символов";
    }
    
    // Обновление аватарки
    $avatar_url = $user['avatar_url'];
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['avatar']['type'];
        $file_size = $_FILES['avatar']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Разрешены только изображения JPEG, PNG, GIF и WebP";
        } elseif ($file_size > 5 * 1024 * 1024) { // 5MB
            $errors[] = "Размер изображения не должен превышать 5MB";
        } else {
            // Создаем папку для аватаров, если ее нет
            $avatar_dir = 'uploads/avatars/' . $_SESSION['user_id'];
            if (!is_dir($avatar_dir)) {
                mkdir($avatar_dir, 0777, true);
            }
            
            // Генерируем уникальное имя файла
            $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . time() . '.' . $extension;
            $filepath = $avatar_dir . '/' . $filename;
            
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath)) {
                // Удаляем старый аватар, если он не стандартный
                $old_avatar = $user['avatar_url'];
                // Используем strpos для совместимости с PHP 7.x
                if ($old_avatar && strpos($old_avatar, 'githubusercontent.com') === false) {
                    if (file_exists($old_avatar)) {
                        unlink($old_avatar);
                    }
                }
                
                $avatar_url = $filepath;
                $success .= "Аватар успешно обновлен. ";
            } else {
                $errors[] = "Ошибка при загрузке аватара";
            }
        }
    }
    
    // Обновление пароля (если указан)
    if (!empty($_POST['current_password']) || !empty($_POST['new_password']) || !empty($_POST['confirm_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password)) {
            $errors[] = "Введите текущий пароль";
        } elseif (!password_verify($current_password, $user['password_hash'])) {
            $errors[] = "Текущий пароль неверен";
        } elseif (empty($new_password)) {
            $errors[] = "Введите новый пароль";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Новый пароль должен быть не менее 6 символов";
        } elseif ($new_password !== $confirm_password) {
            $errors[] = "Новые пароли не совпадают";
        } else {
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        }
    }
    
    // Обновление данных в БД
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Основные данные
            $sql = "UPDATE users SET 
                    full_name = ?,
                    bio = ?,
                    location = ?,
                    avatar_url = ?,
                    github_url = ?,
                    linkedin_url = ?,
                    telegram_url = ?,
                    website_url = ?";
            
            $params = [
                $full_name,
                $bio,
                $location,
                $avatar_url,
                $github_url,
                $linkedin_url,
                $telegram_url,
                $website_url
            ];
            
            // Добавляем пароль если он меняется
            if (isset($new_password_hash)) {
                $sql .= ", password_hash = ?";
                $params[] = $new_password_hash;
                $success .= "Пароль успешно изменен. ";
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $_SESSION['user_id'];
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $pdo->commit();
            
            $success .= "Профиль успешно обновлен!";
            
            // Обновляем данные пользователя в сессии
            $user = getUserData();
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Ошибка при обновлении профиля: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFolio - Редактирование профиля</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-edit-page {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .page-header {
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }
        
        .profile-form-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 40px;
        }
        
        @media (max-width: 768px) {
            .profile-form-container {
                grid-template-columns: 1fr;
            }
        }
        
        .avatar-section {
            text-align: center;
        }
        
        .avatar-preview {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            border: 4px solid var(--accent-color);
            margin: 0 auto 20px;
            overflow: hidden;
            position: relative;
        }
        
        .avatar-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .avatar-upload-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            background-color: var(--accent-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            transition: all 0.3s;
        }
        
        .avatar-upload-btn:hover {
            transform: scale(1.1);
            background-color: var(--bg-primary);
            color: var(--accent-color);
            border: 2px solid var(--accent-color);
        }
        
        .avatar-info {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 10px;
        }
        
        .form-section {
            background-color: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 18px;
            margin-bottom: 20px;
            color: var(--accent-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-label.required::after {
            content: ' *';
            color: var(--danger-color);
        }
        
        .form-input, .form-textarea, .form-select {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--bg-tertiary);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-input:focus, .form-textarea:focus, .form-select:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .char-counter {
            text-align: right;
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 5px;
        }
        
        .social-input-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .social-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--bg-tertiary);
            border-radius: 6px;
            color: var(--text-primary);
            font-size: 18px;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }
        
        .btn-secondary:hover {
            background-color: var(--border-color);
        }
        
        .message {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .success {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            border: 1px solid var(--success-color);
        }
        
        .error {
            background-color: rgba(248, 81, 73, 0.1);
            color: var(--danger-color);
            border: 1px solid var(--danger-color);
        }
        
        .error-list {
            list-style: none;
            padding: 0;
            margin: 0 0 20px 0;
        }
        
        .error-list li {
            padding: 10px 15px;
            margin-bottom: 10px;
            background-color: rgba(248, 81, 73, 0.1);
            color: var(--danger-color);
            border-radius: 6px;
            border-left: 4px solid var(--danger-color);
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 16px;
        }
        
        .password-input-wrapper {
            position: relative;
        }
        
        .form-help {
            font-size: 13px;
            color: var(--text-secondary);
            margin-top: 5px;
        }
    </style>
</head>
<body class="theme-<?php echo $theme; ?>">
    <!-- Навигация -->
    <nav class="navbar">
        <div class="container">
            <div class="nav-brand">
                <i class="fas fa-code"></i>
                <span>DevFolio</span>
            </div>
            <div class="nav-menu">
                <a href="index.php" class="nav-link">Профиль</a>
                <a href="projects.php" class="nav-link">Проекты</a>
                <a href="index.php#skills" class="nav-link">Навыки</a>
                
                <div class="nav-controls">
                    <!-- Переключатель темы -->
                    <button class="theme-toggle" title="<?php echo $theme === 'dark' ? 'Включить светлую тему' : 'Включить темную тему'; ?>">
                        <i class="fas fa-<?php echo $theme === 'dark' ? 'sun' : 'moon'; ?>"></i>
                    </button>
                    
                    <!-- Переключатель языка -->
                    <div class="language-switch">
                        <button class="lang-btn <?php echo $language === 'ru' ? 'active' : ''; ?>" data-lang="ru">RU</button>
                        <button class="lang-btn <?php echo $language === 'en' ? 'active' : ''; ?>" data-lang="en">EN</button>
                        <button class="lang-btn <?php echo $language === 'es' ? 'active' : ''; ?>" data-lang="es">ES</button>
                    </div>
                    
                    <!-- Профиль пользователя -->
                    <div class="user-dropdown">
                        <button class="user-btn">
                            <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" alt="Avatar" class="user-avatar">
                            <span><?php echo htmlspecialchars($user['username']); ?></span>
                            <i class="fas fa-chevron-down"></i>
                        </button>
                        <div class="dropdown-menu">
                            <a href="index.php" class="dropdown-item">
                                <i class="fas fa-user"></i> Мой профиль
                            </a>
                            <a href="profile.php" class="dropdown-item">
                                <i class="fas fa-edit"></i> Редактировать профиль
                            </a>
                            <a href="projects.php" class="dropdown-item">
                                <i class="fas fa-project-diagram"></i> Мои проекты
                            </a>
                            <a href="logout.php" class="dropdown-item">
                                <i class="fas fa-sign-out-alt"></i> Выйти
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Основной контент -->
    <div class="profile-edit-page">
        <div class="page-header">
            <h1>Редактирование профиля</h1>
            <p class="text-secondary">Обновите информацию о себе и настройте внешний вид профиля</p>
        </div>

        <!-- Сообщения -->
        <?php if (!empty($errors)): ?>
            <ul class="error-list">
                <?php foreach ($errors as $error): ?>
                    <li><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="profile-form-container">
            <!-- Левая колонка - аватар -->
            <div class="avatar-section">
                <div class="avatar-preview">
                    <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" 
                         alt="Текущий аватар" 
                         id="avatarPreview">
                    <button type="button" class="avatar-upload-btn" onclick="document.getElementById('avatarInput').click()">
                        <i class="fas fa-camera"></i>
                    </button>
                    <input type="file" 
                           id="avatarInput" 
                           name="avatar" 
                           accept="image/*" 
                           style="display: none;"
                           onchange="previewAvatar(this)">
                </div>
                <p class="avatar-info">
                    Нажмите на иконку камеры для загрузки нового аватара
                </p>
                <p class="avatar-info">
                    Разрешены JPG, PNG, GIF, WebP до 5MB
                </p>
            </div>

            <!-- Правая колонка - формы -->
            <div>
                <!-- Основная информация -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-user-circle"></i> Основная информация
                    </h2>
                    
                    <div class="form-group">
                        <label class="form-label required">Полное имя</label>
                        <input type="text" 
                               name="full_name" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                               required
                               placeholder="Иван Иванов">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">О себе (биография)</label>
                        <textarea name="bio" 
                                  class="form-textarea" 
                                  id="bioField"
                                  placeholder="Расскажите о себе, своих интересах и опыте..."
                                  maxlength="500"><?php echo htmlspecialchars($user['bio']); ?></textarea>
                        <div class="char-counter">
                            <span id="charCount">0</span>/500 символов
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Местоположение</label>
                        <input type="text" 
                               name="location" 
                               class="form-input" 
                               value="<?php echo htmlspecialchars($user['location']); ?>"
                               placeholder="Москва, Россия">
                    </div>
                </div>

                <!-- Социальные сети -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-share-alt"></i> Социальные сети
                    </h2>
                    
                    <div class="form-group">
                        <label class="form-label">GitHub</label>
                        <div class="social-input-group">
                            <div class="social-icon">
                                <i class="fab fa-github"></i>
                            </div>
                            <input type="url" 
                                   name="github_url" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($user['github_url'] ?? ''); ?>"
                                   placeholder="https://github.com/username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">LinkedIn</label>
                        <div class="social-input-group">
                            <div class="social-icon">
                                <i class="fab fa-linkedin"></i>
                            </div>
                            <input type="url" 
                                   name="linkedin_url" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($user['linkedin_url'] ?? ''); ?>"
                                   placeholder="https://linkedin.com/in/username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Telegram</label>
                        <div class="social-input-group">
                            <div class="social-icon">
                                <i class="fab fa-telegram"></i>
                            </div>
                            <input type="url" 
                                   name="telegram_url" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($user['telegram_url'] ?? ''); ?>"
                                   placeholder="https://t.me/username">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Веб-сайт</label>
                        <div class="social-input-group">
                            <div class="social-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <input type="url" 
                                   name="website_url" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($user['website_url'] ?? ''); ?>"
                                   placeholder="https://example.com">
                        </div>
                    </div>
                </div>

                <!-- Безопасность -->
                <div class="form-section">
                    <h2 class="section-title">
                        <i class="fas fa-lock"></i> Безопасность
                    </h2>
                    <p class="form-help">Заполняйте поля ниже только если хотите изменить пароль</p>
                    
                    <div class="form-group">
                        <label class="form-label">Текущий пароль</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   name="current_password" 
                                   class="form-input" 
                                   id="currentPassword"
                                   placeholder="Введите текущий пароль">
                            <button type="button" class="password-toggle" onclick="togglePassword('currentPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Новый пароль</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   name="new_password" 
                                   class="form-input" 
                                   id="newPassword"
                                   placeholder="Введите новый пароль (мин. 6 символов)">
                            <button type="button" class="password-toggle" onclick="togglePassword('newPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Подтвердите новый пароль</label>
                        <div class="password-input-wrapper">
                            <input type="password" 
                                   name="confirm_password" 
                                   class="form-input" 
                                   id="confirmPassword"
                                   placeholder="Повторите новый пароль">
                            <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', this)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Кнопки действий -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить изменения
                    </button>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Отмена
                    </a>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Предпросмотр аватара
        function previewAvatar(input) {
            const preview = document.getElementById('avatarPreview');
            const file = input.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                }
                
                reader.readAsDataURL(file);
                
                // Показываем имя файла
                const avatarInfo = document.querySelector('.avatar-info');
                if (avatarInfo) {
                    avatarInfo.textContent = `Выбран файл: ${file.name} (${formatFileSize(file.size)})`;
                }
            }
        }
        
        // Счетчик символов для биографии
        const bioField = document.getElementById('bioField');
        const charCount = document.getElementById('charCount');
        
        if (bioField && charCount) {
            // Устанавливаем начальное значение
            charCount.textContent = bioField.value.length;
            
            // Обновляем при вводе
            bioField.addEventListener('input', function() {
                charCount.textContent = this.value.length;
                
                if (this.value.length > 500) {
                    charCount.style.color = 'var(--danger-color)';
                } else if (this.value.length > 450) {
                    charCount.style.color = 'var(--warning-color)';
                } else {
                    charCount.style.color = 'var(--text-secondary)';
                }
            });
        }
        
        // Переключение видимости пароля
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Форматирование размера файла
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Валидация URL перед отправкой
        document.querySelector('form').addEventListener('submit', function(e) {
            const urlInputs = this.querySelectorAll('input[type="url"]');
            let hasErrors = false;
            
            urlInputs.forEach(input => {
                if (input.value && !isValidUrl(input.value)) {
                    alert(`Некорректный URL в поле: ${input.previousElementSibling?.textContent || 'ссылка'}`);
                    input.focus();
                    hasErrors = true;
                    e.preventDefault();
                }
            });
            
            if (hasErrors) {
                e.preventDefault();
            }
        });
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
        
        // Обработка темы и языка
        document.querySelector('.theme-toggle')?.addEventListener('click', function() {
            const newTheme = document.body.classList.contains('theme-dark') ? 'light' : 'dark';
            fetch('api/update_settings.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    setting: 'theme',
                    value: newTheme
                })
            }).then(() => {
                location.reload();
            });
        });
        
        document.querySelectorAll('.lang-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const lang = this.dataset.lang;
                fetch('api/update_settings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        setting: 'language',
                        value: lang
                    })
                }).then(() => {
                    location.reload();
                });
            });
        });
    </script>
</body>
</html>