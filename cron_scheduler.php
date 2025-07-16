<?php
/**
 * Regulāro uzdevumu automātiskā izpilde
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

// Logging funkcija
function logMessage($message) {
    $logFile = __DIR__ . '/logs/cron_scheduler.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

// Funkcija brīvākā mehāniķa atrašanai
function findLeastBusyMechanic() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT l.id, 
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
        return $result ? $result['id'] : null;
    } catch (PDOException $e) {
        logMessage("Kļūda meklējot brīvāko mehāniķi: " . $e->getMessage());
        return null;
    }
}

// Funkcija uzdevuma izveidošanai no šablona
function createTaskFromTemplate($template, $mechanic_id) {
    global $pdo;
    
    try {
        $pdo->beginTransaction();
        
        // Izveidot uzdevumu
        $stmt = $pdo->prepare("
            INSERT INTO uzdevumi 
            (nosaukums, apraksts, veids, vietas_id, iekartas_id, kategorijas_id, 
             prioritate, piešķirts_id, izveidoja_id, paredzamais_ilgums, regulara_uzdevuma_id)
            VALUES (?, ?, 'Regulārais', ?, ?, ?, ?, ?, 1, ?, ?)
        ");
        
        $stmt->execute([
            $template['nosaukums'],
            $template['apraksts'],
            $template['vietas_id'],
            $template['iekartas_id'],
            $template['kategorijas_id'],
            $template['prioritate'],
            $mechanic_id,
            $template['paredzamais_ilgums'],
            $template['id']
        ]);
        
        $task_id = $pdo->lastInsertId();
        
        // Pievienot vēsturi
        $stmt = $pdo->prepare("
            INSERT INTO uzdevumu_vesture 
            (uzdevuma_id, iepriekšējais_statuss, jaunais_statuss, komentars, mainīja_id)
            VALUES (?, NULL, 'Jauns', 'Regulārais uzdevums izveidots automātiski', 1)
        ");
        $stmt->execute([$task_id]);
        
        // Paziņot mehāniķim
        createNotification(
            $mechanic_id,
            'Jauns regulārais uzdevums',
            "Jums ir piešķirts regulārais uzdevums: {$template['nosaukums']}",
            'Jauns uzdevums',
            'Uzdevums',
            $task_id
        );
        
        $pdo->commit();
        
        logMessage("Izveidots regulārais uzdevums: {$template['nosaukums']} (ID: $task_id) mehāniķim ID: $mechanic_id");
        return $task_id;
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        logMessage("Kļūda izveidojot uzdevumu no šablona {$template['id']}: " . $e->getMessage());
        return false;
    }
}

// Funkcija nedēļas dienas pārbaudei
function shouldRunToday($periodicitate, $periodicitas_dienas) {
    $today = date('N'); // 1 = Pirmdiena, 7 = Svētdiena
    $today_date = date('j'); // Mēneša diena
    
    switch ($periodicitate) {
        case 'Katru dienu':
            return true;
            
        case 'Katru nedēļu':
            if ($periodicitas_dienas) {
                $dienas = json_decode($periodicitas_dienas, true);
                return in_array($today, $dienas);
            }
            return false;
            
        case 'Reizi mēnesī':
            if ($periodicitas_dienas) {
                $dienas = json_decode($periodicitas_dienas, true);
                return in_array($today_date, $dienas);
            }
            return false;
            
        case 'Reizi ceturksnī':
            // Pirmā mēneša diena ceturksnī
            $month = date('n');
            $quarter_months = [1, 4, 7, 10];
            return in_array($month, $quarter_months) && $today_date == 1;
            
        case 'Reizi gadā':
            // 1. janvārī
            return date('m-d') == '01-01';
            
        default:
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
        
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        logMessage("Kļūda pārbaudot uzdevuma esamību: " . $e->getMessage());
        return true; // Drošības dēļ atgriežam true
    }
}

// Galvenā funkcija
function processRegularTasks() {
    global $pdo;
    
    logMessage("Sākas regulāro uzdevumu apstrāde");
    
    $current_time = date('H:i');
    $processed_count = 0;
    $created_count = 0;
    
    try {
        // Iegūt visus aktīvos regulāros uzdevumus
        $stmt = $pdo->query("
            SELECT * FROM regularo_uzdevumu_sabloni 
            WHERE aktīvs = 1 
            ORDER BY prioritate DESC, id ASC
        ");
        $templates = $stmt->fetchAll();
        
        logMessage("Atrasti " . count($templates) . " aktīvi regulārie šabloni");
        
        foreach ($templates as $template) {
            $processed_count++;
            
            // Pārbaudīt laiku
            if ($template['laiks'] && $template['laiks'] != $current_time) {
                continue;
            }
            
            // Pārbaudīt vai šodien ir jāizveido uzdevums
            if (!shouldRunToday($template['periodicitate'], $template['periodicitas_dienas'])) {
                continue;
            }
            
            // Pārbaudīt vai uzdevums jau izveidots šodien
            if (isTaskCreatedToday($template['id'])) {
                logMessage("Uzdevums jau izveidots šodien šablonam: {$template['nosaukums']}");
                continue;
            }
            
            // Atrast brīvāko mehāniķi
            $mechanic_id = findLeastBusyMechanic();
            if (!$mechanic_id) {
                logMessage("Nav pieejamu mehāniķu šablonam: {$template['nosaukums']}");
                continue;
            }
            
            // Izveidot uzdevumu
            $task_id = createTaskFromTemplate($template, $mechanic_id);
            if ($task_id) {
                $created_count++;
            }
        }
        
    } catch (PDOException $e) {
        logMessage("Kļūda apstrādājot regulāros uzdevumus: " . $e->getMessage());
    }
    
    logMessage("Apstrāde pabeigta. Apstrādāti: $processed_count, izveidoti: $created_count uzdevumi");
}

// Pārbaudīt vai skripts ir izsaukts no komandas rindas
if (php_sapi_name() === 'cli') {
    processRegularTasks();
} else {
    // Ja izsaukts no web, pārbaudīt atļaujas
    requireRole(ROLE_ADMIN);
    
    if (isset($_POST['run_scheduler'])) {
        processRegularTasks();
        setFlashMessage('success', 'Regulāro uzdevumu scheduler ir izpildīts!');
        redirect('regular_tasks.php');
    }
}
?>