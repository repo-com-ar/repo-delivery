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
  <title>Repo Delivery — Iniciar sesión</title>
  <script>(function(){var t=localStorage.getItem('deliveryTema')||'light';document.documentElement.setAttribute('data-theme',t);})();</script>
  <link rel="icon" href="favicon.ico">
  <link rel="stylesheet" href="assets/css/delivery.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    [data-theme="dark"] .logo-light { display: none !important; }
    [data-theme="dark"] .logo-dark  { display: block !important; }
  </style>
</head>
<body>

<div class="login-wrap">
  <div class="login-card">

    <div class="login-logo">
      <img class="logo-light" src="assets/img/repo_logo_black.png" alt="Repo" style="height:52px;width:auto;display:block;margin:0 auto">
      <img class="logo-dark"  src="assets/img/repo_logo_withe.png" alt="Repo" style="height:52px;width:auto;display:none;margin:0 auto">
    </div>
    <p class="login-subtitle">Repo Delivery</p>

    <div class="error-msg" id="errorMsg"></div>

    <form onsubmit="return false">
      <div class="form-group">
        <label class="form-label" for="correo">Correo electrónico</label>
        <input class="form-input" type="email" id="correo" placeholder="correo@ejemplo.com"
               autocomplete="email" inputmode="email" required>
      </div>
      <div class="form-group">
        <label class="form-label" for="contrasena">Contraseña</label>
        <input class="form-input" type="password" id="contrasena" placeholder="••••••••"
               autocomplete="current-password" required>
      </div>
      <button class="btn-login" id="btnLogin" onclick="login()">
        <i class="fa-solid fa-right-to-bracket"></i> Ingresar
      </button>
    </form>

    <p style="text-align:center;margin-top:18px;font-size:.83rem">
      <a href="recuperar" style="color:var(--primary);font-weight:600">Olvidé mi contraseña</a>
    </p>

  </div>
</div>

<script>
  document.addEventListener('keydown', e => { if (e.key === 'Enter') login(); });

  async function login() {
    const correo    = document.getElementById('correo').value.trim();
    const contrasena= document.getElementById('contrasena').value.trim();
    const errEl     = document.getElementById('errorMsg');
    const btn       = document.getElementById('btnLogin');

    if (!correo || !contrasena) {
      showError('Completá todos los campos');
      return;
    }

    btn.disabled    = true;
    btn.innerHTML   = '<i class="fa-solid fa-spinner fa-spin"></i> Ingresando…';
    errEl.classList.remove('show');

    try {
      const r = await fetch('api/auth', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ correo, contrasena }),
      });
      const data = await r.json();
      if (data.ok) {
        location.href = 'index.php';
      } else {
        showError(data.error || 'Celular o clave incorrectos');
        btn.disabled  = false;
        btn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Ingresar';
      }
    } catch (err) {
      showError('Error de conexión. Intentá de nuevo.');
      btn.disabled  = false;
      btn.innerHTML = '<i class="fa-solid fa-right-to-bracket"></i> Ingresar';
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
