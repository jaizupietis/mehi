
-- SQL skripts latviešu burtu problēmas novēršanai
-- Palaidiet šo skriptu uz jūsu MariaDB servera

-- 1. Mainīt datubāzes kodējumu
ALTER DATABASE mehu_uzd CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;

-- 2. Mainīt visu tabulu kodējumu
ALTER TABLE lietotaji CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE darba_grafiks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE vietas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE iekartas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE uzdevumi CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE problemas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE uzdevumu_kategorijas CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE regularo_uzdevumu_sabloni CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE faili CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE uzdevumu_vesture CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE darba_laiks CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;
ALTER TABLE pazinojumi CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_latvian_ci;

-- 3. Pārbaudīt vai viss ir kārtībā
SHOW TABLE STATUS FROM mehu_uzd;
