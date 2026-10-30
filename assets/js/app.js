const $ = (s, r = document) => r.querySelector(s);
const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const root = document.documentElement;

// smooth color transition when the mode / accent changes
let themingTimer;
function smoothTheme(fn) {
  root.classList.add('theming'); fn();
  clearTimeout(themingTimer); themingTimer = setTimeout(() => root.classList.remove('theming'), 450);
}

// ---------- dark / light mode ----------
document.querySelectorAll('.theme-toggle, .theme-toggle-text').forEach(b => b.addEventListener('click', () => {
  const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
  smoothTheme(() => { root.dataset.theme = next; });
  try { localStorage.setItem('theme', next); } catch (e) {}
}));

// ---------- theme color (color wheel + presets) ----------
const DEFAULT_ACCENT = '#1f7a4d';
const PRESETS = [['Green','#1f7a4d'],['Teal','#0f766e'],['Blue','#2563eb'],['Indigo','#4f46e5'],['Purple','#7c3aed'],
                 ['Pink','#db2777'],['Red','#dc2626'],['Orange','#ea580c'],['Gold','#b7791f'],['Slate','#475569']];
const textOn = hex => { const n = parseInt(hex.slice(1), 16);
  return (0.2126 * (n >> 16) + 0.7152 * ((n >> 8) & 255) + 0.0722 * (n & 255)) / 255 > 0.6 ? '#0b1410' : '#ffffff'; };
function applyAccent(hex, save, animate) {
  if (!/^#[0-9a-f]{6}$/i.test(hex)) return;
  const set = () => { root.style.setProperty('--accent', hex); root.style.setProperty('--on-primary', textOn(hex)); };
  animate ? smoothTheme(set) : set();
  const m = document.querySelector('meta[name=theme-color]'); if (m) m.content = hex;
  if (save) { try { localStorage.setItem('accent', hex); } catch (e) {} }
  const inp = $('#accent-input'); if (inp) inp.value = hex;
  document.querySelectorAll('.swatch').forEach(b => b.classList.toggle('on', b.dataset.c.toLowerCase() === hex.toLowerCase()));
}
const palBtn = $('#palette-btn'), palPop = $('#palette-pop');
if (palBtn && palPop) {
  const box = $('#swatches');
  PRESETS.forEach(([name, c]) => {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'swatch'; b.dataset.c = c;
    b.style.background = c; b.title = name; b.setAttribute('aria-label', name);
    b.addEventListener('click', () => applyAccent(c, true, true)); box.appendChild(b);
  });
  let saved = null; try { saved = localStorage.getItem('accent'); } catch (e) {}
  applyAccent(saved || DEFAULT_ACCENT, false, false);
  $('#accent-input').addEventListener('input', e => applyAccent(e.target.value, true, false));
  $('#accent-reset').addEventListener('click', () => {
    try { localStorage.removeItem('accent'); } catch (e) {}
    smoothTheme(() => { root.style.removeProperty('--accent'); root.style.removeProperty('--on-primary'); });
    applyAccent(DEFAULT_ACCENT, false, false);
  });
  const toggle = open => { palPop.hidden = !open; palBtn.setAttribute('aria-expanded', open); };
  palBtn.addEventListener('click', e => { e.stopPropagation(); toggle(palPop.hidden); });
  document.addEventListener('click', e => { if (!palPop.hidden && !palPop.contains(e.target)) toggle(false); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') toggle(false); });
}

// ---------- mobile menu ----------
const setMenu = open => document.body.classList.toggle('menu-open', open);
document.querySelectorAll('.js-menu').forEach(b => b.addEventListener('click', () => setMenu(!document.body.classList.contains('menu-open'))));
const overlay = $('#overlay'); if (overlay) overlay.addEventListener('click', () => setMenu(false));
document.querySelectorAll('.sidebar nav a').forEach(a => a.addEventListener('click', () => setMenu(false)));

// ---------- toast messages fade out by themselves ----------
document.querySelectorAll('.main > .flash').forEach(f => {
  setTimeout(() => { f.classList.add('out'); setTimeout(() => f.remove(), 400); }, f.classList.contains('flash-error') ? 7000 : 4500);
  f.addEventListener('click', () => f.remove());
});

// ---------- numbers count up once when the page opens ----------
if (!reduceMotion) {
  document.querySelectorAll('.num').forEach(el => {
    const m = el.textContent.trim().match(/^(\D*?)([\d,]*\.?\d+)(\D*)$/);
    if (!m) return;
    const target = parseFloat(m[2].replace(/,/g, '')); if (!target) return;
    const dec = (m[2].split('.')[1] || '').length, t0 = performance.now(), dur = 900;
    const fmt = v => m[1] + v.toLocaleString('en-PH', { minimumFractionDigits: dec, maximumFractionDigits: dec }) + m[3];
    const tick = now => {
      const p = Math.min(1, (now - t0) / dur), eased = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(target * eased);
      if (p < 1) requestAnimationFrame(tick); else el.textContent = fmt(target);
    };
    el.textContent = fmt(0); requestAnimationFrame(tick);
  });
}

// ---------- button ripple ----------
document.addEventListener('pointerdown', e => {
  const b = e.target.closest('.btn'); if (!b || b.disabled || reduceMotion) return;
  const r = b.getBoundingClientRect(), size = Math.max(r.width, r.height) * 1.2;
  const s = document.createElement('span'); s.className = 'ripple';
  s.style.cssText = `width:${size}px;height:${size}px;left:${e.clientX - r.left - size / 2}px;top:${e.clientY - r.top - size / 2}px`;
  b.appendChild(s); setTimeout(() => s.remove(), 650);
});

// ---------- show / hide password ----------
document.querySelectorAll('input[type=password]').forEach(inp => {
  const wrap = document.createElement('div'); wrap.className = 'pw-wrap';
  inp.parentNode.insertBefore(wrap, inp); wrap.appendChild(inp);
  const eye = document.createElement('button'); eye.type = 'button'; eye.className = 'pw-eye'; eye.setAttribute('aria-label', 'Show password');
  eye.innerHTML = '<svg class="i" viewBox="0 0 24 24"><use href="#i-eye"/></svg>';
  eye.addEventListener('click', () => { const show = inp.type === 'password'; inp.type = show ? 'text' : 'password'; eye.setAttribute('aria-label', show ? 'Hide password' : 'Show password'); });
  wrap.appendChild(eye);
});

// ---------- dialogs (inventory + sales) ----------
document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => b.closest('dialog').close()));
document.querySelectorAll('dialog').forEach(d => d.addEventListener('click', e => { if (e.target === d) d.close(); }));

document.querySelectorAll('[data-open]').forEach(btn => btn.addEventListener('click', () => {
  const kind = btn.dataset.open;
  const p = btn.dataset.p ? JSON.parse(btn.dataset.p) : null;
  if (kind === 'add' || kind === 'edit') {
    $('#pf-action').value = kind;
    $('#pf-title').textContent = kind === 'add' ? 'Add product' : 'Edit product';
    $('#pf-stock-wrap').style.display = kind === 'add' ? '' : 'none';
    $('#pf-id').value = p ? p.id : '';
    $('#pf-name').value = p ? p.name : '';
    $('#pf-cat').value = p ? p.cat : $('#pf-cat').options[0].value;
    $('#pf-price').value = p ? p.price : '';
    $('#pf-low').value = p ? p.low : 10;
    $('#pf-high').value = p ? p.high : 60;
    $('#dlg-product').showModal();
  } else if (kind === 'stock') {
    $('#sf-id').value = p.id; $('#sf-name').textContent = p.name; $('#sf-cur').textContent = p.stock;
    $('#dlg-stock').showModal();
  } else if (kind === 'sale') {
    $('#sale-rows').innerHTML = ''; addSaleRow(); calcTotal();
    $('#dlg-sale').showModal();
  }
}));

// ---------- inventory: live search + filters (no Enter / reload needed) ----------
const invRows = [...document.querySelectorAll('#inv-body tr[data-search]')];
if (invRows.length) {
  const norm = s => (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const fq = $('#f-q'), fc = $('#f-cat'), fs = $('#f-status'), none = $('#inv-none'), count = $('#inv-count');
  const apply = () => {
    const q = norm(fq.value.trim()), c = fc.value, s = fs.value;
    let n = 0;
    invRows.forEach(r => {
      const ok = (!q || norm(r.dataset.search).includes(q)) && (c === '0' || r.dataset.cat === c) && (!s || r.dataset.status === s);
      r.hidden = !ok; if (ok) n++;
    });
    none.hidden = n > 0;
    count.textContent = 'Showing ' + n + ' of ' + invRows.length + ' products';
  };
  ['input', 'change', 'search', 'keyup'].forEach(ev => [fq, fc, fs].forEach(el => el.addEventListener(ev, apply)));
  $('#inv-filters').addEventListener('submit', e => { e.preventDefault(); apply(); });
  apply();
}

// ---------- quick quantity chips (stock dialog) ----------
document.querySelectorAll('.qty-chips button').forEach(b => b.addEventListener('click', () => {
  const q = $('#sf-qty'); if (!q) return;
  q.value = (parseInt(q.value) || 0) + parseInt(b.dataset.q); q.focus();
}));

// ---------- press "/" to jump to the search box ----------
document.addEventListener('keydown', e => {
  if (e.key !== '/' || e.ctrlKey || e.metaKey || /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName)) return;
  const f = $('#f-q') || $('.top-search input'); if (f) { e.preventDefault(); f.focus(); f.select(); }
});

// ---------- sale rows ----------
function addSaleRow() {
  const wrap = $('#sale-rows'); if (!wrap) return;
  const row = document.createElement('div'); row.className = 'sale-row';
  const sel = document.createElement('select'); sel.name = 'product_id[]'; sel.required = true;
  sel.innerHTML = '<option value="">Select product...</option>' + window.PRODUCTS.map(p =>
    `<option value="${p.product_id}" data-price="${p.unit_price}">${p.product_name} (stock ${p.stock_quantity})</option>`).join('');
  const qty = document.createElement('input'); qty.type = 'number'; qty.name = 'qty[]'; qty.min = 1; qty.value = 1; qty.required = true;
  const del = document.createElement('button'); del.type = 'button'; del.className = 'btn btn-sm'; del.textContent = '✕'; del.setAttribute('aria-label', 'Remove row');
  del.onclick = () => { if (wrap.children.length > 1) { row.remove(); calcTotal(); } };
  sel.onchange = qty.oninput = calcTotal;
  row.append(sel, qty, del); wrap.appendChild(row);
}
function calcTotal() {
  let t = 0;
  document.querySelectorAll('.sale-row').forEach(r => {
    const o = r.querySelector('select').selectedOptions[0];
    t += (o && o.dataset.price ? parseFloat(o.dataset.price) : 0) * (parseInt(r.querySelector('input').value) || 0);
  });
  const el = $('#sale-total'); if (el) el.textContent = '₱' + t.toLocaleString('en-PH', { minimumFractionDigits: 2 });
}
if ($('#add-row')) $('#add-row').addEventListener('click', () => { addSaleRow(); calcTotal(); });

// ---------- reports: print ----------
const pb = $('#print-btn');
if (pb) pb.addEventListener('click', () => {
  fetch(`reports.php?type=${pb.dataset.type}&from=${pb.dataset.from}&to=${pb.dataset.to}&log=pdf`).finally(() => window.print());
});

// ---------- AI chat ----------
const chat = $('#chat');
if (chat) {
  const csrf = document.querySelector('meta[name=csrf]').content;
  const add = (text, cls, engine, note) => {
    const d = document.createElement('div'); d.className = 'msg ' + cls; d.textContent = text;
    if (engine) { const s = document.createElement('small'); s.textContent = 'Answered by ' + engine; d.appendChild(s); }
    if (note) { const n = document.createElement('small'); n.textContent = note; d.appendChild(n); }
    chat.appendChild(d); chat.scrollTop = chat.scrollHeight; return d;
  };
  const ask = async q => {
    q = q.trim(); if (!q) return;
    add(q, 'me');
    const wait = document.createElement('div'); wait.className = 'msg bot typing'; wait.innerHTML = '<span></span><span></span><span></span>';
    chat.appendChild(wait); chat.scrollTop = chat.scrollHeight;
    try {
      const r = await fetch('ai_chat.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf }, body: JSON.stringify({ q }) });
      const j = await r.json(); wait.remove(); add(j.answer, 'bot', j.engine, j.note);
    } catch (e) { wait.remove(); add('Something went wrong. Please try again.', 'bot'); }
  };
  $('#chat-form').addEventListener('submit', e => { e.preventDefault(); const i = $('#chat-input'); ask(i.value); i.value = ''; });
  document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => ask(c.textContent)));
}
