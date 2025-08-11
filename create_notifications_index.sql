
-- Indeksa izveide paziņojumu tabulas optimizācijai
-- Šis indekss uzlabos vaicājumu veiktspēju, kas meklē paziņojumus pēc lietotāja ID un skatīšanas statusa

CREATE INDEX IF NOT EXISTS idx_pazinojumi_lietotaja_skatits ON pazinojumi(lietotaja_id, skatīts);

-- Pārbaude vai indekss tika izveidots veiksmīgi
SHOW INDEX FROM pazinojumi WHERE Key_name = 'idx_pazinojumi_lietotaja_skatits';
