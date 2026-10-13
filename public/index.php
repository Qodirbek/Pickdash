<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>PickDash — Live Faucet Monitor</title>
<style>
:root {
  --bg: #0b0f17;
  --panel: #121826;
  --panel2: #1a2234;
  --border: #243049;
  --text: #e8eefc;
  --muted: #8b9bb8;
  --accent: #5b8cff;
  --green: #3ddc97;
  --red: #ff6b7a;
  --orange: #ffb020;
  --cyan: #2ec4b6;
}
* { box-sizing: border-box; }
body {
  margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
  background: radial-gradient(1200px 600px at 10% -10%, #1a2744 0%, var(--bg) 55%);
  color: var(--text); min-height: 100vh;
}
.wrap { max-width: 1200px; margin: 0 auto; padding: 24px 16px 48px; }
header {
  display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
  gap: 12px; margin-bottom: 22px;
}
.logo { font-size: 1.35rem; font-weight: 700; letter-spacing: .3px; }
.logo span { color: var(--accent); }
.meta { color: var(--muted); font-size: .85rem; }
.badge {
  display: inline-flex; align-items: center; gap: 6px;
  background: var(--panel2); border: 1px solid var(--border);
  padding: 6px 10px; border-radius: 999px; font-size: .8rem; color: var(--muted);
}
.dot { width: 8px; height: 8px; border-radius: 50%; background: var(--green); box-shadow: 0 0 8px var(--green); }
.dot.off { background: var(--red); box-shadow: 0 0 8px var(--red); }

.cards {
  display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 12px; margin-bottom: 20px;
}
.card {
  background: linear-gradient(180deg, var(--panel2), var(--panel));
  border: 1px solid var(--border); border-radius: 14px; padding: 14px 16px;
}
.card .k { color: var(--muted); font-size: .75rem; text-transform: uppercase; letter-spacing: .06em; }
.card .v { font-size: 1.4rem; font-weight: 700; margin-top: 6px; }

.grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 14px; margin-bottom: 24px;
}
.acc {
  background: var(--panel); border: 1px solid var(--border); border-radius: 16px;
  padding: 16px; cursor: pointer; transition: .15s border-color, .15s transform;
}
.acc:hover { border-color: var(--accent); transform: translateY(-1px); }
.acc.active { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent); }
.acc .top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.acc .name { font-weight: 700; font-size: 1.05rem; word-break: break-all; }
.acc .site {
  font-size: .7rem; text-transform: uppercase; letter-spacing: .05em;
  background: #243556; color: #a8c0ff; padding: 3px 8px; border-radius: 6px;
}
.acc .bal { font-size: 1.25rem; font-weight: 700; color: var(--cyan); margin: 10px 0 6px; }
.acc .row { display: flex; justify-content: space-between; color: var(--muted); font-size: .82rem; margin: 4px 0; }
.acc .st {
  display: inline-block; margin-top: 10px; font-size: .75rem; padding: 4px 8px; border-radius: 6px;
}
.st.running, .st.waiting { background: #1e3d32; color: var(--green); }
.st.error { background: #3d1e24; color: var(--red); }
.st.idle { background: #2a3144; color: var(--muted); }

.detail {
  display: none; background: var(--panel); border: 1px solid var(--border);
  border-radius: 16px; padding: 18px; margin-bottom: 20px;
}
.detail.open { display: block; }
.detail h2 { margin: 0 0 12px; font-size: 1.1rem; }
.detail .kv { display: grid; grid-template-columns: 140px 1fr; gap: 8px; font-size: .9rem; }
.detail .kv div:nth-child(odd) { color: var(--muted); }

.events {
  background: var(--panel); border: 1px solid var(--border); border-radius: 16px; padding: 16px;
}
.events h3 { margin: 0 0 12px; font-size: 1rem; }
.ev {
  display: grid; grid-template-columns: 150px 90px 1fr; gap: 8px;
  padding: 8px 0; border-bottom: 1px solid var(--border); font-size: .82rem;
}
.ev:last-child { border-bottom: 0; }
.ev .t { color: var(--muted); }
.ev .type.claim { color: var(--green); }
.ev .type.fail { color: var(--red); }
.ev .type.info { color: var(--orange); }

@media (max-width: 640px) {
  .ev { grid-template-columns: 1fr; gap: 2px; }
  .detail .kv { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div>
      <div class="logo">Pick<span>Dash</span> Live</div>
      <div class="meta">Tonpick / Tronpick — barcha hisoblar</div>
    </div>
    <div class="badge"><span class="dot" id="liveDot"></span> <span id="liveText">ulanish...</span></div>
  </header>

  <div class="cards" id="summaryCards"></div>
  <div class="grid" id="accGrid"></div>
  <div class="detail" id="detail"></div>
  <div class="events">
    <h3>So‘nggi hodisalar</h3>
    <div id="eventList"></div>
  </div>
</div>
<script>
let selected = null;
const unit = (site) => site === 'tronpick' ? 'TRX' : 'TON';

function fmtLeft(iso) {
  if (!iso) return '—';
  const t = new Date(iso.replace(' ', 'T') + 'Z'); // treat as UTC-ish; display relative
  // server stores local UZT without Z — parse as local
  const end = Date.parse(iso.replace(' ', 'T'));
  if (!end) return iso;
  let s = Math.floor((end - Date.now()) / 1000);
  if (s < 0) return 'tayyor';
  const h = Math.floor(s / 3600); s %= 3600;
  const m = Math.floor(s / 60); const sec = s % 60;
  return `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`;
}

function render(data) {
  const sum = data.summary || {};
  let totalAcc = 0, totalClaims = 0, totalFails = 0;
  Object.values(sum).forEach(s => {
    totalAcc += s.accounts; totalClaims += s.claims_today; totalFails += s.fails_today;
  });
  const cards = [
    {k:'Hisoblar', v: totalAcc},
    {k:'Bugun claim', v: totalClaims},
    {k:'Bugun fail', v: totalFails},
    {k:'Site', v: Object.keys(sum).length || 0},
  ];
  document.getElementById('summaryCards').innerHTML = cards.map(c =>
    `<div class="card"><div class="k">${c.k}</div><div class="v">${c.v}</div></div>`
  ).join('');

  const grid = document.getElementById('accGrid');
  grid.innerHTML = (data.accounts || []).map(a => {
    const active = selected === a.site + '|' + a.username ? ' active' : '';
    return `<div class="acc${active}" data-key="${a.site}|${a.username}">
      <div class="top"><div class="name">${esc(a.username || '—')}</div><span class="site">${esc(a.site)}</span></div>
      <div class="bal">${esc(a.balance || '0')} ${unit(a.site)}</div>
      <div class="row"><span>Bugun</span><span>${a.claims_today || 0} claim / ${a.fails_today || 0} fail</span></div>
      <div class="row"><span>Jami</span><span>${a.total_claims || 0}</span></div>
      <div class="row"><span>Captcha</span><span>${esc(a.last_captcha || '—')}</span></div>
      <div class="row"><span>Keyingi</span><span class="next" data-next="${esc(a.next_claim_at || '')}">${fmtLeft(a.next_claim_at)}</span></div>
      <span class="st ${esc(a.status || 'idle')}">${esc(a.status || 'idle')}</span>
    </div>`;
  }).join('') || '<div class="meta">Hali hisob yo‘q — bot API ga yuborsin</div>';

  grid.querySelectorAll('.acc').forEach(el => {
    el.onclick = () => {
      selected = el.dataset.key;
      const [site, user] = selected.split('|');
      const a = (data.accounts || []).find(x => x.site === site && x.username === user);
      showDetail(a);
      render(data);
    };
  });

  const ev = document.getElementById('eventList');
  ev.innerHTML = (data.events || []).map(e =>
    `<div class="ev"><div class="t">${esc(e.created_at || '')}</div>
     <div class="type ${esc(e.event_type)}">${esc(e.event_type)} · ${esc(e.site)}</div>
     <div><b>${esc(e.username)}</b> ${esc(e.captcha || '')} — ${esc(e.message || '')} <span style="color:var(--muted)">${esc(e.balance||'')}</span></div></div>`
  ).join('') || '<div class="meta">Hodisa yo‘q</div>';

  document.getElementById('liveDot').classList.remove('off');
  document.getElementById('liveText').textContent = 'live · ' + (data.server_time || '').replace('T',' ').slice(0,19);
}

function showDetail(a) {
  const box = document.getElementById('detail');
  if (!a) { box.classList.remove('open'); return; }
  box.classList.add('open');
  box.innerHTML = `<h2>${esc(a.username)} <span class="site">${esc(a.site)}</span></h2>
    <div class="kv">
      <div>Balance</div><div><b>${esc(a.balance)} ${unit(a.site)}</b></div>
      <div>Status</div><div>${esc(a.status)}</div>
      <div>Oxirgi captcha</div><div>${esc(a.last_captcha || '—')}</div>
      <div>Oxirgi claim</div><div>${esc(a.last_claim_at || '—')}</div>
      <div>Keyingi claim</div><div>${fmtLeft(a.next_claim_at)} <span style="color:var(--muted)">${esc(a.next_claim_at||'')}</span></div>
      <div>Bugun</div><div>${a.claims_today||0} / fail ${a.fails_today||0}</div>
      <div>Jami claim</div><div>${a.total_claims||0}</div>
      <div>API bal</div><div>${esc(a.api_balance||'—')}</div>
      <div>Xabar</div><div>${esc(a.last_message||'—')}</div>
      <div>Yangilangan</div><div>${esc(a.updated_at||'—')}</div>
    </div>`;
}

function esc(s) {
  return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
}

async function tick() {
  try {
    const r = await fetch('data.php?_=' + Date.now());
    const j = await r.json();
    if (j.ok) render(j);
    else throw new Error(j.error || 'fail');
  } catch (e) {
    document.getElementById('liveDot').classList.add('off');
    document.getElementById('liveText').textContent = 'offline: ' + e.message;
  }
}
tick();
setInterval(tick, 5000);
setInterval(() => {
  document.querySelectorAll('.next').forEach(el => {
    el.textContent = fmtLeft(el.dataset.next);
  });
}, 1000);
</script>
</body>
</html>
