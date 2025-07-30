<?php
/**
 * Regulāro uzdevumu automātiskā izpilde ar uzlabot debugging
 * Šis fails ir paredzēts, lai to palaistu ar cron job
 * 
 * Cron iestatījumu piemērs:
 * 0 * * * * /usr/bin/php /path/to/cron_scheduler.php
 * (Palaiž katru stundu)
 */

// Ieslēgt kļūdu ziņošanu
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Iekļaut konfigurāciju
require_once __DIR__ . '/config.php';

// Uzlabota logging funkcija
function logMessage($message, $type = 'INFO') {
    $logFile = __DIR__ . '/logs/cron_scheduler.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$type] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Izvadīt arī uz konsolei, ja tiek palaists no command line
    if (php_sapi_name() === 'cli') {
        echo $logEntry;
    }
}

// Funkcija brīvākā mehāniķa atrašanai
function findLeastBusyMechanic() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT l.id, 
                   CONCAT(l.vards, ' ', l.uzvards) as pilns_vards,
                   COUNT(u.id) as aktīvo_uzdevumu_skaits,
                   SUM(CASE WHEN u.prioritate = 'Kritiska' THEN 3 
                            WHEN u.prioritate = 'Augsta' THEN 2 
                            WHEN u.prioritate = 'Vidēja' THEN 1 
                            ELSE 0 END) as prioritātes_svars
            FROM lietotaji l
            LEFT JOIN uzdevumi u ON l.id = u.piešķirts_id AND u.statuss IN ('Jauns', 'Procesā')
            WHERE l.loma = 'Mehāniķis' AND l.statuss = 'Aktīvs'
            GROUP BY l.id
            ORDER BY aktīvo_uzdevumu_skaits ASC, prioritātes_svars ASC, l.id ASC
            LIMIT 1
        ");
        
        $result = $stmt->fetch();
        if ($result) {
            logMessage("Brīvākais mehāniķis atrasts: {$result['pilns_vards']} (ID: {$result['id']}) ar {$result['aktīvo_uzdevumu_skaits']} aktīviem uzdevumiem");
            return $result;
        } else {
            logMessage("Nav atrasti aktīvi mehāniķi!", 'WARNING');
            return null;
        }
    } catch (PDOException $e) {
        logMessage("Kļūda meklējot brīvāko mehāniķi: " . $e->getMessage(), 'ERROR');
        return null;
    }
}

// Uzlabota funkcija uzdevuma izveidošanai no šablona
function createTaskFromTemplate($template, $mechanic_info) {
    global $pdo;
    
    try {
        logMessage("Sāk izveidot uzdevumu no šablona: {$template['nosaukums']} mehāniķim: {$mechanic_info['pilns_vards']}");
        
        $pdo->beginTransaction();
        
        // Izveidot uzdevumu
        $stmt = $pdo->prepare("
            INSERT INTO uzdevumi 
            (nosaukums, apraksts, veids, vietas_id, iekartas_id, kategorijas_id, 
             prioritate, piešķirts_id, izveidoja_id, paredzamais_ilgums, regulara_uzdevuma_id)
            VALUES (?, ?, 'Regulārais', ?, ?, ?, ?, ?, 1, ?, ?)
        ");
        
        $success = $stmt->execute([
            $template['nosaukums'],
            $template['apraksts'],
            $template['vietas_id'],
            $template['iekartas_id'],
            $template['kategorijas_id'],
            $template['prioritate'],
            $mechanic_info['id'],
            $template['paredzamais_ilgums'],
            $template['id']
        ]);

        if (!$success) {
            throw new Exception("Neizdevās izveidot uzdevumu datubāzē");
        }
        
        $task_id = $pdo->lastInsertId();
        logMessage("Uzdevums izveidots ar ID: $task_id");
        
        // Pievienot vēsturi
        $stmt = $pdo->prepare("
            INSERT INTO uzdevumu_vesture 
            (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
            VALUES (?, NULL, 'Jauns', 'Regulārais uzdevums izveidots automātiski', 1)
        ");
        $stmt->execute([$task_id]);
        logMessage("Uzdevuma vēsture pievienota");
        
        // Paziņot mehāniķim - UZLABOTS AR DEBUGGING
        try {
            $notification_result = createNotification(
                $mechanic_info['id'],
                'Jauns regulārais uzdevums',
                "Jums ir piešķirts regulārais uzdevums: {$template['nosaukums']}",
                'Jauns uzdevums',
                'Uzdevums',
                $task_id
            );
            
            if ($notification_result) {
                logMessage("Paziņojums veiksmīgi nosūtīts mehāniķim: {$mechanic_info['pilns_vards']} (ID: {$mechanic_info['id']})");
                
                // Pārbaudīt vai paziņojums tiešām tika izveidots
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM pazinojumi WHERE lietotaja_id = ? AND saistitas_id = ?");
                $stmt->execute([$mechanic_info['id'], $task_id]);
                $notification_count = $stmt->fetchColumn();
                logMessage("Datubāzē atrasti $notification_count paziņojumi šim uzdevumam");
            } else {
                logMessage("Kļūda: Paziņojums netika izveidots!", 'ERROR');
            }
        } catch (Exception $e) {
            logMessage("Kļūda izveidojot paziņojumu: " . $e->getMessage(), 'ERROR');
            // Neapstādinām procesu - uzdevums jau izveidots
        }
        
        $pdo->commit();
        
        logMessage("Regulārais uzdevums veiksmīgi izveidots: {$template['nosaukums']} (ID: $task_id) mehāniķim {$mechanic_info['pilns_vards']}");
        return $task_id;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        logMessage("Kļūda izveidojot uzdevumu no šablona {$template['id']}: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Funkcija nedēļas dienas pārbaudei
function shouldRunToday($periodicitate, $periodicitas_dienas) {
    $today = date('N'); // 1 = Pirmdiena, 7 = Svētdiena
    $today_date = date('j'); // Mēneša diena
    
    logMessage("Pārbauda periodicitāti: $periodicitate, šodienas nedēļas diena: $today, mēneša diena: $today_date");
    
    switch ($periodicitate) {
        case 'Katru dienu':
            logMessage("Periodicitāte: Katru dienu - ATBILST");
            return true;
            
        case 'Katru nedēļu':
            if ($periodicitas_dienas) {
                $dienas = json_decode($periodicitas_dienas, true);
                $atbilst = in_array($today, $dienas);
                logMessage("Periodicitāte: Katru nedēļu, dienas: " . implode(',', $dienas) . " - " . ($atbilst ? 'ATBILST' : 'NEATBILST'));
                return $atbilst;
            }
            logMessage("Periodicitāte: Katru nedēļu, bet nav norādītas dienas - NEATBILST");
            return false;
            
        case 'Reizi mēnesī':
            if ($periodicitas_dienas) {
                $dienas = json_decode($periodicitas_dienas, true);
                $atbilst = in_array($today_date, $dienas);
                logMessage("Periodicitāte: Reizi mēnesī, datumi: " . implode(',', $dianas) . " - " . ($atbilst ? 'ATBILST' : 'NEATBILST'));
                return $atbilst;
            }
            logMessage("Periodicitāte: Reizi mēnesī, bet nav norādīti datumi - NEATBILST");
            return false;
            
        case 'Reizi ceturksnī':
            // Pirmā mēneša diena ceturksnī
            $month = date('n');
            $quarter_months = [1, 4, 7, 10];
            $atbilst = in_array($month, $quarter_months) && $today_date == 1;
            logMessage("Periodicitāte: Reizi ceturksnī - " . ($atbilst ? 'ATBILST' : 'NEATBILST'));
            return $atbilst;
            
        case 'Reizi gadā':
            // 1. janvārī
            $atbilst = date('m-d') == '01-01';
            logMessage("Periodicitāte: Reizi gadā - " . ($atbilst ? 'ATBILST' : 'NEATBILST'));
            return $atbilst;
            
        default:
            logMessage("Nezināma periodicitāte: $periodicitate - NEATBILST");
            return false;
    }
}

// Funkcija pārbaudīt vai uzdevums jau izveidots šodien
function isTaskCreatedToday($template_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM uzdevumi 
            WHERE regulara_uzdevuma_id = ? 
            AND DATE(izveidots) = CURDATE()
        ");
        $stmt->execute([$template_id]);
        
        $count = $stmt->fetchColumn();
        $jau_izveidots = $count > 0;
        logMessage("Šablons $template_id: šodien jau izveidoti $count uzdevumi - " . ($jau_izveidots ? 'JAU IZVEIDOTS' : 'VAR IZVEIDOT'));
        
        return $jau_izveidots;
    } catch (PDOException $e) {
        logMessage("Kļūda pārbaudot uzdevuma esamību: " . $e->getMessage(), 'ERROR');
        return true; // Drošības dēļ atgriežam true
    }
}

// Galvenā funkcija ar uzlabotu debugging
function processRegularTasks() {
    global $pdo;
    
    logMessage("=== SĀKAS REGULĀRO UZDEVUMU APSTRĀDE ===");
    logMessage("Pašreizējais laiks: " . date('Y-m-d H:i:s'));
    
    $current_time = date('H:i');
    $processed_count = 0;
    $created_count = 0;
    $skipped_count = 0;
    
    try {
        // Iegūt visus aktīvos regulāros uzdevumus
        $stmt = $pdo->query("
            SELECT * FROM regularo_uzdevumu_sabloni 
            WHERE aktīvs = 1 
            ORDER BY prioritate DESC, id ASC
        ");
        $templates = $stmt->fetchAll();
        
        logMessage("Atrasti " . count($templates) . " aktīvi regulārie šabloni");
        
        if (empty($templates)) {
            logMessage("Nav aktīvu regulāro uzdevumu šablonu - apstrāde beigta");
            return;
        }
        
        foreach ($templates as $template) {
            $processed_count++;
            logMessage("--- Apstrādā šablonu #{$template['id']}: {$template['nosaukums']} ---");
            
            // Pārbaudīt laiku
            if ($template['laiks'] && $template['laiks'] != $current_time) {
                logMessage("Laika neatbilstība: plānots {$template['laiks']}, pašreizējais $current_time - IZLAIŽAM");
                $skipped_count++;
                continue;
            }
            logMessage("Laiks atbilst: {$template['laiks']} = $current_time");
            
            // Pārbaudīt vai šodien ir jāizveido uzdevums
            if (!shouldRunToday($template['periodicitate'], $template['periodicitas_dienas'])) {
                logMessage("Šodien nav jāizveido uzdevums šim šablonam - IZLAIŽAM");
                $skipped_count++;
                continue;
            }
            
            // Pārbaudīt vai uzdevums jau izveidots šodien
            if (isTaskCreatedToday($template['id'])) {
                logMessage("Uzdevums jau izveidots šodien šablonam: {$template['nosaukums']} - IZLAIŽAM");
                $skipped_count++;
                continue;
            }
            
            // Atrast brīvāko mehāniķi
            $mechanic_info = findLeastBusyMechanic();
            if (!$mechanic_info) {
                logMessage("Nav pieejamu mehāniķu šablonam: {$template['nosaukums']} - IZLAIŽAM", 'WARNING');
                $skipped_count++;
                continue;
            }
            
            // Izveidot uzdevumu
            $task_id = createTaskFromTemplate($template, $mechanic_info);
            if ($task_id) {
                $created_count++;
                logMessage("VEIKSMĪGI IZVEIDOTS uzdevums ID: $task_id", 'SUCCESS');
            } else {
                logMessage("NEIZDEVĀS IZVEIDOT uzdevumu no šablona: {$template['nosaukums']}", 'ERROR');
            }
        }
        
    } catch (PDOException $e) {
        logMessage("Kļūda apstrādājot regulāros uzdevumus: " . $e->getMessage(), 'ERROR');
    }
    
    logMessage("=== APSTRĀDE PABEIGTA ===");
    logMessage("Apstrādāti: $processed_count šabloni");
    logMessage("Izveidoti: $created_count uzdevumi");
    logMessage("Izlaisti: $skipped_count šabloni");
    logMessage("=============================");
}

// Funkcija paziņojumu testēšanai
function testNotificationSystem() {
    global $pdo;
    
    logMessage("=== TESTĒ PAZIŅOJUMU SISTĒMU ===");
    
    try {
        // Atrast pirmo aktīvo mehāniķi
        $stmt = $pdo->query("
            SELECT id, CONCAT(vards, ' ', uzvards) as pilns_vards 
            FROM lietotaji 
            WHERE loma = 'Mehāniķis' AND statuss = 'Aktīvs' 
            LIMIT 1
        ");
        $mechanic = $stmt->fetch();
        
        if (!$mechanic) {
            logMessage("Nav aktīvu mehāniķu testēšanai", 'ERROR');
            return false;
        }
        
        logMessage("Testē paziņojumu mehāniķim: {$mechanic['pilns_vards']} (ID: {$mechanic['id']})");
        
        // Izveidot testa paziņojumu
        $result = createNotification(
            $mechanic['id'],
            'Testa paziņojums',
            'Šis ir testa paziņojums no cron_scheduler.php - ' . date('Y-m-d H:i:s'),
            'Sistēmas',
            null,
            null
        );
        
        if ($result) {
            // Pārbaudīt vai paziņojums tika izveidots
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM pazinojumi 
                WHERE lietotaja_id = ? AND virsraksts = 'Testa paziņojums'
                AND izveidots >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
            ");
            $stmt->execute([$mechanic['id']]);
            $count = $stmt->fetchColumn();
            
            logMessage("Testa paziņojums izveidots: " . ($count > 0 ? 'JĀ' : 'NĒ'), $count > 0 ? 'SUCCESS' : 'ERROR');
            return $count > 0;
        } else {
            logMessage("createNotification() atgrieza false", 'ERROR');
            return false;
        }
        
    } catch (Exception $e) {
        logMessage("Kļūda testējot paziņojumu sistēmu: " . $e->getMessage(), 'ERROR');
        return false;
    }
}

// Pārbaudīt vai skripts ir izsaukts no komandas rindas
if (php_sapi_name() === 'cli') {
    // Iespējas no komandas rindas
    $options = getopt("", ["test-notifications", "debug"]);
    
    if (isset($options['test-notifications'])) {
        testNotificationSystem();
    } else {
        processRegularTasks();
    }
} else {
    // Ja izsaukts no web, pārbaudīt atļaujas
    requireRole(ROLE_ADMIN);
    
    if (isset($_POST['run_scheduler'])) {
        processRegularTasks();
        setFlashMessage('success', 'Regulāro uzdevumu scheduler ir izpildīts!');
        redirect('regular_tasks.php');
    }
    
    if (isset($_POST['test_notifications'])) {
        $test_result = testNotificationSystem();
        if ($test_result) {
            setFlashMessage('success', 'Paziņojumu sistēma darbojas pareizi!');
        } else {
            setFlashMessage('error', 'Paziņojumu sistēmā ir problēmas. Pārbaudiet log failu.');
        }
        redirect('cron_setup.php');
    }
}
?>