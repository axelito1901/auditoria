<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

try {
    // Para no romper claves foráneas, deshabilitamos checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Vaciar tablas
    $pdo->exec("TRUNCATE TABLE audit_answers");
    $pdo->exec("TRUNCATE TABLE audits");
    $pdo->exec("TRUNCATE TABLE orders");

    // Volvemos a habilitar checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "<h2 style='font-family:system-ui; color:green'>✅ Todas las órdenes fueron borradas y los IDs reiniciados a 1</h2>";
    echo "<p><a href='index.php'>Volver al inicio</a></p>";

} catch (Throwable $e) {
    echo "<h2 style='font-family:system-ui; color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
