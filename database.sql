-- ============================================================
-- 上网入口 - 数据库初始化脚本
-- 适用：MySQL 5.7+ / 8.0
-- 用法一：phpMyAdmin 导入本文件
-- 用法二：命令行 mysql -u root -p < database.sql
-- 用法三：直接访问站点 install.php 一键安装（无需本文件）
--
-- 默认管理员：admin / admin123（首次登录后请立即修改密码）
-- ============================================================

CREATE DATABASE IF NOT EXISTS `net_portal`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `net_portal`;

-- ---------------- 用户表 ----------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(191) NULL DEFAULT NULL,
  `email_verified` TINYINT NOT NULL DEFAULT 0 COMMENT '1=邮箱已验证 0=未验证',
  `phone` VARCHAR(50) NOT NULL DEFAULT '',
  `real_name` VARCHAR(50) NOT NULL DEFAULT '',
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','user') NOT NULL DEFAULT 'user',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1=正常 0=禁用',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uk_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- 分类表 ----------------
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- 链接表 ----------------
CREATE TABLE IF NOT EXISTS `links` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NULL=全局链接（管理员维护）；非NULL=该用户的个人链接',
  `category_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `description` VARCHAR(500) NOT NULL DEFAULT '',
  `icon` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'emoji 或图标图片地址',
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_pinned` TINYINT NOT NULL DEFAULT 0 COMMENT '1=置顶 0=正常',
  `status` TINYINT NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_category` (`category_id`),
  KEY `idx_user` (`user_id`),
  CONSTRAINT `fk_links_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- 点击统计表（明细） ----------------
CREATE TABLE IF NOT EXISTS `click_logs` (
  `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `link_id` INT UNSIGNED NOT NULL,
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_link` (`link_id`),
  KEY `idx_date` (`created_at`),
  CONSTRAINT `fk_clicks_link` FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- 点击统计表（按天聚合，大数据量性能） ----------------
CREATE TABLE IF NOT EXISTS `click_daily` (
  `link_id` INT UNSIGNED NOT NULL,
  `click_date` DATE NOT NULL,
  `count` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`link_id`, `click_date`),
  KEY `idx_date` (`click_date`),
  CONSTRAINT `fk_daily_link` FOREIGN KEY (`link_id`) REFERENCES `links`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- 站点设置表（外观等 key-value） ----------------
CREATE TABLE IF NOT EXISTS `settings` (
  `skey` VARCHAR(64) NOT NULL PRIMARY KEY,
  `svalue` TEXT,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 默认设置（后台「外观设置」页可修改）
INSERT IGNORE INTO `settings` (`skey`, `svalue`) VALUES
('site_name', ''),
('tagline', ''),
('accent_color', '#2563eb'),
('bg_mode', 'default'),
('bg_color', '#f3f5f9'),
('gradient_a', '#60a5fa'),
('gradient_b', '#8b5cf6'),
('bg_image', ''),
('bg_overlay', '0.35'),
('bg_fixed', '1'),
('card_opacity', '1'),
('footer_text', '');

-- ---------------- 用户个人外观设置表（个人中心「我的外观」） ----------------
CREATE TABLE IF NOT EXISTS `user_settings` (
  `user_id` INT UNSIGNED NOT NULL PRIMARY KEY,
  `accent_color` VARCHAR(20) NOT NULL DEFAULT '',
  `bg_mode` VARCHAR(20) NOT NULL DEFAULT '',
  `bg_color` VARCHAR(20) NOT NULL DEFAULT '',
  `gradient_a` VARCHAR(20) NOT NULL DEFAULT '',
  `gradient_b` VARCHAR(20) NOT NULL DEFAULT '',
  `bg_image` VARCHAR(500) NOT NULL DEFAULT '',
  `bg_overlay` VARCHAR(10) NOT NULL DEFAULT '',
  `bg_fixed` VARCHAR(2) NOT NULL DEFAULT '',
  `card_opacity` VARCHAR(10) NOT NULL DEFAULT '',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------- 初始数据 ----------------

-- 管理员账号（密码：admin123）
INSERT INTO `users` (`username`, `password_hash`, `role`, `status`) VALUES
('admin', '$2y$10$PVZPu25PiqRB2DxFbVzsJue4yjGPE/lc5c5LJ/G7S0Pd9um/vFb8a', 'admin', 1);

-- 示例分类
INSERT INTO `categories` (`name`, `sort_order`, `status`) VALUES
('常用工具', 1, 1),
('技术开发', 2, 1),
('资讯阅读', 3, 1),
('影音娱乐', 4, 1);

-- 示例链接
INSERT INTO `links` (`category_id`, `title`, `url`, `description`, `icon`, `sort_order`, `is_pinned`, `status`) VALUES
(1, '百度搜索', 'https://www.baidu.com', '综合搜索', '🔍', 1, 0, 1),
(1, 'Google', 'https://www.google.com', '全球搜索', '🌐', 2, 0, 1),
(1, 'Bing', 'https://www.bing.com', '微软搜索', '🪟', 3, 0, 1),
(1, '百度网盘', 'https://pan.baidu.com', '云盘存储', '📦', 4, 0, 1),
(1, '有道翻译', 'https://fanyi.youdao.com', '在线翻译', '📖', 5, 0, 1),
(2, 'GitHub', 'https://github.com', '代码托管与开源社区', '🐙', 1, 0, 1),
(2, 'Gitee 码云', 'https://gitee.com', '国内代码托管', '🐴', 2, 0, 1),
(2, '掘金', 'https://juejin.cn', '技术文章社区', '⛏️', 3, 0, 1),
(2, '菜鸟教程', 'https://www.runoob.com', '编程入门教程', '🐦', 4, 0, 1),
(2, 'MDN Web Docs', 'https://developer.mozilla.org', 'Web 开发文档', '📚', 5, 0, 1),
(3, '知乎', 'https://www.zhihu.com', '问答与社区', '💡', 1, 0, 1),
(3, '微博', 'https://weibo.com', '社交媒体', '📣', 2, 0, 1),
(3, '36氪', 'https://36kr.com', '科技与创投资讯', '⚡', 3, 0, 1),
(3, '少数派', 'https://sspai.com', '效率与生活方式', '🎯', 4, 0, 1),
(3, '澎湃新闻', 'https://www.thepaper.cn', '时事新闻', '📰', 5, 0, 1),
(4, '哔哩哔哩', 'https://www.bilibili.com', '视频弹幕网站', '📺', 1, 0, 1),
(4, '腾讯视频', 'https://v.qq.com', '在线影视', '🎬', 2, 0, 1),
(4, '网易云音乐', 'https://music.163.com', '在线音乐', '🎵', 3, 0, 1),
(4, '抖音', 'https://www.douyin.com', '短视频', '📱', 4, 0, 1);
