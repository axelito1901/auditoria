<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$typesAllowed = ['cliente','garantia','interna'];

// === Cargar orden ===
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header('Location: index.php?toast=ID%20inv%C3%A1lido&type=error'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order) {
  header('Location: index.php?toast=La%20orden%20no%20existe&type=error'); exit;
}

// === Guardar (POST) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $order_number = trim($_POST['order_number'] ?? '');
  $order_type   = trim($_POST['order_type'] ?? '');
  $auditor      = trim($_POST['auditor'] ?? '');
  $week_date    = trim($_POST['week_date'] ?? '');
  $notes        = trim($_POST['notes'] ?? '');

  if (!in_array($order_type, $typesAllowed, true)) $order_type = 'cliente';

  // Validaciones mínimas
  if ($order_number === '') {
    $err = 'El N° de OR es obligatorio.';
  } elseif ($week_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_date)) {
    $err = 'La fecha debe tener formato AAAA-MM-DD.';
  }

  if (!isset($err)) {
    $upd = $pdo->prepare("UPDATE orders
                          SET order_number=?, order_type=?, auditor=?, week_date=?, notes=?
                          WHERE id=?");
    $upd->execute([$order_number, $order_type, $auditor, ($week_date ?: null), ($notes ?: null), $id]);

    // Volver al index mostrando toast y (opcional) filtrando por la auditoría de esa fecha
    $redirWeek = $week_date ? ('&week=' . urlencode($week_date)) : '';
    header('Location: index.php?toast=Orden%20actualizada&type=success'.$redirWeek);
    exit;
  }
}

// estilos base
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Editar orden #<?= h($order['order_number']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <style>
    :root{
      --bg:#0a0f18; --bg2:#0b121d; --surface:#101826; --surface2:#0f1725; --border:#1b2535;
      --text:#e7ecf3; --muted:#9fb0c9; --accent:#00A3E0; --accent2:#00b4ff; --radius:14px;
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family:system-ui, Segoe UI, Roboto, Arial, sans-serif; color:var(--text);
      background:
        radial-gradient(1200px 600px at 20% -20%, rgba(0,163,224,.18), transparent 50%),
        radial-gradient(1000px 700px at 110% 10%, rgba(0,163,224,.18), transparent 40%),
        linear-gradient(180deg,#0a0f18, #0b121d 50%, #0a0f18 100%);
    }
    .bar{position:sticky; top:0; z-index:10; background:rgba(10,15,24,.7); backdrop-filter:blur(8px); border-bottom:1px solid var(--border)}
    .bar-inner{width:min(1200px,94vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .brand img{height:60px; width:auto; display:block}
    .spacer{flex:1}
    .btn{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); color:#dbe7ff; background:transparent; padding:8px 12px; border-radius:10px; text-decoration:none}
    .btn:hover{border-color:#2a3a55}
    .btn-primary{background:linear-gradient(180deg,var(--accent),var(--accent2)); color:#fff; border:0}
    .wrap{width:min(900px,94vw); margin:20px auto 40px}
    .card{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
          border:1px solid var(--border); border-radius:var(--radius); padding:16px; margin:14px 0}
    label{display:block; margin:0 0 6px; color:#d5deea; font-size:14px}
    input[type=text], input[type=date], select, textarea{
      width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--border);
      background:var(--surface2); color:var(--text); outline:none
    }
    textarea{min-height:110px; resize:vertical}
    .grid{display:grid; gap:12px; grid-template-columns: 1fr 1fr}
    @media(max-width:720px){ .grid{grid-template-columns: 1fr} }
    .muted{color:var(--muted)}
    .actions{display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-top:12px}
    .error{color:#ffb4b4; font-size:14px; margin-bottom:8px}
  </style>
</head>
<body>
  <div class="bar">
    <div class="bar-inner">
      <div class="brand"><img src="../assets/logo.png" alt="Logo Organización Sur"></div>
      <div class="spacer"></div>
      <a class="btn" href="index.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
    </div>
  </div>

  <div class="wrap">
    <div class="card">
      <h2 style="margin:0 0 12px">Editar orden</h2>
      <?php if (!empty($err)): ?><div class="error"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($err) ?></div><?php endif; ?>
      <form method="post" action="">
        <div class="grid">
          <div>
            <label>N° OR</label>
            <input type="text" name="order_number" value="<?= h($order['order_number']) ?>" required>
          </div>
          <div>
            <label>Tipo</label>
            <select name="order_type" required>
              <?php foreach ($typesAllowed as $t): ?>
                <option value="<?= h($t) ?>" <?= $order['order_type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label>Auditor</label>
            <input type="text" name="auditor" value="<?= h($order['auditor'] ?? '') ?>">
          </div>
          <div>
            <label>Auditorias (fecha)</label>
            <input type="date" name="week_date" value="<?= h($order['week_date'] ?? '') ?>">
          </div>
          <div style="grid-column:1/-1">
            <label>Notas</label>
            <textarea name="notes" placeholder="Observaciones..."><?= h($order['notes'] ?? '') ?></textarea>
          </div>
        </div>
        <div class="actions">
          <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button>
          <a class="btn" href="index.php"><i class="fa-solid fa-xmark"></i> Cancelar</a>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
