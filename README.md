# repo-delivery — App para repartidores

PWA mobile-first para los repartidores. Permite ver los pedidos disponibles para tomar, confirmar entregas, consultar el historial del día con mapa de ubicación integrado y recibir notificaciones push en tiempo real.

---

## Tecnologías

- PHP (APIs REST, sin framework)
- JavaScript vanilla
- MySQL vía PDO (conexión compartida con `repo-api`)
- Google Maps API (visualización de dirección de entrega y mapa de pedidos disponibles)
- Web Push API (notificaciones nativas)

---

## Estructura

```
repo-delivery/
├── index.php          # SPA principal (inicio, en reparto, historial, perfil)
├── login.php          # Autenticación del repartidor
├── api/
│   ├── auth.php       # Login email + contraseña → JWT
│   ├── pedidos.php    # Listar pedidos, tomar pedido, marcar como entregado
│   ├── ubicacion.php  # Activar/desactivar seguimiento GPS
│   ├── heartbeat.php  # Actualización periódica de coordenadas GPS
│   ├── push.php       # Registro y baja de suscripción Web Push
│   └── notificaciones.php # Listado de notificaciones recibidas
├── lib/
│   ├── auth_check.php # Middleware JWT (requireAuth, authRepartidor)
│   └── jwt.php        # Encode/decode JWT HS256
└── assets/
    ├── css/delivery.css
    └── js/delivery.js
```

---

## Autenticación

Email + contraseña contra la tabla `repartidores`. El JWT se guarda en la cookie `delivery_token` (TTL: 7 días).

El token incluye: `id`, `nombre`, `correo`, `celular`, `rol: repartidor`.

---

## Flujo de pedidos

```
[asignacion sin repartidor]
        ↓  repartidor presiona Tomar
    [reparto]  ←→  visible en "En reparto"
        ↓  repartidor confirma entrega
    [entregado]
```

El botón **Tomar** es atómico: usa `UPDATE ... WHERE repartidor_id IS NULL` para evitar que dos repartidores tomen el mismo pedido simultáneamente.

---

## Secciones de la app

| Sección | Contenido |
|---|---|
| **Inicio** | Pedidos en `asignacion` sin repartidor asignado (disponibles para tomar), con mapa y modal de detalles |
| **En reparto** | Pedidos en `reparto` asignados a este repartidor |
| **Historial** | Pedidos en `entregado` del día de hoy |
| **Perfil** | Nombre, correo y celular del repartidor autenticado |

---

## API — endpoints principales

### `api/pedidos.php`

| Método | Acción |
|---|---|
| `GET` | Devuelve `disponibles`, `listos` (en reparto) y `entregados` del día |
| `PUT { id, accion: 'tomar' }` | Asigna este repartidor al pedido y lo mueve a `reparto` |
| `PUT { id, estado: 'entregado' }` | Marca el pedido como entregado y notifica al cliente |

### `api/ubicacion.php`

| Método | Acción |
|---|---|
| `PUT { accion: 'activar' }` | Activa el seguimiento GPS y notifica al panel admin |
| `PUT { accion: 'desactivar' }` | Desactiva el seguimiento GPS |

### `api/heartbeat.php`

Recibe `lat` y `lng` y actualiza la posición del repartidor en la tabla `repartidores`. El frontend lo llama periódicamente mientras el seguimiento esté activo.

---

## Notificaciones push

El repartidor recibe notificaciones Web Push para:
- Nuevo pedido disponible para tomar
- Actualizaciones de estado del pedido asignado

`api/push.php` gestiona el registro y la baja de la suscripción. Las claves VAPID se generan y almacenan en `configuracion`.

---

## PWA

- Instalable en Android e iOS (prompt de instalación nativo para Android y banner personalizado para iOS)
- Tema dinámico: color de la barra del navegador cambia según la sección activa
- Service Worker con estrategia de caché para funcionamiento offline básico

---

## Dependencias externas

| Servicio | Uso |
|---|---|
| `repo-api/config/db.php` | Conexión a la base de datos compartida |
| Google Maps API | Mapa de la dirección de entrega y mapa de pedidos disponibles |
| Web Push (VAPID) | Notificaciones push nativas |
