// Состояние приложения
let state = {
    projects: [],
    skills: [],
    filters: {
        language: null,
        tag: null
    },
    sortBy: 'lastUpdated',
    theme: document.body.classList.contains('theme-dark') ? 'dark' : 'light',
    language: document.documentElement.lang || 'ru',
    pinnedProjects: []
};

// Тексты локализации
const translations = {
    ru: {
        pinned: 'Закрепленные проекты',
        myProjects: 'Мои проекты',
        skills: 'Навыки и инструменты',
        activity: 'Активность за год',
        less: 'Меньше',
        more: 'Больше',
        filterAll: 'Все языки',
        allLanguages: 'Все языки',
        sortDate: 'По дате обновления',
        sortStars: 'По количеству звезд',
        sortName: 'По названию',
        commits: 'коммитов',
        copyright: '© 2026 DevFolio. Все права защищены.',
        sourceCode: 'Исходный код этого портфолио',
        limitMessage: 'Можно закрепить не более 6 проектов!',
        noPinned: 'Нет закрепленных проектов',
        noProjects: 'Проекты не найдены',
        projectPinned: 'Проект закреплен!',
        projectUnpinned: 'Проект откреплен!',
        themeChanged: 'Тема изменена!',
        languageChanged: 'Язык изменен!',
        totalCommits: 'Всего: <strong>1,234</strong> коммитов'
    },
    en: {
        pinned: 'Pinned Projects',
        myProjects: 'My Projects',
        skills: 'Skills and Tools',
        activity: 'Activity this year',
        less: 'Less',
        more: 'More',
        filterAll: 'All languages',
        allLanguages: 'All languages',
        sortDate: 'By update date',
        sortStars: 'By stars count',
        sortName: 'By name',
        commits: 'commits',
        copyright: '© 2026 DevFolio. All rights reserved.',
        sourceCode: 'Source code of this portfolio',
        limitMessage: 'You can pin no more than 6 projects!',
        noPinned: 'No pinned projects',
        noProjects: 'No projects found',
        projectPinned: 'Project pinned!',
        projectUnpinned: 'Project unpinned!',
        themeChanged: 'Theme changed!',
        languageChanged: 'Language changed!',
        totalCommits: 'Total: <strong>1,234</strong> commits'
    },
    es: {
        pinned: 'Proyectos fijados',
        myProjects: 'Mis proyectos',
        skills: 'Habilidades y herramientas',
        activity: 'Actividad este año',
        less: 'Menos',
        more: 'Más',
        filterAll: 'Todos los idiomas',
        allLanguages: 'Todos los idiomas',
        sortDate: 'Por fecha de actualización',
        sortStars: 'Por cantidad de estrellas',
        sortName: 'Por nombre',
        commits: 'commits',
        copyright: '© 2026 DevFolio. Todos los derechos reservados.',
        sourceCode: 'Código fuente de este portafolio',
        limitMessage: '¡Puedes fijar un máximo de 6 proyectos!',
        noPinned: 'No hay proyectos fijados',
        noProjects: 'No se encontraron proyectos',
        projectPinned: '¡Proyecto fijado!',
        projectUnpinned: '¡Proyecto desfijado!',
        themeChanged: '¡Tema cambiado!',
        languageChanged: '¡Idioma cambiado!',
        totalCommits: 'Total: <strong>1,234</strong> commits'
    }
};

// Инициализация приложения
document.addEventListener('DOMContentLoaded', function() {
    initApp();
});

async function initApp() {
    // Загрузка данных
    await loadProjects();
    await loadSkills();
    
    // Назначение обработчиков событий
    setupEventListeners();
    
    // Первоначальная отрисовка
    renderAll();
}

// Загрузка проектов из БД
async function loadProjects() {
    try {
        const response = await fetch('api/get_projects.php');
        if (response.ok) {
            state.projects = await response.json();
        } else {
            throw new Error('Failed to load projects');
        }
    } catch (error) {
        console.error('Error loading projects:', error);
        // Fallback к статичным данным
        state.projects = getFallbackProjects();
    }
}

// Загрузка навыков из БД
async function loadSkills() {
    try {
        const response = await fetch('api/get_skills.php');
        if (response.ok) {
            state.skills = await response.json();
        } else {
            throw new Error('Failed to load skills');
        }
    } catch (error) {
        console.error('Error loading skills:', error);
        // Если нет навыков, показываем пустой массив
        state.skills = [];
    }
}

// Статичные проекты как fallback
function getFallbackProjects() {
    return [
        {
            id: 1,
            name: 'E-commerce Platform',
            description: 'Полнофункциональная платформа для онлайн-торговли с системой управления заказами',
            language: 'JavaScript',
            languageColor: '#f1e05a',
            stars: 42,
            pinned: true,
            tags: ['react', 'nodejs', 'mongodb'],
            lastUpdated: '2023-10-28'
        },
    ];
}

// Статичные навыки как fallback
function getFallbackSkills() {
    return [
        { name: 'JavaScript', level: 90, color: '#f0db4f' },
        { name: 'PHP', level: 85, color: '#787cb5' },
        { name: 'HTML/CSS', level: 95, color: '#e34c26' },
        { name: 'React', level: 80, color: '#61dafb' },
        { name: 'Vue.js', level: 75, color: '#41b883' },
        { name: 'Node.js', level: 70, color: '#68a063' },
        { name: 'Git', level: 90, color: '#f1502f' },
        { name: 'MySQL', level: 75, color: '#00758f' },
    ];
}

// Назначение обработчиков событий
function setupEventListeners() {
    // Переключение темы
    document.querySelector('.theme-toggle').addEventListener('click', toggleTheme);
    
    // Переключение языка
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            changeLanguage(this.dataset.lang);
        });
    });
    
    // Фильтрация
    document.getElementById('languageFilter').addEventListener('change', function() {
        state.filters.language = this.value || null;
        renderProjects();
    });
    
    // Сортировка
    document.getElementById('sortBy').addEventListener('change', function() {
        state.sortBy = this.value;
        renderProjects();
    });
    
    // Мобильное меню
    document.querySelector('.mobile-menu-btn').addEventListener('click', toggleMobileMenu);
    
    // Навигация с плавным скроллом
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.hash) {
                e.preventDefault();
                
                // Обновляем активную ссылку
                document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                // Плавный скролл к секции
                const target = document.querySelector(this.hash);
                if (target) {
                    const headerOffset = 80;
                    const elementPosition = target.offsetTop;
                    const offsetPosition = elementPosition - headerOffset;
                    
                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                    
                    // Добавляем подсветку секции
                    target.classList.add('highlight');
                    setTimeout(() => {
                        target.classList.remove('highlight');
                    }, 1500);
                }
            }
        });
    });
    
    // Выпадающее меню пользователя
    const userBtn = document.querySelector('.user-btn');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    
    if (userBtn && dropdownMenu) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            dropdownMenu.classList.toggle('show');
        });
        
        // Закрытие меню при клике вне его
        document.addEventListener('click', function() {
            dropdownMenu.classList.remove('show');
        });
    }
    
    // Закрытие мобильного меню при клике на ссылку
    document.querySelectorAll('.nav-link').forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                document.querySelector('.nav-menu').style.display = 'none';
            }
        });
    });
}

// Рендер всех секций
function renderAll() {
    renderPinnedProjects();
    renderProjects();
    renderSkills();
    updateLocalizedTexts();
}

// Рендер закрепленных проектов
function renderPinnedProjects() {
    const container = document.getElementById('pinnedProjects');
    const pinned = state.projects.filter(p => p.pinned);
    const t = translations[state.language];
    
    if (pinned.length === 0) {
        container.innerHTML = `<p style="color: var(--text-secondary);">${t.noPinned}</p>`;
        return;
    }
    
    container.innerHTML = pinned.map(project => createProjectCard(project)).join('');
    
    // Добавляем обработчики для кнопок pin
    container.querySelectorAll('.pin-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const projectId = parseInt(this.closest('.project-card').dataset.id);
            togglePinProject(projectId);
        });
    });
}

// Рендер всех проектов
function renderProjects() {
    const container = document.getElementById('projectsGrid');
    const t = translations[state.language];
    
    // Фильтрация
    let filtered = [...state.projects];
    
    if (state.filters.language) {
        filtered = filtered.filter(p => p.language === state.filters.language);
    }
    
    // Сортировка
    filtered.sort((a, b) => {
        switch (state.sortBy) {
            case 'stars':
                return b.stars - a.stars;
            case 'name':
                return a.name.localeCompare(b.name);
            case 'lastUpdated':
            default:
                return new Date(b.lastUpdated) - new Date(a.lastUpdated);
        }
    });
    
    if (filtered.length === 0) {
        container.innerHTML = `<p style="color: var(--text-secondary); grid-column: 1/-1; text-align: center;">${t.noProjects}</p>`;
        return;
    }
    
    container.innerHTML = filtered.map(project => createProjectCard(project)).join('');
    
    // Добавляем обработчики для кнопок pin
    container.querySelectorAll('.pin-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const projectId = parseInt(this.closest('.project-card').dataset.id);
            togglePinProject(projectId);
        });
    });
}

// Рендер навыков
function renderSkills() {
    const container = document.getElementById('skillsContainer');
    
    if (state.skills.length === 0) {
        container.innerHTML = '<p style="color: var(--text-secondary); text-align: center;">Навыки не найдены</p>';
        return;
    }
    
    container.innerHTML = state.skills.map(skill => createSkillItem(skill)).join('');
    
    // Анимация появления
    setTimeout(() => {
        container.querySelectorAll('.skill-level').forEach(bar => {
            bar.style.width = bar.style.width;
        });
    }, 100);
}

// Создание элемента навыка
function createSkillItem(skill) {
    return `
    <div class='skill-item'>
        <div class='skill-header'>
            <span class='skill-name'>${skill.name}</span>
            <span class='skill-percent'>${skill.level}%</span>
        </div>
        <div class='skill-bar'>
            <div class='skill-level' 
                 style='width: 0%; background-color: ${skill.color};'
                 data-width='${skill.level}%'></div>
        </div>
    </div>`;
}

// Создание карточки проекта
function createProjectCard(project) {
    return `
    <div class="project-card ${project.pinned ? 'pinned' : ''}" data-id="${project.id}">
        <div class="project-header">
            <a href="#" class="project-title">${project.name}</a>
            <button class="pin-btn ${project.pinned ? 'active' : ''}" 
                    title="${project.pinned ? 'Открепить' : 'Закрепить'}">
                <i class="fas fa-thumbtack"></i>
            </button>
        </div>
        <p class="project-description">${project.description}</p>
        <div class="project-tags">
            ${project.tags ? project.tags.map(tag => `<span class="tag">${tag}</span>`).join('') : ''}
        </div>
        <div class="project-footer">
            <div class="project-language">
                <span class="language-dot" style="background-color: ${project.languageColor || '#ccc'}"></span>
                <span>${project.language}</span>
            </div>
            <div class="project-stars">
                <i class="far fa-star"></i>
                <span>${project.stars}</span>
            </div>
            <span class="project-date">Обновлен: ${formatDate(project.lastUpdated)}</span>
        </div>
    </div>`;
}

// Закрепление/открепление проекта
async function togglePinProject(projectId) {
    const project = state.projects.find(p => p.id === projectId);
    if (!project) return;
    
    // Если пытаемся закрепить
    if (!project.pinned) {
        // Проверяем лимит
        const pinnedCount = state.projects.filter(p => p.pinned).length;
        if (pinnedCount >= 6) {
            showNotification(translations[state.language].limitMessage, 'warning');
            return;
        }
    }
    
    // Меняем статус
    project.pinned = !project.pinned;
    
    try {
        // Сохраняем в БД
        const response = await fetch('api/update_project.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                projectId: project.id,
                pinned: project.pinned
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to update project');
        }
    } catch (error) {
        console.error('Error updating project:', error);
        // Отменяем изменение если ошибка
        project.pinned = !project.pinned;
        showNotification('Ошибка при обновлении проекта', 'error');
        return;
    }
    
    // Перерисовываем
    renderPinnedProjects();
    renderProjects();
    
    // Показываем уведомление
    const t = translations[state.language];
    showNotification(
        project.pinned ? t.projectPinned : t.projectUnpinned,
        'success'
    );
}

// Переключение темы
async function toggleTheme() {
    const newTheme = state.theme === 'dark' ? 'light' : 'dark';
    state.theme = newTheme;
    
    // Обновляем класс body
    document.body.className = `theme-${newTheme}`;
    
    // Обновляем иконку
    updateThemeIcon(newTheme);
    
    // Сохраняем в БД через API
    try {
        const response = await fetch('api/update_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                setting: 'theme',
                value: newTheme
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to save theme');
        }
    } catch (error) {
        console.error('Error saving theme:', error);
    }
    
    // Показываем уведомление
    const t = translations[state.language];
    showNotification(t.themeChanged, 'success');
}

// Обновление иконки темы
function updateThemeIcon(theme) {
    const icon = document.querySelector('.theme-toggle i');
    const button = document.querySelector('.theme-toggle');
    
    if (icon) {
        icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
    }
    
    if (button) {
        button.title = theme === 'dark' ? 'Включить светлую тему' : 'Включить темную тему';
    }
}

// Смена языка
async function changeLanguage(lang) {
    state.language = lang;
    
    // Обновляем атрибут html
    document.documentElement.lang = lang;
    
    // Обновляем кнопки
    updateLanguageButtons(lang);
    
    // Обновляем тексты
    updateLocalizedTexts();
    
    // Сохраняем в БД через API
    try {
        const response = await fetch('api/update_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                setting: 'language',
                value: lang
            })
        });
        
        if (!response.ok) {
            throw new Error('Failed to save language');
        }
    } catch (error) {
        console.error('Error saving language:', error);
    }
    
    // Показываем уведомление
    const t = translations[state.language];
    showNotification(t.languageChanged, 'success');
}

// Обновление кнопок языка
function updateLanguageButtons(lang) {
    document.querySelectorAll('.lang-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.lang === lang);
    });
}

// Обновление локализованных текстов
function updateLocalizedTexts() {
    const t = translations[state.language];
    
    // Обновляем все элементы с data-i18n атрибутом
    document.querySelectorAll('[data-i18n]').forEach(element => {
        const key = element.getAttribute('data-i18n');
        if (t[key]) {
            element.innerHTML = t[key]; // Используем innerHTML для поддержки тегов
        }
    });
    
    // Обновляем заголовки фильтров и сортировки
    const filterSelect = document.getElementById('languageFilter');
    if (filterSelect) {
        const allOption = filterSelect.querySelector('option[value=""]');
        if (allOption && allOption.getAttribute('data-i18n')) {
            allOption.textContent = t[allOption.getAttribute('data-i18n')];
        }
    }
    
    const sortSelect = document.getElementById('sortBy');
    if (sortSelect) {
        sortSelect.querySelectorAll('option').forEach(option => {
            const key = option.getAttribute('data-i18n');
            if (key && t[key]) {
                option.textContent = t[key];
            }
        });
    }
    
    // Обновляем названия кнопок в выпадающем меню
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    dropdownItems.forEach(item => {
        const icon = item.querySelector('i');
        const text = item.textContent.trim();
        
        if (text.includes('Мой профиль') || text.includes('My Profile') || text.includes('Mi perfil')) {
            item.innerHTML = `<i class="${icon.className}"></i> ${state.language === 'en' ? 'My Profile' : state.language === 'es' ? 'Mi perfil' : 'Мой профиль'}`;
        } else if (text.includes('Выйти') || text.includes('Logout') || text.includes('Salir')) {
            item.innerHTML = `<i class="${icon.className}"></i> ${state.language === 'en' ? 'Logout' : state.language === 'es' ? 'Salir' : 'Выйти'}`;
        }
    });
}

// Форматирование даты
function formatDate(dateString) {
    if (!dateString) return '';
    
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
    
    const t = translations[state.language];
    
    if (diffDays === 0) return state.language === 'en' ? 'today' : state.language === 'es' ? 'hoy' : 'сегодня';
    if (diffDays === 1) return state.language === 'en' ? 'yesterday' : state.language === 'es' ? 'ayer' : 'вчера';
    if (diffDays < 7) {
        if (state.language === 'en') return `${diffDays} days ago`;
        if (state.language === 'es') return `hace ${diffDays} días`;
        return `${diffDays} дней назад`;
    }
    if (diffDays < 30) {
        const weeks = Math.floor(diffDays / 7);
        if (state.language === 'en') return `${weeks} weeks ago`;
        if (state.language === 'es') return `hace ${weeks} semanas`;
        return `${weeks} недель назад`;
    }
    
    return date.toLocaleDateString(state.language === 'ru' ? 'ru-RU' : state.language === 'es' ? 'es-ES' : 'en-US', {
        day: 'numeric',
        month: 'short',
        year: 'numeric'
    });
}

// Показать уведомление
function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const text = document.getElementById('notificationText');
    
    if (!notification || !text) return;
    
    // Устанавливаем цвет в зависимости от типа
    const colors = {
        success: 'var(--success-color)',
        warning: 'var(--warning-color)',
        error: 'var(--danger-color)'
    };
    
    notification.style.backgroundColor = colors[type] || colors.success;
    text.textContent = message;
    
    // Показываем
    notification.classList.add('show');
    
    // Скрываем через 3 секунды
    setTimeout(() => {
        notification.classList.remove('show');
    }, 3000);
}

// Мобильное меню
function toggleMobileMenu() {
    const navMenu = document.querySelector('.nav-menu');
    const isVisible = navMenu.style.display === 'flex';
    
    navMenu.style.display = isVisible ? 'none' : 'flex';
    
    // Адаптивный стиль для мобильного меню
    if (window.innerWidth <= 768) {
        if (!isVisible) {
            navMenu.style.flexDirection = 'column';
            navMenu.style.position = 'absolute';
            navMenu.style.top = '100%';
            navMenu.style.left = '0';
            navMenu.style.right = '0';
            navMenu.style.backgroundColor = 'var(--bg-secondary)';
            navMenu.style.padding = '20px';
            navMenu.style.gap = '15px';
            navMenu.style.borderTop = '1px solid var(--border-color)';
            navMenu.style.borderBottom = '1px solid var(--border-color)';
            navMenu.style.zIndex = '1000';
            navMenu.style.alignItems = 'flex-start';
            
            // Перемещаем контролы в меню на мобильных
            const navControls = document.querySelector('.nav-controls');
            if (navControls && !navControls.parentElement.classList.contains('nav-menu')) {
                navMenu.appendChild(navControls);
                navControls.style.flexDirection = 'column';
                navControls.style.width = '100%';
                navControls.style.gap = '10px';
            }
        }
    }
}

// Закрытие мобильного меню при ресайзе
window.addEventListener('resize', function() {
    const navMenu = document.querySelector('.nav-menu');
    const navControls = document.querySelector('.nav-controls');
    
    if (window.innerWidth > 768) {
        navMenu.style.display = '';
        navMenu.style.flexDirection = '';
        navMenu.style.position = '';
        navMenu.style.backgroundColor = '';
        navMenu.style.padding = '';
        
        // Возвращаем контролы на место
        if (navControls && navControls.parentElement === navMenu) {
            const navMenuParent = document.querySelector('.nav-menu');
            if (navMenuParent) {
                // Вставляем перед кнопкой профиля
                const userDropdown = document.querySelector('.user-dropdown');
                if (userDropdown) {
                    navMenuParent.insertBefore(navControls, userDropdown);
                } else {
                    navMenuParent.appendChild(navControls);
                }
                navControls.style.flexDirection = '';
                navControls.style.width = '';
                navControls.style.gap = '';
            }
        }
    } else {
        navMenu.style.display = 'none';
    }
});

// Анимация навыков при скролле
function animateSkillsOnScroll() {
    const skillsSection = document.getElementById('skills');
    if (!skillsSection) return;
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const skillLevels = document.querySelectorAll('.skill-level');
                skillLevels.forEach(bar => {
                    const width = bar.getAttribute('data-width');
                    if (width) {
                        bar.style.width = width;
                    }
                });
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });
    
    observer.observe(skillsSection);
}

// Инициализация анимации при загрузке
window.addEventListener('load', function() {
    // Запускаем анимацию навыков
    setTimeout(() => {
        const skillLevels = document.querySelectorAll('.skill-level[data-width]');
        skillLevels.forEach(bar => {
            bar.style.width = bar.getAttribute('data-width');
        });
    }, 500);
    
    // Инициализируем наблюдатель для навыков
    animateSkillsOnScroll();
});