# repo-delivery — App para repartidores

PWA mobile-first para los repartidores. Permite ver los pedidos disponibles para tomar, confirmar entregas y consultar el historial del día con mapa de ubicación integrado.

---

## Tecnologías

- PHP (APIs REST, sin framework)
- JavaScript vanilla
- MySQL vía PDO (conexión compartida con `repo-api`)
- Google Maps API (visualización de dirección de entrega)

---

## Estructura

```
repo-delivery/
├── index.php          # SPA principal (inicio, en reparto, historial, perfil)
├── login.php          # Autenticación del repartidor
├── api/
│   ├── auth.php       # Login email + contraseña → JWT
│   └── pedidos.php    # Listar pedidos, tomar pedido, marcar como entregado
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
| **Inicio** | Pedidos en `asignacion` sin repartidor asignado (disponibles para tomar) |
| **En reparto** | Pedidos en `reparto` asignados a este repartidor |
| **Historial** | Pedidos en `entregado` del día de hoy |
| **Perfil** | Nombre, correo y celular del repartidor autenticado |

---

## API — `api/pedidos.php`

| Método | Acción |
|---|---|
| `GET` | Devuelve `disponibles`, `listos` (en reparto) y `entregados` del día |
| `PUT { id, accion: 'tomar' }` | Asigna este repartidor al pedido y lo mueve a `reparto` |
| `PUT { id, estado: 'entregado' }` | Marca el pedido como entregado |

---

## Dependencias externas

| Servicio | Uso |
|---|---|
| `repo-api/config/db.php` | Conexión a la base de datos compartida |
| Google Maps API | Mapa de la dirección de entrega en el detalle del pedido |
