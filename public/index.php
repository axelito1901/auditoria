<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* Semanas con conteo de auditorías */
$weeks = $pdo->query("
  SELECT o.week_date, COUNT(a.id) AS audits
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date IS NOT NULL
  GROUP BY o.week_date
  ORDER BY o.week_date DESC
")->fetchAll();

/* KPIs de cabecera:
   - Últimas 8 semanas: % error por semana
   - Top desvío por ítem (última semana con datos)
   - Top desvío por responsable (última semana con datos) */

/* Últimas 8 semanas: % error */
$wkSeries = $pdo->query("
  SELECT o.week_date,
         SUM(a.errors_count) AS errors,
         SUM(a.total_items - a.not_applicable_count) AS applicable
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date IS NOT NULL
  GROUP BY o.week_date
  ORDER BY o.week_date DESC
  LIMIT 8
")->fetchAll();

/* Última semana con datos */
$lastWeek = null;
if (!empty($wkSeries)) $lastWeek = $wkSeries[0]['week_date'] ?? null;

/* Top ítems por desvío (última semana) */
$topItems = [];
if ($lastWeek){
  $stmt = $pdo->prepare("
    SELECT
      ci.item_order,
      COALESCE(ai.question_text, ci.question) AS question,
      SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) AS errors,
      SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END) AS applicable,
      100.0 * SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END)
        / NULLIF(SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END), 0) AS deviation_pct
    FROM audit_answers ai
    JOIN audits a   ON a.id = ai.audit_id
    JOIN orders o   ON o.id = a.order_id
    JOIN checklist_items ci ON ci.id = ai.item_id
    WHERE o.week_date = ?
    GROUP BY ci.item_order, question
    HAVING applicable > 0
    ORDER BY deviation_pct DESC, errors DESC
    LIMIT 6
  ");
  $stmt->execute([$lastWeek]);
  $topItems = $stmt->fetchAll();
}

/* Top responsables por desvío (última semana) usando snapshot responsable_text */
$topResp = [];
if ($lastWeek){
  $stmt = $pdo->prepare("
    SELECT
      COALESCE(ai.responsable_text, 'Sin responsable') AS responsable,
      SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) AS errors,
      SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END) AS applicable,
      100.0 * SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END)
        / NULLIF(SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END), 0) AS deviation_pct
    FROM audit_answers ai
    JOIN audits a ON a.id = ai.audit_id
    JOIN orders o ON o.id = a.order_id
    WHERE o.week_date = ?
    GROUP BY responsable
    HAVING applicable > 0
    ORDER BY deviation_pct DESC, errors DESC
    LIMIT 6
  ");
  $stmt->execute([$lastWeek]);
  $topResp = $stmt->fetchAll();
}

/* Stmts reutilizados más abajo */
$wkAggStmt = $pdo->prepare("
  SELECT
    SUM(a.errors_count) AS errors,
    SUM(a.total_items - a.not_applicable_count) AS applicable,
    SUM(a.not_applicable_count) AS nas,
    COUNT(*) AS audits
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date = ?
");

$wkAuditsStmt = $pdo->prepare("
  SELECT a.id AS audit_id, a.errors_count, (a.total_items - a.not_applicable_count) AS applicable,
         a.error_percentage, a.audited_at,
         o.order_number, o.order_type
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date = ?
  ORDER BY a.audited_at DESC, a.id DESC
");

$wkItemsStmt = $pdo->prepare("
  SELECT
    ci.item_order,
    COALESCE(ai.question_text, ci.question) AS question,
    SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) AS errors,
    SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END) AS applicable,
    ROUND(100 * SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) /
          NULLIF(SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END), 0), 2) AS deviation_pct
  FROM audit_answers ai
  JOIN audits a   ON a.id = ai.audit_id
  JOIN orders o   ON o.id = a.order_id
  JOIN checklist_items ci ON ci.id = ai.item_id
  WHERE o.week_date = ?
  GROUP BY ci.item_order, question
  ORDER BY ci.item_order ASC
");
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Organización Sur · Auditorías por Semana</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      --bg:#0a0f18; --bg2:#0b121d;
      --surface:#101826; --surface2:#0f1725;
      --border:#1b2535; --text:#e7ecf3; --muted:#9fb0c9;
      --accent:#00A3E0; --accent2:#00b4ff;
      --chip:#0c1420; --radius:12px;
      --ok-b:#2ea26d; --err-b:#d35b5b; --na-b:#5aa1ff;
    }
    *{box-sizing:border-box}
    body{margin:0; font-family:system-ui, Segoe UI, Roboto, Arial, sans-serif; color:var(--text);
         background: radial-gradient(1000px 500px at 15% -10%, rgba(0,163,224,.18), transparent 40%),
                     radial-gradient(1000px 600px at 120% 0%, rgba(0,163,224,.15), transparent 35%),
                     linear-gradient(180deg,var(--bg),var(--bg2))}
    a{color:#cfe3ff; text-decoration:none}
    a:hover{text-decoration:underline}
    .bar{position:sticky; top:0; z-index:5; background:rgba(10,15,24,.7); backdrop-filter:blur(8px);
         border-bottom:1px solid var(--border)}
    .bar-inner{max-width:1200px; margin:0 auto; padding:12px 16px; display:flex; gap:10px; align-items:center}
    .dot{width:18px; height:18px; border-radius:999px; background:linear-gradient(180deg,var(--accent),var(--accent2))}
    .brand{font-weight:700}
    .spacer{flex:1}
    .btn{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); color:#dbe7ff; background:transparent;
         padding:8px 12px; border-radius:10px}
    .btn:hover{border-color:#2a3a55}
    .wrap{max-width:1200px; margin:20px auto; padding:0 16px}

    /* KPI header */
    .kpi-head{display:grid; gap:12px; grid-template-columns: 1.1fr .9fr .9fr; margin-bottom:14px}
    .card{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
          border:1px solid var(--border); border-radius:var(--radius); padding:14px}
    .k-title{font-weight:800; margin-bottom:8px; display:flex; align-items:center; gap:8px}
    .wk-row{display:flex; align-items:center; gap:10px; margin:6px 0}
    .wk-bar{flex:1; height:8px; background:#0f1725; border:1px solid var(--border); border-radius:999px; overflow:hidden}
    .wk-bar i{display:block; height:100%; background:linear-gradient(90deg,var(--accent),var(--accent2))}
    .pill{padding:4px 8px; border:1px solid var(--border); border-radius:999px; font-size:12px; background:var(--chip); color:#d1d5db; white-space:nowrap}
    .row-list{display:flex; flex-direction:column; gap:8px; max-height:240px; overflow:auto}
    .rl{display:grid; grid-template-columns: 1fr 60px 70px; gap:8px; align-items:center}
    .muted{color:var(--muted)}

    .week{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
          border:1px solid var(--border); border-radius:var(--radius); margin:14px 0}
    .week-head{display:flex; align-items:center; gap:10px; padding:12px 14px; cursor:pointer}
    .week-head:hover{background:#0f1725}
    .badge{display:inline-flex; align-items:center; gap:6px; padding:4px 10px; border:1px solid var(--border);
           border-radius:999px; font-size:12px; background:var(--chip); color:#d1d5db}
    .pct{font-weight:700; padding:2px 8px; border-radius:999px; border:1px solid var(--border); background:#0e1624}
    .chev{margin-left:auto; font-size:18px; color:#cbd5e1; transition:transform .15s ease}
    .chev.open{transform:rotate(90deg)}
    .week-body{display:none; padding:0 14px 12px}
    .grid{display:grid; gap:10px}
    @media(min-width:900px){ .grid{grid-template-columns: 1.2fr .8fr} }
    table{width:100%; border-collapse:separate; border-spacing:0 8px}
    thead th{font-size:12px; color:#cbd5e1; text-transform:uppercase; letter-spacing:.08em; padding:0 10px}
    tbody tr{background:var(--surface); border:1px solid var(--border); border-radius:10px; overflow:hidden}
    tbody td{padding:10px; vertical-align:middle}
    .num{color:#cbd5e1; font-weight:700}
    .small{font-size:12px; color:var(--muted)}
    .right{text-align:right}
  </style>
</head>
<body>
  <div class="bar">
    <div class="bar-inner">
      <div class="dot"></div>
      <div class="brand">Organización Sur · Auditorías por Semana</div>
      <div class="spacer"></div>
      <a class="btn" href="create.php">+ Nueva auditoría</a>
    </div>
  </div>

  <div class="wrap">
    <!-- ===== KPI HEAD ===== -->
    <section class="kpi-head">
      <!-- % error por semana (últimas 8) -->
      <div class="card">
        <div class="k-title"><i class="fa-solid fa-calendar-week"></i> % Error por semana (últimas 8)</div>
        <?php if ($wkSeries): ?>
          <?php foreach ($wkSeries as $row):
            $wk = $row['week_date'];
            $app = (int)($row['applicable'] ?? 0); $er = (int)($row['errors'] ?? 0);
            $pct = $app>0 ? round(($er/$app)*100,2) : 0;
            $w = min(100, max(0, $pct));
          ?>
            <div class="wk-row">
              <span class="pill"><?= h($wk) ?></span>
              <div class="wk-bar"><i style="width:<?= $w ?>%"></i></div>
              <span class="pill"><?= number_format($pct,2) ?>%</span>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="muted">Sin datos todavía.</div>
        <?php endif; ?>
      </div>

      <!-- Top ítems por desvío (última semana) -->
      <div class="card">
        <div class="k-title"><i class="fa-solid fa-list-check"></i> Top desvío por ítem (semana <?= h($lastWeek ?? '-') ?>)</div>
        <div class="row-list">
          <?php if ($topItems): foreach ($topItems as $ti):
            $app=(int)$ti['applicable']; $er=(int)$ti['errors'];
            $pct=(float)$ti['deviation_pct']; $w=min(100,max(0,$pct));
          ?>
            <div class="rl">
              <div class="small">#<?= (int)$ti['item_order'] ?> · <?= h($ti['question']) ?></div>
              <div class="small right"><?= $er ?> / <?= $app ?></div>
              <div class="small right"><?= number_format($pct,2) ?>%</div>
              <div class="wk-bar" style="grid-column:1 / -1"><i style="width:<?= $w ?>%"></i></div>
            </div>
          <?php endforeach; else: ?>
            <div class="muted">Sin datos para la última semana.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top responsables por desvío (última semana) -->
      <div class="card">
        <div class="k-title"><i class="fa-solid fa-user-gear"></i> Top desvío por responsable (semana <?= h($lastWeek ?? '-') ?>)</div>
        <div class="row-list">
          <?php if ($topResp): foreach ($topResp as $tr):
            $app=(int)$tr['applicable']; $er=(int)$tr['errors'];
            $pct=(float)$tr['deviation_pct']; $w=min(100,max(0,$pct));
          ?>
            <div class="rl">
              <div class="small"><?= h($tr['responsable']) ?></div>
              <div class="small right"><?= $er ?> / <?= $app ?></div>
              <div class="small right"><?= number_format($pct,2) ?>%</div>
              <div class="wk-bar" style="grid-column:1 / -1"><i style="width:<?= $w ?>%"></i></div>
            </div>
          <?php endforeach; else: ?>
            <div class="muted">Sin datos para la última semana.</div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <?php if (!$weeks): ?>
      <p class="muted">Sin auditorías registradas todavía. Empezá creando una desde <a href="create.php">aquí</a>.</p>
    <?php endif; ?>

    <!-- ===== Listado por semanas (igual que antes, con tu UI) ===== -->
    <?php
    $wkAggStmt; $wkAuditsStmt; $wkItemsStmt;
    foreach ($weeks as $w):
      $week = $w['week_date'];
      $wkAggStmt->execute([$week]);
      $agg = $wkAggStmt->fetch();
      $errors = (int)($agg['errors'] ?? 0);
      $applic = (int)($agg['applicable'] ?? 0);
      $nas    = (int)($agg['nas'] ?? 0);
      $audits = (int)($agg['audits'] ?? 0);
      $wk_pct = $applic > 0 ? round(($errors / $applic) * 100, 2) : 0.00;

      $wkAuditsStmt->execute([$week]);
      $audRows = $wkAuditsStmt->fetchAll();

      $wkItemsStmt->execute([$week]);
      $itemRows = $wkItemsStmt->fetchAll();
    ?>
    <section class="week">
      <div class="week-head" onclick="toggleWeek(this)">
        <div style="font-weight:700">Semana: <?= h($week) ?></div>
        <span class="badge">Auditorías: <b><?= $audits ?></b></span>
        <span class="badge">Aplicables: <b><?= $applic ?></b></span>
        <span class="badge">N: <b><?= $nas ?></b></span>
        <span class="badge">Errores: <b><?= $errors ?></b></span>
        <span class="pct"><?= number_format($wk_pct,2) ?>%</span>
        <div class="chev">▶</div>
      </div>

      <div class="week-body">
        <div class="grid">
          <!-- Tabla de auditorías -->
          <div>
            <table>
              <thead>
                <tr>
                  <th style="width:70px"># OR</th>
                  <th>Tipo</th>
                  <th class="right" style="width:120px">% Error</th>
                  <th class="right" style="width:120px">Errores / Aplic.</th>
                  <th style="width:80px"></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($audRows as $ar):
                  $ap = (int)($ar['applicable'] ?? 0);
                  $er = (int)($ar['errors_count'] ?? 0);
                  $pp = number_format((float)$ar['error_percentage'], 2);
                ?>
                <tr>
                  <td class="num">#<?= h($ar['order_number']) ?></td>
                  <td><span class="pill"><?= h($ar['order_type']) ?></span></td>
                  <td class="right"><b><?= $pp ?>%</b></td>
                  <td class="right" title="Errores / Aplicables"><?= $er ?> / <?= $ap ?></td>
                  <td class="right"><a href="view_audit.php?id=<?= (int)$ar['audit_id'] ?>">ver</a></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <!-- Desvío por ítem (semana) -->
          <div class="item-list">
            <table>
              <thead>
                <tr>
                  <th style="width:50px">#</th>
                  <th>Ítem</th>
                  <th class="right" style="width:90px">% Desvío</th>
                  <th class="right" style="width:120px">Errores / Aplic.</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($itemRows as $ir):
                  $iap = (int)($ir['applicable'] ?? 0);
                  $ier = (int)($ir['errors'] ?? 0);
                  $ip  = number_format((float)$ir['deviation_pct'], 2);
                ?>
                <tr>
                  <td class="num"><?= (int)$ir['item_order'] ?></td>
                  <td class="small"><?= h($ir['question']) ?></td>
                  <td class="right"><span class="pct"><?= $ip ?>%</span></td>
                  <td class="right"><?= $ier ?> / <?= $iap ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p class="small muted">“% desvío por ítem” = errores / aplicables (excluye N).</p>
          </div>
        </div>
      </div>
    </section>
    <?php endforeach; ?>
  </div>

  <script>
    function toggleWeek(head){
      const week = head.parentElement;
      const body = week.querySelector('.week-body');
      const icon = head.querySelector('.chev');
      document.querySelectorAll('.week .week-body').forEach(b=>{ if (b!==body) b.style.display='none'; });
      document.querySelectorAll('.week .chev').forEach(c=>{ if (c!==icon) c.classList.remove('open'); });
      const isOpen = body.style.display === 'block';
      body.style.display = isOpen ? 'none' : 'block';
      icon.classList.toggle('open', !isOpen);
    }
    const firstHead = document.querySelector('.week .week-head');
    if (firstHead) toggleWeek(firstHead);
  </script>

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</body>
</html>
