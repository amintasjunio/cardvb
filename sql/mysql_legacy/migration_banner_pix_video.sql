-- =====================================================================
-- MIGRAÇÃO: Banner (imagem/vídeo/slideshow) + PIX
-- Ajuste o prefixo `jf_` se o seu for diferente (rode SHOW TABLES LIKE '%settings';)
-- Idempotente: pode rodar várias vezes sem quebrar.
-- =====================================================================

SET @tbl = 'jf_settings';

-- banner_url
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_url');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_url` VARCHAR(500) DEFAULT NULL'),
  'SELECT "banner_url já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- banner_height
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_height');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_height` VARCHAR(20) DEFAULT ''md'''),
  'SELECT "banner_height já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- banner_overlay
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_overlay');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_overlay` TINYINT(1) DEFAULT 0'),
  'SELECT "banner_overlay já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- banner_type
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_type');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_type` VARCHAR(20) DEFAULT ''none'''),
  'SELECT "banner_type já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- banner_video_url
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_video_url');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_video_url` VARCHAR(500) DEFAULT NULL'),
  'SELECT "banner_video_url já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- banner_slides
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_slides');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_slides` TEXT DEFAULT NULL'),
  'SELECT "banner_slides já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- banner_slide_speed
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_slide_speed');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_slide_speed` INT DEFAULT 4000'),
  'SELECT "banner_slide_speed já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- pix_key
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pix_key');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pix_key` VARCHAR(255) DEFAULT NULL'),
  'SELECT "pix_key já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- pix_name
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pix_name');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pix_name` VARCHAR(120) DEFAULT NULL'),
  'SELECT "pix_name já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- pix_city
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pix_city');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pix_city` VARCHAR(80) DEFAULT NULL'),
  'SELECT "pix_city já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- pix_show
SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'pix_show');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `pix_show` TINYINT(1) DEFAULT 0'),
  'SELECT "pix_show já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

DESCRIBE `jf_settings`;