<?php
require_once __DIR__ . '/lib/auth_check.php';
requireAuth();
$rep = authRepartidor();
$inicial = strtoupper(mb_substr($rep['nombre'] ?? 'R', 0, 1));
require_once __DIR__ . '/../repo-api/config/db.php';
$googleMapsKey = getConfigValue('google_maps_key');
?><!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Repo Delivery</title>
  <script>(function(){var t=localStorage.getItem('deliveryTema')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="96x96" href="favicon/favicon-96x96.png">
  <link rel="apple-touch-icon" href="favicon/apple-icon-180x180.png">
  <link rel="manifest" href="manifest.webmanifest">
  <link rel="stylesheet" href="assets/css/delivery.css?v=<?= filemtime(__DIR__ . '/assets/css/delivery.css') ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <meta name="theme-color" content="#ffffff" id="metaThemeColor">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body>

<div class="app">

  <!-- ===== Header ===== -->
  <header class="header">
    <div class="header-left">
      <img class="logo-light" src="assets/img/repo_logo_black.png" alt="Repo Online" style="height:30px; width:auto;">
      <img class="logo-dark"  src="assets/img/repo_logo_withe.png" alt="Repo Online" style="height:30px; width:auto;">
    </div>
    <div class="header-right">
      <div class="live-badge">
        <span class="live-dot"></span>
        <span id="lastUpdate">—</span>
      </div>
      <button class="btn-icon" id="btnRefresh" onclick="cargar()" title="Actualizar">
        <i class="fa-solid fa-rotate-right"></i>
      </button>
      <button class="btn-icon btn-notif" id="btnNotif" onclick="toggleNotifPanel()" title="Notificaciones" aria-label="Notificaciones">
        <i class="fa-solid fa-bell"></i>
        <span class="notif-dot" id="notifDot" style="display:none"></span>
      </button>
      <button class="btn-icon" id="btnInstall" onclick="pwaInstall()" title="Instalar aplicación" style="display:none">
        <i class="fa-solid fa-download" style="font-size:18px"></i>
      </button>
    </div>
  </header>

  <!-- Panel de notificaciones -->
  <div class="notif-panel" id="notifPanel">
    <div class="notif-panel-header">
      <span class="notif-panel-title">Notificaciones</span>
      <button class="notif-panel-close" onclick="cerrarNotifPanel()">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="notif-list" id="notifList">
      <div class="notif-empty">Sin notificaciones</div>
    </div>
  </div>
  <div class="notif-overlay" id="notifOverlay" onclick="cerrarNotifPanel()"></div>

  <!-- ===== Content ===== -->
  <main class="content">

    <!-- Sección: Inicio (Dashboard) -->
    <div class="section active" id="sec-inicio">

      <div class="dash-stats">
        <div class="dash-stat dash-stat--pending">
          <div class="dash-stat-icon"><i class="fa-solid fa-bag-shopping"></i></div>
          <div class="dash-stat-num" id="dashNumPending">0</div>
          <div class="dash-stat-label">Por entregar</div>
        </div>
        <div class="dash-stat dash-stat--delivered">
          <div class="dash-stat-icon"><i class="fa-solid fa-check"></i></div>
          <div class="dash-stat-num" id="dashNumDelivered">0</div>
          <div class="dash-stat-label">Entregados hoy</div>
        </div>
      </div>

      <div class="dash-block-title">
        <i class="fa-solid fa-clock"></i> Últimos pedidos entrando
      </div>
      <div id="dashListaPending"></div>
      <div class="empty-state" id="dashEmptyPending" style="display:none">
        <i class="fa-solid fa-box-open"></i>
        <p>No hay pedidos pendientes</p>
      </div>

    </div>

    <!-- Sección: Para entregar -->
    <div class="section" id="sec-pendientes">
      <div class="section-header">
        <span class="section-title">Para entregar</span>
        <span class="section-count empty" id="countPendientes">0</span>
      </div>
      <div class="refresh-bar" onclick="cargar()">
        <i class="fa-solid fa-rotate-right"></i> Actualizar
      </div>
      <div id="listaPendientes"></div>
      <div class="empty-state" id="emptyPendientes" style="display:none">
        <i class="fa-solid fa-box-open"></i>
        <p>No hay pedidos listos para entregar</p>
      </div>
    </div>

    <!-- Sección: Historial de hoy -->
    <div class="section" id="sec-historial">
      <div class="section-header">
        <span class="section-title">Entregados hoy</span>
        <span class="section-count empty" id="countHistorial">0</span>
      </div>
      <div id="listaHistorial"></div>
      <div class="empty-state" id="emptyHistorial" style="display:none">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <p>Aún no entregaste pedidos hoy</p>
      </div>
    </div>

    <!-- Sección: Perfil -->
    <div class="section" id="sec-perfil">
      <div class="profile-card">
        <div class="profile-avatar"><?= $inicial ?></div>
        <div class="profile-name"><?= htmlspecialchars($rep['nombre'] ?? '') ?></div>
        <div class="profile-role">Repartidor</div>
        <div class="profile-row">
          <span class="profile-label">ID</span>
          <span class="profile-value">#<?= (int)($rep['id'] ?? 0) ?></span>
        </div>
        <?php if (!empty($rep['celular'])): ?>
        <div class="profile-row">
          <span class="profile-label"><i class="fa-solid fa-phone" style="margin-right:4px"></i>Celular</span>
          <span class="profile-value"><?= htmlspecialchars($rep['celular']) ?></span>
        </div>
        <?php endif; ?>
        <?php if (!empty($rep['correo'])): ?>
        <div class="profile-row">
          <span class="profile-label"><i class="fa-solid fa-envelope" style="margin-right:4px"></i>Correo</span>
          <span class="profile-value"><?= htmlspecialchars($rep['correo']) ?></span>
        </div>
        <?php endif; ?>
        <button class="btn-logout" onclick="confirmarLogout()">
          <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
        </button>
      </div>

      <div class="toggle-row">
        <span class="toggle-label"><i class="fa-solid fa-mobile-screen-button" style="color:var(--primary);margin-right:6px"></i> Notificaciones al celular</span>
        <label class="toggle-switch">
          <input type="checkbox" id="pushToggle" onchange="onTogglePush(event)">
          <span class="toggle-track"><span class="toggle-thumb"></span></span>
        </label>
      </div>
      <div id="pushStatus" style="display:none;font-size:.78rem;color:var(--text-secondary);padding:0 4px 10px">—</div>

      <div class="toggle-row">
        <span class="toggle-label"><i class="fa-solid fa-bell" style="color:var(--primary);margin-right:6px"></i> Notificaciones de sonido</span>
        <label class="toggle-switch">
          <input type="checkbox" id="sonidoToggle" onchange="toggleSonido()">
          <span class="toggle-track"><span class="toggle-thumb"></span></span>
        </label>
      </div>

      <div class="toggle-row">
        <span class="toggle-label"><i class="fa-solid fa-satellite-dish" style="color:var(--primary);margin-right:6px"></i> Seguimiento en tiempo real</span>
        <label class="toggle-switch">
          <input type="checkbox" id="seguimientoToggle" onchange="toggleSeguimiento()">
          <span class="toggle-track"><span class="toggle-thumb"></span></span>
        </label>
      </div>

      <div class="toggle-row">
        <span class="toggle-label"><i class="fa-solid fa-moon" style="color:var(--primary);margin-right:6px"></i> Modo oscuro</span>
        <label class="toggle-switch">
          <input type="checkbox" id="temaToggle" onchange="toggleTema()">
          <span class="toggle-track"><span class="toggle-thumb"></span></span>
        </label>
      </div>

    </div>

  </main>

  <!-- ===== Bottom Nav ===== -->
  <nav class="bottom-nav">
    <button class="nav-tab active" id="tab-inicio" onclick="ir('inicio')">
      <i class="fa-solid fa-clock"></i>
      <span>Para asignar</span>
      <span class="nav-badge" id="badgeInicio" style="display:none">0</span>
    </button>
    <button class="nav-tab" id="tab-pendientes" onclick="ir('pendientes')">
      <i class="fa-solid fa-motorcycle"></i>
      <span>Para entregar</span>
      <span class="nav-badge" id="badgePendientes" style="display:none">0</span>
    </button>
    <button class="nav-tab" id="tab-historial" onclick="ir('historial')">
      <i class="fa-solid fa-clock-rotate-left"></i>
      <span>Historial</span>
    </button>
    <button class="nav-tab" id="tab-perfil" onclick="ir('perfil')">
      <i class="fa-solid fa-circle-user"></i>
      <span>Perfil</span>
    </button>
  </nav>

</div>

<!-- Modal Ver Pedido -->
<div class="modal-wrap" id="pedidoModal" onclick="if(event.target===this)closePedidoModal()">
  <div class="modal">
    <div class="modal-handle"></div>
    <div class="modal-header">
      <span class="modal-title" id="pedidoModalTitle">Pedido</span>
      <button class="modal-close" onclick="closePedidoModal()" aria-label="Cerrar">
        <i class="fa-solid fa-xmark"></i>
      </button>
    </div>
    <div class="modal-body" id="pedidoModalBody"></div>
  </div>
</div>

<!-- Loading -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- Banner iOS: instalar PWA en pantalla de inicio -->
<div class="ios-install-backdrop" id="pushInstallBanner" onclick="if(event.target===this)cerrarInstallBannerIOS()">
  <div class="ios-install-card">
    <button class="ios-install-close" onclick="cerrarInstallBannerIOS()" aria-label="Cerrar">
      <i class="fa-solid fa-xmark"></i>
    </button>
    <div class="ios-install-icon"><i class="fa-solid fa-mobile-screen-button"></i></div>
    <div class="ios-install-title">Instalá la app para recibir notificaciones</div>
    <p class="ios-install-text">
      En iPhone, Safari solo permite notificaciones cuando la app está en la pantalla de inicio.
    </p>
    <ol class="ios-install-steps">
      <li>Tocá el botón <b>Compartir</b> <i class="fa-solid fa-arrow-up-from-bracket"></i> de Safari.</li>
      <li>Deslizá y elegí <b>Agregar a pantalla de inicio</b>.</li>
      <li>Abrí la app desde el nuevo ícono y activá el toggle otra vez.</li>
    </ol>
  </div>
</div>

<script>window.MAPS_KEY = '<?= htmlspecialchars($googleMapsKey) ?>';</script>
<script src="assets/js/delivery.js?v=<?= filemtime(__DIR__ . '/assets/js/delivery.js') ?>"></script>
<script src="assets/js/push.js?v=<?= filemtime(__DIR__ . '/assets/js/push.js') ?>"></script>
<script>
  function confirmarLogout() {
    if (confirm('¿Cerrar sesión?')) logout();
  }
</script>
<script>
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('sw.js').catch(() => {});
  }
</script>
</body>
</html>
