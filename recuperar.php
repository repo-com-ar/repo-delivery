<?php
require_once __DIR__ . '/lib/auth_check.php';
if (authRepartidor()) {
    header('Location: index.php');
    exit;
}
?><!DOCTYPE html>
<html lang="es" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
  <title>Repo Delivery — Recuperar contraseña</title>
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
    .back-link {
      display: block; text-align: center; margin-top: 18px;
      font-size: .83rem; color: var(--muted);
    }
    .back-link a { color: var(--primary); font-weight: 600; }
  </style>
</head>
<body>

<div class="login-wrap">
  <div class="login-card">

    <div class="login-logo">
      <img class="logo-light" src="assets/img/repo_logo_black.png" alt="Repo" style="height:52px;width:auto;display:block;margin:0 auto">
      <img class="logo-dark"  src="assets/img/repo_logo_withe.png" alt="Repo" style="height:52px;width:auto;display:none;margin:0 auto">
    </div>
    <p class="login-subtitle">Recuperar contraseña<br><span style="font-size:.78rem">Te enviaremos un enlace a tu correo</span></p>

    <div class="error-msg"   id="errorMsg"></div>
    <div class="success-msg" id="successMsg"></div>

    <form onsubmit="return false" id="formRecuperar">
      <div class="form-group">
        <label class="form-label" for="correo">Correo electrónico</label>
        <input class="form-input" type="email" id="correo" placeholder="correo@ejemplo.com"
               autocomplete="email" inputmode="email" required>
      </div>
      <button class="btn-login" id="btnEnviar" onclick="enviar()">
        <i class="fa-solid fa-paper-plane"></i> Enviar enlace
      </button>
    </form>

    <p class="back-link">¿Ya tenés tu contraseña? <a href="login">Iniciar sesión</a></p>

  </div>
</div>

<script>
  document.addEventListener('keydown', e => { if (e.key === 'Enter') enviar(); });

  async function enviar() {
    const correo = document.getElementById('correo').value.trim();
    const btn    = document.getElementById('btnEnviar');
    const errEl  = document.getElementById('errorMsg');
    const okEl   = document.getElementById('successMsg');

    errEl.classList.remove('show');
    okEl.classList.remove('show');

    if (!correo) {
      showError('Ingresá tu correo electrónico');
      return;
    }

    btn.disabled  = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando…';

    try {
      await fetch('api/recuperar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ correo }),
      });
      document.getElementById('formRecuperar').style.display = 'none';
      okEl.textContent = 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.';
      okEl.classList.add('show');
    } catch (err) {
      showError('Error de conexión. Intentá de nuevo.');
      btn.disabled  = false;
      btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Enviar enlace';
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
