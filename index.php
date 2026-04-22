<?php
require_once __DIR__ . '/lib/auth_check.php';
requireAuth();
$rep = authRepartidor();
$inicial = strtoupper(mb_substr($rep['nombre'] ?? 'R', 0, 1));
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
  <link rel="manifest" href="favicon/manifest.json">
  <link rel="stylesheet" href="assets/css/delivery.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <meta name="theme-color" content="#FFA000">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
</head>
<body>

<div class="app">

  <!-- ===== Header ===== -->
  <header class="header">
    <div class="header-left">
      <div class="header-avatar"><?= $inicial ?></div>
      <div>
        <div class="header-name"><?= htmlspecialchars($rep['nombre'] ?? 'Repartidor') ?></div>
        <div class="header-role">Repartidor</div>
      </div>
    </div>
    <div class="header-right">
      <div class="live-badge">
        <span class="live-dot"></span>
        <span id="lastUpdate">—</span>
      </div>
      <button class="btn-icon" id="btnRefresh" onclick="cargar()" title="Actualizar">
        <i class="fa-solid fa-rotate-right"></i>
      </button>
    </div>
  </header>

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

      <div class="dash-block-title" style="margin-top:18px">
        <i class="fa-solid fa-circle-check"></i> Entregados hoy
      </div>
      <div id="dashListaDelivered"></div>
      <div class="empty-state" id="dashEmptyDelivered" style="display:none">
        <i class="fa-solid fa-clock-rotate-left"></i>
        <p>Aún no entregaste pedidos hoy</p>
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
      </div>

      <div class="toggle-row">
        <span class="toggle-label"><i class="fa-solid fa-bell" style="color:var(--primary);margin-right:6px"></i> Notificaciones de sonido</span>
        <label class="toggle-switch">
          <input type="checkbox" id="sonidoToggle" onchange="toggleSonido()">
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

      <button class="btn-logout" onclick="confirmarLogout()">
        <i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión
      </button>
    </div>

  </main>

  <!-- ===== Bottom Nav ===== -->
  <nav class="bottom-nav">
    <button class="nav-tab active" id="tab-inicio" onclick="ir('inicio')">
      <i class="fa-solid fa-house"></i>
      <span>Inicio</span>
    </button>
    <button class="nav-tab" id="tab-pendientes" onclick="ir('pendientes')">
      <i class="fa-solid fa-bag-shopping"></i>
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

<!-- Loading -->
<div class="loading-overlay" id="loadingOverlay">
  <div class="spinner"></div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script src="assets/js/delivery.js"></script>
<script>
  function confirmarLogout() {
    if (confirm('¿Cerrar sesión?')) logout();
  }
</script>
</body>
</html>
