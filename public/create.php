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

    /* Appbar */
    .appbar{position:sticky; top:0; z-index:20; background:rgba(10,15,24,.7); backdrop-filter: blur(8px); border-bottom:1px solid var(--border)}
    .appbar-inner{width:min(1200px, 94vw); margin:0 auto; padding:12px 0; display:flex; gap:12px; align-items:center}
    .dot{width:22px; height:22px; border-radius:999px; background:linear-gradient(180deg,var(--accent),var(--accent-2)); box-shadow:0 0 16px rgba(0,163,224,.35)}
    .brand{font-weight:800; letter-spacing:.25px; display:flex; align-items:center; gap:10px}
    .brand i{opacity:.9}
    .spacer{flex:1}
    .btn{display:inline-flex; align-items:center; gap:8px; border:1px solid var(--border); color:#dbe7ff; background:transparent; padding:8px 12px; border-radius:10px}
    .btn:hover{border-color:#2a3a55}

    /* Layout desktop: sidebar a la izquierda + contenido */
    .wrap{width:min(1200px, 94vw); margin:24px auto 40px}
    .layout{display:grid; grid-template-columns: 320px 1fr; gap:16px}

    /* Panel KPI lateral (desktop) – más opaco */
    .kpi-side{
      position: sticky; top: 76px; align-self:start;
      background:
        linear-gradient(180deg, rgba(10,16,28,.88), rgba(12,20,34,.88));
      border:1px solid var(--border); border-radius:14px; overflow:hidden;
      box-shadow: 0 12px 32px rgba(0,0,0,.55), inset 0 0 0 1px var(--border);
      z-index: 5;
    }
    .kpi-head{display:flex; align-items:center; gap:8px; padding:10px 12px; background:rgba(0,0,0,.35); border-bottom:1px solid var(--border)}
    .kpi-title{font-weight:800; flex:1; display:flex; gap:8px; align-items:center}
    .kpi-body{padding:12px}
    .kpi-grid{display:grid; grid-template-columns: 1fr 1fr; gap:10px; align-items:center}
    .legend{display:flex; gap:8px; flex-wrap:wrap; justify-content:center}
    .lg{display:flex; align-items:center; gap:6px; font-size:12px; color:#cbd5e1}
    .dot-lg{width:10px; height:10px; border-radius:999px}
    .dot-ok{ background: var(--ok-c); }
    .dot-err{ background: var(--err-c); }
    .dot-na{ background: var(--na-c); }
    .stat{background:#0e1624; border:1px solid var(--border); border-radius:12px; padding:8px 10px}
    .mini-list{margin-top:8px; display:flex; flex-direction:column; gap:6px; max-height:200px; overflow:auto}
    .mini-row{display:grid; grid-template-columns: 1fr 34px 64px 58px; gap:8px; align-items:center; font-size:13px}
    .mini-pill{min-width:34px; text-align:center; padding:2px 8px; border-radius:999px; border:1px solid var(--border)}
    .p-ok{background:rgba(46,162,109,.12); color:#bcebd6}
    .p-err{background:rgba(211,91,91,.12); color:#ffd6d6}

    /* Donuts */
    .donut{
      position:relative; width:120px; aspect-ratio:1/1; border-radius:50%;
      background: conic-gradient(var(--bg-donut) 0 100%);
      display:flex; align-items:center; justify-content:center; margin:auto;
      border:1px solid var(--border);
      box-shadow: inset 0 0 0 8px #0b1320;
    }
    .donut.small{ width:110px; }
    .donut .center{
      position:absolute; width:62%; height:62%; border-radius:50%; background:#0b1320;
      display:flex; align-items:center; justify-content:center; flex-direction:column; text-align:center; padding:6px;
    }
    .donut .big{ font-size:20px; font-weight:800; }
    .donut .sub{ font-size:11px; color:#9fb0c9 }

    /* Tarjetas y tabla */
    .card{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01));
          border:1px solid var(--border); border-radius:var(--radius);
          box-shadow: 0 10px 30px rgba(0,0,0,.35), inset 0 0 0 1px var(--border);
          padding:16px; margin:16px 0}
    .grid{display:grid; gap:12px; grid-template-columns:repeat(12,1fr)}
    .col-4{grid-column:span 4} .col-12{grid-column:1 / -1}
    label{display:block; margin-bottom:6px; color:#d5deea}
    .lbl i{opacity:.75; margin-right:6px}
    input[type=text], select{
      width:100%; padding:12px 12px; border-radius:12px; border:1px solid var(--border);
      background:var(--surface2); color:var(--text); outline:none
    }
    input[type=text]:focus, select:focus{ border-color:var(--accent); box-shadow:0 0 0 3px rgba(0,163,224,.18) }
    input[disabled]{opacity:.65}

    /* Chip táctil para order_type */
    .tapchip{
      display:inline-flex; align-items:center; gap:8px;
      background:var(--surface2); border:1px solid var(--border);
      padding:10px 12px; border-radius:12px; cursor:pointer; user-select:none;
    }
    .tapchip .dot-mini{width:10px; height:10px; border-radius:999px}
    .t-cliente .dot-mini{ background:#22c55e }
    .t-garantia .dot-mini{ background:#f59e0b }
    .t-interna .dot-mini{ background:#60a5fa }

    table{width:100%; border-collapse:separate; border-spacing:0 10px}
    thead th{font-size:13px; color:#cbd5e1; text-transform:uppercase; letter-spacing:.08em; padding:0 10px}
    tbody tr{background:linear-gradient(180deg, rgba(255,255,255,.015), rgba(255,255,255,.01)); border:1px solid var(--border);
             border-radius:12px; overflow:hidden; transition:transform .08s ease}
    tbody tr:hover{transform:translateY(-2px)}
    tbody td{padding:12px 10px; vertical-align:middle}

    /* Selección + color segun estado */
    tbody tr.sel{outline:2px solid transparent; background: var(--sel)}
    tbody tr.sel.state-OK{ outline-color: var(--ok-b); }
    tbody tr.sel.state-1 { outline-color: var(--err-b); }
    tbody tr.sel.state-N { outline-color: var(--na-b); }

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

    .stats{display:flex; gap:12px; flex-wrap:wrap}
    .stat-inline{background:#0e1624; border:1px solid var(--border); border-radius:12px; padding:10px 12px}

    /* Guardar fijo desktop */
    .fab{
      position:fixed; right:24px; bottom:24px; z-index:10; display:flex; align-items:center; gap:10px;
      background:linear-gradient(180deg,var(--accent),var(--accent-2)); color:#fff; padding:12px 16px;
      border-radius:12px; border:0; cursor:pointer; box-shadow: 0 10px 25px rgba(0,163,224,.25); font-weight:700
    }
    .fab:hover{filter:brightness(1.05)}

    /* ======== TABLET (<= 980px): KPI como sidebar con solapa ======== */
    .kpi-tab{ display:none; }
    .kpi-overlay{ display:none; }

    @media (max-width: 980px){
      .layout{grid-template-columns: 1fr}

      /* Sidebar KPI (drawer) – más opaco */
      .kpi-side{
        position: fixed; left:0; top:0; height:100vh; width:86vw; max-width:420px;
        transform: translateX(-100%); transition: transform .22s ease;
        border-radius: 0; z-index: 60;
        background:
          linear-gradient(180deg, rgba(10,16,28,.95), rgba(12,20,34,.95)); /* MÁS OPACO */
        box-shadow: 0 22px 60px rgba(0,0,0,.55), inset 0 0 0 1px var(--border);
      }
      .kpi-side.open{ transform: translateX(0); }

      .kpi-head{ padding:12px 14px; }
      .kpi-body{ padding:14px; }

      /* SOLAPA vertical pegada al borde izquierdo */
      .kpi-tab{
        position: fixed; left:0; top:50%; transform: translateY(-50%);
        display:flex; align-items:center; justify-content:center;
        width:42px; height:140px; /* solapa larga vertical */
        background: linear-gradient(180deg, var(--accent), var(--accent-2));
        color:#fff; border:none; z-index: 65;
        border-radius: 0 10px 10px 0; /* pegado al borde izq */
        box-shadow: 0 8px 24px rgba(0,0,0,.35);
        cursor:pointer;
      }
      .kpi-tab i{ font-size:18px; }

      /* overlay para cerrar al tocar afuera */
      .kpi-overlay{
        position: fixed; inset:0; background: rgba(0,0,0,.55); /* MÁS OPACO */
        z-index: 55; display:none;
      }
      .kpi-overlay.show{ display:block; }
    }

    /* Accesibilidad táctil */
    html { font-size: clamp(14px, 1.6vw, 16px); }
    button, .btn, input[type="text"], select { min-height: 44px; }
  </style>
</head>
<body>
  <div class="appbar">
    <div class="appbar-inner">
      <div class="dot"></div>
      <div class="brand"><i class="fa-solid fa-clipboard-check"></i> Nueva Auditoría</div>
      <div class="spacer"></div>
      <a class="btn" href="index.php"><i class="fa-solid fa-house"></i> Inicio</a>
    </div>
  </div>

  <div class="wrap">
    <div class="layout">
      <!-- ===== Sidebar KPI (desktop) / Drawer (tablet) ===== -->
      <aside class="kpi-side" id="kpiSide" aria-live="polite" aria-hidden="false">
        <div class="kpi-head">
          <div class="kpi-title"><i class="fa-solid fa-chart-pie"></i> KPI en vivo</div>
          <button class="btn" id="kpiCloseBtn" type="button" title="Cerrar" style="padding:6px 10px; display:none">
            <i class="fa-solid fa-xmark"></i>
          </button>
        </div>
        <div class="kpi-body" id="kpiBody">
          <div class="kpi-grid">
            <!-- Donut % Error -->
            <div>
              <div class="donut" id="donutPct">
                <div class="center">
                  <div class="big" id="pctTxt">0%</div>
                  <div class="sub">% error</div>
                </div>
              </div>
            </div>
            <!-- Donut distribución OK/1/N -->
            <div>
              <div class="donut small" id="donutDist">
                <div class="center">
                  <div class="big" id="distTot">0</div>
                  <div class="sub">ítems</div>
                </div>
              </div>
              <div class="legend" style="margin-top:8px">
                <span class="lg"><i class="dot-lg dot-err"></i> 1</span>
                <span class="lg"><i class="dot-lg dot-ok"></i> OK</span>
                <span class="lg"><i class="dot-lg dot-na"></i> N</span>
              </div>
            </div>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin:10px 0">
            <div class="stat"><div class="muted">Errores</div><div style="font-weight:800;font-size:18px" id="kpiErr">0</div></div>
            <div class="stat"><div class="muted">Aplicables</div><div style="font-weight:800;font-size:18px" id="kpiApp">0</div></div>
            <div class="stat"><div class="muted">No aplica</div><div style="font-weight:800;font-size:18px" id="kpiNA">0</div></div>
          </div>

          <div class="stat" style="padding:8px 10px; margin-bottom:6px"><i class="fa-solid fa-user-gear" style="margin-right:6px"></i> Desvío por responsable</div>
          <div class="mini-list" id="kpiRespList"><div class="mini-row muted" style="grid-template-columns:1fr">Sin datos aún…</div></div>
        </div>
      </aside>

      <!-- Solapa vertical (solo tablet) + overlay -->
      <button class="kpi-tab" id="kpiOpenTab" type="button" title="Ver KPI" aria-controls="kpiSide" aria-expanded="false" style="display:none">
        <i class="fa-solid fa-chart-pie"></i>
      </button>
      <div class="kpi-overlay" id="kpiOverlay"></div>

      <!-- ===== Contenido principal ===== -->
      <main>
        <form method="post" action="save_audit.php" id="auditForm">
          <input type="hidden" name="week_date" value="<?= htmlspecialchars($weekMonday) ?>">

          <div class="card grid">
            <div class="col-4">
              <label class="lbl"><i class="fa-solid fa-hashtag"></i>Número de OR</label>
              <input type="text" name="order_number" required autocomplete="off" autofocus>
            </div>

            <!-- Tap para alternar tipo: cliente → garantia → interna -->
            <div class="col-4">
              <label class="lbl"><i class="fa-solid fa-tags"></i>Tipo</label>
              <input type="hidden" name="order_type" id="order_type" value="cliente">
              <div id="orderTypeChip" class="tapchip t-cliente" role="button" aria-label="Tipo de orden: Cliente" tabindex="0">
                <span class="dot-mini"></span>
                <span class="tapchip-text">Cliente</span>
                <i class="fa-solid fa-rotate-right" style="opacity:.7"></i>
              </div>
              <p class="muted" style="margin:.35rem 0 0">Tocá para alternar: cliente → garantía → interna</p>
            </div>

            <div class="col-4">
              <label class="lbl"><i class="fa-regular fa-calendar"></i>Semana (auto)</label>
              <input type="text" value="<?= htmlspecialchars($weekMonday) ?>" disabled>
            </div>
          </div>

          <div class="card">
            <div class="stats">
              <div class="stat-inline"><b>Aplicables (OK/1):</b> <span id="st-app">0</span></div>
              <div class="stat-inline"><b>Errores (1):</b> <span id="st-err">0</span></div>
              <div class="stat-inline"><b>No aplica (N):</b> <span id="st-na">0</span></div>
              <div class="stat-inline"><b>% Error:</b> <span id="st-pct">0%</span></div>
            </div>
            <p class="muted">PC: ↑/↓ mover · ← = 1 · → = N · Espacio = OK · R = responsable · Ctrl+Enter = guardar</p>
            <p class="muted" style="margin-top:-6px">Tablet/Click: tocá el <b>ítem</b> para alternar <b>1 → OK → N</b>.</p>

            <table id="tbl">
              <thead>
                <tr>
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
                    <td class="tap-state"><?= htmlspecialchars($it['question']) ?></td>
                    <td>
                      <div class="resp-wrap">
                        <select name="resp[<?= $id ?>]" id="resp-<?= $id ?>" onchange="toggleOtro(<?= $id ?>)">
                          <?php foreach ($responsables as $r): ?>
                            <option value="<?= htmlspecialchars($r) ?>" <?= $r===$def?'selected':'' ?>>
                              <?= htmlspecialchars($r) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <input type="text" class="resp-otro" id="resp-otro-<?= $id ?>"
                               name="resp_other[<?= $id ?>]" placeholder="Especificar responsable" />
                      </div>
                    </td>
                    <td><span class="state-badge b-ok state-label">OK</span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div id="hiddenInputs" style="display:none;">
            <?php foreach ($items as $it): $id=(int)$it['id']; ?>
              <input type="hidden" name="ans[<?= $id ?>]" id="ans-<?= $id ?>" value="OK">
            <?php endforeach; ?>
          </div>

          <button type="submit" class="fab" title="Guardar (Ctrl+Enter)"><i class="fa-solid fa-floppy-disk"></i> Guardar auditoría</button>
        </form>
      </main>
    </div>
  </div>

  <script>
    // Mostrar/ocultar input "Otros"
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
      updateKPIsLive();
    }

    function recomputeStats(){
      let err=0, na=0, app=0;
      rows.forEach(r=>{
        const val = r.querySelector('.state-label').textContent.trim();
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

    // Tap: seleccionar + alternar 1 → OK → N
    function cycleState(i){
      const r = rows[i]; if (!r) return;
      const badge = r.querySelector('.state-label');
      const cur = badge.textContent.trim();
      const next = (cur === '1') ? 'OK' : (cur === 'OK' ? 'N' : '1');
      setState(i, next);
    }
    rows.forEach((r, i) => {
      r.addEventListener('click', (e) => {
        if (e.target.tagName === 'SELECT' || e.target.closest('select') || e.target.closest('input') || e.target.classList.contains('resp-otro')) return;
        selectRow(i);
        cycleState(i);
      });
      const sel = r.querySelector('select');
      if (sel) sel.addEventListener('change', updateKPIsLive);
    });

    selectRow(0); recomputeStats(); updateKPIsLive();

    // Atajos PC
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

    // Tapchip tipo: cliente → garantía → interna
    (function(){
      const chip   = document.getElementById('orderTypeChip');
      const hidden = document.getElementById('order_type');
      const textEl = chip.querySelector('.tapchip-text');
      const seq = ['cliente','garantia','interna'];
      const labels = { cliente:'Cliente', garantia:'Garantía', interna:'Interna' };
      function classFor(v){ return v==='cliente' ? 't-cliente' : (v==='garantia' ? 't-garantia' : 't-interna'); }
      function cycle(){
        const cur = hidden.value;
        const idx = seq.indexOf(cur);
        const next = seq[(idx+1)%seq.length];
        hidden.value = next;
        chip.className = 'tapchip ' + classFor(next);
        textEl.textContent = labels[next];
        chip.setAttribute('aria-label', 'Tipo de orden: ' + labels[next]);
      }
      chip.addEventListener('click', cycle);
      chip.addEventListener('keydown', (e)=>{ if (e.key==='Enter' || e.key===' ') { e.preventDefault(); cycle(); }});
    })();

    // ====== KPI DONUTS + ranking vivo ======
    function setDonutPct(el, pct, color){
      const p = Math.max(0, Math.min(100, pct));
      el.style.background = `conic-gradient(${color} ${p}%, var(--bg-donut) 0)`;
    }
    function setDonutDist(el, ok, err, na){
      const total = ok + err + na;
      if (total <= 0){
        el.style.background = `conic-gradient(var(--bg-donut) 0 100%)`;
        return;
      }
      const pErr = err / total * 100;
      const pOk  = ok  / total * 100;
      const pNa  = 100 - pErr - pOk;
      el.style.background = `
        conic-gradient(
          var(--err-c) 0 ${pErr}%,
          var(--ok-c) ${pErr}% ${pErr+pOk}%,
          var(--na-c) ${pErr+pOk}% 100%
        )`;
    }

    function updateKPIsLive(){
      let err=0, na=0, app=0;
      const byResp = {};
      rows.forEach(r=>{
        const sel = r.querySelector('select');
        const who = sel ? sel.value : '(sin)';
        const val = r.querySelector('.state-label').textContent.trim();
        if (!byResp[who]) byResp[who] = {app:0, err:0};
        if (val==='N'){ na++; }
        else if (val==='1'){ app++; err++; byResp[who].app++; byResp[who].err++; }
        else if (val==='OK'){ app++; byResp[who].app++; }
      });
      const pctErr = app>0 ? Math.round((err/app)*10000)/100 : 0;

      // KPIs numéricos
      document.getElementById('kpiErr').textContent = err;
      document.getElementById('kpiApp').textContent = app;
      document.getElementById('kpiNA').textContent  = na;
      document.getElementById('pctTxt').textContent = pctErr.toFixed(2) + '%';
      document.getElementById('distTot').textContent = (app + na);

      // Donuts
      setDonutPct(document.getElementById('donutPct'), pctErr, 'var(--err-c)');
      setDonutDist(document.getElementById('donutDist'), (app-err), err, na);

      // Ranking responsables
      const entries = Object.entries(byResp).map(([name, v])=>{
        const pct = v.app>0 ? (v.err / v.app)*100 : 0;
        return {name, app:v.app, err:v.err, pct};
      }).sort((a,b)=> b.pct - a.pct || b.err - a.err);

      const list = document.getElementById('kpiRespList');
      list.innerHTML = '';
      if (!entries.length){
        list.innerHTML = '<div class="mini-row muted" style="grid-template-columns:1fr">Sin datos aún…</div>';
        return;
      }
      entries.forEach(e=>{
        const pct = e.app>0 ? Math.round(e.pct*10)/10 : 0;
        const row = document.createElement('div');
        row.className = 'mini-row';
        row.innerHTML = `
          <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap">${e.name}</div>
          <div class="mini-pill p-err">${e.err}</div>
          <div class="mini-pill p-ok">${Math.max(0,e.app - e.err)}</div>
          <div style="text-align:right">${pct.toFixed(1)}%</div>
        `;
        list.appendChild(row);
      });
    }

    // ====== Drawer tablet: abrir/cerrar con solapa vertical y overlay ======
    (function(){
      const side   = document.getElementById('kpiSide');
      const tab    = document.getElementById('kpiOpenTab');
      const close  = document.getElementById('kpiCloseBtn');
      const overlay= document.getElementById('kpiOverlay');

      function isTablet(){ return window.matchMedia('(max-width: 980px)').matches; }

      function applyMode(){
        if (isTablet()){
          tab.style.display = side.classList.contains('open') ? 'none' : 'flex';
          close.style.display = 'inline-flex';
          side.setAttribute('aria-hidden', !side.classList.contains('open'));
        }else{
          tab.style.display = 'none';
          close.style.display = 'none';
          side.classList.remove('open');
          overlay.classList.remove('show');
          side.setAttribute('aria-hidden', 'false');
          document.body.style.overflow = '';
        }
      }

      function openDrawer(){
        if (!isTablet()) return;
        side.classList.add('open');
        overlay.classList.add('show');
        tab.style.display = 'none';  // solapa desaparece al abrir
        tab.setAttribute('aria-expanded','true');
        side.setAttribute('aria-hidden','false');
        document.body.style.overflow = 'hidden';
      }
      function closeDrawer(){
        side.classList.remove('open');
        overlay.classList.remove('show');
        tab.style.display = isTablet() ? 'flex' : 'none'; // vuelve la solapa
        tab.setAttribute('aria-expanded','false');
        side.setAttribute('aria-hidden','true');
        document.body.style.overflow = '';
      }

      tab.addEventListener('click', openDrawer);
      close.addEventListener('click', closeDrawer);
      overlay.addEventListener('click', closeDrawer);
      window.addEventListener('keydown', (e)=>{ if (e.key==='Escape' && side.classList.contains('open')) closeDrawer(); });
      window.addEventListener('resize', applyMode);

      applyMode();
    })();
  </script>
</body>
</html>
