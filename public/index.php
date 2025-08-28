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

// ===== Auditorias disponibles (fechas) =====
$allWeeks = $pdo->query("
  SELECT o.week_date, COUNT(a.id) AS audits
  FROM audits a
  JOIN orders o ON o.id = a.order_id
  WHERE o.week_date IS NOT NULL
  GROUP BY o.week_date
  ORDER BY o.week_date DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Si hay filtro de auditoria (fecha), solo esa; si no, todas
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
         o.order_number, o.order_type, o.id AS order_id
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
  <title>Organización Sur · Ordenes</title>
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

    /* Appbar */
    .bar{position:sticky; top:0; z-index:10; background:rgba(10,15,24,.7); backdrop-filter:blur(8px); border-bottom:1px solid var(--border)}
    .bar-inner{width:min(1200px,94vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .brand img{height:60px; width:auto; display:block}
    .spacer{flex:1}
    .btn{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); color:#dbe7ff; background:transparent; padding:8px 12px; border-radius:10px}
    .btn:hover{border-color:#2a3a55}
    .btn-primary{background:linear-gradient(180deg,var(--accent),var(--accent2)); color:#fff; border:0}

    .wrap{width:min(1200px,94vw); margin:20px auto 40px}

    /* Tarjetas / filtros */
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

    /* Auditorias (grupo por fecha) */
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

    .actions{display:flex; gap:8px; flex-wrap:wrap; align-items:center}

    /* ===== Toast (GENÉRICO: top-right) ===== */
    #toast{
      position:fixed; top:16px; right:16px; z-index:9999;
      min-width:260px; max-width:90vw; padding:12px 14px; border-radius:12px;
      border:1px solid var(--border); background:#101826; color:#e7ecf3;
      box-shadow:0 10px 30px rgba(0,0,0,.45); display:none; font-weight:600;
    }
    #toast.success{ border-color: var(--ok-b); }
    #toast.error{ border-color: var(--err-b); }

    /* ===== NOTIFICACIÓN ESPECIAL (sólo “eliminado correctamente”) ===== */
    #notif{
      position:fixed; left:50%; transform:translateX(-50%);
      top:-140px; /* fuera de pantalla al inicio */
      z-index:10000;
      min-width:320px; max-width:min(92vw,680px);
      background:#0b1322; /* sólido */
      border:1px solid #2a3a55; border-radius:14px;
      color:#e7ecf3; padding:14px 16px; text-align:center;
      box-shadow:0 20px 60px rgba(0,0,0,.6);
      font-weight:700; letter-spacing:.2px; opacity:0;
      transition: top .38s ease, opacity .38s ease;
    }
    #notif.show{ top:28px; opacity:1; }
    #notif .ok{ color:#88e2b3; margin-right:6px }

    /* ===== Modal de confirmación custom ===== */
    .modal-backdrop{
      position:fixed; inset:0;
      background:rgba(0,0,0,.75); /* más oscuro */
      display:none; align-items:center; justify-content:center; z-index:9998;
      backdrop-filter:saturate(110%) blur(2px);
    }
    .modal{
      width:min(520px, 94vw);
      border-radius:16px;
      border:1px solid #2a3a55;
      background:#0c1627; /* SÓLIDO */
      box-shadow:0 24px 80px rgba(0,0,0,.65);
      padding:20px;
    }
    .modal h3{margin:0 0 8px}
    .modal p{margin:0 0 14px; color:#cbd5e1}
    .modal .modal-actions{display:flex; gap:10px; justify-content:flex-end; flex-wrap:wrap}
    .btn-danger{background:linear-gradient(180deg,#e64b4b,#c63d3d); color:#fff; border:0}
    .btn-ghost{background:#101826; color:#dbe7ff; border:1px solid #2a3a55}
  </style>
</head>
<body>
  <div class="bar">
    <div class="bar-inner">
      <div class="brand">
        <img src="../assets/logo.png" alt="Logo Organización Sur">
      </div>
      <div class="spacer"></div>
      <a class="btn btn-primary" href="create.php"><i class="fa-solid fa-plus"></i> Nueva orden</a>
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
          <label><i class="fa-regular fa-calendar"></i> Auditorias</label>
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
      <p class="muted">No hay ordenes para los filtros seleccionados. <a href="index.php">Ver todo</a>.</p>
    <?php endif; ?>

    <!-- Controles globales -->
    <?php if ($weeks): ?>
      <div class="actions" style="margin:6px 0 12px">
        <button class="btn" type="button" onclick="expandAll(true)"><i class="fa-regular fa-square-plus"></i> Expandir todo</button>
        <button class="btn" type="button" onclick="expandAll(false)"><i class="fa-regular fa-square-minus"></i> Colapsar todo</button>
      </div>
    <?php endif; ?>

    <!-- Listado por auditorias (fecha) -->
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

        // Ordenes
        $pAud = $p;
        if ($q) $pAud[':q'] = '%'.$q.'%';
        $wkAud->execute($pAud);
        $audRows = $wkAud->fetchAll(PDO::FETCH_ASSOC);

        // Desvío por ítem
        $wkItems->execute($p);
        $itemRows = $wkItems->fetchAll(PDO::FETCH_ASSOC);
    ?>
      <section class="week">
        <div class="week-head" onclick="toggleWeek(this)">
          <div style="font-weight:800">Auditorias <?= h($week) ?></div>
          <span class="pill">Ordenes: <b><?= $audits ?></b></span>
          <span class="pill">Aplicables: <b><?= $applic ?></b></span>
          <span class="pill">N: <b><?= $nas ?></b></span>
          <span class="pill">Errores: <b><?= $errors ?></b></span>
          <span class="pill"><?= number_format($wk_pct,2) ?>%</span>
          <div class="chev">▶</div>
        </div>
        <div class="week-body">
          <div class="grid">
            <!-- Tabla de ordenes -->
            <div class="card" style="margin:0">
              <div style="font-weight:800; margin-bottom:6px"><i class="fa-regular fa-clipboard"></i> Ordenes (<?= count($audRows) ?>)</div>
              <div style="overflow:auto">
                <table>
                  <thead>
                    <tr>
                      <th style="width:90px"># OR</th>
                      <th>Tipo</th>
                      <th class="right" style="width:120px">% Error</th>
                      <th class="right" style="width:140px">Errores / Aplic.</th>
                      <th style="width:180px">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if ($audRows): foreach ($audRows as $ar):
                      $ap = (int)($ar['applicable'] ?? 0);
                      $er = (int)($ar['errors_count'] ?? 0);
                      $pp = number_format((float)$ar['error_percentage'], 2);
                      $urlView = "view_audit.php?id=".(int)$ar['audit_id'];
                      $urlEdit = "edit.php?id=".(int)$ar['order_id'];
                      $urlDel  = "delete.php?id=".(int)$ar['order_id'];
                    ?>
                      <tr>
                        <td class="num"><?= h($ar['order_number']) ?></td>
                        <td><span class="pill"><?= h($ar['order_type']) ?></span></td>
                        <td class="right"><b><?= $pp ?>%</b></td>
                        <td class="right"><?= $er ?> / <?= $ap ?></td>
                        <td class="right actions">
                          <a class="btn" href="<?= h($urlView) ?>"><i class="fa-solid fa-eye"></i></a>
                          <button class="btn" type="button"
                                  onclick="openConfirm('edit', '<?= h($urlEdit) ?>', '¿Editar la orden #<?= h($ar['order_number']) ?>?')"
                                  title="Editar">
                            <i class="fa-solid fa-pen"></i>
                          </button>
                          <button class="btn" type="button"
                                  onclick="openConfirm('delete', '<?= h($urlDel) ?>', '¿Eliminar la orden #<?= h($ar['order_number']) ?>? Esta acción no se puede deshacer.')"
                                  title="Eliminar">
                            <i class="fa-solid fa-trash"></i>
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; else: ?>
                      <tr><td colspan="5" class="muted">Sin ordenes para esta auditoria / filtros.</td></tr>
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
                      <tr><td colspan="4" class="muted">Sin datos para esta auditoria.</td></tr>
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

  <!-- Toast genérico (top-right) -->
  <div id="toast"></div>

  <!-- Notificación ESPECIAL centrada (solo delete success) -->
  <div id="notif"><i class="fa-solid fa-circle-check ok"></i><span id="notifMsg">Operación exitosa</span></div>

  <!-- Modal custom -->
  <div class="modal-backdrop" id="modalBackdrop" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal" role="document" aria-labelledby="modalTitle" aria-describedby="modalMsg">
      <h3 id="modalTitle" style="display:flex; align-items:center; gap:8px; margin-bottom:4px">
        <i id="modalIcon" class="fa-solid fa-circle-question"></i>
        <span id="modalTitleText">Confirmación</span>
      </h3>
      <p id="modalMsg">¿Confirmás esta acción?</p>
      <div class="modal-actions">
        <button class="btn btn-ghost" type="button" onclick="closeConfirm()">Cancelar</button>
        <button class="btn btn-danger" type="button" id="modalConfirmBtn">Sí, continuar</button>
      </div>
    </div>
  </div>

  <script>
    // ---------- expandir/colapsar ----------
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
    <?php if (!$fWeek): ?>
      document.addEventListener('DOMContentLoaded', () => {
        const firstHead = document.querySelector('.week .week-head');
        if (firstHead){ toggleWeek(firstHead); }
      });
    <?php endif; ?>

    // ---------- Toast genérico (top-right) ----------
    function showToast(message, type){
      const el = document.getElementById('toast');
      el.textContent = message || '';
      el.classList.remove('success','error');
      if (type==='success') el.classList.add('success');
      if (type==='error')   el.classList.add('error');
      el.style.display = 'block';
      setTimeout(()=>{ el.style.display='none'; }, 3200);
    }

    // ---------- Notificación ESPECIAL (centrada, baja desde arriba) ----------
    function showNotif(message){
      const n = document.getElementById('notif');
      const t = document.getElementById('notifMsg');
      t.textContent = message || 'Operación exitosa';
      n.classList.add('show');
      setTimeout(()=>{ n.classList.remove('show'); }, 3000);
    }

    // ---------- Leer parámetros y disparar UI ----------
    (function(){
      const params = new URLSearchParams(location.search);

      // Notificación ESPECIAL para delete
      const notif = params.get('notif'); // 'deleted'
      const msg   = params.get('msg') || '';
      if (notif === 'deleted') {
        showNotif(msg || 'Orden eliminada correctamente');
      }

      // Toast genérico para el resto (edición, etc.)
      const toastMsg = params.get('toast');
      const type     = params.get('type') || 'info';
      if (toastMsg && !notif) { // si hay notif especial, no mostramos toast
        showToast(toastMsg, type);
      }

      // limpiar query sin recargar
      try{
        const clean = new URL(location.href);
        clean.searchParams.delete('toast');
        clean.searchParams.delete('type');
        clean.searchParams.delete('notif');
        clean.searchParams.delete('msg');
        window.history.replaceState({}, '', clean.toString());
      }catch(e){}
    })();

    // ---------- Modal de confirmación custom ----------
    let modalNextUrl = null;
    function openConfirm(kind, url, message){
      modalNextUrl = url;
      const backdrop = document.getElementById('modalBackdrop');
      const titleEl  = document.getElementById('modalTitleText');
      const iconEl   = document.getElementById('modalIcon');
      const msgEl    = document.getElementById('modalMsg');
      const btn      = document.getElementById('modalConfirmBtn');

      msgEl.textContent = message || '¿Confirmás esta acción?';
      if (kind === 'delete'){
        titleEl.textContent = 'Eliminar orden';
        iconEl.className = 'fa-solid fa-triangle-exclamation';
        btn.className = 'btn btn-danger';
        btn.textContent = 'Sí, eliminar';
      } else if (kind === 'edit'){
        titleEl.textContent = 'Editar orden';
        iconEl.className = 'fa-solid fa-pen-to-square';
        btn.className = 'btn btn-primary';
        btn.textContent = 'Sí, editar';
      } else {
        titleEl.textContent = 'Confirmación';
        iconEl.className = 'fa-solid fa-circle-question';
        btn.className = 'btn btn-primary';
        btn.textContent = 'Continuar';
      }

      backdrop.style.display = 'flex';
      backdrop.setAttribute('aria-hidden','false');

      btn.onclick = () => {
        backdrop.style.display = 'none';
        backdrop.setAttribute('aria-hidden','true');
        if (modalNextUrl) window.location.href = modalNextUrl;
      };

      function escHandler(e){ if (e.key === 'Escape') closeConfirm(); }
      function outHandler(e){ if (e.target === backdrop) closeConfirm(); }
      document.addEventListener('keydown', escHandler, { once:true });
      backdrop.addEventListener('click', outHandler, { once:true });
    }
    function closeConfirm(){
      const backdrop = document.getElementById('modalBackdrop');
      backdrop.style.display = 'none';
      backdrop.setAttribute('aria-hidden','true');
      modalNextUrl = null;
    }
  </script>
</body>
</html>
