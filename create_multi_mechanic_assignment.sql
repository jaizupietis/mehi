
-- Tabula vairāku mehāniķu piešķīrumiem uzdevumiem
CREATE TABLE IF NOT EXISTS uzdevumu_piešķīrumi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzdevuma_id INT NOT NULL,
    mehāniķa_id INT NOT NULL,
    piešķirts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    statuss ENUM('Piešķirts', 'Sākts', 'Pabeigts', 'Noņemts') DEFAULT 'Piešķirts',
    sākts TIMESTAMP NULL,
    pabeigts TIMESTAMP NULL,
    FOREIGN KEY (uzdevuma_id) REFERENCES uzdevumi(id) ON DELETE CASCADE,
    FOREIGN KEY (mehāniķa_id) REFERENCES lietotaji(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (uzdevuma_id, mehāniķa_id)
);

-- Pievienot lauku uzdevumu tabulai
ALTER TABLE uzdevumi ADD COLUMN daudziem_mehāniķiem BOOLEAN DEFAULT FALSE AFTER piešķirts_id;

-- Indeksi
CREATE INDEX idx_uzdevumu_piešķīrumi_uzdevuma_id ON uzdevumu_piešķīrumi(uzdevuma_id);
CREATE INDEX idx_uzdevumu_piešķīrumi_mehāniķa_id ON uzdevumu_piešķīrumi(mehāniķa_id);
CREATE INDEX idx_uzdevumu_piešķīrumi_statuss ON uzdevumu_piešķīrumi(statuss);
