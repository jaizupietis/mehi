<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

// Iegūt filtrus
$date_from = $_POST['date_from'] ?? date('Y-m-01');
$date_to = $_POST['date_to'] ?? date('Y-m-d');
$selected_mechanic = intval($_POST['mechanic_id'] ?? 0);
$selected_location = intval($_POST['location_id'] ?? 0);

// Izveidot CSV saturu
$csv_content = '';
$filename = 'avoti_atskaite_' . date('Y-m-d_H-i-s') . '.csv';

// CSV headers
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Atvērt output stream
$output = fopen('php://output', 'w');

// BOM priekš UTF-8
fwrite($output, "\xEF\xBB\xBF");

try {
    // Filtru parametri
    $date_filter = "DATE(u.izveidots) BETWEEN ? AND ?";
    $date_params = [$date_from, $date_to];
    
    $mechanic_filter = $selected_mechanic > 0 ? "AND u.piešķirts_id = ?" : "";
    $mechanic_params = $selected_mechanic > 0 ? [$selected_mechanic] : [];
    
    $location_filter = $selected_location > 0 ? "AND u.vietas_id = ?" : "";
    $location_params = $selected_location > 0 ? [$selected_location] : [];
    
    $all_params = array_merge($date_params, $mechanic_params, $location_params);
    
    // 1. Vispārīgā statistika
    fputcsv($output, ['VISPĀRĪGĀ STATISTIKA'], ';');
    fputcsv($output, ['Periods', $date_from . ' - ' . $date_to], ';');
    fputcsv($output, [], ';'); // Tukša rinda
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā_uzdevumi,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_uzdevumi,
            SUM(CASE WHEN u.statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi_uzdevumi,
            SUM(CASE WHEN u.statuss = 'Atcelts' THEN 1 ELSE 0 END) as atcelti_uzdevumi,
            SUM(CASE WHEN u.prioritate = 'Kritiska' THEN 1 ELSE 0 END) as kritiski_uzdevumi,
            AVG(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums END) as vidējais_ilgums,
            SUM(CASE WHEN u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavētie
        FROM uzdevumi u
        WHERE $date_filter $mechanic_filter $location_filter
    ");
    $stmt->execute($all_params);
    $stats = $stmt->fetch();
    
    fputcsv($output, ['Rādītājs', 'Vērtība'], ';');
    fputcsv($output, ['Kopā uzdevumi', $stats['kopā_uzdevumi']], ';');
    fputcsv($output, ['Pabeigti uzdevumi', $stats['pabeigti_uzdevumi']], ';');
    fputcsv($output, ['Aktīvi uzdevumi', $stats['aktīvi_uzdevumi']], ';');
    fputcsv($output, ['Atcelti uzdevumi', $stats['atcelti_uzdevumi']], ';');
    fputcsv($output, ['Kritiski uzdevumi', $stats['kritiski_uzdevumi']], ';');
    fputcsv($output, ['Vidējais ilgums (h)', number_format($stats['vidējais_ilgums'] ?? 0, 2)], ';');
    fputcsv($output, ['Nokavētie uzdevumi', $stats['nokavētie']], ';');
    
    fputcsv($output, [], ';'); // Tukša rinda
    
    // 2. Mehāniķu produktivitāte
    fputcsv($output, ['MEHĀNIĶU PRODUKTIVITĀTE'], ';');
    fputcsv($output, [], ';');
    
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(l.vards, ' ', l.uzvards) as mehaniķis,
            COUNT(u.id) as uzdevumu_skaits,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigto_skaits,
            AVG(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums END) as vidējais_ilgums,
            SUM(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums ELSE 0 END) as kopējais_darba_laiks,
            SUM(CASE WHEN u.jabeidz_lidz < NOW() AND u.statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavētie
        FROM lietotaji l
        LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND $date_filter $location_filter
        WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
        " . ($selected_mechanic > 0 ? "AND l.id = ?" : "") . "
        GROUP BY l.id, l.vards, l.uzvards
        ORDER BY pabeigto_skaits DESC
    ");
    
    $productivity_params = array_merge($date_params, $location_params);
    if ($selected_mechanic > 0) {
        $productivity_params[] = $selected_mechanic;
    }
    $stmt->execute($productivity_params);
    $mechanics = $stmt->fetchAll();
    
    fputcsv($output, ['Mehāniķis', 'Kopā uzdevumi', 'Pabeigti', 'Efektivitāte (%)', 'Vidējais ilgums (h)', 'Kopējais darba laiks (h)', 'Nokavētie'], ';');
    
    foreach ($mechanics as $mechanic) {
        $efektivitāte = $mechanic['uzdevumu_skaits'] > 0 ? 
            round(($mechanic['pabeigto_skaits'] / $mechanic['uzdevumu_skaits']) * 100, 1) : 0;
            
        fputcsv($output, [
            $mechanic['mehaniķis'],
            $mechanic['uzdevumu_skaits'],
            $mechanic['pabeigto_skaits'],
            $efektivitāte,
            number_format($mechanic['vidējais_ilgums'] ?? 0, 2),
            number_format($mechanic['kopējais_darba_laiks'], 2),
            $mechanic['nokavētie']
        ], ';');
    }
    
    fputcsv($output, [], ';'); // Tukša rinda
    
    // 3. Prioritāšu sadalījums
    fputcsv($output, ['PRIORITĀŠU SADALĪJUMS'], ';');
    fputcsv($output, [], ';');
    
    $stmt = $pdo->prepare("
        SELECT 
            u.prioritate,
            COUNT(*) as skaits,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti,
            AVG(CASE WHEN u.faktiskais_ilgums IS NOT NULL THEN u.faktiskais_ilgums END) as vidējais_ilgums
        FROM uzdevumi u
        WHERE $date_filter $mechanic_filter $location_filter
        GROUP BY u.prioritate
        ORDER BY FIELD(u.prioritate, 'Kritiska', 'Augsta', 'Vidēja', 'Zema')
    ");
    $stmt->execute($all_params);
    $priorities = $stmt->fetchAll();
    
    fputcsv($output, ['Prioritāte', 'Skaits', 'Pabeigti', 'Efektivitāte (%)', 'Vidējais ilgums (h)'], ';');
    
    foreach ($priorities as $priority) {
        $efektivitāte = $priority['skaits'] > 0 ? 
            round(($priority['pabeigti'] / $priority['skaits']) * 100, 1) : 0;
            
        fputcsv($output, [
            $priority['prioritate'],
            $priority['skaits'],
            $priority['pabeigti'],
            $efektivitāte,
            number_format($priority['vidējais_ilgums'] ?? 0, 2)
        ], ';');
    }
    
    fputcsv($output, [], ';'); // Tukša rinda
    
    // 4. Detalizētu uzdevumu saraksts
    fputcsv($output, ['DETALIZĒTS UZDEVUMU SARAKSTS'], ';');
    fputcsv($output, [], ';');
    
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nosaukums,
            u.veids,
            u.prioritate,
            u.statuss,
            u.izveidots,
            u.sakuma_laiks,
            u.beigu_laiks,
            u.faktiskais_ilgums,
            u.jabeidz_lidz,
            v.nosaukums as vieta,
            i.nosaukums as iekārta,
            CONCAT(l.vards, ' ', l.uzvards) as mehaniķis,
            CONCAT(e.vards, ' ', e.uzvards) as izveidoja,
            r.periodicitate
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        LEFT JOIN lietotaji e ON u.izveidoja_id = e.id
        LEFT JOIN regularo_uzdevumu_sabloni r ON u.regulara_uzdevuma_id = r.id
        WHERE $date_filter $mechanic_filter $location_filter
        ORDER BY u.izveidots DESC
    ");
    $stmt->execute($all_params);
    $tasks = $stmt->fetchAll();
    
    fputcsv($output, [
        'ID', 'Nosaukums', 'Veids', 'Periodicitāte', 'Prioritāte', 'Statuss', 
        'Vieta', 'Iekārta', 'Mehāniķis', 'Izveidoja', 'Izveidots', 
        'Sākts', 'Pabeigts', 'Faktiskais ilgums (h)', 'Termiņš'
    ], ';');
    
    foreach ($tasks as $task) {
        fputcsv($output, [
            $task['id'],
            $task['nosaukums'],
            $task['veids'],
            $task['periodicitate'] ?? '',
            $task['prioritate'],
            $task['statuss'],
            $task['vieta'] ?? '',
            $task['iekārta'] ?? '',
            $task['mehaniķis'],
            $task['izveidoja'],
            $task['izveidots'],
            $task['sakuma_laiks'] ?? '',
            $task['beigu_laiks'] ?? '',
            $task['faktiskais_ilgums'] ?? '',
            $task['jabeidz_lidz'] ?? ''
        ], ';');
    }
    
    // 5. Regulāro uzdevumu statistika
    fputcsv($output, [], ';'); // Tukša rinda
    fputcsv($output, ['REGULĀRO UZDEVUMU STATISTIKA'], ';');
    fputcsv($output, [], ';');
    
    $stmt = $pdo->prepare("
        SELECT 
            r.nosaukums,
            r.periodicitate,
            r.prioritate,
            r.aktīvs,
            COUNT(u.id) as izveidoto_uzdevumu_skaits,
            SUM(CASE WHEN u.statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigto_skaits,
            MAX(u.izveidots) as pēdējais_izveidots
        FROM regularo_uzdevumu_sabloni r
        LEFT JOIN uzdevumi u ON r.id = u.regulara_uzdevuma_id AND $date_filter
        GROUP BY r.id
        ORDER BY r.nosaukums
    ");
    $stmt->execute($date_params);
    $regular_templates = $stmt->fetchAll();
    
    fputcsv($output, ['Šablons', 'Periodicitāte', 'Prioritāte', 'Aktīvs', 'Izveidoti uzdevumi', 'Pabeigti uzdevumi', 'Pēdējais izveidots'], ';');
    
    foreach ($regular_templates as $template) {
        fputcsv($output, [
            $template['nosaukums'],
            $template['periodicitate'],
            $template['prioritate'],
            $template['aktīvs'] ? 'Jā' : 'Nē',
            $template['izveidoto_uzdevumu_skaits'],
            $template['pabeigto_skaits'],
            $template['pēdējais_izveidots'] ?? ''
        ], ';');
    }
    
} catch (PDOException $e) {
    fputcsv($output, ['Kļūda iegūstot datus:', $e->getMessage()], ';');
}

fclose($output);
exit();
?>