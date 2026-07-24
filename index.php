<?php
require_once 'config.php';

// Если пользователь не авторизован, перенаправляем на логин
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = getUserData();
$theme = getTheme();
$language = getLanguage();
?>

<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DevFolio - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
                <a href="#profile" class="nav-link active">Профиль</a>
                <a href="projects.php" class="nav-link">Проекты</a>
                <a href="#skills" class="nav-link">Навыки</a>
                
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
    <div class="container main-container">
        <!-- Боковая панель профиля -->
        <aside class="sidebar" id="profile">
            <div class="profile-card">
                <img src="<?php echo htmlspecialchars($user['avatar_url']); ?>" 
                     alt="Аватар" class="avatar">
                <h1 class="name"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p class="bio"><?php echo htmlspecialchars($user['bio']); ?></p>
                <p class="location">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($user['location']); ?></span>
                </p>
                
                <div class="social-links">
    <?php if ($user['github_url']): ?>
        <a href="<?php echo htmlspecialchars($user['github_url']); ?>" target="_blank" class="social-link" title="GitHub">
            <i class="fab fa-github"></i>
        </a>
    <?php endif; ?>
    
    <?php if ($user['linkedin_url']): ?>
        <a href="<?php echo htmlspecialchars($user['linkedin_url']); ?>" target="_blank" class="social-link" title="LinkedIn">
            <i class="fab fa-linkedin"></i>
        </a>
    <?php endif; ?>
    
    <?php if ($user['telegram_url']): ?>
        <a href="<?php echo htmlspecialchars($user['telegram_url']); ?>" target="_blank" class="social-link" title="Telegram">
            <i class="fab fa-telegram"></i>
        </a>
    <?php endif; ?>
    
    <?php if ($user['website_url']): ?>
        <a href="<?php echo htmlspecialchars($user['website_url']); ?>" target="_blank" class="social-link" title="Веб-сайт">
            <i class="fas fa-globe"></i>
        </a>
    <?php endif; ?>
</div>
                
                <!-- Календарь активности -->
<div class="activity-graph">
    <div class="activity-header">
        <h3 data-i18n="activity">Активность за год</h3>
        <span class="activity-total" data-i18n="totalCommits">Всего: <strong>1,234</strong> коммитов</span>
    </div>
    <div class="calendar-container">
        <div class="month-labels">
            <span>Янв</span>
            <span>Фев</span>
            <span>Мар</span>
            <span>Апр</span>
            <span>Май</span>
            <span>Июн</span>
            <span>Июл</span>
            <span>Авг</span>
            <span>Сен</span>
            <span>Окт</span>
            <span>Ноя</span>
            <span>Дек</span>
        </div>
        <div class="calendar-wrapper">
            <div class="day-labels">
                <span>Пн</span>
                <span>Вт</span>
                <span>Ср</span>
                <span>Чт</span>
                <span>Пт</span>
                <span>Сб</span>
                <span>Вс</span>
            </div>
            <div class="calendar">
                <?php
                // Генерация компактного календаря (53 недели, но меньшего размера)
                $months = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];
                $currentMonth = -1;
                
                for ($week = 0; $week < 53; $week++) {
                    $showMonth = false;
                    $weekDate = strtotime("-" . (52 - $week) . " weeks");
                    
                    if (date('n', $weekDate) != $currentMonth) {
                        $currentMonth = date('n', $weekDate);
                        $showMonth = true;
                    }
                    
                    echo '<div class="week">';
                    
                    for ($day = 0; $day < 7; $day++) {
                        $intensity = rand(0, 4);
                        $count = rand(0, 15);
                        
                        // Делаем некоторые дни пустыми (начало/конец года)
                        if (($week == 0 && $day < 2) || ($week == 52 && $day > 4)) {
                            echo '<div class="day empty"></div>';
                        } else {
                            $date = date('Y-m-d', strtotime("-$week weeks +$day days"));
                            echo "<div class='day intensity-$intensity' 
                                  data-count='$count'
                                  data-date='$date'></div>";
                        }
                    }
                    
                    echo '</div>';
                }
                ?>
            </div>
        </div>
        <div class="activity-legend">
            <span data-i18n="less">Меньше</span>
            <div class="legend-cells">
                <div class="day intensity-0"></div>
                <div class="day intensity-1"></div>
                <div class="day intensity-2"></div>
                <div class="day intensity-3"></div>
                <div class="day intensity-4"></div>
            </div>
            <span data-i18n="more">Больше</span>
        </div>
    </div>
</div>
            </div>
        </aside>

        <!-- Основная область -->
        <main class="main-content">
            <!-- Закрепленные проекты -->
            <section class="pinned-section">
                <h2 data-i18n="pinned">Закрепленные проекты</h2>
                <div class="pinned-grid" id="pinnedProjects">
                    <!-- Закрепленные проекты загружаются через JS -->
                </div>
            </section>

            <!-- Все проекты с управлением -->
            <section class="projects-section" id="projects">
                <div class="section-header">
                    <h2 data-i18n="myProjects">Мои проекты</h2>
                    <div class="controls">
                        <div class="filter-control">
                            <select id="languageFilter" class="filter-select">
                                <option value="" data-i18n="allLanguages">Все языки</option>
                                <option value="JavaScript">JavaScript</option>
                                <option value="PHP">PHP</option>
                                <option value="Python">Python</option>
                                <option value="Java">Java</option>
                            </select>
                        </div>
                        <div class="sort-control">
                            <select id="sortBy" class="sort-select">
                                <option value="lastUpdated" data-i18n="sortDate">По дате обновления</option>
                                <option value="stars" data-i18n="sortStars">По количеству звезд</option>
                                <option value="name" data-i18n="sortName">По названию</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="projects-grid" id="projectsGrid">
                    <!-- Проекты загружаются через JS -->
                </div>
            </section>

            <!-- Навыки -->
            <section class="skills-section" id="skills">
                <h2 data-i18n="skills">Навыки и инструменты</h2>
                <div class="skills-container" id="skillsContainer">
                    <!-- Навыки загружаются через JS -->
                </div>
            </section>
        </main>
    </div>

    <!-- Футер -->
    <footer class="footer">
        <div class="container">
            <p data-i18n="copyright">© 2026 DevFolio. Все права защищены.</p>
        </div>
    </footer>

    <!-- Модальное окно уведомления -->
    <div class="notification" id="notification">
        <span id="notificationText"></span>
    </div>

    <script src="script.js"></script>
</body>
</html>