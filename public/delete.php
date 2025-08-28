<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: index.php?toast=ID%20inv%C3%A1lido&type=error'); exit;
}

// Verificar existencia
$st = $pdo->prepare("SELECT order_number, week_date FROM orders WHERE id=?");
$st->execute([$id]);
$ord = $st->fetch(PDO::FETCH_ASSOC);
if (!$ord) {
  header('Location: index.php?toast=La%20orden%20no%20existe&type=error'); exit;
}

// Eliminar (cascade hará el resto)
$del = $pdo->prepare("DELETE FROM orders WHERE id=?");
$del->execute([$id]);

// Redirigir con NOTIFICACIÓN ESPECIAL (no toast)
$redirWeek = !empty($ord['week_date']) ? ('&week=' . urlencode($ord['week_date'])) : '';
$msg = 'Orden%20%23' . rawurlencode($ord['order_number']) . '%20eliminada%20correctamente';
header('Location: index.php?notif=deleted&msg='.$msg.$redirWeek);
exit;
