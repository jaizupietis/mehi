
-- SQL skripts noklusētās vietas un iekārtas kolonnu pievienošanai lietotāju tabulā
-- Palaidiet šo skriptu uz jūsu MariaDB servera

USE mehu_uzd;

-- Pievienot noklusētās vietas kolonnu, ja tā vēl neeksistē
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'mehu_uzd'
    AND TABLE_NAME = 'lietotaji'
    AND COLUMN_NAME = 'nokluseta_vietas_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE lietotaji ADD COLUMN nokluseta_vietas_id INT(11) NULL DEFAULT NULL AFTER telefons',
    'SELECT "Kolonna nokluseta_vietas_id jau eksistē" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Pievienot noklusētās iekārtas kolonnu, ja tā vēl neeksistē
SET @column_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = 'mehu_uzd'
    AND TABLE_NAME = 'lietotaji'
    AND COLUMN_NAME = 'noklusetas_iekartas_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE lietotaji ADD COLUMN noklusetas_iekartas_id INT(11) NULL DEFAULT NULL AFTER nokluseta_vietas_id',
    'SELECT "Kolonna noklusetas_iekartas_id jau eksistē" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Pievienot ārējās atslēgas, ja tās vēl neeksistē
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'mehu_uzd'
    AND TABLE_NAME = 'lietotaji'
    AND COLUMN_NAME = 'nokluseta_vietas_id'
    AND REFERENCED_TABLE_NAME = 'vietas'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE lietotaji ADD FOREIGN KEY (nokluseta_vietas_id) REFERENCES vietas(id) ON DELETE SET NULL',
    'SELECT "Ārējā atslēga nokluseta_vietas_id jau eksistē" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Pievienot ārējo atslēgu iekārtām
SET @fk_exists = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'mehu_uzd'
    AND TABLE_NAME = 'lietotaji'
    AND COLUMN_NAME = 'noklusetas_iekartas_id'
    AND REFERENCED_TABLE_NAME = 'iekartas'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE lietotaji ADD FOREIGN KEY (noklusetas_iekartas_id) REFERENCES iekartas(id) ON DELETE SET NULL',
    'SELECT "Ārējā atslēga noklusetas_iekartas_id jau eksistē" as message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Pārbaudīt rezultātu
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = 'mehu_uzd'
    AND TABLE_NAME = 'lietotaji'
    AND COLUMN_NAME IN ('nokluseta_vietas_id', 'noklusetas_iekartas_id');

-- Parādīt ārējās atslēgas
SELECT 
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'mehu_uzd'
    AND TABLE_NAME = 'lietotaji'
    AND COLUMN_NAME IN ('nokluseta_vietas_id', 'noklusetas_iekartas_id');

-- Komentārs par izmantošanu:
-- Kolonna nokluseta_vietas_id: INT, nullable, references vietas(id)
-- Kolonna noklusetas_iekartas_id: INT, nullable, references iekartas(id)
-- 
-- Šīs kolonnas paredzētas operatoriem, lai uzstādītu noklusētās vērtības
-- problēmu ziņošanas formā (report_problem.php).
