<?php
require_once __DIR__ . '/lib/auth_check.php';
if (authRepartidor()) {
    header('Location: index.php');
    exit;
}

$token = trim($_GET['token'] ?? '');
if (!$token) {
    header('Location: login');
    exit;
}
?><!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Repo Delivery — Nueva contraseña</title>
  <script>(function(){var t=localStorage.getItem('deliveryTema')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" href="favicon.ico">
  <link rel="stylesheet" href="assets/css/delivery.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    [data-theme="dark"] .logo-light { display: none !important; }
    [data-theme="dark"] .logo-dark  { display: block !important; }
    .success-msg {
      background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a;
      border-radius: 8px; padding: 10px 14px; font-size: .85rem;
      margin-bottom: 14px; display: none;
    }
    .success-msg.show { display: block; }
    .pw-wrap { position: relative; }
    .pw-toggle {
      position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--muted); cursor: pointer;
      font-size: .9rem; padding: 4px;
    }
    .pw-wrap .form-input { padding-right: 42px; }
  </style>
</head>
<body>

<div class="login-wrap">
  <div class="login-card">

    <div class="login-logo">
      <img class="logo-light" src="assets/img/repo_logo_black.png" alt="Repo" style="height:52px;width:auto;display:block;margin:0 auto">
      <img class="logo-dark"  src="assets/img/repo_logo_withe.png" alt="Repo" style="height:52px;width:auto;display:none;margin:0 auto">
    </div>
    <p class="login-subtitle">Nueva contraseña<br><span style="font-size:.78rem">Elegí una nueva contraseña para tu cuenta</span></p>

    <div class="error-msg"   id="errorMsg"></div>
    <div class="success-msg" id="successMsg"></div>

    <form onsubmit="return false" id="formReset">
      <div class="form-group">
        <label class="form-label" for="contrasena">Nueva contraseña</label>
        <div class="pw-wrap">
          <input class="form-input" type="password" id="contrasena" placeholder="Mínimo 6 caracteres"
                 autocomplete="new-password" required>
          <button type="button" class="pw-toggle" onclick="togglePw('contrasena', this)">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label" for="confirmar">Confirmar contraseña</label>
        <div class="pw-wrap">
          <input class="form-input" type="password" id="confirmar" placeholder="Repetí la contraseña"
                 autocomplete="new-password" required>
          <button type="button" class="pw-toggle" onclick="togglePw('confirmar', this)">
            <i class="fa-solid fa-eye"></i>
          </button>
        </div>
      </div>
      <button class="btn-login" id="btnGuardar" onclick="guardar()">
        <i class="fa-solid fa-lock"></i> Guardar contraseña
      </button>
    </form>

  </div>
</div>

<script>
  const TOKEN = <?= json_encode($token) ?>;

  function togglePw(id, btn) {
    const inp = document.getElementById(id);
    const ico = btn.querySelector('i');
    if (inp.type === 'password') {
      inp.type = 'text';
      ico.className = 'fa-solid fa-eye-slash';
    } else {
      inp.type = 'password';
      ico.className = 'fa-solid fa-eye';
    }
  }

  async function guardar() {
    const contrasena = document.getElementById('contrasena').value.trim();
    const confirmar  = document.getElementById('confirmar').value.trim();
    const btn        = document.getElementById('btnGuardar');
    const errEl      = document.getElementById('errorMsg');
    const okEl       = document.getElementById('successMsg');

    errEl.classList.remove('show');
    okEl.classList.remove('show');

    if (!contrasena || !confirmar) {
      showError('Completá ambos campos');
      return;
    }
    if (contrasena.length < 6) {
      showError('La contraseña debe tener al menos 6 caracteres');
      return;
    }
    if (contrasena !== confirmar) {
      showError('Las contraseñas no coinciden');
      return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';

    try {
      const r    = await fetch('api/reset', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ token: TOKEN, contrasena }),
      });
      const data = await r.json();

      if (data.ok) {
        document.getElementById('formReset').style.display = 'none';
        okEl.innerHTML = 'Contraseña actualizada. <a href="login" style="color:#16a34a;font-weight:700">Iniciá sesión</a>';
        okEl.classList.add('show');
      } else {
        showError(data.error || 'Enlace inválido o expirado');
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-lock"></i> Guardar contraseña';
      }
    } catch (err) {
      showError('Error de conexión. Intentá de nuevo.');
      btn.disabled  = false;
      btn.innerHTML = '<i class="fa-solid fa-lock"></i> Guardar contraseña';
    }
  }

  function showError(msg) {
    const el = document.getElementById('errorMsg');
    el.textContent = msg;
    el.classList.add('show');
  }
</script>
</body>
</html>
