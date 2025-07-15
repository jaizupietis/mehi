-- AVOTI Task Management sistēmas datu bāzes struktūra
-- Izveidot datu bāzi un lietotāju

CREATE DATABASE IF NOT EXISTS mehu_uzd CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;

-- Izveidot lietotāju un piešķirt privilēģijas
CREATE USER IF NOT EXISTS 'tasks'@'localhost' IDENTIFIED BY 'Astalavista1920';
GRANT ALL PRIVILEGES ON mehu_uzd.* TO 'tasks'@'localhost';
FLUSH PRIVILEGES;

USE mehu_uzd;

-- 1. Lietotāju tabula
CREATE TABLE IF NOT EXISTS lietotaji (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lietotajvards VARCHAR(50) UNIQUE NOT NULL,
    parole VARCHAR(255) NOT NULL,
    vards VARCHAR(100) NOT NULL,
    uzvards VARCHAR(100) NOT NULL,
    epasts VARCHAR(100),
    telefons VARCHAR(20),
    loma ENUM('Administrators', 'Menedžeris', 'Operators', 'Mehāniķis') NOT NULL,
    statuss ENUM('Aktīvs', 'Neaktīvs') DEFAULT 'Aktīvs',
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pēdējā_pieslēgšanās TIMESTAMP NULL
);

-- 2. Vietu tabula
CREATE TABLE IF NOT EXISTS vietas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nosaukums VARCHAR(100) NOT NULL,
    apraksts TEXT,
    aktīvs BOOLEAN DEFAULT TRUE,
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Iekārtu tabula
CREATE TABLE IF NOT EXISTS iekartas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nosaukums VARCHAR(100) NOT NULL,
    apraksts TEXT,
    vietas_id INT,
    aktīvs BOOLEAN DEFAULT TRUE,
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vietas_id) REFERENCES vietas(id) ON DELETE SET NULL
);

-- 4. Uzdevumu kategoriju tabula
CREATE TABLE IF NOT EXISTS uzdevumu_kategorijas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nosaukums VARCHAR(100) NOT NULL,
    apraksts TEXT,
    aktīvs BOOLEAN DEFAULT TRUE
);

-- 5. Problēmu tabula
CREATE TABLE IF NOT EXISTS problemas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nosaukums VARCHAR(200) NOT NULL,
    apraksts TEXT NOT NULL,
    vietas_id INT,
    iekartas_id INT,
    prioritate ENUM('Zema', 'Vidēja', 'Augsta', 'Kritiska') DEFAULT 'Vidēja',
    sarezgitibas_pakape ENUM('Vienkārša', 'Vidēja', 'Sarežģīta', 'Ļoti sarežģīta') DEFAULT 'Vidēja',
    aptuvenais_ilgums DECIMAL(5,2) COMMENT 'Stundas',
    statuss ENUM('Jauna', 'Apskatīta', 'Pārvērsta uzdevumā', 'Atcelta') DEFAULT 'Jauna',
    zinotajs_id INT NOT NULL,
    apstradasija_id INT NULL,
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atjaunots TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vietas_id) REFERENCES vietas(id) ON DELETE SET NULL,
    FOREIGN KEY (iekartas_id) REFERENCES iekartas(id) ON DELETE SET NULL,
    FOREIGN KEY (zinotajs_id) REFERENCES lietotaji(id),
    FOREIGN KEY (apstradasija_id) REFERENCES lietotaji(id) ON DELETE SET NULL
);

-- 6. Regulāro uzdevumu šablonu tabula
CREATE TABLE IF NOT EXISTS regularo_uzdevumu_sabloni (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nosaukums VARCHAR(200) NOT NULL,
    apraksts TEXT,
    vietas_id INT,
    iekartas_id INT,
    kategorijas_id INT,
    prioritate ENUM('Zema', 'Vidēja', 'Augsta', 'Kritiska') DEFAULT 'Vidēja',
    paredzamais_ilgums DECIMAL(5,2) COMMENT 'Stundas',
    periodicitate ENUM('Katru dienu', 'Katru nedēļu', 'Reizi mēnesī', 'Reizi ceturksnī', 'Reizi gadā') NOT NULL,
    periodicitas_dienas JSON COMMENT 'Nedēļas dienas vai mēneša dienas',
    laiks TIME COMMENT 'Kad izveidot uzdevumu',
    aktīvs BOOLEAN DEFAULT TRUE,
    izveidoja_id INT NOT NULL,
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vietas_id) REFERENCES vietas(id) ON DELETE SET NULL,
    FOREIGN KEY (iekartas_id) REFERENCES iekartas(id) ON DELETE SET NULL,
    FOREIGN KEY (kategorijas_id) REFERENCES uzdevumu_kategorijas(id) ON DELETE SET NULL,
    FOREIGN KEY (izveidoja_id) REFERENCES lietotaji(id)
);

-- 7. Uzdevumu tabula
CREATE TABLE IF NOT EXISTS uzdevumi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nosaukums VARCHAR(200) NOT NULL,
    apraksts TEXT,
    veids ENUM('Ikdienas', 'Regulārais') NOT NULL,
    vietas_id INT,
    iekartas_id INT,
    kategorijas_id INT,
    prioritate ENUM('Zema', 'Vidēja', 'Augsta', 'Kritiska') DEFAULT 'Vidēja',
    statuss ENUM('Jauns', 'Procesā', 'Pabeigts', 'Atcelts', 'Atlikts') DEFAULT 'Jauns',
    piešķirts_id INT NOT NULL COMMENT 'Mehāniķis',
    izveidoja_id INT NOT NULL,
    sakuma_datums TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    jabeidz_lidz TIMESTAMP,
    paredzamais_ilgums DECIMAL(5,2) COMMENT 'Stundas',
    faktiskais_ilgums DECIMAL(5,2) COMMENT 'Stundas',
    sakuma_laiks TIMESTAMP NULL,
    beigu_laiks TIMESTAMP NULL,
    problemas_id INT NULL COMMENT 'Ja uzdevums izveidots no problēmas',
    regulara_uzdevuma_id INT NULL COMMENT 'Ja regulārs uzdevums',
    atbildes_komentars TEXT,
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    atjaunots TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (vietas_id) REFERENCES vietas(id) ON DELETE SET NULL,
    FOREIGN KEY (iekartas_id) REFERENCES iekartas(id) ON DELETE SET NULL,
    FOREIGN KEY (kategorijas_id) REFERENCES uzdevumu_kategorijas(id) ON DELETE SET NULL,
    FOREIGN KEY (piešķirts_id) REFERENCES lietotaji(id),
    FOREIGN KEY (izveidoja_id) REFERENCES lietotaji(id),
    FOREIGN KEY (problemas_id) REFERENCES problemas(id) ON DELETE SET NULL,
    FOREIGN KEY (regulara_uzdevuma_id) REFERENCES regularo_uzdevumu_sabloni(id) ON DELETE SET NULL
);

-- 8. Failu tabula (attēli, PDF)
CREATE TABLE IF NOT EXISTS faili (
    id INT AUTO_INCREMENT PRIMARY KEY,
    originalais_nosaukums VARCHAR(255) NOT NULL,
    saglabatais_nosaukums VARCHAR(255) NOT NULL,
    faila_cels VARCHAR(500) NOT NULL,
    faila_tips VARCHAR(50),
    faila_izmers INT,
    tips ENUM('Uzdevums', 'Problēma') NOT NULL,
    saistitas_id INT NOT NULL COMMENT 'uzdevuma vai problēmas ID',
    augšupielādēja_id INT NOT NULL,
    augšupielādēts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (augšupielādēja_id) REFERENCES lietotaji(id)
);

-- 9. Uzdevumu vēstures tabula
CREATE TABLE IF NOT EXISTS uzdevumu_vesture (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzdevuma_id INT NOT NULL,
    iepriekšējais_statuss VARCHAR(50),
    jaunais_statuss VARCHAR(50),
    komentars TEXT,
    mainīja_id INT NOT NULL,
    mainīts TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uzdevuma_id) REFERENCES uzdevumi(id) ON DELETE CASCADE,
    FOREIGN KEY (mainīja_id) REFERENCES lietotaji(id)
);

-- 10. Darba laika uzskaites tabula
CREATE TABLE IF NOT EXISTS darba_laiks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lietotaja_id INT NOT NULL,
    uzdevuma_id INT NOT NULL,
    sakuma_laiks TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    beigu_laiks TIMESTAMP NULL,
    stundu_skaits DECIMAL(5,2) NULL,
    komentars TEXT,
    FOREIGN KEY (lietotaja_id) REFERENCES lietotaji(id),
    FOREIGN KEY (uzdevuma_id) REFERENCES uzdevumi(id) ON DELETE CASCADE
);

-- 11. Paziņojumu tabula
CREATE TABLE IF NOT EXISTS pazinojumi (
    id INT AUTO_INCREMENT PRIMARY KEY,
    lietotaja_id INT NOT NULL,
    virsraksts VARCHAR(200) NOT NULL,
    zinojums TEXT NOT NULL,
    tips ENUM('Jauns uzdevums', 'Jauna problēma', 'Statusa maiņa', 'Sistēmas') NOT NULL,
    skatīts BOOLEAN DEFAULT FALSE,
    saistitas_tips ENUM('Uzdevums', 'Problēma') NULL,
    saistitas_id INT NULL,
    izveidots TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (lietotaja_id) REFERENCES lietotaji(id) ON DELETE CASCADE
);

-- Ievadīt sākotnējos datus

-- Administratora lietotājs
INSERT INTO lietotaji (lietotajvards, parole, vards, uzvards, epasts, loma) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrators', 'AVOTI', 'admin@avoti.lv', 'Administrators');

-- Pamata vietas
INSERT INTO vietas (nosaukums, apraksts) VALUES 
('M1 Cehs', 'Mašīnbūves cehs Nr.1'),
('M2 Cehs', 'Mašīnbūves cehs Nr.2'),
('M3 Cehs', 'Mašīnbūves cehs Nr.3'),
('M4 Cehs', 'Mašīnbūves cehs Nr.4'),
('Galdniecība', 'Galdniecības darbnīca'),
('Lakotava', 'Lakošanas iecirknis'),
('Pakotava', 'Pakošanas iecirknis'),
('Granulas', 'Granulu ražošana'),
('Biroji', 'Administratīvās telpas'),
('Noliktava', 'Gatavo izstrādājumu noliktava');

-- Pamata uzdevumu kategorijas
INSERT INTO uzdevumu_kategorijas (nosaukums, apraksts) VALUES 
('Apkope', 'Regulārā iekārtu apkope'),
('Remonts', 'Iekārtu remonti'),
('Tīrīšana', 'Darbavietu un iekārtu tīrīšana'),
('Pārbaude', 'Plānveida pārbaudes'),
('Uzstādīšana', 'Jaunu iekārtu uzstādīšana'),
('Kalibrēšana', 'Instrumentu kalibrēšana'),
('Drošība', 'Darba drošības pasākumi');

-- Pamata iekārtas (piemērs)
INSERT INTO iekartas (nosaukums, apraksts, vietas_id) VALUES 
('Iekārta M1-001', 'Griezējmašīna M1 cehā', 1),
('Iekārta M1-002', 'Virpamašīna M1 cehā', 1),
('Iekārta M2-001', 'Frēzmašīna M2 cehā', 2),
('Iekārta M2-002', 'Presformu M2 cehā', 2),
('Iekārta G-001', 'Zāģmašīna galdniecībā', 5),
('Iekārta G-002', 'Ēvelmašīna galdniecībā', 5),
('Iekārta L-001', 'Krāsošanas kamera lakotavā', 6),
('Iekārta P-001', 'Pakošanas līnija', 7),
('Iekārta GR-001', 'Granulu press', 8);
