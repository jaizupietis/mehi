
-- Darba maiņu grafika tabula
CREATE TABLE IF NOT EXISTS darba_grafiks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lietotaja_id INT NOT NULL,
    datums DATE NOT NULL,
    maina ENUM('R', 'V', 'B') NOT NULL COMMENT 'R=Rīta maiņa, V=Vakara maiņa, B=Brīvdiena',
    izveidoja_id INT NOT NULL,
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (lietotaja_id) REFERENCES lietotaji(id) ON DELETE CASCADE,
    FOREIGN KEY (izveidoja_id) REFERENCES lietotaji(id) ON DELETE CASCADE,
    
    UNIQUE KEY unique_user_date_shift (lietotaja_id, datums, maina),
    INDEX idx_datums (datums),
    INDEX idx_lietotaja_id (lietotaja_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_latvian_ci;

-- Komentārs par maiņu laikiem
-- R (Rīta maiņa): 07:00 - 16:00
-- V (Vakara maiņa): 16:00 - 01:00 (nākamās dienas)
-- B (Brīvdiena): nestrādā
