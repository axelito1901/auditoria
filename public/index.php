<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Filtros =====
$fWeek = isset($_GET['week']) ? trim($_GET['week']) : '';
$fType = isset($_GET['type']) ? trim($_GET['type']) : '';
$q     = isset($_GET['q'])    ? trim($_GET['q'])    : '';

$typesAllowed = ['','cliente','garantia','interna'];
if (!in_array($fType, $typesAllowed, true)) $fType = '';

// ===== Semanas disponibles =====
$allWeeks = $pdo->query("
  SELECT o.week_date, COUNT(a.id) AS audits
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date IS NOT NULL
  GROUP BY o.week_date
  ORDER BY o.week_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Si hay filtro de semana, solo esa; si no, todas
$weeks = [];
if ($fWeek) {
  foreach ($allWeeks as $w) if ($w['week_date'] === $fWeek) { $weeks[] = $w; break; }
} else {
  $weeks = $allWeeks;
}

// ===== Statements =====
$wkAggBase = "
  SELECT
    SUM(a.errors_count) AS errors,
    SUM(a.total_items - a.not_applicable_count) AS applicable,
    SUM(a.not_applicable_count) AS nas,
    COUNT(*) AS audits
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date = :w
";
$wkAggType = $fType ? " AND o.order_type = :t " : "";
$wkAgg = $pdo->prepare($wkAggBase . $wkAggType);

$wkAudBase = "
  SELECT a.id AS audit_id, a.errors_count, (a.total_items - a.not_applicable_count) AS applicable,
         a.error_percentage, a.audited_at,
         o.order_number, o.order_type
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date = :w
";
$wkAudType = $fType ? " AND o.order_type = :t " : "";
$wkAudQ    = $q     ? " AND o.order_number LIKE :q " : "";
$wkAud = $pdo->prepare($wkAudBase . $wkAudType . $wkAudQ . " ORDER BY a.audited_at DESC, a.id DESC");

$wkItemsBase = "
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
  WHERE o.week_date = :w
";
$wkItemsType = $fType ? " AND o.order_type = :t " : "";
$wkItems = $pdo->prepare($wkItemsBase . $wkItemsType . " GROUP BY ci.item_order, question ORDER BY ci.item_order ASC");

?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Organización Sur · Auditorías</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    :root{
      --bg:#0a0f18; --bg2:#0b121d; --surface:#101826; --surface2:#0f1725; --border:#1b2535;
      --text:#e7ecf3; --muted:#9fb0c9; --accent:#00A3E0; --accent2:#00b4ff;
      --ok-b:#2ea26d; --err-b:#d35b5b; --na-b:#5aa1ff; --radius:14px; --chip:#0c1420;
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

    /* Appbar */
    .bar{position:sticky; top:0; z-index:10; background:rgba(10,15,24,.7); backdrop-filter:blur(8px); border-bottom:1px solid var(--border)}
    .bar-inner{width:min(1200px,94vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .brand img{height:60px; width:auto; display:block}
    .spacer{flex:1}
    .btn{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); color:#dbe7ff; background:transparent; padding:8px 12px; border-radius:10px}
    .btn:hover{border-color:#2a3a55}
    .btn-primary{background:linear-gradient(180deg,var(--accent),var(--accent2)); color:#fff; border:0}

    .wrap{width:min(1200px,94vw); margin:20px auto 40px}

    /* Filtros */
    .card{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
          border:1px solid var(--border); border-radius:var(--radius); padding:14px; margin:14px 0}
    .filters{display:grid; grid-template-columns: 1.2fr 1fr 1fr auto; gap:10px; align-items:end}
    @media(max-width:900px){ .filters{grid-template-columns: 1fr 1fr; } }
    label{display:block; margin:0 0 6px; color:#d5deea; font-size:14px}
    input[type=text], select{
      width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--border);
      background:var(--surface2); color:var(--text); outline:none
    }
    input[type=text]:focus, select:focus{ border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,163,224,.18) }
    .muted{color:var(--muted)}
    .pill{padding:4px 8px; border:1px solid var(--border); border-radius:999px; font-size:12px; background:var(--chip); color:#d1d5db; white-space:nowrap}

    /* Semana */
    .week{border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; margin:14px 0}
    .week-head{display:flex; align-items:center; gap:10px; padding:12px 14px; background:rgba(255,255,255,.02); cursor:pointer}
    .week-head:hover{background:#0f1725}
    .chev{margin-left:auto; font-size:18px; color:#cbd5e1; transition:transform .15s ease}
    .chev.open{transform:rotate(90deg)}
    .week-body{display:none; padding:12px 14px}

    .grid{display:grid; gap:10px}
    @media(min-width:900px){ .grid{grid-template-columns: 1.2fr .8fr} }

    table{width:100%; border-collapse:separate; border-spacing:0 8px}
    thead th{font-size:12px; color:#cbd5e1; text-transform:uppercase; letter-spacing:.08em; padding:0 10px}
    tbody tr{background:var(--surface); border:1px solid var(--border); border-radius:10px; overflow:hidden}
    tbody td{padding:10px; vertical-align:middle}
    .num{color:#cbd5e1; font-weight:700}
    .right{text-align:right}

    /* Acciones */
    .actions{display:flex; gap:8px; flex-wrap:wrap; align-items:center}
  </style>
</head>
<body>
  <div class="bar">
    <div class="bar-inner">
      <div class="brand">
        <img src="../assets/logo.png" alt="Logo Organización Sur">
      </div>
      <div class="spacer"></div>
      <a class="btn" href="create.php"><i class="fa-solid fa-plus"></i> Nueva auditoría</a>
    </div>
  </div>

  <div class="wrap">
    <!-- Filtros -->
    <form class="card" method="get" action="">
      <div class="filters">
        <div>
          <label><i class="fa-solid fa-magnifying-glass"></i> Buscar por N° OR</label>
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Ej: 12345">
        </div>
        <div>
          <label><i class="fa-regular fa-calendar"></i> Semana</label>
          <select name="week">
            <option value="">Todas</option>
            <?php foreach ($allWeeks as $w): ?>
              <option value="<?= h($w['week_date']) ?>" <?= $fWeek===$w['week_date']?'selected':'' ?>>
                <?= h($w['week_date']) ?> (<?= (int)$w['audits'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label><i class="fa-solid fa-tags"></i> Tipo</label>
          <select name="type">
            <option value="" <?= $fType===''?'selected':'' ?>>Todos</option>
            <option value="cliente"  <?= $fType==='cliente'?'selected':'' ?>>Cliente</option>
            <option value="garantia" <?= $fType==='garantia'?'selected':'' ?>>Garantía</option>
            <option value="interna"  <?= $fType==='interna'?'selected':'' ?>>Interna</option>
          </select>
        </div>
        <div class="actions">
          <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
          <a class="btn" href="index.php"><i class="fa-solid fa-rotate"></i> Limpiar</a>
        </div>
      </div>
    </form>

    <?php if (!$weeks): ?>
      <p class="muted">No hay auditorías para los filtros seleccionados. <a href="index.php">Ver todo</a>.</p>
    <?php endif; ?>

    <!-- Controles globales -->
    <?php if ($weeks): ?>
      <div class="actions" style="margin:6px 0 12px">
        <button class="btn" type="button" onclick="expandAll(true)"><i class="fa-regular fa-square-plus"></i> Expandir todo</button>
        <button class="btn" type="button" onclick="expandAll(false)"><i class="fa-regular fa-square-minus"></i> Colapsar todo</button>
      </div>
    <?php endif; ?>

    <!-- Listado por semanas -->
    <?php
      foreach ($weeks as $w):
        $week = $w['week_date'];
        $p = [':w'=>$week];
        if ($fType) $p[':t'] = $fType;

        // Agregados
        $wkAgg->execute($p);
        $agg = $wkAgg->fetch(PDO::FETCH_ASSOC);
        $errors = (int)($agg['errors'] ?? 0);
        $applic = (int)($agg['applicable'] ?? 0);
        $nas    = (int)($agg['nas'] ?? 0);
        $audits = (int)($agg['audits'] ?? 0);
        $wk_pct = $applic > 0 ? round(($errors / $applic) * 100, 2) : 0.00;

        // Auditorías
        $pAud = $p;
        if ($q) $pAud[':q'] = '%'.$q.'%';
        $wkAud->execute($pAud);
        $audRows = $wkAud->fetchAll(PDO::FETCH_ASSOC);

        // Items
        $wkItems->execute($p);
        $itemRows = $wkItems->fetchAll(PDO::FETCH_ASSOC);
    ?>
      <section class="week">
        <div class="week-head" onclick="toggleWeek(this)">
          <div style="font-weight:800">Semana <?= h($week) ?></div>
          <span class="pill">Auditorías: <b><?= $audits ?></b></span>
          <span class="pill">Aplicables: <b><?= $applic ?></b></span>
          <span class="pill">N: <b><?= $nas ?></b></span>
          <span class="pill">Errores: <b><?= $errors ?></b></span>
          <span class="pill"><?= number_format($wk_pct,2) ?>%</span>
          <div class="chev">▶</div>
        </div>
        <div class="week-body">
          <div class="grid">
            <!-- Tabla de auditorías -->
            <div class="card" style="margin:0">
              <div style="font-weight:800; margin-bottom:6px"><i class="fa-regular fa-clipboard"></i> Auditorías (<?= count($audRows) ?>)</div>
              <div style="overflow:auto">
                <table>
                  <thead>
                    <tr>
                      <th style="width:90px"># OR</th>
                      <th>Tipo</th>
                      <th class="right" style="width:120px">% Error</th>
                      <th class="right" style="width:140px">Errores / Aplic.</th>
                      <th style="width:80px"></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($audRows): foreach ($audRows as $ar):
                      $ap = (int)($ar['applicable'] ?? 0);
                      $er = (int)($ar['errors_count'] ?? 0);
                      $pp = number_format((float)$ar['error_percentage'], 2);
                    ?>
                      <tr>
                        <td class="num"><?= h($ar['order_number']) ?></td>
                        <td><span class="pill"><?= h($ar['order_type']) ?></span></td>
                        <td class="right"><b><?= $pp ?>%</b></td>
                        <td class="right" title="Errores / Aplicables"><?= $er ?> / <?= $ap ?></td>
                        <td class="right"><a href="view_audit.php?id=<?= (int)$ar['audit_id'] ?>">ver</a></td>
                      </tr>
                    <?php endforeach; else: ?>
                      <tr><td colspan="5" class="muted">Sin auditorías para esta semana / filtros.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Desvío por ítem -->
            <div class="card" style="margin:0">
              <div style="font-weight:800; margin-bottom:6px"><i class="fa-solid fa-list-check"></i> Desvío por ítem</div>
              <div style="overflow:auto">
                <table>
                  <thead>
                    <tr>
                      <th style="width:50px">#</th>
                      <th>Ítem</th>
                      <th class="right" style="width:110px">% Desvío</th>
                      <th class="right" style="width:140px">Errores / Aplic.</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($itemRows): foreach ($itemRows as $ir):
                      $iap = (int)($ir['applicable'] ?? 0);
                      $ier = (int)($ir['errors'] ?? 0);
                      $ip  = number_format((float)$ir['deviation_pct'], 2);
                    ?>
                      <tr>
                        <td class="num"><?= (int)$ir['item_order'] ?></td>
                        <td class="muted"><?= h($ir['question']) ?></td>
                        <td class="right"><span class="pill"><?= $ip ?>%</span></td>
                        <td class="right"><?= $ier ?> / <?= $iap ?></td>
                      </tr>
                    <?php endforeach; else: ?>
                      <tr><td colspan="4" class="muted">Sin datos para esta semana.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
              <p class="muted" style="font-size:12px">“% desvío por ítem” = errores / aplicables (excluye N).</p>
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
      const isOpen = body.style.display === 'block';
      body.style.display = isOpen ? 'none' : 'block';
      icon.classList.toggle('open', !isOpen);
    }
    function expandAll(open){
      document.querySelectorAll('.week .week-body').forEach(b => b.style.display = open ? 'block' : 'none');
      document.querySelectorAll('.week .chev').forEach(c => c.classList.toggle('open', open));
    }
    // Abrir primera semana por defecto (si no hay filtro de semana)
    <?php if (!$fWeek): ?>
      const firstHead = document.querySelector('.week .week-head');
      if (firstHead){ toggleWeek(firstHead); }
    <?php endif; ?>
  </script>
</body>
</html>
