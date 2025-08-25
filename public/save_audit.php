<?php
require_once __DIR__ . '/../app/db.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$pdo = db();

$items = $pdo->query("
  SELECT id, item_order, question, responsable_default
  FROM checklist_items
  WHERE active=1
  ORDER BY item_order
")->fetchAll();

$now = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
$weekMonday = (clone $now)->modify('monday this week')->format('Y-m-d');

$responsables = [
  "As. Servicio",
  "As. Citas",
  "Cajero",
  "Jefe de Taller",
  "Gerente PostVenta",
  "Gerente repuestos",
  "Responsable de Calidad",
  "Encuestador Telefónico",
  "Responsable de Garantía",
  "Responsable de Chapa y Pintura",
  "Otros"
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Nueva Auditoría · Organización Sur</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 + Font Awesome -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
  <script defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <style>
    :root{
      --bg:#0a0f18; --bg2:#0b121d; --surface:#101826; --surface2:#0f1725; --border:#1b2535;
      --text:#e7ecf3; --muted:#9fb0c9;
      --accent:#00A3E0; --accent-2:#00b4ff; /* VW cyan */
      --ok-b:#2ea26d; --err-b:#d35b5b; --na-b:#5aa1ff; --sel:#111c2f; --chip:#0c1420; --radius:14px;
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
    .appbar-inner{width:min(1150px, 92vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .dot{width:22px; height:22px; border-radius:999px; background:linear-gradient(180deg,var(--accent),var(--accent-2)); box-shadow:0 0 16px rgba(0,163,224,.35)}
    .brand{font-weight:800; letter-spacing:.25px; display:flex; align-items:center; gap:10px}
    .brand i{opacity:.9}
    .spacer{flex:1}

    .wrap{width:min(1150px, 92vw); margin:24px auto; padding-bottom:110px}
    .card-dark{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01)); border:1px solid var(--border); border-radius:14px; color:var(--text); box-shadow: 0 10px 30px rgba(0,0,0,.35), inset 0 0 0 1px var(--border)}

    label{display:block; margin-bottom:6px; color:#d5deea}
    input[type=text], select{width:100%; padding:12px 12px; border-radius:12px; border:1px solid var(--border); background:var(--surface2); color:var(--text); outline:none}
    input[type=text]:focus, select:focus{ border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,163,224,.18) }
    input[disabled]{opacity:.65}

    table{width:100%; border-collapse:separate; border-spacing:0 10px}
    thead th{font-size:13px; color:#cbd5e1; text-transform:uppercase; letter-spacing:.08em; padding:0 10px}
    tbody tr{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01)); border:1px solid var(--border); border-radius:12px; overflow:hidden; transition:transform .08s ease}
    tbody tr:hover{transform:translateY(-2px)}
    tbody td{padding:12px 10px; vertical-align:middle}
    tbody tr.sel{outline:2px solid var(--accent); background:var(--sel)}
    tbody tr.state-OK{box-shadow: inset 4px 0 0 var(--ok-b)}
    tbody tr.state-1{box-shadow: inset 4px 0 0 var(--err-b)}
    tbody tr.state-N{box-shadow: inset 4px 0 0 var(--na-b)}
    .num{color:#cbd5e1; font-weight:700}
    .state-badge{font-weight:700; padding:6px 10px; border-radius:999px; font-size:12px}
    .b-ok{background:rgba(46,162,109,.12); color:#bcebd6; border:1px solid rgba(46,162,109,.35)}
    .b-err{background:rgba(211,91,91,.12); color:#ffd6d6; border:1px solid rgba(211,91,91,.35)}
    .b-na{background:rgba(90,161,255,.12); color:#d5e7ff; border:1px solid rgba(90,161,255,.35)}
    .resp-wrap{display:flex; gap:8px; align-items:center}
    .resp-otro{display:none}

    .fab{position:fixed; right:24px; bottom:24px; z-index:30; display:flex; align-items:center; gap:10px;
         background: linear-gradient(180deg, var(--accent), var(--accent-2));
         color:#fff; padding:12px 16px; border-radius:12px; border:0; cursor:pointer; box-shadow: 0 10px 25px rgba(0,163,224,.25); font-weight:700}
    .fab:hover{filter:brightness(1.05)}

    .stat{background:#0e1624; border:1px solid var(--border); border-radius:12px; padding:10px 12px; display:inline-block; margin-right:10px}
  </style>
</head>
<body>
  <div class="appbar">
    <div class="appbar-inner">
      <div class="dot"></div>
      <div class="brand"><i class="fa-solid fa-clipboard-check"></i> Nueva Auditoría</div>
      <div class="spacer"></div>
      <div class="btn-group">
        <a class="btn btn-sm btn-outline-light" href="index.php" data-bs-toggle="tooltip" title="Inicio">
          <i class="fa-solid fa-house"></i>
        </a>
        <button class="btn btn-sm btn-success" type="submit" form="auditForm" data-bs-toggle="tooltip" title="Guardar (Ctrl+Enter)">
          <i class="fa-solid fa-floppy-disk"></i> Guardar
        </button>
      </div>
    </div>
  </div>

  <div class="wrap">
    <form method="post" action="save_audit.php" id="auditForm">
      <input type="hidden" name="week_date" value="<?= htmlspecialchars($weekMonday) ?>">

      <div class="card-dark p-3 mb-3">
        <div class="row g-3">
          <div class="col-md-4">
            <label>Número de OR</label>
            <input type="text" name="order_number" required autocomplete="off" autofocus>
          </div>
          <div class="col-md-4">
            <label>Tipo</label>
            <select name="order_type" required>
              <option value="cliente">Cliente</option>
              <option value="garantia">Garantía</option>
              <option value="interna">Interna</option>
            </select>
          </div>
          <div class="col-md-4">
            <label>Semana (auto)</label>
            <input type="text" value="<?= htmlspecialchars($weekMonday) ?>" disabled>
          </div>
        </div>
      </div>

      <div class="card-dark p-3">
        <div class="mb-2">
          <span class="stat"><b>Aplicables (OK/1):</b> <span id="st-app">0</span></span>
          <span class="stat"><b>Errores (1):</b> <span id="st-err">0</span></span>
          <span class="stat"><b>No aplica (N):</b> <span id="st-na">0</span></span>
          <span class="stat"><b>% Error:</b> <span id="st-pct">0%</span></span>
        </div>
        <p class="text-secondary mb-2">Atajos: ↑/↓ mover · ← = 1 (error) · → = N · Espacio = OK · R = editar responsable · Ctrl+Enter = guardar</p>

        <div class="table-responsive">
          <table id="tbl" class="table table-sm align-middle">
            <thead>
              <tr class="text-secondary">
                <th style="width:56px">#</th>
                <th>Ítem</th>
                <th style="width:320px">Responsable</th>
                <th style="width:120px">Estado</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it):
                $id = (int)$it['id']; $def = $it['responsable_default'] ?? '';
              ?>
                <tr data-id="<?= $id ?>" class="state-OK">
                  <td class="num"><?= (int)$it['item_order'] ?></td>
                  <td><?= htmlspecialchars($it['question']) ?></td>
                  <td>
                    <div class="resp-wrap">
                      <select name="resp[<?= $id ?>]" id="resp-<?= $id ?>" onchange="toggleOtro(<?= $id ?>)">
                        <?php foreach ($responsables as $r): ?>
                          <option value="<?= htmlspecialchars($r) ?>" <?= $r===$def?'selected':'' ?>>
                            <?= htmlspecialchars($r) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <input type="text" class="resp-otro form-control form-control-sm" style="max-width:220px"
                             id="resp-otro-<?= $id ?>" name="resp_other[<?= $id ?>]"
                             placeholder="Especificar responsable" />
                    </div>
                  </td>
                  <td><span class="state-badge b-ok state-label">OK</span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div id="hiddenInputs" style="display:none;">
        <?php foreach ($items as $it): $id=(int)$it['id']; ?>
          <input type="hidden" name="ans[<?= $id ?>]" id="ans-<?= $id ?>" value="OK">
        <?php endforeach; ?>
      </div>

      <button type="submit" class="fab" title="Guardar (Ctrl+Enter)">
        <i class="fa-solid fa-floppy-disk"></i> Guardar auditoría
      </button>
    </form>
  </div>

  <script>
    // Tooltips
    document.addEventListener('DOMContentLoaded', () => {
      const tts = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tts.map(el => new bootstrap.Tooltip(el));
    });

    function toggleOtro(id){
      var sel = document.getElementById('resp-' + id);
      var inp = document.getElementById('resp-otro-' + id);
      if (sel.value === 'Otros') { inp.style.display = 'block'; inp.required = true; }
      else { inp.style.display = 'none'; inp.required = false; inp.value=''; }
    }
    <?php foreach ($items as $it): $id=(int)$it['id']; ?> toggleOtro(<?= $id ?>); <?php endforeach; ?>

    const rows = Array.from(document.querySelectorAll('#tbl tbody tr'));
    let idx = 0;

    function selectRow(i){
      rows.forEach(r => r.classList.remove('sel'));
      idx = Math.max(0, Math.min(rows.length-1, i));
      const row = rows[idx]; row.classList.add('sel');
      const rect = row.getBoundingClientRect();
      if (rect.top < 90 || rect.bottom > window.innerHeight - 90) {
        row.scrollIntoView({behavior:'smooth', block:'center'});
      }
    }
    function setState(i, val){
      const r = rows[i]; if (!r) return;
      r.classList.remove('state-OK','state-1','state-N');
      r.classList.add('state-'+val);
      const badge = r.querySelector('.state-label');
      if (val==='OK'){ badge.textContent='OK'; badge.className='state-badge b-ok state-label'; }
      if (val==='1'){  badge.textContent='1';  badge.className='state-badge b-err state-label'; }
      if (val==='N'){  badge.textContent='N';  badge.className='state-badge b-na state-label'; }
      const id = r.getAttribute('data-id');
      document.getElementById('ans-'+id).value = val;
      recomputeStats();
    }
    function recomputeStats(){
      let err=0, na=0, app=0;
      rows.forEach(r=>{
        const val = r.querySelector('.state-label').textContent;
        if (val==='N'){ na++; }
        else if (val==='1'){ app++; err++; }
        else if (val==='OK'){ app++; }
      });
      const pctErr = app>0 ? Math.round((err/app)*10000)/100 : 0;
      document.getElementById('st-err').textContent = err;
      document.getElementById('st-na').textContent  = na;
      document.getElementById('st-app').textContent = app+na;
      document.getElementById('st-pct').textContent = pctErr.toFixed(2)+'%';
    }
    selectRow(0); recomputeStats();

    // Teclado: ↑/↓ · ←=1 · →=N · ESPACIO=OK · R responsable · Ctrl+Enter guardar
    document.addEventListener('keydown', (e) => {
      const tag = document.activeElement.tagName;
      const typing = (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT');

      if (e.ctrlKey && e.key === 'Enter') { e.preventDefault(); document.getElementById('auditForm').submit(); return; }

      if (!typing && ['ArrowUp','ArrowDown','ArrowLeft','ArrowRight',' ','r','R'].includes(e.key)) e.preventDefault();
      else if (typing) return;

      if (e.key === 'ArrowUp') selectRow(idx-1);
      else if (e.key === 'ArrowDown') selectRow(idx+1);
      else if (e.key === 'ArrowLeft'){ setState(idx,'1'); selectRow(idx+1); }
      else if (e.key === 'ArrowRight'){ setState(idx,'N'); selectRow(idx+1); }
      else if (e.key === ' '){ setState(idx,'OK'); selectRow(idx+1); }
      else if (e.key.toLowerCase() === 'r'){
        const row = rows[idx], id = row.getAttribute('data-id');
        const sel = document.getElementById('resp-'+id), otro = document.getElementById('resp-otro-'+id);
        sel.focus(); setTimeout(()=>{ if (sel.value==='Otros'){ otro.style.display='block'; otro.required=true; otro.focus(); } },0);
      }
    });
  </script>
</body>
</html>
