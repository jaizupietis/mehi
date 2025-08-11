
<?php
require_once 'config.php';

// Pārbaudīt atļaujas
requireRole([ROLE_ADMIN, ROLE_MANAGER]);

$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
$type = $_GET['type'] ?? 'csv';

// Aprēķināt nedēļas beigas
$end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));

try {
    // Nodrošināt UTF-8 kodējumu datubāzei
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_latvian_ci");
    
    // Iegūt mehāniķus
    $stmt = $pdo->query("
        SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards 
        FROM lietotaji 
        WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' 
        ORDER BY vards, uzvards
    ");
    $mechanics = $stmt->fetchAll();
    
    // Iegūt grafiku
    $stmt = $pdo->prepare("
        SELECT g.*, CONCAT(l.vards, ' ', l.uzvards) as mehaniķis
        FROM darba_grafiks g
        JOIN lietotaji l ON g.lietotaja_id = l.id
        WHERE g.datums BETWEEN ? AND ?
        ORDER BY g.datums, l.vards, l.uzvards, g.maina
    ");
    $stmt->execute([$start_date, $end_date]);
    $schedule_data = $stmt->fetchAll();
    
    // Organizēt datus
    $schedule = [];
    foreach ($schedule_data as $entry) {
        $schedule[$entry['datums']][$entry['lietotaja_id']][] = $entry['maina'];
    }
    
    if ($type === 'csv') {
        // Nodrošināt UTF-8 kodējumu
        mb_internal_encoding('UTF-8');
        
        // CSV eksports
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="darba_grafiks_' . str_replace('-', '_', $start_date) . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Pievienot UTF-8 BOM, lai Excel pareizi attēlotu latviešu burtus
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // UTF-8 BOM priekš Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Galvene
        $header = ['Mehāniķis'];
        for ($i = 0; $i < 7; $i++) {
            $current_date = date('Y-m-d', strtotime($start_date . " +$i days"));
            $day_name = ['Pirmdiena', 'Otrdiena', 'Trešdiena', 'Ceturtdiena', 'Piektdiena', 'Sestdiena', 'Svētdiena'][$i];
            $header[] = $day_name . ' (' . date('d.m.Y', strtotime($current_date)) . ')';
        }
        fputcsv($output, $header, ';');
        
        // Dati
        foreach ($mechanics as $mechanic) {
            // Nodrošināt UTF-8 kodējumu vārdam
            $row = [mb_convert_encoding($mechanic['pilns_vards'], 'UTF-8', 'UTF-8')];
            
            for ($i = 0; $i < 7; $i++) {
                $current_date = date('Y-m-d', strtotime($start_date . " +$i days"));
                $shifts = $schedule[$current_date][$mechanic['id']] ?? [];
                
                // Formatēt maiņas bez HTML tagiem
                if (empty($shifts)) {
                    $row[] = '-';
                } else {
                    // Aizvietot maiņu kodus ar pilniem nosaukumiem
                    $shift_names = [];
                    foreach ($shifts as $shift) {
                        switch ($shift) {
                            case 'R':
                                $shift_names[] = 'Rīta maiņa (7:00-16:00)';
                                break;
                            case 'V':
                                $shift_names[] = 'Vakara maiņa (16:00-01:00)';
                                break;
                            case 'B':
                                $shift_names[] = 'Brīvdiena';
                                break;
                            default:
                                $shift_names[] = $shift;
                        }
                    }
                    $row[] = mb_convert_encoding(implode(', ', $shift_names), 'UTF-8', 'UTF-8');
                }
            }
            
            fputcsv($output, $row, ';');
        }
        
        fclose($output);
        exit;
    }
    
} catch (PDOException $e) {
    http_response_code(500);
    echo "Kļūda: " . $e->getMessage();
    exit;
}
?>
