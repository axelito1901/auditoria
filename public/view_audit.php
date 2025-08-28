<?php
// view_audit.php — Muestra una orden auditada existente (GET id=)
// Tablas: orders, audits(order_id,total_items,errors_count,not_applicable_count,error_percentage,audited_at),
// audit_answers(audit_id,item_id,value,responsable_text,question_text), checklist_items(id,item_order,question)
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Día de la semana en español
function dia_es($dateStr){
  if (!$dateStr) return '';
  // soporta 'YYYY-mm-dd' o 'YYYY-mm-dd HH:ii:ss'
  $dt = DateTime::createFromFormat('Y-m-d H:i:s', $dateStr) ?: DateTime::createFromFormat('Y-m-d', $dateStr);
  if (!$dt) {
    // intento genérico
    $ts = strtotime($dateStr);
    if ($ts === false) return '';
    $dt = (new DateTime())->setTimestamp($ts);
  }
  $dias = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
  $d = (int)$dt->format('w');
  // 28/08/2025 por ejemplo
  $fecha = $dt->format('d/m/Y');
  return $dias[$d] . ' ' . $fecha;
}

$auditId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errMsg = null;
$hdr = null;     // cabecera orden + audit
$rows = [];      // respuestas
$app = $err = $na = 0;
$pct = 0.0;

if ($auditId <= 0) {
  $errMsg = 'Falta el parámetro ?id de la orden.';
} else {
  try {
    // 1) Cabecera (incluyo audited_at para mostrar el día)
    $stmt = $pdo->prepare("
      SELECT
        a.id,
        a.order_id,
        a.total_items,
        a.errors_count,
        a.not_applicable_count,
        a.error_percentage,
        a.audited_at,
        o.order_number,
        o.order_type,
        o.week_date
      FROM audits a
      JOIN orders o ON o.id = a.order_id
      WHERE a.id = :id
      LIMIT 1
    ");
    $stmt->execute([':id' => $auditId]);
    $hdr = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$hdr) {
      $errMsg = 'La orden no existe (id inválido).';
    } else {
      // 2) Respuestas
      $stmt2 = $pdo->prepare("
        SELECT
          aa.item_id,
          aa.value,
          aa.responsable_text,
          COALESCE(aa.question_text, ci.question) AS question,
          ci.item_order
        FROM audit_answers aa
        LEFT JOIN checklist_items ci ON ci.id = aa.item_id
        WHERE aa.audit_id = :id
        ORDER BY ci.item_order ASC, aa.item_id ASC
      ");
      $stmt2->execute([':id' => $auditId]);
      $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

      // KPIs (uso guardados; si faltan, calculo)
      $app = (int)($hdr['total_items'] ?? 0);
      $err = (int)($hdr['errors_count'] ?? 0);
      $na  = (int)($hdr['not_applicable_count'] ?? 0);
      $pct = (float)($hdr['error_percentage'] ?? 0);

      if ($app === 0 && !empty($rows)) {
        $k = ['OK'=>0,'1'=>0,'N'=>0];
        foreach ($rows as $r) {
          $v = strtoupper(trim((string)$r['value']));
          if (!isset($k[$v])) continue;
          $k[$v]++;
        }
        $app = $k['OK'] + $k['1'];
        $err = $k['1'];
        $na  = $k['N'];
        $pct = ($app > 0) ? round(($err / $app) * 100, 2) : 0.0;
      }
    }
  } catch (Throwable $e) {
    $errMsg = 'Error al leer la orden: ' . $e->getMessage();
  }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?= $hdr ? ('Orden #'.(int)$hdr['id']) : 'Ver orden' ?> · Organización Sur</title>
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

    /* Appbar */
    .appbar{position:sticky; top:0; z-index:20; background:rgba(10,15,24,.7); backdrop-filter: blur(8px); border-bottom:1px solid var(--border)}
    .appbar-inner{width:min(1200px, 94vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .brand{display:flex; align-items:center; gap:12px; font-weight:800; letter-spacing:.25px}
    .brand img{height:60px; width:auto; display:block}
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
      <!-- Logo a la izquierda (sin dot ni texto) -->
      <div class="brand">
        <img src="../assets/logo.png" alt="Logo Organización Sur">
      </div>
      <div class="spacer"></div>
      <a class="btn" href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
    </div>
  </div>

  <div class="wrap">
    <?php if ($errMsg): ?>
      <div class="card">
        <h2 style="margin:0 0 6px">No se pudo mostrar</h2>
        <p class="muted" style="margin:0 0 12px"><?= $errMsg ?></p>
        <div style="display:flex; gap:10px; flex-wrap:wrap">
          <a class="btn" href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
        </div>
      </div>
    <?php else: ?>
      <?php
        $typeMap = ['cliente'=>'Cliente','garantia'=>'Garantía','interna'=>'Interna'];
        $typeLabel = $typeMap[$hdr['order_type']] ?? ucfirst((string)$hdr['order_type']);
        // Fecha principal para "día de la orden": priorizo audited_at si existe; si no, week_date
        $fechaOrdenRef = $hdr['audited_at'] ?: $hdr['week_date'];
        $diaOrden = dia_es($fechaOrdenRef);
      ?>
      <!-- Resumen -->
      <div class="card grid">
        <div class="col-6">
          <h2 style="margin:0 0 8px"><i class="fa-solid fa-eye"></i> Detalle de orden</h2>
          <p class="muted" style="margin:0">ID Orden: <b>#<?= (int)$hdr['id'] ?></b></p>
          <p class="muted" style="margin:0">OR: <b><?= h($hdr['order_number']) ?></b> · Tipo: <b><?= h($typeLabel) ?></b></p>
          <p class="muted" style="margin:0">Semana (lunes): <?= h($hdr['week_date']) ?></p>
          <p class="muted" style="margin:0">Día de la orden: <b><?= h($diaOrden) ?></b></p>
        </div>
        <div class="col-6" style="display:flex; gap:10px; justify-content:flex-end; align-items:flex-start; flex-wrap:wrap">
          <a class="btn" href="create.php"><i class="fa-solid fa-plus"></i> Nueva orden</a>
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
          <div class="muted"><i class="fa-solid fa-list-check"></i> Respuestas</div>
          <div class="muted" style="font-size:12px">Total registros: <?= count($rows) ?></div>
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
              <?php if (!empty($rows)): ?>
                <?php foreach ($rows as $r): ?>
                  <tr>
                    <td class="num"><?= h((string)($r['item_order'] ?? '')) ?></td>
                    <td><?= h($r['question'] ?? '') ?></td>
                    <td><?= h($r['responsable_text'] ?? '') ?></td>
                    <td>
                      <?php
                        $v = strtoupper(trim((string)($r['value'] ?? '')));
                        if ($v === '1'){
                          echo '<span class="badge-err">1</span>';
                        } elseif ($v === 'N'){
                          echo '<span class="badge-na">N</span>';
                        } else {
                          echo '<span class="badge-ok">OK</span>';
                        }
                      ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td colspan="4" class="muted">Sin respuestas para esta orden.</td></tr>
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
