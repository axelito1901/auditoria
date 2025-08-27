<?php
// save_audit.php — Guarda usando orders → audits → audit_answers y muestra confirmación bonita
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===============================
// Cargar checklist para validar y mostrar textos
// ===============================
$items = $pdo->query("
  SELECT id, item_order, question, responsable_default
  FROM checklist_items
  WHERE active=1
  ORDER BY item_order
")->fetchAll(PDO::FETCH_ASSOC);

$itemIndex = [];
foreach ($items as $it) {
  $itemIndex[(int)$it['id']] = $it;
}

// ===============================
// Leer POST
// ===============================
$errMsg  = null;
$auditId = null;
$orderId = null;

// Campos del formulario
$order_number = trim($_POST['order_number'] ?? '');
$order_type   = trim($_POST['order_type'] ?? '');       // cliente | garantia | interna
$week_date    = trim($_POST['week_date'] ?? '');        // YYYY-MM-DD (lunes de la semana)
$audit_date   = trim($_POST['audit_date'] ?? '');       // opcional (si lo enviás), YYYY-MM-DD

$ans        = $_POST['ans']        ?? []; // [item_id => 'OK'|'1'|'N']
$resp       = $_POST['resp']       ?? []; // [item_id => 'Responsable'|'Otros']
$resp_other = $_POST['resp_other'] ?? []; // [item_id => 'Texto si Otros']

// Validaciones mínimas
$errors = [];
if ($order_number === '') $errors[] = 'Falta el número de OR.';
if (!in_array($order_type, ['cliente','garantia','interna'], true)) $errors[] = 'Tipo de orden inválido.';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $week_date)) $errors[] = 'Fecha de semana inválida (formato esperado YYYY-MM-DD).';

// Sanear respuestas y calcular KPIs
$cleanAnswers = [];
foreach ($ans as $iid => $val) {
  $iid = (int)$iid;
  if (!isset($itemIndex[$iid])) continue; // ignorar ítems que no existen

  $val = strtoupper(trim((string)$val));
  if (!in_array($val, ['OK','1','N'], true)) $val = 'OK';

  $rval = isset($resp[$iid]) ? trim((string)$resp[$iid]) : '';
  if ($rval === 'Otros') {
    $alt = trim((string)($resp_other[$iid] ?? ''));
    if ($alt !== '') $rval = $alt;
  }
  if ($rval === '') $rval = (string)($itemIndex[$iid]['responsable_default'] ?? '');

  $cleanAnswers[$iid] = [
    'status'      => $val,
    'responsable' => $rval
  ];
}

if (empty($cleanAnswers)) $errors[] = 'No se recibieron respuestas de auditoría.';

$kpis = ['OK'=>0,'1'=>0,'N'=>0];
foreach ($cleanAnswers as $row) $kpis[$row['status']]++;
$app = $kpis['OK'] + $kpis['1'];            // aplicables
$err = $kpis['1'];
$na  = $kpis['N'];
$pct = ($app > 0) ? round(($err / $app) * 100, 2) : 0.0;

// ===============================
// Guardar si todo OK
// ===============================
if (empty($errors)) {
  try {
    $pdo->beginTransaction();

    // 1) Buscar ó crear la OR en `orders`
    //    (si ya existe por order_number, la usamos; si no, la creamos)
    $stmtOrd = $pdo->prepare("SELECT id FROM orders WHERE order_number = :num LIMIT 1");
    $stmtOrd->execute([':num' => $order_number]);
    $orderId = $stmtOrd->fetchColumn();

    if (!$orderId) {
      $stmtInsOrd = $pdo->prepare("
        INSERT INTO orders (order_number, order_type, week_date)
        VALUES (:num, :type, :week)
      ");
      $stmtInsOrd->execute([
        ':num'  => $order_number,
        ':type' => $order_type,
        ':week' => $week_date,
      ]);
      $orderId = (int)$pdo->lastInsertId();
    } else {
      // opcional: si querés mantener sincronizado tipo/semana cuando la OR ya existe
      $stmtUpdOrd = $pdo->prepare("
        UPDATE orders
        SET order_type = :type, week_date = :week
        WHERE id = :id
      ");
      $stmtUpdOrd->execute([
        ':type' => $order_type,
        ':week' => $week_date,
        ':id'   => $orderId,
      ]);
    }

    // 2) Crear la auditoría en `audits`
    $stmtA = $pdo->prepare("
      INSERT INTO audits (order_id, total_items, errors_count, not_applicable_count, error_percentage)
      VALUES (:order_id, :total, :errors, :na, :pct)
    ");
    $stmtA->execute([
      ':order_id' => $orderId,
      ':total'    => count($cleanAnswers),
      ':errors'   => $err,
      ':na'       => $na,
      ':pct'      => $pct,
    ]);
    $auditId = (int)$pdo->lastInsertId();

    // 3) Insertar las respuestas en `audit_answers`
    $stmtAns = $pdo->prepare("
      INSERT INTO audit_answers (audit_id, item_id, value, responsable_text, question_text)
      VALUES (:audit_id, :item_id, :val, :resp, :qtxt)
    ");
    foreach ($cleanAnswers as $iid => $row) {
      $stmtAns->execute([
        ':audit_id' => $auditId,
        ':item_id'  => $iid,
        ':val'      => $row['status'],
        ':resp'     => $row['responsable'],
        ':qtxt'     => $itemIndex[$iid]['question'] ?? '',
      ]);
    }

    $pdo->commit();

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $errors[] = 'Error al guardar en BD: ' . $e->getMessage();
  }
}

$errMsg = empty($errors) ? null : implode(' ', $errors);

// ===============================
// UI — mismo estilo que create.php (sin Bootstrap)
// ===============================
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $auditId ? 'Auditoría guardada' : 'Error al guardar' ?> · Organización Sur</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    :root{
      --bg:#0a0f18; --bg2:#0b121d; --surface:#101826; --surface2:#0f1725; --border:#1b2535;
      --text:#e7ecf3; --muted:#9fb0c9; --accent:#00A3E0; --accent-2:#00b4ff;
      --ok-b:#2ea26d; --err-b:#d35b5b; --na-b:#5aa1ff; --sel:#111c2f; --chip:#0c1420; --radius:14px;
      --ok-c:#2ea26d; --err-c:#e06666; --na-c:#5aa1ff; --bg-donut:#0f1725;
    }
    *{box-sizing:border-box}
    body{
      margin:0; font-family:system-ui, Segoe UI, Roboto, Arial, sans-serif; color:var(--text);
      background:
        radial-gradient(1200px 600px at 20% -20%, rgba(0,163,224,.18), transparent 50%),
        radial-gradient(1000px 700px at 110% 10%, rgba(0,163,224,.18), transparent 40%),
        linear-gradient(180deg,#0a0f18, #0b121d 50%, #0a0f18 100%);
    }
    a{color:#cfe3ff; text-decoration:none}
    a:hover{text-decoration:underline}

    .appbar{position:sticky; top:0; z-index:20; background:rgba(10,15,24,.7); backdrop-filter: blur(8px); border-bottom:1px solid var(--border)}
    .appbar-inner{width:min(1200px, 94vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .dot{width:22px; height:22px; border-radius:999px; background:linear-gradient(180deg,var(--accent),var(--accent-2)); box-shadow:0 0 16px rgba(0,163,224,.35)}
    .brand{font-weight:800; letter-spacing:.25px; display:flex; align-items:center; gap:10px}
    .brand i{opacity:.9}
    .spacer{flex:1}
    .btn{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); color:#dbe7ff; background:transparent; padding:8px 12px; border-radius:10px}
    .btn:hover{border-color:#2a3a55}
    .btn-primary{background:linear-gradient(180deg,var(--accent),var(--accent-2)); color:#fff; border:0}

    .wrap{width:min(1200px, 94vw); margin:24px auto 40px}
    .card{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
          border:1px solid var(--border); border-radius:14px;
          box-shadow: 0 10px 30px rgba(0,0,0,.35), inset 0 0 0 1px var(--border);
          padding:16px; margin:16px 0}
    .grid{display:grid; gap:12px; grid-template-columns:repeat(12,1fr)}
    .col-3{grid-column:span 3} .col-4{grid-column:span 4} .col-6{grid-column:span 6} .col-8{grid-column:span 8} .col-12{grid-column:1/-1}
    .muted{color:var(--muted)}

    table{width:100%; border-collapse:separate; border-spacing:0 10px}
    thead th{font-size:13px; color:#cbd5e1; text-transform:uppercase; letter-spacing:.08em; padding:0 10px}
    tbody tr{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01)); border:1px solid var(--border);
             border-radius:12px; overflow:hidden;}
    tbody td{padding:12px 10px; vertical-align:middle}
    .num{color:#cbd5e1; font-weight:700}

    .donut{
      position:relative; width:132px; aspect-ratio:1/1; border-radius:50%;
      background: conic-gradient(var(--bg-donut) 0 100%); display:flex; align-items:center; justify-content:center; margin:auto;
      border:1px solid var(--border); box-shadow: inset 0 0 0 8px #0b1320;
    }
    .donut .center{ position:absolute; width:62%; height:62%; border-radius:50%; background:#0b1320;
      display:flex; align-items:center; justify-content:center; flex-direction:column; text-align:center; padding:6px; }
    .donut .big{ font-size:20px; font-weight:800; }
    .donut .sub{ font-size:11px; color:#9fb0c9 }

    .badge-ok{background:rgba(46,162,109,.15); color:#bcebd6; border:1px solid rgba(46,162,109,.35); padding:6px 10px; border-radius:999px; font-weight:700}
    .badge-err{background:rgba(211,91,91,.15); color:#ffd6d6; border:1px solid rgba(211,91,91,.35); padding:6px 10px; border-radius:999px; font-weight:700}
    .badge-na{background:rgba(90,161,255,.15); color:#d5e7ff; border:1px solid rgba(90,161,255,.35); padding:6px 10px; border-radius:999px; font-weight:700}

    @media (max-width: 820px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>
  <div class="appbar">
    <div class="appbar-inner">
      <div class="dot"></div>
      <div class="brand">
        <i class="fa-solid fa-clipboard-check"></i>
        <span><?= $auditId ? 'Auditoría guardada' : 'Error al guardar' ?></span>
      </div>
      <div class="spacer"></div>
      <a class="btn" href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
    </div>
  </div>

  <div class="wrap">
    <?php if ($errMsg): ?>
      <div class="card">
        <h2 style="margin:0 0 6px">No se pudo guardar</h2>
        <p class="muted" style="margin:0 0 12px"><?= $errMsg ?></p>
        <div style="display:flex; gap:10px; flex-wrap:wrap">
          <a class="btn" href="create.php"><i class="fa-solid fa-arrow-left"></i> Volver a crear</a>
          <a class="btn btn-primary" href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
        </div>
      </div>
    <?php else: ?>
      <?php
        // Datos para mostrar
        $order_types = ['cliente'=>'Cliente','garantia'=>'Garantía','interna'=>'Interna'];
        $order_type_label = $order_types[$order_type] ?? ucfirst($order_type);
      ?>
      <!-- Resumen -->
      <div class="card grid">
        <div class="col-6">
          <h2 style="margin:0 0 8px"><i class="fa-solid fa-circle-check" style="color:#22c55e"></i> Guardado correctamente</h2>
          <p class="muted" style="margin:0">ID Auditoría: <b>#<?= (int)$auditId ?></b></p>
          <p class="muted" style="margin:0">OR: <b><?= h($order_number) ?></b> · Tipo: <b><?= h($order_type_label) ?></b></p>
          <p class="muted" style="margin:0">Semana (lunes): <?= h($week_date) ?></p>
          <?php if ($audit_date): ?>
            <p class="muted" style="margin:0">Fecha auditoría: <?= h(DateTime::createFromFormat('Y-m-d',$audit_date)->format('d-m-Y')) ?></p>
          <?php endif; ?>
        </div>
        <div class="col-6" style="display:flex; gap:10px; justify-content:flex-end; align-items:flex-start; flex-wrap:wrap">
          <a class="btn btn-primary" href="view_audit.php?id=<?= (int)$auditId ?>"><i class="fa-solid fa-eye"></i> Ver auditoría</a>
          <a class="btn" href="create.php"><i class="fa-solid fa-plus"></i> Nueva auditoría</a>
          <a class="btn" href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
        </div>
      </div>

      <!-- KPIs -->
      <div class="card grid" style="align-items:center">
        <div class="col-4">
          <div class="donut" id="donutPct">
            <div class="center">
              <div class="big" id="pctTxt"><?= h(number_format($pct,2)) ?>%</div>
              <div class="sub">% error</div>
            </div>
          </div>
        </div>
        <div class="col-8" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center">
          <div class="muted">Aplicables (OK/1): <b id="st-app"><?= (int)$app ?></b></div>
          <div class="muted">Errores (1): <b id="st-err"><?= (int)$err ?></b></div>
          <div class="muted">No aplica (N): <b id="st-na"><?= (int)$na ?></b></div>
          <div class="muted">% Error: <b id="st-pct"><?= h(number_format($pct,2)) ?>%</b></div>
        </div>
      </div>

      <!-- Tabla de resultados -->
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px">
          <div class="muted"><i class="fa-solid fa-list-check"></i> Resultados de la auditoría</div>
          <div class="muted" style="font-size:12px">Total ítems: <?= count($cleanAnswers ?? []) ?></div>
        </div>
        <div style="overflow:auto">
          <table>
            <thead>
              <tr>
                <th style="width:56px">#</th>
                <th>Ítem</th>
                <th style="width:320px">Responsable</th>
                <th style="width:120px">Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($cleanAnswers)): ?>
                <?php
                  $ordered = [];
                  foreach ($cleanAnswers as $iid => $row) {
                    $ordered[] = [
                      'order'       => (int)($itemIndex[$iid]['item_order'] ?? 0),
                      'question'    => (string)($itemIndex[$iid]['question'] ?? ('Ítem '.$iid)),
                      'status'      => $row['status'],
                      'responsable' => $row['responsable'],
                    ];
                  }
                  usort($ordered, fn($a,$b)=> $a['order'] <=> $b['order']);
                ?>
                <?php foreach ($ordered as $r): ?>
                  <tr>
                    <td class="num"><?= (int)$r['order'] ?></td>
                    <td><?= h($r['question']) ?></td>
                    <td><?= h($r['responsable']) ?></td>
                    <td>
                      <?php if ($r['status'] === 'OK'): ?>
                        <span class="badge-ok">OK</span>
                      <?php elseif ($r['status'] === '1'): ?>
                        <span class="badge-err">1</span>
                      <?php else: ?>
                        <span class="badge-na">N</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="muted">Sin datos para mostrar.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // Donut % error visual
    (function(){
      const donut = document.getElementById('donutPct');
      const pctTxt = document.getElementById('pctTxt');
      if (!donut || !pctTxt) return;
      const valStr = pctTxt.textContent.replace('%','').trim();
      const p = Math.max(0, Math.min(100, parseFloat(valStr) || 0));
      donut.style.background = `conic-gradient(var(--err-c) ${p}%, var(--bg-donut) 0)`;
    })();
  </script>
</body>
</html>
