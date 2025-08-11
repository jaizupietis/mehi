
<?php
require_once 'config.php';

// Tikai administratori var palaist šo testu
requireRole(ROLE_ADMIN);

echo "<h2>Kritisko uzdevumu sistēmas tests</h2>";

// Pārbaudīt pašreizējo darba grafiku
echo "<h3>1. Pašreizējais darba grafiks</h3>";
$currentTime = date('H:i:s');
$currentDate = date('Y-m-d');

echo "Pašreizējais laiks: $currentTime<br>";
echo "Pašreizējais datums: $currentDate<br><br>";

try {
    $stmt = $pdo->prepare("
        SELECT m.id, CONCAT(m.vards, ' ', m.uzvards) as pilns_vards, dg.maina, dg.datums
        FROM lietotaji m
        LEFT JOIN darba_grafiks dg ON m.id = dg.lietotaja_id 
            AND dg.datums = ? 
        WHERE m.loma = 'Mehāniķis' 
            AND m.statuss = 'Aktīvs'
        ORDER BY m.vards, m.uzvards
    ");
    $stmt->execute([$currentDate]);
    $allMechanics = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Mehāniķis</th><th>Maiņa šodien</th><th>Vai strādā šobrīd?</th></tr>";
    
    foreach ($allMechanics as $mechanic) {
        $isWorking = false;
        if ($mechanic['maina']) {
            if ($mechanic['maina'] === 'R' && $currentTime >= '07:00:00' && $currentTime <= '16:59:59') {
                $isWorking = true;
            } elseif ($mechanic['maina'] === 'V' && ($currentTime >= '16:00:00' || $currentTime <= '01:00:00')) {
                $isWorking = true;
            }
        }
        
        echo "<tr>";
        echo "<td>{$mechanic['pilns_vards']}</td>";
        echo "<td>" . ($mechanic['maina'] ?: 'Nav iestatīta') . "</td>";
        echo "<td style='color: " . ($isWorking ? 'green' : 'red') . "'>" . ($isWorking ? 'JĀ' : 'NĒ') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "Kļūda: " . $e->getMessage();
}

// Pārbaudīt kritisko uzdevumu funkciju
echo "<h3>2. Testēt kritisko uzdevumu izveidi</h3>";

if (isset($_POST['test_critical'])) {
    echo "Testē kritisko uzdevumu izveidi...<br>";
    
    $testProblemData = [
        'nosaukums' => 'TEST KRITISKA PROBLĒMA',
        'apraksts' => 'Šis ir tests kritisko uzdevumu sistēmai - ' . date('Y-m-d H:i:s'),
        'vietas_id' => 1, // Pirmā vieta
        'iekartas_id' => null,
        'aptuvenais_ilgums' => 1
    ];
    
    $result = createCriticalTaskFromProblem(999999, $testProblemData); // Fake problem ID
    
    if ($result && is_array($result)) {
        echo "<strong style='color: green'>VEIKSMĪGI!</strong> Izveidoti " . count($result) . " uzdevumi:<br>";
        foreach ($result as $task) {
            echo "- Uzdevums ID: {$task['task_id']}, Mehāniķis: {$task['mechanic_name']}<br>";
        }
        
        // Dzēst testa uzdevumus
        echo "<br>Dzēš testa uzdevumus...<br>";
        foreach ($result as $task) {
            $stmt = $pdo->prepare("DELETE FROM uzdevumi WHERE id = ?");
            $stmt->execute([$task['task_id']]);
            echo "Dzēsts uzdevums ID: {$task['task_id']}<br>";
        }
    } else {
        echo "<strong style='color: red'>NEVEIKSMĪGI!</strong> Nav izveidoti uzdevumi.<br>";
    }
}

echo "<form method='POST'>";
echo "<button type='submit' name='test_critical' style='background: red; color: white; padding: 10px; border: none; cursor: pointer;'>TESTĒT KRITISKO UZDEVUMU IZVEIDI</button>";
echo "</form>";

echo "<h3>3. Log failu pārbaude</h3>";
echo "<a href='cron_setup.php'>Skatīt sistēmas logus</a>";

?>
