/* ================================================
   Repo Delivery — SPA para repartidores
   Auto-refresh cada 40 s. Mobile-first.
   ================================================ */

const API_PEDIDOS = 'api/pedidos';
const API_AUTH    = 'api/auth';
const REFRESH_MS  = 40000;

const state = {
  listos:      [],
  entregados:  [],
  stats:       {},
  seccion:     'inicio',
  sonido:      false,
  idsConocidos: new Set(),
};

// ─── Boot ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initTema();
  initSonido();
  cargar(true);
  setInterval(cargar, REFRESH_MS);
});

function initTema() {
  const t = localStorage.getItem('deliveryTema') || 'light';
  document.documentElement.setAttribute('data-theme', t);
  const toggle = document.getElementById('temaToggle');
  if (toggle) toggle.checked = (t === 'dark');
}

function toggleTema() {
  const dark = document.documentElement.getAttribute('data-theme') === 'dark';
  const nuevo = dark ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', nuevo);
  localStorage.setItem('deliveryTema', nuevo);
}

function initSonido() {
  state.sonido = localStorage.getItem('deliverySonido') === '1';
  actualizarToggleSonido();
}

function toggleSonido() {
  state.sonido = !state.sonido;
  localStorage.setItem('deliverySonido', state.sonido ? '1' : '0');
  actualizarToggleSonido();
}

function actualizarToggleSonido() {
  const t = document.getElementById('sonidoToggle');
  if (t) t.checked = state.sonido;
}

// ─── API ───────────────────────────────────────────
async function cargar(inicial = false) {
  if (inicial) mostrarLoading(true);
  const btnR = document.getElementById('btnRefresh');
  if (btnR) btnR.querySelector('i').classList.add('fa-spin');

  try {
    const r = await fetch(API_PEDIDOS, { credentials: 'include' });
    if (r.status === 401) { location.href = 'login.php'; return; }
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');

    detectarNuevos(data.listos);
    state.listos     = data.listos    || [];
    state.entregados = data.entregados|| [];
    state.stats      = data.stats     || {};
    state.idsConocidos = new Set(state.listos.map(p => p.id));

    renderAll();
    actualizarHora();
  } catch (e) {
    if (inicial) toast('Error al cargar pedidos');
  } finally {
    mostrarLoading(false);
    if (btnR) btnR.querySelector('i').classList.remove('fa-spin');
  }
}

function detectarNuevos(listos) {
  if (state.idsConocidos.size === 0) return;
  listos.forEach(p => {
    if (!state.idsConocidos.has(p.id)) {
      toast(`🛒 Nuevo pedido: ${p.numero}`);
      if (state.sonido) beep();
      notificarDesktop(p);
    }
  });
}

function beep() {
  try {
    const ctx = new (window.AudioContext || window.webkitAudioContext)();
    const o = ctx.createOscillator();
    const g = ctx.createGain();
    o.connect(g); g.connect(ctx.destination);
    o.type = 'sine'; o.frequency.value = 880;
    g.gain.setValueAtTime(.5, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + .6);
    o.start(); o.stop(ctx.currentTime + .6);
  } catch (_) {}
}

function notificarDesktop(p) {
  if (!('Notification' in window) || Notification.permission !== 'granted') return;
  new Notification('Nuevo pedido', {
    body: `${p.numero} — ${p.cliente} — $${fmt(p.total)}`,
    icon: 'favicon/android-icon-96x96.png',
  });
}

async function marcarEntregado(id) {
  const btn = document.querySelector(`[data-deliver="${id}"]`);
  if (btn) btn.disabled = true;

  try {
    const r = await fetch(API_PEDIDOS, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id, estado: 'entregado' }),
    });
    if (r.status === 401) { location.href = 'login.php'; return; }
    const data = await r.json();
    if (!data.ok) throw new Error(data.error || 'Error');

    // Mover de listos → entregados localmente sin esperar refresh
    const idx = state.listos.findIndex(p => p.id === id);
    if (idx !== -1) {
      const p = { ...state.listos[idx], estado: 'entregado', entregado_at: new Date().toISOString() };
      state.listos.splice(idx, 1);
      state.entregados.unshift(p);
    }
    renderAll();
    toast('✅ Marcado como entregado');
  } catch (e) {
    toast('Error: ' + e.message);
    if (btn) btn.disabled = false;
  }
}

async function logout() {
  await fetch(API_AUTH, { method: 'DELETE', credentials: 'include' });
  location.href = 'login.php';
}

// ─── Render ────────────────────────────────────────
function renderAll() {
  renderDashboard();
  renderPendientes();
  renderHistorial();
  actualizarBadges();
}

function renderDashboard() {
  const numP = document.getElementById('dashNumPending');
  const numD = document.getElementById('dashNumDelivered');
  if (numP) numP.textContent = state.listos.length;
  if (numD) numD.textContent = state.entregados.length;

  const contP  = document.getElementById('dashListaPending');
  const emptyP = document.getElementById('dashEmptyPending');
  if (contP) {
    const slice = state.listos.slice(0, 3);
    if (!slice.length) {
      contP.innerHTML = '';
      if (emptyP) emptyP.style.display = '';
    } else {
      if (emptyP) emptyP.style.display = 'none';
      contP.innerHTML = slice.map(p => dashCardPending(p)).join('');
    }
  }

  const contD  = document.getElementById('dashListaDelivered');
  const emptyD = document.getElementById('dashEmptyDelivered');
  if (contD) {
    const slice = state.entregados.slice(0, 3);
    if (!slice.length) {
      contD.innerHTML = '';
      if (emptyD) emptyD.style.display = '';
    } else {
      if (emptyD) emptyD.style.display = 'none';
      contD.innerHTML = slice.map(p => dashCardDelivered(p)).join('');
    }
  }
}

function dashCardPending(p) {
  return `
    <div class="dash-card" onclick="ir('pendientes')">
      <div class="dash-card-strip"></div>
      <div class="dash-card-body">
        <div class="dash-card-top">
          <span class="dash-card-num">${esc(p.numero)}</span>
          <span class="dash-card-time">${tiempoRelativo(p.fecha)}</span>
        </div>
        <div class="dash-card-bottom">
          <span class="dash-card-cliente">${esc(p.cliente)}</span>
          <span class="dash-card-total">$${fmt(p.total)}</span>
        </div>
      </div>
    </div>`;
}

function dashCardDelivered(p) {
  const hora = p.entregado_at ? formatHora(p.entregado_at) : '';
  return `
    <div class="dash-card dash-card--delivered" onclick="ir('historial')">
      <div class="dash-card-strip dash-card-strip--ok"></div>
      <div class="dash-card-body">
        <div class="dash-card-top">
          <span class="dash-card-num">${esc(p.numero)}</span>
          ${hora ? `<span class="dash-card-time">${hora}</span>` : ''}
        </div>
        <div class="dash-card-bottom">
          <span class="dash-card-cliente">${esc(p.cliente)}</span>
          <span class="dash-card-total">$${fmt(p.total)}</span>
        </div>
      </div>
    </div>`;
}

function renderPendientes() {
  const cont = document.getElementById('listaPendientes');
  const empty = document.getElementById('emptyPendientes');
  if (!cont) return;

  if (!state.listos.length) {
    cont.innerHTML  = '';
    empty.style.display = '';
    return;
  }
  empty.style.display = 'none';
  cont.innerHTML = state.listos.map(p => cardListo(p)).join('');
}

function renderHistorial() {
  const cont  = document.getElementById('listaHistorial');
  const empty = document.getElementById('emptyHistorial');
  if (!cont) return;

  if (!state.entregados.length) {
    cont.innerHTML = '';
    empty.style.display = '';
    return;
  }
  empty.style.display = 'none';
  cont.innerHTML = state.entregados.map(p => cardEntregado(p)).join('');
}

function cardListo(p) {
  const mapsUrl = mapaUrl(p);
  const itemsHtml = (p.items || []).map(it => `
    <div class="card-item-row">
      <span class="card-item-qty">${it.cantidad}x</span>
      <span class="card-item-name">${esc(it.nombre)}</span>
      <span class="card-item-price">$${fmt(it.precio * it.cantidad)}</span>
    </div>
  `).join('');

  return `
    <div class="order-card">
      <div class="card-strip"></div>
      <div class="card-body">
        <div class="card-row1">
          <span class="card-num">${esc(p.numero)}</span>
          <span class="card-time">${tiempoRelativo(p.fecha)}</span>
        </div>
        <div class="card-cliente">${esc(p.cliente)}</div>
        ${p.direccion ? `
        <div class="card-address">
          <i class="fa-solid fa-location-dot"></i>
          <span>${esc(p.direccion)}</span>
        </div>` : ''}
        ${p.celular ? `
        <div class="card-phone">
          <i class="fa-solid fa-phone"></i>
          <a href="tel:${esc(p.celular)}">${esc(p.celular)}</a>
        </div>` : ''}
        ${p.notas ? `<div class="card-address"><i class="fa-solid fa-note-sticky"></i><span>${esc(p.notas)}</span></div>` : ''}
        ${itemsHtml ? `<div class="card-items">${itemsHtml}</div>` : ''}
        ${p.distancia_km ? `
        <div class="card-address" style="margin-bottom:10px">
          <i class="fa-solid fa-route"></i>
          <span>${p.distancia_km} km · ~${p.tiempo_min} min</span>
        </div>` : ''}
        <div class="card-footer">
          <span class="card-total">$${fmt(p.total)}</span>
          <div class="card-actions">
            <a class="btn-map" href="${mapsUrl}" target="_blank" rel="noopener">
              <i class="fa-solid fa-map-location-dot"></i> Mapa
            </a>
            <button class="btn-deliver" data-deliver="${p.id}" onclick="marcarEntregado(${p.id})">
              <i class="fa-solid fa-check"></i> Entregado
            </button>
          </div>
        </div>
      </div>
    </div>`;
}

function cardEntregado(p) {
  const hora = p.entregado_at ? formatHora(p.entregado_at) : '';
  return `
    <div class="order-card card-entregado">
      <div class="card-strip entregado"></div>
      <div class="card-body">
        <div class="card-row1">
          <span class="card-num">${esc(p.numero)}</span>
          <span class="badge-entregado"><i class="fa-solid fa-check"></i> Entregado${hora ? ' · ' + hora : ''}</span>
        </div>
        <div class="card-cliente">${esc(p.cliente)}</div>
        ${p.direccion ? `<div class="card-address"><i class="fa-solid fa-location-dot"></i><span>${esc(p.direccion)}</span></div>` : ''}
        <div class="card-footer">
          <span class="card-total">$${fmt(p.total)}</span>
        </div>
      </div>
    </div>`;
}

function actualizarBadges() {
  const n = state.listos.length;
  const badge = document.getElementById('badgePendientes');
  if (badge) {
    badge.textContent  = n;
    badge.style.display = n > 0 ? '' : 'none';
  }

  const countPend = document.getElementById('countPendientes');
  const countHist = document.getElementById('countHistorial');
  if (countPend) {
    countPend.textContent = n;
    countPend.className   = 'section-count' + (n === 0 ? ' empty' : '');
  }
  if (countHist) {
    countHist.textContent = state.entregados.length;
    countHist.className   = 'section-count' + (state.entregados.length === 0 ? ' empty' : '');
  }
}

// ─── Nav ───────────────────────────────────────────
function ir(seccion) {
  state.seccion = seccion;
  document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));

  const sec = document.getElementById('sec-' + seccion);
  const tab = document.getElementById('tab-' + seccion);
  if (sec) sec.classList.add('active');
  if (tab) tab.classList.add('active');
}

// ─── Helpers ───────────────────────────────────────
function mapaUrl(p) {
  if (p.lat && p.lng) {
    return `https://www.google.com/maps/dir/?api=1&destination=${p.lat},${p.lng}&travelmode=driving`;
  }
  if (p.direccion) {
    return `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(p.direccion)}&travelmode=driving`;
  }
  return '#';
}

function fmt(n) {
  return Number(n).toLocaleString('es-AR', { minimumFractionDigits: 0 });
}

function esc(str) {
  if (!str) return '';
  return String(str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;')
    .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function tiempoRelativo(fechaStr) {
  if (!fechaStr) return '';
  const diff = Math.floor((Date.now() - new Date(fechaStr)) / 1000);
  if (diff < 60)   return 'hace un momento';
  if (diff < 3600) return `hace ${Math.floor(diff / 60)} min`;
  return `hace ${Math.floor(diff / 3600)} h`;
}

function formatHora(fechaStr) {
  try {
    return new Date(fechaStr).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });
  } catch (_) { return ''; }
}

function actualizarHora() {
  const el = document.getElementById('lastUpdate');
  if (el) {
    el.textContent = new Date().toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  }
}

function mostrarLoading(show) {
  document.getElementById('loadingOverlay').classList.toggle('show', show);
}

let toastTimer;
function toast(msg) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}

// Pedir permiso notificaciones al interactuar
document.addEventListener('click', () => {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }
}, { once: true });
