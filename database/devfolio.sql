-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1:3306
-- Время создания: Июл 24 2026 г., 11:38
-- Версия сервера: 8.0.30
-- Версия PHP: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `devfolio`
--

-- --------------------------------------------------------

--
-- Структура таблицы `commits`
--

CREATE TABLE `commits` (
  `id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `message` text,
  `commit_hash` varchar(32) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `commits`
--

INSERT INTO `commits` (`id`, `project_id`, `user_id`, `message`, `commit_hash`, `created_at`) VALUES
(3, 12, 2, 'Added file: commits.php', '66b5810d', '2026-01-13 15:54:13'),
(4, 12, 2, 'Added file: config.php', 'f9cd650b', '2026-01-13 15:54:19'),
(5, 12, 2, 'Added file: index.php', '13d28e4d', '2026-01-13 15:54:31'),
(6, 12, 2, 'Added file: login.php', '86c62d23', '2026-01-13 15:54:39'),
(7, 12, 2, 'Added file: logout.php', '1bc4d653', '2026-01-13 15:54:45'),
(8, 12, 2, 'Added file: profile.php', '556895f9', '2026-01-13 15:54:51'),
(9, 12, 2, 'Added file: project_editor.php', '323cc61f', '2026-01-13 15:54:56'),
(10, 12, 2, 'Added file: project_files.php', '0fff5673', '2026-01-13 15:55:02'),
(11, 12, 2, 'Added file: projects.php', 'e01b1c1c', '2026-01-13 15:55:08'),
(12, 12, 2, 'Added file: register.php', 'fc03a5be', '2026-01-13 15:55:14'),
(13, 12, 2, 'Added file: script.js', 'e8dd9457', '2026-01-13 15:55:19'),
(14, 12, 2, 'Added file: style.css', '3e8eeea9', '2026-01-13 15:55:23'),
(15, 12, 2, 'Added file: delete_project.php', 'a4d44546', '2026-01-13 15:55:29'),
(16, 12, 2, 'Added file: get_commit_details.php', '069d9a6e', '2026-01-13 15:55:34'),
(17, 12, 2, 'Added file: get_file_content.php', '87b66c3b', '2026-01-13 15:55:38'),
(18, 12, 2, 'Added file: get_projects.php', '1be9f044', '2026-01-13 15:55:43'),
(19, 12, 2, 'Added file: get_skills.php', '0e4cd2b7', '2026-01-13 15:55:48'),
(20, 12, 2, 'Added file: update_project.php', '18e53566', '2026-01-13 15:55:54'),
(21, 12, 2, 'Added file: update_settings.php', 'f326b8e4', '2026-01-13 15:55:59');

-- --------------------------------------------------------

--
-- Структура таблицы `file_history`
--

CREATE TABLE `file_history` (
  `id` int NOT NULL,
  `file_id` int DEFAULT NULL,
  `commit_id` int DEFAULT NULL,
  `old_content` longtext,
  `new_content` longtext,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `projects`
--

CREATE TABLE `projects` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `language` varchar(50) DEFAULT NULL,
  `language_color` varchar(20) DEFAULT NULL,
  `stars` int DEFAULT '0',
  `pinned` tinyint(1) DEFAULT '0',
  `tags` json DEFAULT NULL,
  `last_updated` date DEFAULT NULL,
  `project_path` varchar(500) DEFAULT NULL,
  `total_files` int DEFAULT '0',
  `total_lines` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `projects`
--

INSERT INTO `projects` (`id`, `user_id`, `name`, `description`, `language`, `language_color`, `stars`, `pinned`, `tags`, `last_updated`, `project_path`, `total_files`, `total_lines`) VALUES
(12, 2, 'DevFolio', 'Учебная практика', NULL, NULL, 0, 0, NULL, '2026-01-13', 'uploads/2/1768319620_DevFolio', 19, 6912);

-- --------------------------------------------------------

--
-- Структура таблицы `project_files`
--

CREATE TABLE `project_files` (
  `id` int NOT NULL,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(500) NOT NULL,
  `filetype` varchar(50) DEFAULT NULL,
  `filesize` int DEFAULT NULL,
  `language` varchar(50) DEFAULT NULL,
  `lines_of_code` int DEFAULT '0',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `project_files`
--

INSERT INTO `project_files` (`id`, `project_id`, `user_id`, `filename`, `filepath`, `filetype`, `filesize`, `language`, `lines_of_code`, `uploaded_at`) VALUES
(3, 12, 2, 'commits.php', 'uploads/2/1768319620_DevFolio/commits.php', 'application/octet-stream', 42771, 'PHP', 1085, '2026-01-13 15:54:13'),
(4, 12, 2, 'config.php', 'uploads/2/1768319620_DevFolio/config.php', 'application/octet-stream', 1578, 'PHP', 54, '2026-01-13 15:54:19'),
(5, 12, 2, 'index.php', 'uploads/2/1768319620_DevFolio/index.php', 'application/octet-stream', 11970, 'PHP', 266, '2026-01-13 15:54:31'),
(6, 12, 2, 'login.php', 'uploads/2/1768319620_DevFolio/login.php', 'application/octet-stream', 5086, 'PHP', 164, '2026-01-13 15:54:39'),
(7, 12, 2, 'logout.php', 'uploads/2/1768319620_DevFolio/logout.php', 'application/octet-stream', 86, 'PHP', 6, '2026-01-13 15:54:45'),
(8, 12, 2, 'profile.php', 'uploads/2/1768319620_DevFolio/profile.php', 'application/octet-stream', 32633, 'PHP', 811, '2026-01-13 15:54:51'),
(9, 12, 2, 'project_editor.php', 'uploads/2/1768319620_DevFolio/project_editor.php', 'application/octet-stream', 4789, 'PHP', 117, '2026-01-13 15:54:56'),
(10, 12, 2, 'project_files.php', 'uploads/2/1768319620_DevFolio/project_files.php', 'application/octet-stream', 37465, 'PHP', 946, '2026-01-13 15:55:02'),
(11, 12, 2, 'projects.php', 'uploads/2/1768319620_DevFolio/projects.php', 'application/octet-stream', 50793, 'PHP', 1222, '2026-01-13 15:55:08'),
(12, 12, 2, 'register.php', 'uploads/2/1768319620_DevFolio/register.php', 'application/octet-stream', 7775, 'PHP', 211, '2026-01-13 15:55:14'),
(13, 12, 2, 'script.js', 'uploads/2/1768319620_DevFolio/script.js', 'text/javascript', 27794, 'JavaScript', 746, '2026-01-13 15:55:19'),
(14, 12, 2, 'style.css', 'uploads/2/1768319620_DevFolio/style.css', 'text/css', 19832, 'HTML/CSS', 979, '2026-01-13 15:55:23'),
(15, 12, 2, 'delete_project.php', 'uploads/2/1768319620_DevFolio/delete_project.php', 'application/octet-stream', 3622, 'PHP', 122, '2026-01-13 15:55:29'),
(16, 12, 2, 'get_commit_details.php', 'uploads/2/1768319620_DevFolio/get_commit_details.php', 'application/octet-stream', 1223, 'PHP', 38, '2026-01-13 15:55:34'),
(17, 12, 2, 'get_file_content.php', 'uploads/2/1768319620_DevFolio/get_file_content.php', 'application/octet-stream', 947, 'PHP', 30, '2026-01-13 15:55:38'),
(18, 12, 2, 'get_projects.php', 'uploads/2/1768319620_DevFolio/get_projects.php', 'application/octet-stream', 772, 'PHP', 31, '2026-01-13 15:55:43'),
(19, 12, 2, 'get_skills.php', 'uploads/2/1768319620_DevFolio/get_skills.php', 'application/octet-stream', 521, 'PHP', 22, '2026-01-13 15:55:48'),
(20, 12, 2, 'update_project.php', 'uploads/2/1768319620_DevFolio/update_project.php', 'application/octet-stream', 803, 'PHP', 31, '2026-01-13 15:55:54'),
(21, 12, 2, 'update_settings.php', 'uploads/2/1768319620_DevFolio/update_settings.php', 'application/octet-stream', 860, 'PHP', 31, '2026-01-13 15:55:59');

-- --------------------------------------------------------

--
-- Структура таблицы `skills`
--

CREATE TABLE `skills` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `level` int DEFAULT '0',
  `color` varchar(20) DEFAULT NULL,
  `total_lines` int DEFAULT '0',
  `last_updated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `bio` text,
  `location` varchar(100) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT 'https://avatars.githubusercontent.com/u/583231?v=4',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `github_url` varchar(255) DEFAULT NULL,
  `linkedin_url` varchar(255) DEFAULT NULL,
  `telegram_url` varchar(255) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `full_name`, `bio`, `location`, `avatar_url`, `created_at`, `github_url`, `linkedin_url`, `telegram_url`, `website_url`, `updated_at`) VALUES
(1, 'alexey', 'alexey@example.com', '$2y$10$YourHashedPasswordHere', 'Алексей Петров', 'Full-stack разработчик с 5-летним опытом', 'Москва, Россия', 'https://avatars.githubusercontent.com/u/583231?v=4', '2026-01-12 11:51:33', 'https://github.com/username', 'https://linkedin.com/in/username', 'https://t.me/username', NULL, '2026-01-12 12:31:33'),
(2, 'Dmirtriy', '123@gmail.com', '$2y$10$7Whtlg6TfX1jj1I6.zJI1.uovtrDUnIT8OHgXDK1S/xmSzPOw/WmO', 'Дмитрий', '', '', 'uploads/avatars/2/avatar_1768222069.jpg', '2026-01-12 11:54:16', 'https://github.com/username', 'https://linkedin.com/in/username', 'https://t.me/username', '', '2026-01-12 12:47:49');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `commits`
--
ALTER TABLE `commits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `commits_ibfk_1` (`project_id`);

--
-- Индексы таблицы `file_history`
--
ALTER TABLE `file_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `file_id` (`file_id`),
  ADD KEY `commit_id` (`commit_id`);

--
-- Индексы таблицы `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `project_files`
--
ALTER TABLE `project_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `project_files_ibfk_1` (`project_id`);

--
-- Индексы таблицы `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `commits`
--
ALTER TABLE `commits`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `file_history`
--
ALTER TABLE `file_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `projects`
--
ALTER TABLE `projects`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `project_files`
--
ALTER TABLE `project_files`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT для таблицы `skills`
--
ALTER TABLE `skills`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `commits`
--
ALTER TABLE `commits`
  ADD CONSTRAINT `commits_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `commits_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `file_history`
--
ALTER TABLE `file_history`
  ADD CONSTRAINT `file_history_ibfk_1` FOREIGN KEY (`file_id`) REFERENCES `project_files` (`id`),
  ADD CONSTRAINT `file_history_ibfk_2` FOREIGN KEY (`commit_id`) REFERENCES `commits` (`id`);

--
-- Ограничения внешнего ключа таблицы `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `project_files`
--
ALTER TABLE `project_files`
  ADD CONSTRAINT `project_files_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `project_files_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `skills`
--
ALTER TABLE `skills`
  ADD CONSTRAINT `skills_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
