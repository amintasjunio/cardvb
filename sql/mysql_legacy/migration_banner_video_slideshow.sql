-- =====================================================================
-- MIGRAÇÃO: Banner com vídeo e slideshow
-- Ajuste `jf_settings` se seu prefixo for diferente
-- Idempotente: pode rodar várias vezes
-- =====================================================================

SET @tbl = 'jf_settings';

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_type');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_type` VARCHAR(20) DEFAULT ''none'''),
  'SELECT "banner_type já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_video_url');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_video_url` VARCHAR(500) DEFAULT NULL'),
  'SELECT "banner_video_url já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_slides');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_slides` TEXT DEFAULT NULL'),
  'SELECT "banner_slides já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @c := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = @tbl AND COLUMN_NAME = 'banner_slide_speed');
SET @sql := IF(@c = 0,
  CONCAT('ALTER TABLE `', @tbl, '` ADD COLUMN `banner_slide_speed` INT DEFAULT 4000'),
  'SELECT "banner_slide_speed já existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

DESCRIBE `jf_settings`;