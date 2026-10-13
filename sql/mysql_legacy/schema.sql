-- Rode este script no phpMyAdmin, Adminer, ou via linha de comando:
-- mysql -u seu_usuario -p seu_banco < sql/schema.sql

CREATE TABLE IF NOT EXISTS `jf_admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(190) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jf_admin_sessions` (
  `token` CHAR(64) PRIMARY KEY,
  `admin_id` INT NOT NULL,
  `expires_at` DATETIME NOT NULL,
  FOREIGN KEY (`admin_id`) REFERENCES `jf_admin_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jf_profile` (
  `id` INT PRIMARY KEY,
  `name` VARCHAR(190) DEFAULT 'Seu Nome',
  `title` VARCHAR(190) DEFAULT 'Sua profissão ou frase de efeito',
  `bio` TEXT,
  `avatar_url` VARCHAR(500),
  `avatar_file` VARCHAR(255),
  `initials_override` VARCHAR(6) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `jf_profile` (`id`, `name`, `title`, `bio`)
VALUES (1, 'Seu Nome', 'Sua profissão ou frase de efeito', 'Uma bio curta sobre você.');

CREATE TABLE IF NOT EXISTS `jf_settings` (
  `id` INT PRIMARY KEY,
  `bg_color` VARCHAR(9) DEFAULT '#0F3D3E',
  `bg_image` VARCHAR(500),
  `banner_type` VARCHAR(20) DEFAULT 'none',
  `banner_url` VARCHAR(500) DEFAULT NULL,
  `banner_video_url` VARCHAR(500) DEFAULT NULL,
  `banner_slides` TEXT DEFAULT NULL,
  `banner_slide_speed` INT DEFAULT 4000,
  `banner_height` VARCHAR(20) DEFAULT 'md',
  `banner_overlay` TINYINT(1) DEFAULT 0,
  `card_color` VARCHAR(9) DEFAULT '#FAF6ED',
  `text_color` VARCHAR(9) DEFAULT '#1B2A28',
  `accent_color` VARCHAR(9) DEFAULT '#B08D57',
  `font_display` VARCHAR(20) DEFAULT 'newsreader',
  `font_body` VARCHAR(20) DEFAULT 'public',
  `avatar_shape` VARCHAR(20) DEFAULT 'circle',
  `button_style` VARCHAR(20) DEFAULT 'soft',
  `layout` VARCHAR(10) DEFAULT 'card',
  `pix_key` VARCHAR(255) DEFAULT NULL,
  `pix_name` VARCHAR(120) DEFAULT NULL,
  `pix_city` VARCHAR(80) DEFAULT NULL,
  `pix_show` TINYINT(1) DEFAULT 0,
  `pix_payload` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `jf_settings` (`id`) VALUES (1);

CREATE TABLE IF NOT EXISTS `jf_links` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `label` VARCHAR(190) NOT NULL,
  `url` VARCHAR(500) NOT NULL,
  `icon` VARCHAR(255),
  `position` INT NOT NULL DEFAULT 0,
  `active` TINYINT(1) NOT NULL DEFAULT 1,
  `featured` TINYINT(1) NOT NULL DEFAULT 0,
  `banner_image` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `jf_migrations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL UNIQUE,
  `applied_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;