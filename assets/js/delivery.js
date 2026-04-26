/* ================================================
   Repo Delivery — SPA para repartidores
   Auto-refresh cada 40 s. Mobile-first.
   ================================================ */

/* ===== PWA Install — capturado lo antes posible ===== */
let _pwaPrompt = null;
window.addEventListener('beforeinstallprompt', e => {
  e.preventDefault();
  _pwaPrompt = e;
  const btn = document.getElementById('btnInstall');
  if (btn) btn.style.display = '';
});
window.addEventListener('appinstalled', () => {
  _pwaPrompt = null;
  const btn = document.getElementById('btnInstall');
  if (btn) btn.style.display = 'none';
});

async function pwaInstall() {
  if (!_pwaPrompt) return;
  _pwaPrompt.prompt();
  const { outcome } = await _pwaPrompt.userChoice;
  if (outcome === 'accepted') {
    _pwaPrompt = null;
    const btn = document.getElementById('btnInstall');
    if (btn) btn.style.display = 'none';
  }
}

const API_PEDIDOS = 'api/pedidos';
const API_AUTH    = 'api/auth';
const REFRESH_MS  = 40000;

const state = {
  disponibles:  [],
  listos:       [],
  entregados:   [],
  stats:        {},
  seccion:      'inicio',
  sonido:       false,
  idsConocidos: new Set(),
};

// ─── Boot ──────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  initTema();
  initSonido();
  initSeguimiento();
  initHeartbeat();
  cargar(true);
  setInterval(cargar, REFRESH_MS);
});

// ─── Heartbeat "en línea" ──────────────────────────
const HEARTBEAT_MS = 30000;
let heartbeatTimer = null;

function initHeartbeat() {
  // Arranca o pausa según la visibilidad actual
  aplicarHeartbeat();
  document.addEventListener('visibilitychange', aplicarHeartbeat);
}

function aplicarHeartbeat() {
  if (document.hidden) {
    if (heartbeatTimer) { clearInterval(heartbeatTimer); heartbeatTimer = null; }
    return;
  }
  if (heartbeatTimer) return; // ya activo
  enviarHeartbeat();
  heartbeatTimer = setInterval(enviarHeartbeat, HEARTBEAT_MS);
}

async function enviarHeartbeat() {
  try {
    await fetch('api/heartbeat', { method: 'POST', credentials: 'include' });
  } catch (_) { /* silencioso */ }
}

// ─── Seguimiento GPS ───────────────────────────────
const SEGUIMIENTO_MIN_MS = 30000;   // no enviar más seguido que esto
let geoWatchId          = null;
let geoLastSentAt       = 0;
let geoLastPos          = null;

function initSeguimiento() {
  // Por defecto desactivado (requiere consentimiento explícito + permiso de GPS)
  const activo = localStorage.getItem('deliverySeguimiento') === '1';
  const toggle = document.getElementById('seguimientoToggle');
  if (toggle) toggle.checked = activo;
  if (activo) iniciarSeguimiento();
}

async function toggleSeguimiento() {
  const toggle = document.getElementById('seguimientoToggle');
  const activo = !!(toggle && toggle.checked);

  if (activo) {
    const ok = await iniciarSeguimiento();
    if (!ok) {
      if (toggle) toggle.checked = false;
      return;
    }
    notificarSeguimientoActivado();
    localStorage.setItem('deliverySeguimiento', '1');
    toast('📡 Seguimiento activado');
  } else {
    detenerSeguimiento();
    localStorage.setItem('deliverySeguimiento', '0');
    notificarSeguimientoApagado();
    toast('⏸ Seguimiento desactivado');
  }
}

async function notificarSeguimientoActivado() {
  try {
    await fetch('api/ubicacion', {
      method: 'PUT',
      credentials: 'include',
    });
  } catch (_) { /* silencioso */ }
}

async function notificarSeguimientoApagado() {
  try {
    await fetch('api/ubicacion', {
      method: 'DELETE',
      credentials: 'include',
      keepalive: true, // garantiza que se complete aunque se navegue de página
    });
  } catch (_) { /* silencioso */ }
}

async function iniciarSeguimiento() {
  if (!('geolocation' in navigator)) {
    toast('Este dispositivo no soporta geolocalización');
    return false;
  }
  if (geoWatchId !== null) return true; // ya activo

  try {
    geoWatchId = navigator.geolocation.watchPosition(
      (pos) => {
        geoLastPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
        const ahora = Date.now();
        if (ahora - geoLastSentAt >= SEGUIMIENTO_MIN_MS) {
          geoLastSentAt = ahora;
          enviarUbicacion(geoLastPos.lat, geoLastPos.lng);
        }
      },
      (err) => {
        console.warn('geoWatch error', err);
        if (err.code === err.PERMISSION_DENIED) {
          toast('Permiso de ubicación denegado');
          detenerSeguimiento();
          const toggle = document.getElementById('seguimientoToggle');
          if (toggle) toggle.checked = false;
          localStorage.setItem('deliverySeguimiento', '0');
        }
      },
      { enableHighAccuracy: true, maximumAge: 10000, timeout: 20000 }
    );
    return true;
  } catch (e) {
    console.error('iniciarSeguimiento error', e);
    toast('No se pudo iniciar el seguimiento');
    return false;
  }
}

function detenerSeguimiento() {
  if (geoWatchId !== null) {
    try { navigator.geolocation.clearWatch(geoWatchId); } catch (_) {}
    geoWatchId = null;
  }
  geoLastPos = null;
  geoLastSentAt = 0;
}

async function enviarUbicacion(lat, lng) {
  if (geoWatchId === null) return; // Seguimiento apagado entre el callback y este fetch
  try {
    await fetch('api/ubicacion', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ lat, lng }),
    });
  } catch (e) {
    // Silencioso: si no hay red, reintentará con el próximo tick
    console.warn('enviarUbicacion error', e);
  }
}

const THEME_COLORS = { light: '#ffffff', dark: '#242b35' };

function applyThemeColor(t) {
  const meta = document.getElementById('metaThemeColor');
  if (meta) meta.setAttribute('content', THEME_COLORS[t] || THEME_COLORS.light);
}

function initTema() {
  const t = localStorage.getItem('deliveryTema') || 'light';
  document.documentElement.setAttribute('data-theme', t);
  const toggle = document.getElementById('temaToggle');
  if (toggle) toggle.checked = (t === 'dark');
  applyThemeColor(t);
}

function toggleTema() {
  const dark = document.documentElement.getAttribute('data-theme') === 'dark';
  const nuevo = dark ? 'light' : 'dark';
  document.documentElement.setAttribute('data-theme', nuevo);
  localStorage.setItem('deliveryTema', nuevo);
  applyThemeColor(nuevo);
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

    detectarNuevos(data.disponibles || []);
    state.disponibles = data.disponibles || [];
    state.listos      = data.listos      || [];
    state.entregados  = data.entregados  || [];
    state.stats       = data.stats       || {};
    state.idsConocidos = new Set(state.disponibles.map(p => p.id));

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

function logout() {
  location.href = 'logout.php';
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
  if (numP) numP.textContent = state.disponibles.length;
  if (numD) numD.textContent = state.entregados.length;

  // Pedidos disponibles para tomar
  const contP  = document.getElementById('dashListaPending');
  const emptyP = document.getElementById('dashEmptyPending');
  if (contP) {
    if (!state.disponibles.length) {
      contP.innerHTML = '';
      if (emptyP) emptyP.style.display = '';
    } else {
      if (emptyP) emptyP.style.display = 'none';
      contP.innerHTML = state.disponibles.map(p => dashCardDisponible(p)).join('');
    }
  }

}

function dashCardDisponible(p) {
  const dir = p.direccion
    ? `<div class="dash-card-addr"><i class="fa-solid fa-location-dot"></i> ${esc(p.direccion)}</div>`
    : '';

  const MAPS_KEY = window.MAPS_KEY || '';
  let mapaHtml = '';
  const q = (p.lat && p.lng) ? `${p.lat},${p.lng}`
          : p.direccion     ? encodeURIComponent(p.direccion)
          : null;
  if (q) {
    const src = `https://www.google.com/maps/embed/v1/place?key=${MAPS_KEY}&q=${q}&zoom=16`;
    mapaHtml = `
      <div class="card-map-wrap dash-card-map">
        <iframe class="card-map-frame" src="${src}" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
      </div>`;
  }

  const distTxt = p.distancia_km ? `${p.distancia_km} km` : '— km';
  const tiempoTxt = p.tiempo_min ? `~${p.tiempo_min} min` : '~— min';

  return `
    <div class="dash-card dash-card--disponible">
      <div class="dash-card-strip"></div>
      <div class="dash-card-body">
        <div class="dash-card-top">
          <span class="dash-card-num">${esc(p.numero)}</span>
          <span class="dash-card-time">${tiempoRelativo(p.fecha)}</span>
        </div>
        <div class="dash-card-travel">
          <span><i class="fa-solid fa-route"></i> ${distTxt}</span>
          <span><i class="fa-solid fa-clock"></i> ${tiempoTxt}</span>
        </div>
        ${dir}
        ${mapaHtml}
        <button class="btn-ver-pedido" onclick="event.stopPropagation();verPedidoDisponible(${p.id})">
          <i class="fa-solid fa-eye"></i> Ver pedido
        </button>
        <button class="btn-tomar" data-tomar="${p.id}" onclick="event.stopPropagation();tomarPedido(${p.id})">
          <i class="fa-solid fa-hand"></i> Tomar pedido
        </button>
      </div>
    </div>`;
}

function verPedidoDisponible(id) {
  const p = state.disponibles.find(x => x.id === id);
  if (!p) { toast('El pedido ya no está disponible'); return; }

  const MAPS_KEY = window.MAPS_KEY || '';
  const q = (p.lat && p.lng) ? `${p.lat},${p.lng}`
          : p.direccion     ? encodeURIComponent(p.direccion)
          : null;
  const mapaHtml = q ? `
    <div class="card-map-wrap">
      <iframe class="card-map-frame" src="https://www.google.com/maps/embed/v1/place?key=${MAPS_KEY}&q=${q}&zoom=16" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
    </div>` : '';

  const itemsHtml = (p.items || []).map(it => `
    <div class="pm-item">
      <span class="pm-item-qty">${it.cantidad}x</span>
      <span class="pm-item-name">${esc(it.nombre)}</span>
      <span class="pm-item-price">$${fmt(it.precio * it.cantidad)}</span>
    </div>`).join('');

  const info = [
    p.celular   ? `<div class="pm-row"><i class="fa-solid fa-phone"></i><a href="tel:${esc(p.celular)}">${esc(p.celular)}</a></div>` : '',
    p.correo    ? `<div class="pm-row"><i class="fa-solid fa-envelope"></i><span>${esc(p.correo)}</span></div>` : '',
    p.direccion ? `<div class="pm-row"><i class="fa-solid fa-location-dot"></i><span>${esc(p.direccion)}</span></div>` : '',
    p.distancia_km ? `<div class="pm-row"><i class="fa-solid fa-route"></i><span>${p.distancia_km} km · ~${p.tiempo_min} min</span></div>` : '',
    p.notas     ? `<div class="pm-row"><i class="fa-solid fa-note-sticky"></i><span>${esc(p.notas)}</span></div>` : '',
  ].filter(Boolean).join('');

  document.getElementById('pedidoModalTitle').textContent = p.numero || 'Pedido';
  document.getElementById('pedidoModalBody').innerHTML = `
    <div class="pm-block">
      <div class="pm-block-title">Cliente</div>
      <div class="pm-row"><i class="fa-solid fa-user"></i><span>${esc(p.cliente || '—')}</span></div>
      ${info}
      <div class="pm-row"><i class="fa-solid fa-clock"></i><span>${tiempoRelativo(p.fecha)}</span></div>
    </div>
    ${mapaHtml ? `<div class="pm-block"><div class="pm-block-title">Ubicación</div>${mapaHtml}</div>` : ''}
    <div class="pm-block">
      <div class="pm-block-title">Productos</div>
      <div class="pm-items">${itemsHtml || '<div class="pm-item"><span></span><span>Sin productos</span><span></span></div>'}</div>
      <div class="pm-total-row"><span>Total</span><span>$${fmt(p.total)}</span></div>
    </div>
    <button class="btn-tomar" data-tomar-modal="${p.id}" onclick="tomarPedido(${p.id})">
      <i class="fa-solid fa-hand"></i> Tomar pedido
    </button>`;

  document.getElementById('pedidoModal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closePedidoModal() {
  document.getElementById('pedidoModal').classList.remove('open');
  document.body.style.overflow = '';
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

async function tomarPedido(id) {
  const botones = document.querySelectorAll(`[data-tomar="${id}"], [data-tomar-modal="${id}"]`);
  botones.forEach(b => { b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Tomando…'; });

  try {
    const r = await fetch(API_PEDIDOS, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'include',
      body: JSON.stringify({ id, accion: 'tomar' }),
    });
    if (r.status === 401) { location.href = 'login.php'; return; }
    const data = await r.json();
    if (data.ok) {
      toast('✅ Pedido tomado — aparece en "Para entregar"');
      state.disponibles = state.disponibles.filter(p => p.id !== id);
      closePedidoModal();
      renderDashboard();
      actualizarBadges();
    } else {
      toast('⚠️ ' + (data.error || 'No se pudo tomar el pedido'));
      botones.forEach(b => { b.disabled = false; b.innerHTML = '<i class="fa-solid fa-hand"></i> Tomar pedido'; });
    }
  } catch (e) {
    toast('Error de conexión');
    botones.forEach(b => { b.disabled = false; b.innerHTML = '<i class="fa-solid fa-hand"></i> Tomar pedido'; });
  }
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

const ESTADO_BADGE = {
  pendiente:   { label: 'Pendiente',   color: 'var(--info)',    strip: '#3b82f6' },
  preparacion: { label: 'Preparación', color: 'var(--primary)', strip: 'var(--primary)' },
  asignacion:  { label: 'Asignación',  color: '#8b5cf6',        strip: '#8b5cf6' },
  reparto:     { label: 'En reparto',  color: 'var(--success)', strip: 'var(--success)' },
};

function cardListo(p) {
  const est      = ESTADO_BADGE[p.estado] || ESTADO_BADGE.pendiente;
  const mapsUrl  = mapaUrl(p);
  const listo    = p.estado === 'reparto';

  const itemsHtml = (p.items || []).map(it => `
    <div class="card-item-row">
      <span class="card-item-qty">${it.cantidad}x</span>
      <span class="card-item-name">${esc(it.nombre)}</span>
      <span class="card-item-price">$${fmt(it.precio * it.cantidad)}</span>
    </div>`).join('');

  // Mapa embebido Google Maps
  const MAPS_KEY = window.MAPS_KEY || '';
  let mapaHtml = '';
  if (p.lat && p.lng) {
    const q   = `${p.lat},${p.lng}`;
    const src = `https://www.google.com/maps/embed/v1/place?key=${MAPS_KEY}&q=${q}&zoom=16`;
    mapaHtml = `
      <div class="card-map-wrap">
        <iframe class="card-map-frame" src="${src}" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
        <a class="card-map-open" href="${mapsUrl}" target="_blank" rel="noopener">
          <i class="fa-solid fa-up-right-from-square"></i> Abrir en Google Maps
        </a>
      </div>`;
  } else if (p.direccion) {
    const q   = encodeURIComponent(p.direccion);
    const src = `https://www.google.com/maps/embed/v1/place?key=${MAPS_KEY}&q=${q}&zoom=16`;
    mapaHtml = `
      <div class="card-map-wrap">
        <iframe class="card-map-frame" src="${src}" loading="lazy" allowfullscreen referrerpolicy="no-referrer-when-downgrade"></iframe>
        <a class="card-map-open" href="${mapsUrl}" target="_blank" rel="noopener">
          <i class="fa-solid fa-up-right-from-square"></i> Abrir en Google Maps
        </a>
      </div>`;
  }

  return `
    <div class="order-card">
      <div class="card-strip" style="background:${est.strip}"></div>
      <div class="card-body">

        <div class="card-row1">
          <span class="card-num">${esc(p.numero)}</span>
          <span class="card-estado-badge" style="color:${est.color};background:${est.color}18">${est.label}</span>
        </div>
        <div class="card-time" style="margin-bottom:8px">${tiempoRelativo(p.fecha)}</div>

        <div class="card-cliente">${esc(p.cliente)}</div>

        ${p.celular ? `
        <div class="card-phone">
          <i class="fa-solid fa-phone"></i>
          <a href="tel:${esc(p.celular)}">${esc(p.celular)}</a>
        </div>` : ''}

        ${p.direccion ? `
        <div class="card-address">
          <i class="fa-solid fa-location-dot"></i>
          <span>${esc(p.direccion)}</span>
        </div>` : ''}

        ${p.distancia_km ? `
        <div class="card-address">
          <i class="fa-solid fa-route"></i>
          <span>${p.distancia_km} km · ~${p.tiempo_min} min</span>
        </div>` : ''}

        ${p.notas ? `
        <div class="card-address">
          <i class="fa-solid fa-note-sticky"></i>
          <span>${esc(p.notas)}</span>
        </div>` : ''}

        ${mapaHtml}

        ${itemsHtml ? `<div class="card-items">${itemsHtml}</div>` : ''}

        <div class="card-footer">
          <span class="card-total">$${fmt(p.total)}</span>
          <button class="btn-deliver" data-deliver="${p.id}"
            onclick="marcarEntregado(${p.id})"
            ${listo ? '' : 'disabled title="Esperá a que el pedido esté listo"'}>
            <i class="fa-solid fa-check"></i> ${listo ? 'Entregado' : 'Esperando…'}
          </button>
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
  const nListos = state.listos.length;
  const nDisp   = state.disponibles.length;

  // Badge tab "Para entregar" (pedidos listos asignados a mí)
  const badge = document.getElementById('badgePendientes');
  if (badge) {
    badge.textContent   = nListos;
    badge.style.display = nListos > 0 ? '' : 'none';
  }

  // Badge tab "Inicio" (pedidos disponibles para tomar)
  const badgeInicio = document.getElementById('badgeInicio');
  if (badgeInicio) {
    badgeInicio.textContent   = nDisp;
    badgeInicio.style.display = nDisp > 0 ? '' : 'none';
  }

  const countPend = document.getElementById('countPendientes');
  const countHist = document.getElementById('countHistorial');
  if (countPend) {
    countPend.textContent = nListos;
    countPend.className   = 'section-count' + (nListos === 0 ? ' empty' : '');
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

// El permiso de notificaciones se pide explícitamente desde el toggle
// en el perfil (push.js), no de manera automática al primer click.

/* ===== Campana de notificaciones ===== */
const NOTIF_API   = 'api/notificaciones.php';
let   notifData   = [];
let   notifPanelOpen = false;
let   notifPollTimer = null;

async function fetchNotifCount() {
  try {
    const res  = await fetch(NOTIF_API);
    const data = await res.json();
    if (!data.ok) return;
    const dot = document.getElementById('notifDot');
    if (dot) dot.style.display = data.sin_leer > 0 ? '' : 'none';
    notifData = data.data || [];
    // Si el panel está abierto, re-render en vivo
    if (notifPanelOpen) renderNotifList();
  } catch (e) { /* red caída */ }
}

function renderNotifList() {
  const list = document.getElementById('notifList');
  if (!notifData.length) {
    list.innerHTML = '<div class="notif-empty">Sin notificaciones</div>';
    return;
  }
  list.innerHTML = notifData.map(n => {
    const unread = Number(n.leida) === 0;
    const ts = n.created_at
      ? new Date(n.created_at.replace(' ', 'T')).toLocaleString('es-AR', {
          day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit', hour12: false
        })
      : '';
    return '<div class="notif-item' + (unread ? ' unread' : '') + '">' +
      '<div class="notif-item-title">' +
        (unread ? '<span class="unread-dot"></span>' : '') +
        escHtml(n.titulo || 'Notificación') +
      '</div>' +
      (n.cuerpo ? '<div class="notif-item-body">' + escHtml(n.cuerpo) + '</div>' : '') +
      '<div class="notif-item-time">' + ts + '</div>' +
    '</div>';
  }).join('');
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function toggleNotifPanel() {
  if (notifPanelOpen) {
    cerrarNotifPanel();
  } else {
    // Cargar datos frescos antes de abrir
    await fetchNotifCount();
    renderNotifList();
    document.getElementById('notifPanel').classList.add('open');
    document.getElementById('notifOverlay').classList.add('open');
    notifPanelOpen = true;
    // Marcar todas como leídas al abrir
    marcarTodasLeidas();
  }
}

function cerrarNotifPanel() {
  document.getElementById('notifPanel').classList.remove('open');
  document.getElementById('notifOverlay').classList.remove('open');
  notifPanelOpen = false;
}

async function marcarTodasLeidas() {
  const sinLeer = notifData.filter(n => Number(n.leida) === 0);
  if (!sinLeer.length) return;
  try {
    await fetch(NOTIF_API, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ accion: 'marcar_leidas', ids: [] }), // ids vacío = todas
    });
    // Quitar el punto rojo y marcar local
    notifData.forEach(n => { n.leida = 1; });
    const dot = document.getElementById('notifDot');
    if (dot) dot.style.display = 'none';
    renderNotifList();
  } catch (e) { /* silencioso */ }
}

// Polling: contar sin leer cada 30 s
document.addEventListener('DOMContentLoaded', function() {
  fetchNotifCount();
  notifPollTimer = setInterval(fetchNotifCount, 30000);
});
