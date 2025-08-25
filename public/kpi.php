<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ==== Filtros ====
$allWeeks = $pdo->query("
  SELECT DISTINCT o.week_date AS wk
  FROM orders o
  WHERE o.week_date IS NOT NULL
  ORDER BY wk DESC
")->fetchAll(PDO::FETCH_COLUMN);

$defaultFrom = $allWeeks[min(11, count($allWeeks)-1)] ?? null; // ~12 semanas atrás
$defaultTo   = $allWeeks[0] ?? null;

$week_from = $_GET['week_from'] ?? $defaultFrom;
$week_to   = $_GET['week_to']   ?? $defaultTo;
$type      = $_GET['type']      ?? 'all'; // all|cliente|garantia|interna

// Normalizar
if (!$week_from) $week_from = $defaultFrom;
if (!$week_to)   $week_to   = $defaultTo;

// WHERE dinámico
$where = "o.week_date BETWEEN :from AND :to";
$params = [':from'=>$week_from, ':to'=>$week_to];
if (in_array($type, ['cliente','garantia','interna'], true)){
  $where .= " AND o.order_type = :type";
  $params[':type'] = $type;
}

// ==== Exportaciones CSV ====
if (isset($_GET['export'])) {
  $exp = $_GET['export'];

  // Export semanal
  if ($exp === 'weekly') {
    $stmt = $pdo->prepare("
      SELECT o.week_date,
             SUM(a.errors_count) AS errors,
             SUM(a.total_items - a.not_applicable_count) AS applicable,
             SUM(a.not_applicable_count) AS nas,
             COUNT(*) AS audits
      FROM audits a
      JOIN orders o ON o.id = a.order_id
      WHERE $where
      GROUP BY o.week_date
      ORDER BY o.week_date ASC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=kpi_semanal.csv");
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Semana','Errores','Aplicables','No aplica','Auditorías','% Error']);
    foreach ($rows as $r){
      $app=(int)$r['applicable']; $er=(int)$r['errors'];
      $pct=$app>0?round(($er/$app)*100,2):0;
      fputcsv($out, [$r['week_date'],$er,$app,(int)$r['nas'],(int)$r['audits'],$pct]);
    }
    exit;
  }

  // Export ítems
  if ($exp === 'items') {
    $stmt = $pdo->prepare("
      SELECT ci.item_order, COALESCE(ai.question_text,ci.question) AS question,
             SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END) AS errors,
             SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END) AS applicable,
             100.0*SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END)/NULLIF(SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END),0) AS deviation_pct
      FROM audit_answers ai
      JOIN audits a ON a.id=ai.audit_id
      JOIN orders o ON o.id=a.order_id
      JOIN checklist_items ci ON ci.id=ai.item_id
      WHERE $where
      GROUP BY ci.item_order,question
      HAVING applicable>0
      ORDER BY deviation_pct DESC
    ");
    $stmt->execute($params);
    $rows=$stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=kpi_items.csv");
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');
    fputcsv($out,['#','Ítem','Errores','Aplicables','% Desvío']);
    foreach($rows as $r){
      fputcsv($out,[(int)$r['item_order'],(string)$r['question'],(int)$r['errors'],(int)$r['applicable'],round((float)$r['deviation_pct'],2)]);
    }
    exit;
  }

  // Export responsables
  if ($exp === 'responsables') {
    $stmt = $pdo->prepare("
      SELECT COALESCE(ai.responsable_text,'Sin responsable') AS responsable,
             SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END) AS errors,
             SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END) AS applicable,
             100.0*SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END)/NULLIF(SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END),0) AS deviation_pct
      FROM audit_answers ai
      JOIN audits a ON a.id=ai.audit_id
      JOIN orders o ON o.id=a.order_id
      WHERE $where
      GROUP BY responsable
      HAVING applicable>0
      ORDER BY deviation_pct DESC
    ");
    $stmt->execute($params);
    $rows=$stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=kpi_responsables.csv");
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','w');
    fputcsv($out,['Responsable','Errores','Aplicables','% Desvío']);
    foreach($rows as $r){
      fputcsv($out,[(string)$r['responsable'],(int)$r['errors'],(int)$r['applicable'],round((float)$r['deviation_pct'],2)]);
    }
    exit;
  }
}

// ==== Consultas ====
$weekly=$pdo->prepare("
  SELECT o.week_date,
         SUM(a.errors_count) AS errors,
         SUM(a.total_items - a.not_applicable_count) AS applicable,
         COUNT(*) AS audits
  FROM audits a
  JOIN orders o ON o.id=a.order_id
  WHERE $where
  GROUP BY o.week_date
  ORDER BY o.week_date ASC
");
$weekly->execute($params);
$weeklyRows=$weekly->fetchAll();

$items=$pdo->prepare("
  SELECT ci.item_order, COALESCE(ai.question_text,ci.question) AS question,
         SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END) AS errors,
         SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END) AS applicable,
         100.0*SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END)/NULLIF(SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END),0) AS deviation_pct
  FROM audit_answers ai
  JOIN audits a ON a.id=ai.audit_id
  JOIN orders o ON o.id=a.order_id
  JOIN checklist_items ci ON ci.id=ai.item_id
  WHERE $where
  GROUP BY ci.item_order,question
  HAVING applicable>0
  ORDER BY deviation_pct DESC
  LIMIT 15
");
$items->execute($params);
$itemsRows=$items->fetchAll();

$resps=$pdo->prepare("
  SELECT COALESCE(ai.responsable_text,'Sin responsable') AS responsable,
         SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END) AS errors,
         SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END) AS applicable,
         100.0*SUM(CASE WHEN ai.value='1' THEN 1 ELSE 0 END)/NULLIF(SUM(CASE WHEN ai.value IN('OK','1') THEN 1 ELSE 0 END),0) AS deviation_pct
  FROM audit_answers ai
  JOIN audits a ON a.id=ai.audit_id
  JOIN orders o ON o.id=a.order_id
  WHERE $where
  GROUP BY responsable
  HAVING applicable>0
  ORDER BY deviation_pct DESC
  LIMIT 15
");
$resps->execute($params);
$respsRows=$resps->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>KPIs · Organización Sur</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
body{background:#0a0f18;color:#e7ecf3;font-family:sans-serif;margin:0}
.bar{padding:12px;background:#0b121d;display:flex;align-items:center;gap:10px;position:sticky;top:0}
.bar a{color:#cfe3ff;text-decoration:none;margin-left:auto}
.wrap{padding:20px;max-width:1200px;margin:auto}
.card{background:#101826;padding:14px;border-radius:12px;margin:10px 0}
h2{margin:0 0 10px}
table{width:100%;border-collapse:collapse}
th,td{padding:6px 8px;border-bottom:1px solid #1b2535}
th{text-align:left;color:#9fb0c9}
.btns{margin:10px 0;display:flex;gap:10px;flex-wrap:wrap}
.btn{padding:8px 12px;border:1px solid #1b2535;border-radius:8px;background:#0f1725;color:#cfe3ff;text-decoration:none}
</style>
</head>
<body>
<div class="bar">
  <div><b>KPIs</b> · Organización Sur</div>
  <a href="index.php">Volver</a>
</div>
<div class="wrap">

<form method="get">
  Desde: <select name="week_from"><?php foreach($allWeeks as $w): ?>
    <option value="<?=h($w)?>" <?=$w==$week_from?'selected':''?>><?=h($w)?></option>
  <?php endforeach;?></select>
  Hasta: <select name="week_to"><?php foreach($allWeeks as $w): ?>
    <option value="<?=h($w)?>" <?=$w==$week_to?'selected':''?>><?=h($w)?></option>
  <?php endforeach;?></select>
  Tipo:
  <select name="type">
    <option value="all" <?=$type==='all'?'selected':''?>>Todos</option>
    <option value="cliente" <?=$type==='cliente'?'selected':''?>>Cliente</option>
    <option value="garantia" <?=$type==='garantia'?'selected':''?>>Garantía</option>
    <option value="interna" <?=$type==='interna'?'selected':''?>>Interna</option>
  </select>
  <button type="submit">Filtrar</button>
</form>

<div class="btns">
  <a class="btn" href="?week_from=<?=$week_from?>&week_to=<?=$week_to?>&type=<?=$type?>&export=weekly">Exportar Semanal CSV</a>
  <a class="btn" href="?week_from=<?=$week_from?>&week_to=<?=$week_to?>&type=<?=$type?>&export=items">Exportar Ítems CSV</a>
  <a class="btn" href="?week_from=<?=$week_from?>&week_to=<?=$week_to?>&type=<?=$type?>&export=responsables">Exportar Responsables CSV</a>
  <button class="btn" onclick="window.print();return false;">Imprimir</button>
</div>

<div class="card">
  <h2>Errores por Semana</h2>
  <table>
    <tr><th>Semana</th><th>Errores</th><th>Aplicables</th><th>% Error</th><th>Auditorías</th></tr>
    <?php foreach($weeklyRows as $r): $app=(int)$r['applicable'];$er=(int)$r['errors'];$pct=$app>0?round(($er/$app)*100,2):0;?>
      <tr><td><?=h($r['week_date'])?></td><td><?=$er?></td><td><?=$app?></td><td><?=$pct?>%</td><td><?=$r['audits']?></td></tr>
    <?php endforeach;?>
  </table>
</div>

<div class="card">
  <h2>Top Ítems con Desvío</h2>
  <table>
    <tr><th>#</th><th>Ítem</th><th>Errores</th><th>Aplicables</th><th>% Desvío</th></tr>
    <?php foreach($itemsRows as $r):?>
      <tr><td><?=$r['item_order']?></td><td><?=h($r['question'])?></td><td><?=$r['errors']?></td><td><?=$r['applicable']?></td><td><?=round($r['deviation_pct'],2)?>%</td></tr>
    <?php endforeach;?>
  </table>
</div>

<div class="card">
  <h2>Top Responsables con Desvío</h2>
  <table>
    <tr><th>Responsable</th><th>Errores</th><th>Aplicables</th><th>% Desvío</th></tr>
    <?php foreach($respsRows as $r):?>
      <tr><td><?=h($r['responsable'])?></td><td><?=$r['errors']?></td><td><?=$r['applicable']?></td><td><?=round($r['deviation_pct'],2)?>%</td></tr>
    <?php endforeach;?>
  </table>
</div>

</div>
</body>
</html>
