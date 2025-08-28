<?php
// stats.php — Estadísticas por ítem, por responsable y por semana (Ordenes)
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ===== Filtros =====
$from = isset($_GET['from']) ? trim($_GET['from']) : '';   // YYYY-MM-DD
$to   = isset($_GET['to'])   ? trim($_GET['to'])   : '';   // YYYY-MM-DD
$type = isset($_GET['type']) ? trim($_GET['type']) : '';   // cliente|garantia|interna|''
$q    = isset($_GET['q'])    ? trim($_GET['q'])    : '';   // búsqueda ítem/responsable

$typesAllowed = ['','cliente','garantia','interna'];
if (!in_array($type, $typesAllowed, true)) $type = '';

// Normalizo rango (opcionales)
$hasFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from);
$hasTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to);

// =========================
// Construcción de WHERE base
// =========================
$where = [];
$params = [];

if ($hasFrom) { $where[] = 'o.week_date >= :from'; $params[':from'] = $from; }
if ($hasTo)   { $where[] = 'o.week_date <= :to';   $params[':to']   = $to;   }
if ($type)    { $where[] = 'o.order_type = :t';    $params[':t']    = $type; }

$WHERE = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ========= Estadística por Ítem =========
// - Errores (value='1')
// - Aplicables (OK o '1')
// - % desvío (errores/aplicables)
$sqlItems = "
  SELECT
    ci.item_order,
    COALESCE(ai.question_text, ci.question) AS question,
    SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) AS errors,
    SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END) AS applicable,
    ROUND(100 * SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) /
          NULLIF(SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END), 0), 2) AS deviation_pct
  FROM audit_answers ai
  JOIN audits a ON a.id = ai.audit_id
  JOIN orders o ON o.id = a.order_id
  LEFT JOIN checklist_items ci ON ci.id = ai.item_id
  $WHERE
  GROUP BY ci.item_order, question
  ORDER BY ci.item_order ASC
";
$stItems = $pdo->prepare($sqlItems);
$stItems->execute($params);
$rowsItems = $stItems->fetchAll(PDO::FETCH_ASSOC);

// Filtro de búsqueda aplicado a ítems (en memoria para simplicidad)
if ($q !== '') {
  $rowsItems = array_values(array_filter($rowsItems, function($r) use ($q){
    return stripos((string)$r['question'], $q) !== false;
  }));
}

// ========= Estadística por Responsable =========
$sqlResp = "
  SELECT
    TRIM(ai.responsable_text) AS responsable,
    SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) AS errors,
    SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END) AS applicable,
    ROUND(100 * SUM(CASE WHEN ai.value = '1' THEN 1 ELSE 0 END) /
          NULLIF(SUM(CASE WHEN ai.value IN ('OK','1') THEN 1 ELSE 0 END), 0), 2) AS deviation_pct
  FROM audit_answers ai
  JOIN audits a ON a.id = ai.audit_id
  JOIN orders o ON o.id = a.order_id
  $WHERE
  GROUP BY responsable
  HAVING responsable IS NOT NULL AND responsable <> ''
  ORDER BY errors DESC, applicable DESC
";
$stResp = $pdo->prepare($sqlResp);
$stResp->execute($params);
$rowsResp = $stResp->fetchAll(PDO::FETCH_ASSOC);

// Búsqueda aplicada a responsables (en memoria)
if ($q !== '') {
  $rowsResp = array_values(array_filter($rowsResp, function($r) use ($q){
    return stripos((string)$r['responsable'], $q) !== false;
  }));
}

// ========= Estadística por Semana =========
$sqlWeeks = "
  SELECT
    o.week_date,
    SUM(a.errors_count) AS errors,
    SUM(a.total_items - a.not_applicable_count) AS applicable,
    SUM(a.not_applicable_count) AS nas,
    COUNT(*) AS audits,
    ROUND(100 * SUM(a.errors_count) / NULLIF(SUM(a.total_items - a.not_applicable_count),0), 2) AS deviation_pct
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  $WHERE
  GROUP BY o.week_date
  ORDER BY o.week_date DESC
";
$stWeeks = $pdo->prepare($sqlWeeks);
$stWeeks->execute($params);
$rowsWeeks = $stWeeks->fetchAll(PDO::FETCH_ASSOC);

// Utilidades pequeñas
function pct_class($p){
  if ($p === null) return '';
  $p = (float)$p;
  if ($p >= 20) return 'bad';      // rojo
  if ($p >= 10) return 'warn';     // amarillo
  return 'ok';                     // verde
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Estadísticas · Organización Sur</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Font Awesome -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <style>
    :root{
      --bg:#0a0f18; --bg2:#0b121d; --surface:#101826; --surface2:#0f1725; --border:#1b2535;
      --text:#e7ecf3; --muted:#9fb0c9; --accent:#00A3E0; --accent2:#00b4ff;
      --ok:#2ea26d; --warn:#e0a800; --bad:#d35b5b; --chip:#0c1420; --radius:14px;
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
    a.btn{text-decoration:none!important}

    .bar{position:sticky; top:0; z-index:10; background:rgba(10,15,24,.7); backdrop-filter:blur(8px); border-bottom:1px solid var(--border)}
    .bar-inner{width:min(1200px,94vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .brand img{height:60px; width:auto; display:block}
    .spacer{flex:1}
    .btn{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); color:#dbe7ff; background:transparent; padding:8px 12px; border-radius:10px}
    .btn:hover{border-color:#2a3a55}
    .btn-primary{background:linear-gradient(180deg,var(--accent),var(--accent2)); color:#fff; border:0}

    .wrap{width:min(1200px,94vw); margin:20px auto 40px}
    .card{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
          border:1px solid var(--border); border-radius:var(--radius); padding:14px; margin:14px 0}

    .filters{display:grid; grid-template-columns: 1fr 1fr 1fr auto; gap:10px; align-items:end}
    @media(max-width:900px){ .filters{grid-template-columns: 1fr 1fr } }
    label{display:block; margin:0 0 6px; color:#d5deea; font-size:14px}
    input[type=date], input[type=text], select{
      width:100%; padding:10px 12px; border-radius:12px; border:1px solid var(--border);
      background:var(--surface2); color:var(--text); outline:none
    }
    input:focus, select:focus{ border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,163,224,.18) }

    .grid{display:grid; gap:10px}
    @media(min-width:1000px){ .grid-3{grid-template-columns: 1fr 1fr 1fr} }

    table{width:100%; border-collapse:separate; border-spacing:0 8px}
    thead th{font-size:12px; color:#cbd5e1; text-transform:uppercase; letter-spacing:.08em; padding:0 10px}
    tbody tr{background:var(--surface); border:1px solid var(--border); border-radius:10px; overflow:hidden}
    tbody td{padding:10px; vertical-align:middle}
    .right{text-align:right}
    .num{color:#cbd5e1; font-weight:700}
    .pill{padding:4px 8px; border:1px solid var(--border); border-radius:999px; font-size:12px; background:var(--chip); color:#d1d5db; white-space:nowrap}

    /* semáforo % */
    .pct.ok{color:#b7f4d2}
    .pct.warn{color:#ffe38a}
    .pct.bad{color:#ffc7c7}

    /* barrita horizontal */
    .barwrap{height:8px; background:#0c1420; border:1px solid var(--border); border-radius:999px; overflow:hidden}
    .barfill{height:100%; background:linear-gradient(90deg,var(--accent),var(--accent2)); width:0%}

  </style>
</head>
<body>
  <div class="bar">
    <div class="bar-inner">
      <div class="brand">
        <img src="../assets/logo.png" alt="Logo Organización Sur">
      </div>
      <div class="spacer"></div>
      <a class="btn" href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
      <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Nueva orden</a>
    </div>
  </div>

  <div class="wrap">
    <!-- Filtros -->
    <form class="card" method="get" action="">
      <div class="filters">
        <div>
          <label>Desde (semana)</label>
          <input type="date" name="from" value="<?= h($from) ?>">
        </div>
        <div>
          <label>Hasta (semana)</label>
          <input type="date" name="to" value="<?= h($to) ?>">
        </div>
        <div>
          <label>Tipo</label>
          <select name="type">
            <option value="" <?= $type===''?'selected':'' ?>>Todos</option>
            <option value="cliente"  <?= $type==='cliente'?'selected':'' ?>>Cliente</option>
            <option value="garantia" <?= $type==='garantia'?'selected':'' ?>>Garantía</option>
            <option value="interna"  <?= $type==='interna'?'selected':'' ?>>Interna</option>
          </select>
        </div>
        <div style="grid-column:1/-1; display:flex; gap:8px; margin-top:6px">
          <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Filtrar</button>
          <a class="btn" href="stats.php"><i class="fa-solid fa-rotate"></i> Limpiar</a>
        </div>
      </div>
    </form>

    <!-- Grillas -->
    <div class="grid grid-3">
      <!-- Por Ítem -->
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px">
          <div style="font-weight:800"><i class="fa-solid fa-list-check"></i> Por ítem</div>
          <span class="pill">Ítems: <b><?= count($rowsItems) ?></b></span>
        </div>
        <div style="overflow:auto">
          <table>
            <thead>
              <tr>
                <th style="width:52px">#</th>
                <th>Ítem</th>
                <th class="right" style="width:100px">% Desvío</th>
                <th class="right" style="width:130px">Err / Ap.</th>
                <th style="width:160px">Tendencia</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rowsItems): foreach ($rowsItems as $r):
                $ap = (int)($r['applicable'] ?? 0);
                $er = (int)($r['errors'] ?? 0);
                $pp = (float)($r['deviation_pct'] ?? 0);
                $cls = pct_class($pp);
              ?>
                <tr>
                  <td class="num"><?= (int)$r['item_order'] ?></td>
                  <td class="muted"><?= h($r['question']) ?></td>
                  <td class="right pct <?= $cls ?>"><?= number_format($pp,2) ?>%</td>
                  <td class="right"><?= $er ?> / <?= $ap ?></td>
                  <td>
                    <div class="barwrap"><div class="barfill" style="width:<?= max(0,min(100,$pp)) ?>%"></div></div>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="5" class="muted">Sin datos para este filtro.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
        <p class="muted" style="font-size:12px">“% desvío por ítem” = errores / aplicables (excluye N).</p>
      </div>

      <!-- Por Responsable -->
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px">
          <div style="font-weight:800"><i class="fa-solid fa-user-shield"></i> Por responsable</div>
          <span class="pill">Responsables: <b><?= count($rowsResp) ?></b></span>
        </div>
        <div style="overflow:auto">
          <table>
            <thead>
              <tr>
                <th>Responsable</th>
                <th class="right" style="width:100px">% Desvío</th>
                <th class="right" style="width:140px">Err / Ap.</th>
                <th style="width:160px">Tendencia</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rowsResp): foreach ($rowsResp as $r):
                $ap = (int)($r['applicable'] ?? 0);
                $er = (int)($r['errors'] ?? 0);
                $pp = (float)($r['deviation_pct'] ?? 0);
                $cls = pct_class($pp);
              ?>
                <tr>
                  <td><?= h($r['responsable']) ?></td>
                  <td class="right pct <?= $cls ?>"><?= number_format($pp,2) ?>%</td>
                  <td class="right"><?= $er ?> / <?= $ap ?></td>
                  <td>
                    <div class="barwrap"><div class="barfill" style="width:<?= max(0,min(100,$pp)) ?>%"></div></div>
                  </td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="muted">Sin datos para este filtro.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Por Semana -->
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px">
          <div style="font-weight:800"><i class="fa-regular fa-calendar"></i> Por semana</div>
          <span class="pill">Semanas: <b><?= count($rowsWeeks) ?></b></span>
        </div>
        <div style="overflow:auto">
          <table>
            <thead>
              <tr>
                <th>Semana</th>
                <th class="right" style="width:100px">% Error</th>
                <th class="right" style="width:140px">Err / Ap.</th>
                <th class="right" style="width:90px">Ordenes</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($rowsWeeks): foreach ($rowsWeeks as $r):
                $ap = (int)($r['applicable'] ?? 0);
                $er = (int)($r['errors'] ?? 0);
                $pp = (float)($r['deviation_pct'] ?? 0);
                $aud = (int)($r['audits'] ?? 0);
                $cls = pct_class($pp);
              ?>
                <tr>
                  <td><?= h($r['week_date']) ?></td>
                  <td class="right pct <?= $cls ?>"><?= number_format($pp,2) ?>%</td>
                  <td class="right"><?= $er ?> / <?= $ap ?></td>
                  <td class="right"><?= $aud ?></td>
                </tr>
              <?php endforeach; else: ?>
                <tr><td colspan="4" class="muted">Sin datos para este filtro.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div><!-- grid -->

  </div><!-- wrap -->

  <script>
    // nada especial aquí; las barritas usan width inline
  </script>
</body>
</html>
