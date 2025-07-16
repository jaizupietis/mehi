<?php
// AVOTI Task Management System - Testa fails

echo "<h1>AVOTI Task Management System</h1>";
echo "<h2>Sistēmas tests</h2>";

// PHP versija
echo "<p><strong>PHP versija:</strong> " . phpversion() . "</p>";

// MySQL pieslēgums
try {
    $pdo = new PDO("mysql:host=localhost;dbname=mehu_uzd;charset=utf8mb4", "tasks", "Astalavista1920");
    echo "<p><strong>Datu bāze:</strong> <span style='color:green'>Savienojums izveidots</span></p>";
    
    // Pārbaudīt tabulas
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<p><strong>Tabulas:</strong> " . count($tables) . " tabulas atrastas</p>";
    
} catch (PDOException $e) {
    echo "<p><strong>Datu bāze:</strong> <span style='color:red'>Kļūda: " . $e->getMessage() . "</span></p>";
}

// Failu atļaujas
echo "<p><strong>Uploads direktorijs:</strong> " . (is_writable('uploads') ? '<span style="color:green">Rakstāms</span>' : '<span style="color:red">Nav rakstāms</span>') . "</p>";

// Servera informācija
echo "<p><strong>Serveris:</strong> " . $_SERVER['SERVER_SOFTWARE'] . "</p>";
echo "<p><strong>Dokument root:</strong> " . $_SERVER['DOCUMENT_ROOT'] . "</p>";

echo "<hr>";
echo "<p><a href='login.php'>Doties uz pieslēgšanās lapu</a></p>";