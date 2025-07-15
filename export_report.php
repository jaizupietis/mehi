<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole(ROLE_ADMIN);

$currentUser = getCurrentUser();

// Pārbaudīt vai ir eksportēšanas pieprasījums
if (!isset($_POST['export']) || $_POST['export'] !== 'excel') {
    redirect('reports.php');
}

// Iegūt filtru parametrus
$date_from = sanitizeInput($_POST['date_from'] ?? date('Y-m-01'));
$date_to = sanitizeInput($_POST['date_to'] ?? date('Y-m-d'));
$selected_mechanic = intval($_POST['mechanic_id'] ?? 0);
$selected_location = intval($_POST['location_id'] ?? 0);

try {
    // Būvēt filtru nosacījumus
    $date_filter = "DATE(izveidots) BETWEEN ? AND ?";
    $date_params = [$date_from, $date_to];
    
    $mechanic_filter = $selected_mechanic > 0 ? "AND piešķirts_id = ?" : "";
    $mechanic_params = $selected_mechanic > 0 ? [$selected_mechanic] : [];
    
    $location_filter = $selected_location > 0 ? "AND vietas_id = ?" : "";
    $location_params = $selected_location > 0 ? [$selected_location] : [];
    
    $all_params = array_merge($date_params, $mechanic_params, $location_params);
    
    // Iegūt vispārīgo statistiku
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as kopā_uzdevumi,
            SUM(CASE WHEN statuss = 'Pabeigts' THEN 1 ELSE 0 END) as pabeigti_uzdevumi,
            SUM(CASE WHEN statuss IN ('Jauns', 'Procesā') THEN 1 ELSE 0 END) as aktīvi_uzdevumi,
            SUM(CASE WHEN statuss = 'Atcelts' THEN 1 ELSE 0 END) as atcelti_uzdevumi,
            SUM(CASE WHEN prioritate = 'Kritiska' THEN 1 ELSE 0 END) as kritiski_uzdevumi,
            AVG(CASE WHEN faktiskais_ilgums IS NOT NULL THEN faktiskais_ilgums END) as vidējais_ilgums,
            SUM(CASE WHEN jabeidz_lidz < NOW() AND statuss NOT IN ('Pabeigts', 'Atcelts') THEN 1 ELSE 0 END) as nokavētie
        FROM uzdevumi 
        WHERE $date_filter $mechanic_filter $location_filter
    ");
    $stmt->execute($all_params);
    $vispārīgā_statistika = $stmt->fetch();
    
    // Iegūt detalizētu uzdevumu sarakstu
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            u.nosaukums,
            u.apraksts,
            u.veids,
            u.statuss,
            u.prioritate,
            u.izveidots,
            u.sakuma_datums,
            u.jabeidz_lidz,
            u.sakuma_laiks,
            u.beigu_laiks,
            u.paredzamais_ilgums,
            u.faktiskais_ilgums,
            v.nosaukums as vieta,
            i.nosaukums as iekārta,
            k.nosaukums as kategorija,
            CONCAT(l.vards, ' ', l.uzvards) as mehaniķis,
            CONCAT(e.vards, ' ', e.uzvards) as izveidoja
        FROM uzdevumi u
        LEFT JOIN vietas v ON u.vietas_id = v.id
        LEFT JOIN iekartas i ON u.iekartas_id = i.id
        LEFT JOIN uzdevumu_kategorijas k ON u.kategorijas_id = k.id
        LEFT JOIN lietotaji l ON u.piešķirts_id = l.id
        LEFT JOIN lietotaji e ON u.izveidoja_id = e.id
        WHERE $date_filter $mechanic_filter $location_filter
        ORDER BY u.izveidots DESC
    ");
    $stmt->execute($all_params);
    $uzdevumi = $stmt->fetchAll();
    
    // Iegūt mehāniķu produktivitāti
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
    $mehāniķu_produktivitāte = $stmt->fetchAll();
    
} catch (PDOException $e) {
    error_log("Kļūda eksportējot atskaiti: " . $e->getMessage());
    setFlashMessage('danger', 'Kļūda eksportējot atskaiti.');
    redirect('reports.php');
}

// Izveidot Excel failu (vienkāršota CSV versija)
$filename = 'AVOTI_Atskaite_' . $date_from . '_' . $date_to . '_' . date('Y-m-d_H-i-s') . '.csv';

// Iestatīt CSV galvenes
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Atvērt output stream
$output = fopen('php://output', 'w');

// Pievienot BOM UTF-8 atbalstam Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Atskaites galvene
fputcsv($output, ['AVOTI TASK MANAGEMENT SYSTEM - ATSKAITE'], ';');
fputcsv($output, ['Izveidots: ' . date('d.m.Y H:i:s')], ';');
fputcsv($output, ['Periods: ' . $date_from . ' - ' . $date_to], ';');
fputcsv($output, ['Izveidoja: ' . $currentUser['vards'] . ' ' . $currentUser['uzvards']], ';');
fputcsv($output, [''], ';'); // Tukša rinda

// Vispārīgā statistika
fputcsv($output, ['VISPĀRĪGĀ STATISTIKA'], ';');
fputcsv($output, ['Parametrs', 'Vērtība'], ';');
fputcsv($output, ['Kopā uzdevumi', $vispārīgā_statistika['kopā_uzdevumi']], ';');
fputcsv($output, ['Pabeigti uzdevumi', $vispārīgā_statistika['pabeigti_uzdevumi']], ';');
fputcsv($output, ['Aktīvie uzdevumi', $vispārīgā_statistika['aktīvi_uzdevumi']], ';');
fputcsv($output, ['Atcelti uzdevumi', $vispārīgā_statistika['atcelti_uzdevumi']], ';');
fputcsv($output, ['Kritiski uzdevumi', $vispārīgā_statistika['kritiski_uzdevumi']], ';');
fputcsv($output, ['Vidējais ilgums (h)', number_format($vispārīgā_statistika['vidējais_ilgums'] ?? 0, 2)], ';');
fputcsv($output, ['Nokavētie uzdevumi', $vispārīgā_statistika['nokavētie']], ';');
fputcsv($output, [''], ';'); // Tukša rinda

// Mehāniķu produktivitāte
fputcsv($output, ['MEHĀNIĶU PRODUKTIVITĀTE'], ';');
fputcsv($output, [
    'Mehāniķis',
    'Uzdevumu skaits',
    'Pabeigti',
    'Efektivitāte (%)',
    'Vidējais ilgums (h)',
    'Kopējais darba laiks (h)',
    'Nokavētie'
], ';');

foreach ($mehāniķu_produktivitāte as $mehaniķis) {
    $efektivitāte = $mehaniķis['uzdevumu_skaits'] > 0 ? 
        ($mehaniķis['pabeigto_skaits'] / $mehaniķis['uzdevumu_skaits']) * 100 : 0;
    
    fputcsv($output, [
        $mehaniķis['mehaniķis'],
        $mehaniķis['uzdevumu_skaits'],
        $mehaniķis['pabeigto_skaits'],
        number_format($efektivitāte, 1),
        number_format($mehaniķis['vidējais_ilgums'] ?? 0, 2),
        number_format($mehaniķis['kopējais_darba_laiks'], 2),
        $mehaniķis['nokavētie']
    ], ';');
}

fputcsv($output, [''], ';'); // Tukša rinda

// Detalizēts uzdevumu saraksts
fputcsv($output, ['DETALIZĒTS UZDEVUMU SARAKSTS'], ';');
fputcsv($output, [
    'ID',
    'Nosaukums',
    'Apraksts',
    'Veids',
    'Statuss',
    'Prioritāte',
    'Mehāniķis',
    'Izveidoja',
    'Vieta',
    'Iekārta',
    'Kategorija',
    'Izveidots',
    'Sākuma datums',
    'Jābeidz līdz',
    'Darbs sākts',
    'Darbs pabeigts',
    'Paredzamais ilgums (h)',
    'Faktiskais ilgums (h)'
], ';');

foreach ($uzdevumi as $uzdevums) {
    fputcsv($output, [
        $uzdevums['id'],
        $uzdevums['nosaukums'],
        str_replace(["\n", "\r"], ' ', $uzdevums['apraksts']), // Noņemt jaunas rindas
        $uzdevums['veids'],
        $uzdevums['statuss'],
        $uzdevums['prioritate'],
        $uzdevums['mehaniķis'],
        $uzdevums['izveidoja'],
        $uzdevums['vieta'] ?? '',
        $uzdevums['iekārta'] ?? '',
        $uzdevums['kategorija'] ?? '',
        $uzdevums['izveidots'] ? date('d.m.Y H:i', strtotime($uzdevums['izveidots'])) : '',
        $uzdevums['sakuma_datums'] ? date('d.m.Y H:i', strtotime($uzdevums['sakuma_datums'])) : '',
        $uzdevums['jabeidz_lidz'] ? date('d.m.Y H:i', strtotime($uzdevums['jabeidz_lidz'])) : '',
        $uzdevums['sakuma_laiks'] ? date('d.m.Y H:i', strtotime($uzdevums['sakuma_laiks'])) : '',
        $uzdevums['beigu_laiks'] ? date('d.m.Y H:i', strtotime($uzdevums['beigu_laiks'])) : '',
        $uzdevums['paredzamais_ilgums'] ? number_format($uzdevums['paredzamais_ilgums'], 2) : '',
        $uzdevums['faktiskais_ilgums'] ? number_format($uzdevums['faktiskais_ilgums'], 2) : ''
    ], ';');
}

fputcsv($output, [''], ';'); // Tukša rinda

// Kājene
fputcsv($output, ['AVOTI Task Management System'], ';');
fputcsv($output, ['SIA "AVOTI"'], ';');
fputcsv($output, ['Eksportēts: ' . date('d.m.Y H:i:s')], ';');

// Aizvērt output stream
fclose($output);

// Loģēt eksportēšanas darbību
error_log("Atskaite eksportēta: $filename, lietotājs: {$currentUser['lietotajvards']}, periods: $date_from - $date_to");

exit();
?>