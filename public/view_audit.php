<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit('Falta ID de auditoría'); }

// Traer auditoría + OR
$aud = $pdo->prepare("
  SELECT a.*, o.order_number, o.order_type, o.week_date
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE a.id = ?
");
$aud->execute([$id]);
$auditoria = $aud->fetch();
if (!$auditoria) { http_response_code(404); exit('Auditoría no encontrada'); }

// Traer respuestas (con orden del checklist)
$ans = $pdo->prepare("
  SELECT ai.*, ci.item_order
  FROM audit_answers ai
  JOIN checklist_items ci ON ci.id = ai.item_id
  WHERE ai.audit_id = ?
  ORDER BY ci.item_order
");
$ans->execute([$id]);
$respuestas = $ans->fetchAll();

// Exportar CSV si corresponde
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $file = "auditoria_OR_" . preg_replace('/[^A-Za-z0-9_-]/','',$auditoria['order_number']) . "_" . $id . ".csv";
  header('Content-Type: text/csv; charset=utf-8');
  header("Content-Disposition: attachment; filename=\"$file\"");
  echo "\xEF\xBB\xBF"; // BOM UTF-8 para Excel

  $out = fopen('php://output', 'w');
  fputcsv($out, ['#','Ítem (snapshot)','Responsable (snapshot)','Valor','Comentario']);
  foreach ($respuestas as $r) {
    fputcsv($out, [
      (int)$r['item_order'],
      (string)($r['question_text'] ?? ''),
      (string)($r['responsable_text'] ?? ''),
      (string)$r['value'],
      (string)($r['comment'] ?? '')
    ]);
  }
  fclose($out);
  exit;
}

// Derivados
$aplicables = max(0, (int)$auditoria['total_items'] - (int)$auditoria['not_applicable_count']);
$errores    = (int)$auditoria['errors_count'];
$pct        = number_format((float)$auditoria['error_percentage'], 2);
$fechaAud   = new DateTime($auditoria['audited_at'] ?? 'now');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Auditoría OR #<?= htmlspecialchars($auditoria['order_number']) ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome para íconos -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    /* ====== Tema oscuro moderno estilo VW ====== */
    :root{
      --bg:#0a0f18;
      --bg-grad: radial-gradient(1200px 600px at 20% -20%, rgba(0,163,224,.18), transparent 50%),
                 radial-gradient(1000px 700px at 110% 10%, rgba(0,163,224,.18), transparent 40%),
                 linear-gradient(180deg,#0a0f18, #0b121d 50%, #0a0f18 100%);
      --surface:#101826;
      --surface-2:#0f1725;
      --border:#1b2535;
      --text:#e7ecf3;
      --muted:#9fb0c9;
      --accent:#00A3E0;
      --accent-2:#00b4ff;
      --ok:#0f2a1f; --ok-b:#2ea26d;
      --err:#2a1313; --err-b:#d35b5b;
      --na:#142539;  --na-b:#5aa1ff;
      --sel:#111c2f;
      --chip:#0c1420;
      --radius:14px;
      --shadow: 0 10px 30px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.03);
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0; font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif; color:var(--text);
      background: var(--bg-grad), var(--bg);
    }
    a{color:#cfe3ff; text-decoration:none}
    a:hover{text-decoration:underline}

    .appbar{
      position:sticky; top:0; z-index:20;
      background:rgba(10,15,24,.7); backdrop-filter: blur(8px);
      border-bottom:1px solid var(--border);
    }
    .appbar-inner{width:min(1150px, 92vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .dot{width:22px; height:22px; border-radius:999px; background:linear-gradient(180deg,var(--accent),var(--accent-2)); box-shadow:0 0 16px rgba(0,163,224,.35)}
    .brand{font-weight:800; letter-spacing:.25px; display:flex; align-items:center; gap:10px}
    .brand i{opacity:.9}
    .spacer{flex:1}
    .bar-actions{display:flex; gap:8px; flex-wrap:wrap}
    .btn{
      display:inline-flex; align-items:center; gap:8px;
      background:linear-gradient(180deg,var(--accent),#0088bb);
      color:#fff; border:0; border-radius:12px; padding:10px 14px; cursor:pointer;
      box-shadow:0 6px 18px rgba(0,163,224,.25); font-weight:700; transition:transform .06s, filter .2s;
    }
    .btn:hover{filter:brightness(1.05); transform:translateY(-1px)}
    .btn.ghost{
      background:transparent; border:1px solid var(--border); color:#dbe7ff; box-shadow:none; font-weight:600;
    }

    .wrap{width:min(1150px, 92vw); margin:20px auto 40px}
    .card{
      background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
      border:1px solid var(--border);
      border-radius:var(--radius);
      box-shadow: var(--shadow);
      padding:16px; margin:16px 0
    }
    .grid{display:grid; gap:12px; grid-template-columns:repeat(12,1fr)}
    .col-3{grid-column:span 3}
    .col-4{grid-column:span 4}
    .col-6{grid-column:span 6}
    .col-12{grid-column:1 / -1}
    .k{color:#cbd5e1}
    .v{color:#fff}
    .stat{background:var(--surface); border:1px solid var(--border); padding:10px 12px; border-radius:12px; display:flex; gap:8px; align-items:center}
    .pill{padding:6px 10px; border:1px solid var(--border); border-radius:999px; font-size:12px; background:var(--chip); color:#d1d5db}

    .meter{height:10px; background:rgba(255,255,255,.06); border:1px solid var(--border); border-radius:999px; overflow:hidden}
    .meter-bar{height:100%; width:0%; background:linear-gradient(90deg, var(--err-b), var(--accent-2));}

    table{width:100%; border-collapse:separate; border-spacing:0 10px}
    thead th{font-size:13px; color:#cbd5e1; text-transform:uppercase; letter-spacing:.08em; padding:0 10px}
    tbody tr{
      background:var(--surface);
      border:1px solid var(--border);
      border-radius:12px; overflow:hidden;
    }
    tbody td{padding:12px 10px; vertical-align:top}
    tbody tr.state-OK{box-shadow: inset 4px 0 0 var(--ok-b)}
    tbody tr.state-1{box-shadow: inset 4px 0 0 var(--err-b)}
    tbody tr.state-N{box-shadow: inset 4px 0 0 var(--na-b)}
    .badge{font-weight:700; padding:6px 10px; border-radius:999px; font-size:12px; display:inline-block}
    .b-ok{background:rgba(46,162,109,.12); color:#bcebd6; border:1px solid rgba(46,162,109,.35)}
    .b-err{background:rgba(211,91,91,.12); color:#ffd6d6; border:1px solid rgba(211,91,91,.35)}
    .b-na{background:rgba(90,161,255,.12); color:#d5e7ff; border:1px solid rgba(90,161,255,.35)}

    .footer-actions{display:flex; gap:10px; justify-content:flex-end}
    .muted{color:var(--muted)}

    /* Print */
    @media print{
      .appbar, .footer-actions{display:none}
      body{background:#fff; color:#000}
      .wrap{width:100%; margin:0; padding:0}
      .card{box-shadow:none; border-color:#ccc}
      a{text-decoration:none; color:#000}
      .badge{border:1px solid #000 !important; color:#000 !important; background:#fff !important}
    }
  </style>
</head>
<body>
  <!-- Barra superior -->
  <div class="appbar">
    <div class="appbar-inner">
      <div class="dot"></div>
      <div class="brand"><i class="fa-solid fa-clipboard-check"></i> Organización Sur · Auditoría OR #<?= htmlspecialchars($auditoria['order_number']) ?></div>
      <div class="spacer"></div>
      <div class="bar-actions">
        <a class="btn ghost" href="index.php" title="Volver al inicio"><i class="fa-solid fa-arrow-left"></i> Volver</a>
        <a class="btn ghost" href="?id=<?= (int)$id ?>&export=csv" title="Exportar CSV"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
        <button class="btn" onclick="window.print()" type="button" title="Imprimir"><i class="fa-solid fa-print"></i> Imprimir</button>
      </div>
    </div>
  </div>

  <div class="wrap">
    <!-- Resumen -->
    <div class="card">
      <div class="grid">
        <div class="col-3"><div class="k">Número de OR</div><div class="v">#<?= htmlspecialchars($auditoria['order_number']) ?></div></div>
        <div class="col-3"><div class="k">Tipo</div><div class="v"><?= htmlspecialchars($auditoria['order_type']) ?></div></div>
        <div class="col-3"><div class="k">Semana</div><div class="v"><?= htmlspecialchars($auditoria['week_date'] ?? '') ?></div></div>
        <div class="col-3"><div class="k">Auditado</div><div class="v"><?= htmlspecialchars($fechaAud->format('Y-m-d H:i')) ?></div></div>

        <div class="col-3"><div class="stat"><span class="k">Ítems totales</span><span class="v"><?= (int)$auditoria['total_items'] ?></span></div></div>
        <div class="col-3"><div class="stat"><span class="k">Aplicables</span><span class="v"><?= $aplicables ?></span></div></div>
        <div class="col-3"><div class="stat"><span class="k">No aplica (N)</span><span class="v"><?= (int)$auditoria['not_applicable_count'] ?></span></div></div>
        <div class="col-3"><div class="stat"><span class="k">Errores (1)</span><span class="v"><?= $errores ?></span></div></div>

        <div class="col-12">
          <div class="stat" style="gap:12px; align-items:center">
            <span class="k">% de error</span>
            <span class="v" style="font-size:20px; font-weight:700"><?= $pct ?>%</span>
            <div class="meter" style="flex:1">
              <div class="meter-bar" style="width: <?= max(0,min(100,(float)$pct)) ?>%"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Detalle -->
    <div class="card">
      <table>
        <thead>
          <tr>
            <th style="width:56px">#</th>
            <th>Ítem (snapshot)</th>
            <th style="width:300px">Responsable</th>
            <th style="width:120px">Valor</th>
            <th>Comentario</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($respuestas as $r):
            $cls = 'state-OK'; $badge = '<span class="badge b-ok">OK</span>';
            if ($r['value'] === '1'){ $cls='state-1'; $badge='<span class="badge b-err">1</span>'; }
            if ($r['value'] === 'N'){ $cls='state-N'; $badge='<span class="badge b-na">N</span>'; }
          ?>
          <tr class="<?= $cls ?>">
            <td><strong><?= (int)$r['item_order'] ?></strong></td>
            <td><?= htmlspecialchars($r['question_text'] ?? '') ?></td>
            <td><span class="pill"><?= htmlspecialchars($r['responsable_text'] ?? '') ?></span></td>
            <td><?= $badge ?></td>
            <td class="muted"><?= htmlspecialchars($r['comment'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <div class="footer-actions">
      <a class="btn ghost" href="index.php"><i class="fa-solid fa-arrow-left"></i> Volver</a>
      <a class="btn ghost" href="?id=<?= (int)$id ?>&export=csv"><i class="fa-solid fa-file-csv"></i> Exportar CSV</a>
      <button class="btn" onclick="window.print()" type="button"><i class="fa-solid fa-print"></i> Imprimir</button>
    </div>
  </div>
</body>
</html>
