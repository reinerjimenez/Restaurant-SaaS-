<div align="center">

<br/>

```
██████╗ ███████╗███████╗████████╗    ███████╗ █████╗  █████╗ ███████╗
██╔══██╗██╔════╝██╔════╝╚══██╔══╝   ██╔════╝██╔══██╗██╔══██╗██╔════╝
██████╔╝█████╗  ███████╗   ██║      ███████╗███████║███████║███████╗
██╔══██╗██╔══╝  ╚════██║   ██║      ╚════██║██╔══██║██╔══██║╚════██║
██║  ██║███████╗███████║   ██║      ███████║██║  ██║██║  ██║███████║
╚═╝  ╚═╝╚══════╝╚══════╝   ╚═╝      ╚══════╝╚═╝  ╚═╝╚═╝  ╚═╝╚══════╝
```

# 🍽️ RestSaaS — Sistema de Gestión para Restaurantes

**Plataforma SaaS multi-tenant para la gestión integral de restaurantes modernos**  
*Pedidos · Facturación · Cocina · Control de Caja · Reportes*

---

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
[![Apache](https://img.shields.io/badge/Apache-2.4%2B-D22128?style=for-the-badge&logo=apache&logoColor=white)](https://httpd.apache.org)
[![XAMPP](https://img.shields.io/badge/XAMPP-Compatible-FB7A24?style=for-the-badge&logo=xampp&logoColor=white)](https://apachefriends.org)
[![License](https://img.shields.io/badge/License-Privado-1A1A2E?style=for-the-badge&logo=shield&logoColor=white)](#)
[![Autor](https://img.shields.io/badge/Autor-Reiner%20Jiménez-6C63FF?style=for-the-badge&logo=github&logoColor=white)](https://linkedin.com/in/reiner-jairo-jiménez-huaman-9234a9388)

<br/>

> *"Optimiza tus operaciones, centraliza tus pedidos y acelera tus cobros en una plataforma diseñada para equipos de alto rendimiento."*

</div>

---

## 📋 Tabla de Contenidos

- [🚀 Visión General](#-visión-general)
- [✨ Características Principales](#-características-principales)
- [🏗️ Arquitectura del Sistema](#️-arquitectura-del-sistema)
- [👥 Roles y Permisos](#-roles-y-permisos)
- [🗂️ Estructura del Proyecto](#️-estructura-del-proyecto)
- [⚙️ Instalación y Configuración](#️-instalación-y-configuración)
- [🗄️ Base de Datos](#️-base-de-datos)
- [🔌 API Endpoints](#-api-endpoints)
- [🔐 Seguridad](#-seguridad)
- [🖥️ Módulos del Sistema](#️-módulos-del-sistema)
- [👨‍💻 Autor](#-autor)

---

## 🚀 Visión General

**RestSaaS** es una plataforma de gestión completa para restaurantes, construida sobre una arquitectura **SaaS multi-tenant**. Permite que múltiples restaurantes operen de forma independiente bajo una única instalación, con aislamiento de datos garantizado y gestión centralizada desde el **Superadmin**.

```
┌─────────────────────────────────────────────────────────┐
│                    RESTSAAS PLATFORM                    │
│                                                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │  Restaurante │  │  Restaurante │  │  Restaurante │  │
│  │     "A"      │  │     "B"      │  │     "C"      │  │
│  │  (Tenant 1)  │  │  (Tenant 2)  │  │  (Tenant 3)  │  │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘  │
│         └─────────────────┼─────────────────┘          │
│                           │                             │
│              ┌────────────▼────────────┐                │
│              │     Base de Datos       │                │
│              │   MySQL (restaurante_db)│                │
│              └─────────────────────────┘                │
└─────────────────────────────────────────────────────────┘
```

### 🎯 ¿Para quién es?

| Tipo de Negocio | Compatible |
|:---|:---:|
| 🍕 Pizzerías y fast food | ✅ |
| 🍽️ Restaurantes formales | ✅ |
| ☕ Cafeterías y bares | ✅ |
| 🥡 Delivery y take-away | ✅ |
| 🏨 Room service de hoteles | ✅ |
| 🍱 Negocios con múltiples locales | ✅ |

---

## ✨ Características Principales

### 🧾 Facturación Electrónica
- Emisión de **Boletas**, **Facturas** y **Comprobantes Simples**
- Consulta automática de **DNI** y **RUC** vía [APIs.net.pe](https://apis.net.pe)
- Configuración de series de comprobantes por restaurante (`B001`, `F001`, `T001`)
- Anulación de comprobantes con auditoría completa
- Vista de impresión optimizada con estilos dedicados (`print.css`)

### 🗺️ Mapa Interactivo de Mesas
- Visualización en tiempo real del estado de cada mesa
- Estados: `Libre` · `Ocupada` · `Reservada`
- Gestión CRUD de mesas con posicionamiento
- Indicador de tiempo de ocupación y número de ítems por pedido

### 📊 Dashboard Inteligente
- KPIs en tiempo real: ventas del día, utilidad, pedidos activos
- Desglose de pagos por método (efectivo, tarjeta, Yape, etc.)
- Gráficas interactivas de rendimiento
- Top productos más vendidos del día

### 👨‍🍳 Panel de Cocina (KDS)
- Vista exclusiva para el equipo de cocina
- Notificaciones de nuevos pedidos con alerta sonora
- Marcar ítems como `listos` individualmente
- Organización por tiempo de llegada del pedido

### 💰 Control de Caja y Turnos
- Apertura y cierre de turnos con monto inicial
- Registro de egresos y gastos durante el turno
- Reporte de cierre con desglose completo por método de pago
- Historial de todos los turnos pasados con detalle

### 📈 Reportes y Analytics
- Ventas por rango de fechas
- Rendimiento por producto y categoría
- Historial completo de comprobantes emitidos
- Datos exportables para análisis

---

## 🏗️ Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                        STACK TECNOLÓGICO                        │
├──────────────────┬──────────────────┬───────────────────────────┤
│   PRESENTACIÓN   │    LÓGICA        │       DATOS               │
│                  │                  │                           │
│  HTML5 + CSS3    │  PHP 8.1+        │  MySQL 8.0+               │
│  Vanilla JS      │  PDO (Singleton) │  utf8mb4                  │
│  Font Awesome    │  Sessions        │  Transacciones ACID       │
│  Google Fonts    │  CSRF Protection │  Índices optimizados      │
│  (Inter)         │  Role-based Auth │  restaurante_id isolation │
└──────────────────┴──────────────────┴───────────────────────────┘
```

### Patrón de Arquitectura

```
Cliente (Browser)
      │
      ▼
┌─────────────┐
│  index.php  │ ← Punto de entrada + Login
└──────┬──────┘
       │
       ▼
┌─────────────────────────────────┐
│         config/db.php           │
│  • getDB()       PDO Singleton  │
│  • requireRole() Auth Guard     │
│  • jsonResponse() API Helper    │
│  • CSRF Protection              │
│  • loadEnv()     .env Parser    │
└────────────────┬────────────────┘
                 │
    ┌────────────┼────────────┐
    ▼            ▼            ▼
┌────────┐ ┌────────┐ ┌────────────┐
│ roles/ │ │  api/  │ │  assets/   │
│ Vistas │ │ AJAX   │ │ css + js   │
│ por    │ │ JSON   │ │ multimedia │
│  Rol   │ │ REST   │ │            │
└────────┘ └────────┘ └────────────┘
```

---

## 👥 Roles y Permisos

El sistema implementa un control de acceso basado en roles **(RBAC)** con 4 niveles jerárquicos:

```
╔══════════════════════════════════════════════════════╗
║                   JERARQUÍA DE ROLES                 ║
╠══════════════════════════════════════════════════════╣
║                                                      ║
║  🔴 SUPERADMIN  →  Gestión de toda la plataforma    ║
║         │                                            ║
║  🟠 ADMIN       →  Gestión del restaurante           ║
║         │                                            ║
║  🟡 ATENCION    →  Pedidos, cobros, caja             ║
║         │                                            ║
║  🟢 COCINA      →  Vista KDS, marcar preparados      ║
║                                                      ║
╚══════════════════════════════════════════════════════╝
```

### Matriz de Permisos Detallada

| Módulo | 🔴 Superadmin | 🟠 Admin | 🟡 Atención | 🟢 Cocina |
|:---|:---:|:---:|:---:|:---:|
| Dashboard global | ✅ | — | — | — |
| Gestión de restaurantes | ✅ | — | — | — |
| Dashboard del restaurante | ✅ | ✅ | — | — |
| Gestión de usuarios | ✅ | ✅ | — | — |
| Gestión de mesas | ✅ | ✅ | — | — |
| Menú / Productos | ✅ | ✅ | — | — |
| Categorías | ✅ | ✅ | — | — |
| Configuración facturación | ✅ | ✅ | — | — |
| Reportes | ✅ | ✅ | — | — |
| Historial de ventas | ✅ | ✅ | ✅ | — |
| Comanda / Pedidos | ✅ | ✅ | ✅ | — |
| Cobro y facturación | ✅ | ✅ | ✅ | — |
| Control de caja | ✅ | ✅ | ✅ | — |
| Gastos del turno | ✅ | ✅ | ✅ | — |
| Panel de cocina (KDS) | ✅ | ✅ | — | ✅ |

---

## 🗂️ Estructura del Proyecto

```
system-restaurant/
│
├── 📄 index.php                       ← Login principal (entry point)
├── 📄 .htaccess                       ← Seguridad Apache + cache
├── 📄 .env                            ← Variables de entorno (no subir a git)
│
├── 📁 config/
│   └── 📄 db.php                      ← Core: PDO, Auth, CSRF, helpers
│
├── 📁 auth/
│   ├── 📄 login.php                   ← Procesador de login (POST)
│   └── 📄 logout.php                  ← Destructor de sesión
│
├── 📁 roles/
│   ├── 📁 superadmin/
│   │   ├── 📄 dashboard.php           ← Panel global multi-tenant
│   │   ├── 📄 restaurantes.php        ← CRUD de restaurantes
│   │   └── 📄 perfil.php              ← Perfil del superadmin
│   │
│   ├── 📁 admin/
│   │   ├── 📄 dashboard.php           ← KPIs del día
│   │   ├── 📄 mesas.php               ← Mapa interactivo de mesas
│   │   ├── 📄 menu_productos.php      ← CRUD de productos
│   │   ├── 📄 menu_categorias.php     ← CRUD de categorías
│   │   ├── 📄 usuarios.php            ← Gestión de usuarios
│   │   ├── 📄 caja.php                ← Control de turnos
│   │   ├── 📄 gastos.php              ← Registro de egresos
│   │   ├── 📄 historial.php           ← Historial de ventas
│   │   ├── 📄 historial_comprobantes.php
│   │   ├── 📄 ver_comprobante.php     ← Vista/impresión
│   │   ├── 📄 turno_detalle.php       ← Detalle cierre de turno
│   │   ├── 📄 reportes.php            ← Reportes y gráficas
│   │   └── 📄 config_facturacion.php  ← Datos del negocio y series
│   │
│   ├── 📁 atencion/
│   │   ├── 📄 dashboard.php           ← Mapa de mesas para mozos
│   │   ├── 📄 comanda.php             ← Tomar pedidos (POS)
│   │   ├── 📄 cobrar.php              ← Proceso de cobro
│   │   ├── 📄 historial.php           ← Historial del turno
│   │   ├── 📄 gastos.php              ← Gastos del turno
│   │   └── 📄 comprobante_view.php    ← Vista imprimible
│   │
│   └── 📁 cocina/
│       └── 📄 dashboard.php           ← KDS: comandas en tiempo real
│
├── 📁 api/                            ← 24 Endpoints AJAX (JSON)
│   ├── 📄 get_menu.php
│   ├── 📄 get_mesas.php
│   ├── 📄 get_pedidos_activos.php
│   ├── 📄 get_pedido_detalle.php
│   ├── 📄 get_reportes.php
│   ├── 📄 get_opciones.php
│   ├── 📄 crear_pedido.php
│   ├── 📄 agregar_item.php
│   ├── 📄 eliminar_item.php
│   ├── 📄 marcar_item_listo.php
│   ├── 📄 cancelar_pedido.php
│   ├── 📄 cobrar_pedido.php
│   ├── 📄 guardar_comprobante.php
│   ├── 📄 anular_comprobante.php
│   ├── 📄 eliminar_venta.php
│   ├── 📄 reabrir_venta.php
│   ├── 📄 abrir_turno.php
│   ├── 📄 cerrar_turno.php
│   ├── 📄 consultar_documento.php
│   ├── 📄 admin_mesas.php
│   ├── 📄 admin_productos.php
│   ├── 📄 admin_categorias.php
│   ├── 📄 admin_usuarios.php
│   └── 📄 superadmin_restaurantes.php
│
├── 📁 includes/
│   ├── 📄 header.php                  ← HTML head + meta + CSS
│   ├── 📄 sidebar.php                 ← Navegación lateral por rol
│   └── 📄 footer.php                  ← Cierre de layout + JS
│
├── 📁 assets/
│   ├── 📄 logo.png
│   ├── 📄 timbre.mp3                  ← Alerta sonora (cocina)
│   ├── 📁 css/
│   │   ├── 📄 main.css                ← Design system (55KB)
│   │   └── 📄 print.css               ← Estilos de impresión
│   └── 📁 js/
│       └── 📄 main.js                 ← Lógica JavaScript global
│
└── 📁 Database/
    ├── 📄 restaurante_db.sql          ← Schema base de datos
    └── 📄 produccion_database.sql     ← Dump completo de producción
```

---

## ⚙️ Instalación y Configuración

### Requisitos del Sistema

| Componente | Versión Mínima | Recomendado |
|:---|:---:|:---:|
| PHP | 8.0 | 8.2+ |
| MySQL / MariaDB | 8.0 | 8.0+ |
| Apache | 2.4 | 2.4+ |
| mod_rewrite | ✅ Requerido | — |
| mod_headers | ✅ Requerido | — |

### Paso 1 — Clonar o Copiar el Proyecto

```bash
# Clonar en la carpeta htdocs de XAMPP
git clone https://github.com/reinerjimenez/Restaurant-SaaS- C:/xampp/htdocs/system-restaurant

# O copiar la carpeta manualmente a:
# C:\xampp\htdocs\system-restaurant\
```

### Paso 2 — Configurar la Base de Datos

> ⚠️ **IMPORTANTE:** Nunca modificar la base de datos de forma directa.  
> Siempre ejecutar los scripts `.sql` de la carpeta `Database/` en phpMyAdmin.

```sql
-- 1. Crear la base de datos
CREATE DATABASE restaurante_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Abrir phpMyAdmin → seleccionar restaurante_db → Importar:
--    Database/restaurante_db.sql
```

### Paso 3 — Configurar Variables de Entorno

Editar el archivo `.env` en la raíz del proyecto:

```env
# ── Base de Datos ─────────────────────
DB_HOST=localhost
DB_NAME=restaurante_db
DB_USER=root
DB_PASS=

# ── Charset ───────────────────────────
DB_CHARSET=utf8mb4

# ── API Facturación (DNI/RUC) ─────────
# Obtén tu token en: https://apis.net.pe
APIS_NET_TOKEN=sk_xxxxxxxx.xxxxxxxxxxxx

# ── URL Base (opcional) ───────────────
# Dejar vacío para auto-detección en localhost
# BASE_URL=https://mi-dominio.com
```

> 🔒 **Seguridad:** El archivo `.env` está protegido por `.htaccess`.  
> **Nunca** lo subas a un repositorio público.

### Paso 4 — Verificar Módulos Apache

```apache
# En httpd.conf (o Panel de Control de XAMPP):
LoadModule rewrite_module  modules/mod_rewrite.so
LoadModule headers_module  modules/mod_headers.so
LoadModule deflate_module  modules/mod_deflate.so
```

### Paso 5 — Acceder al Sistema

```
http://localhost/system-restaurant/
```

| Rol | Email | Contraseña |
|:---|:---|:---|
| Superadmin | `superadmin@restsaas.com` | Ver script SQL |
| Admin | Configurar desde panel superadmin | — |

---

## 🗄️ Base de Datos

### Tablas Principales

```
restaurante_db
│
├── 🏪 restaurantes          ← Tenants (multi-restaurante)
├── 👤 usuarios              ← Usuarios con rol y tenant
├── 🍽️  mesas                ← Mesas del restaurante
├── 📂 categorias            ← Categorías del menú
├── 🍕 productos             ← Productos / platos
├── 📋 pedidos               ← Pedidos activos/cobrados
├── 📝 pedido_items          ← Ítems de cada pedido
├── 💳 pagos                 ← Pagos (split payment)
├── 🧾 comprobantes          ← Boletas, facturas, simples
├── 📑 comprobante_items     ← Detalle de cada comprobante
├── 💰 turnos                ← Turnos de caja
├── 💸 gastos                ← Egresos por turno
├── 🏢 config_facturacion    ← Datos de facturación por restaurante
└── 📊 logs_acceso           ← Auditoría de accesos
```

### Convenciones de Diseño

- **Multi-tenancy:** Todas las tablas operativas incluyen `restaurante_id` para aislamiento de datos.
- **Soft delete:** Registros con columna `activo` (1/0) en lugar de eliminación física.
- **Auditoría:** Columnas `created_at` / `updated_at` con `DEFAULT CURRENT_TIMESTAMP`.
- **Transacciones:** Las operaciones críticas (cobro, comprobante) usan `BEGIN TRANSACTION`.
- **FOR UPDATE:** Bloqueo en pedidos para evitar condiciones de carrera concurrentes.

### Scripts de Base de Datos

> **Regla crítica:** Todos los cambios estructurales deben seguir el proceso establecido.

Para agregar un cambio:

```
1. Crear:  Database/NNN_descripcion_breve.sql
           Ejemplo: Database/003_agregar_tabla_reservas.sql

2. Avisar: "Creé el script Database/003_nombre.sql.
            Ejecútalo manualmente en phpMyAdmin antes de probar."
```

---

## 🔌 API Endpoints

Todos los endpoints retornan JSON con estructura estándar:

```json
{
  "success": true,
  "data": { ... },
  "message": "Descripción del resultado"
}
```

### 📋 Pedidos y Mesas

| Método | Endpoint | Descripción | Roles |
|:---:|:---|:---|:---:|
| `GET` | `/api/get_mesas.php` | Estado de todas las mesas | 🟠🟡 |
| `GET` | `/api/get_pedidos_activos.php` | Pedidos en curso (polling) | 🟠🟡🟢 |
| `GET` | `/api/get_pedido_detalle.php` | Detalle de un pedido | 🟠🟡🟢 |
| `GET` | `/api/get_menu.php` | Menú por categoría | 🟠🟡 |
| `POST` | `/api/crear_pedido.php` | Crear nuevo pedido | 🟠🟡 |
| `POST` | `/api/agregar_item.php` | Agregar ítem al pedido | 🟠🟡 |
| `POST` | `/api/eliminar_item.php` | Eliminar ítem del pedido | 🟠🟡 |
| `POST` | `/api/cancelar_pedido.php` | Cancelar pedido activo | 🟠🟡 |
| `POST` | `/api/marcar_item_listo.php` | Marcar ítem como preparado | 🟠🟡🟢 |

### 💳 Facturación y Cobros

| Método | Endpoint | Descripción | Roles |
|:---:|:---|:---|:---:|
| `POST` | `/api/cobrar_pedido.php` | Registrar pago | 🟠🟡 |
| `POST` | `/api/guardar_comprobante.php` | Emitir boleta/factura/simple | 🟠🟡 |
| `POST` | `/api/anular_comprobante.php` | Anular comprobante | 🟠🟡 |
| `POST` | `/api/eliminar_venta.php` | Eliminar venta | 🟠 |
| `POST` | `/api/reabrir_venta.php` | Reabrir pedido cobrado | 🟠🟡 |
| `GET` | `/api/consultar_documento.php` | Consultar DNI/RUC (API externa) | 🟠🟡 |

### 🏪 Administración

| Método | Endpoint | Descripción | Roles |
|:---:|:---|:---|:---:|
| `POST` | `/api/admin_mesas.php` | CRUD mesas | 🟠 |
| `POST` | `/api/admin_productos.php` | CRUD productos | 🟠 |
| `POST` | `/api/admin_categorias.php` | CRUD categorías | 🟠 |
| `POST` | `/api/admin_usuarios.php` | CRUD usuarios | 🟠 |
| `POST` | `/api/abrir_turno.php` | Abrir caja | 🟠🟡 |
| `POST` | `/api/cerrar_turno.php` | Cerrar caja con reporte | 🟠🟡 |
| `GET` | `/api/get_reportes.php` | Datos para gráficas | 🟠 |
| `POST` | `/api/superadmin_restaurantes.php` | CRUD restaurantes | 🔴 |

---

## 🔐 Seguridad

El sistema implementa **múltiples capas de seguridad**:

### Autenticación y Autorización

```php
// Guard de roles en cada página/endpoint
requireRole(['admin', 'superadmin']);

// Sesión segura con regeneración de ID al login
session_regenerate_id(true);

// Contraseñas con bcrypt
password_verify($password, $hash);
```

### Protección CSRF

```php
// Token de 32 bytes aleatorios por sesión
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Validación en form, JSON body y header HTTP
$token = $_SERVER['HTTP_X_CSRF_TOKEN']
      ?? $_POST['csrf_token']
      ?? $jsonPayload['csrf_token'];

// Comparación en tiempo constante (anti-timing attacks)
hash_equals($_SESSION['csrf_token'], $token);
```

### Headers HTTP de Seguridad

```apache
X-Frame-Options: SAMEORIGIN
X-Content-Type-Options: nosniff
Referrer-Policy: strict-origin-when-cross-origin
Content-Security-Policy: default-src 'self'; ...
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

### Prevención de SQL Injection

```php
// 100% Prepared Statements con PDO
$stmt = $db->prepare("SELECT * FROM usuarios WHERE email = :email");
$stmt->execute([':email' => $email]);

// PDO en modo estricto
PDO::ATTR_EMULATE_PREPARES => false
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
```

### Aislamiento Multi-Tenant

```php
// Cada query filtra por restaurante_id de la sesión activa
$restauranteId = $_SESSION['restaurante_id'];
// Todos los SELECTs incluyen: WHERE restaurante_id = ?
```

### Protección de Archivos Sensibles

```apache
# Bloqueo total de acceso directo a archivos críticos
<FilesMatch "\.(sql|ini|log|env)$">
    Deny from all
</FilesMatch>

Options -Indexes   # Sin listado de directorios
```

### Auditoría

```sql
-- Cada login queda registrado con IP y timestamp
INSERT INTO logs_acceso (usuario_id, accion, ip)
VALUES (?, 'login', ?)
```

---

## 🖥️ Módulos del Sistema

### 🔴 Superadmin — Panel Global

El superadmin gestiona toda la plataforma. Puede **cambiar de contexto** entre restaurantes mediante `?set_rest_id=N` para administrar cualquier local sin necesidad de cuentas adicionales.

| Vista | Descripción |
|:---|:---|
| `dashboard.php` | Resumen de todos los restaurantes activos |
| `restaurantes.php` | Crear, editar, activar/desactivar restaurantes |
| `perfil.php` | Gestión del perfil del superadmin |

---

### 🟠 Admin — Gestión del Restaurante

Panel completo de back-office para el administrador del local.

| Vista | Descripción |
|:---|:---|
| `dashboard.php` | KPIs: ventas del día, utilidad, mesas ocupadas |
| `mesas.php` | Mapa interactivo + CRUD de mesas |
| `menu_productos.php` | Gestión de carta con precios |
| `menu_categorias.php` | Organización del menú por categorías |
| `usuarios.php` | Alta/baja de mozos, cocineros, admins |
| `caja.php` | Apertura y cierre de turnos |
| `gastos.php` | Egresos y gastos del turno |
| `historial.php` | Registro histórico de ventas |
| `historial_comprobantes.php` | Comprobantes emitidos con filtros |
| `reportes.php` | Gráficas de rendimiento |
| `turno_detalle.php` | Detalle de cierre de turno específico |
| `ver_comprobante.php` | Vista e impresión de comprobante |
| `config_facturacion.php` | RUC, razón social, series de comprobantes |

---

### 🟡 Atención — Punto de Venta (POS)

Interfaz optimizada para mozos y personal de sala.

| Vista | Descripción |
|:---|:---|
| `dashboard.php` | Mapa de mesas en tiempo real |
| `comanda.php` | Tomar pedidos: menú por categorías + carrito |
| `cobrar.php` | Proceso de cobro: split payment + facturación |
| `historial.php` | Ventas del turno actual |
| `gastos.php` | Registro de egresos |
| `comprobante_view.php` | Vista de impresión de comprobante |

**Flujo de trabajo del mozo:**

```
Mapa de mesas → Seleccionar mesa libre → Crear pedido
      ↓
Comanda: agregar ítems del menú por categoría
      ↓
Cocina recibe la comanda automáticamente
      ↓
Mozo cobra: selecciona método(s) de pago (split permitido)
      ↓
Emitir comprobante: Boleta / Factura / Simple
      ↓
Imprimir ticket → Mesa queda libre
```

---

### 🟢 Cocina — Kitchen Display System (KDS)

Panel de cocina con actualización automática en tiempo real.

- **Polling automático** en JavaScript para refrescar pedidos
- **Alerta sonora** (`timbre.mp3`) al llegar un nuevo pedido
- Visualización por mesa y tiempo de espera
- Botón para marcar cada ítem como preparado individualmente
- Diseño optimizado para pantallas táctiles de cocina

---

## 🗺️ Flujo Completo del Sistema

```
┌─────────────────────────────────────────────────────────────────┐
│                      FLUJO OPERATIVO DIARIO                     │
└─────────────────────────────────────────────────────────────────┘

  [ADMIN] Abre turno de caja con monto inicial
         │
         ▼
  [MOZO] Ve mapa de mesas → Selecciona mesa libre
       → Crea nuevo pedido
       → Añade platos desde el menú por categoría
         │
         ▼
  [COCINA] Recibe pedido en KDS
         → Suena timbre de alerta
         → Prepara cada ítem
         → Marca ítems como "listos"
         │
         ▼
  [MOZO] Sirve los platos al cliente
       → Cliente solicita la cuenta
         │
         ▼
  [MOZO] Va a cobrar
       → Consulta DNI/RUC automáticamente si necesita factura
       → Selecciona método(s) de pago (efectivo, tarjeta, Yape...)
       → Emite comprobante (boleta / factura / simple)
       → Imprime ticket
         │
         ▼
  [ADMIN] Cierra turno al final del día
       → Ve resumen: Ventas - Gastos = Utilidad
       → Desglose por método de pago
       → Imprime reporte de cierre de caja
```

---

## 💻 Tecnologías Utilizadas

| Tecnología | Uso en el Proyecto | Versión |
|:---|:---|:---:|
| **PHP** | Backend, lógica de negocio, PDO, sesiones | 8.1+ |
| **MySQL** | Base de datos relacional multi-tenant | 8.0+ |
| **Apache** | Servidor web, `.htaccess`, compresión gzip | 2.4+ |
| **HTML5** | Estructura semántica de todas las vistas | 5 |
| **CSS3 (Vanilla)** | Design system completo, responsive, print styles | 3 |
| **JavaScript (ES6+)** | AJAX, UI dinámica, polling de cocina | ES6+ |
| **Font Awesome** | Iconografía en toda la interfaz | 6.5.1 |
| **Google Fonts (Inter)** | Tipografía moderna en todo el sistema | — |
| **APIs.net.pe** | Consulta de DNI y RUC para facturación | REST |

---

## 📁 Convenciones de Scripts SQL

> ⚠️ **Regla crítica:** Nunca modificar la base de datos de forma directa o automática.

Cuando se requiera cualquier cambio estructural (CREATE, ALTER, DROP) o datos iniciales:

```
1. Crear:  Database/NNN_descripcion_breve.sql
           Ejemplo: Database/003_agregar_tabla_reservas.sql

2. Avisar: "Creé el script Database/003_nombre.sql.
            Ejecútalo manualmente en phpMyAdmin antes de probar."
```

**Formato de nombre:**
```
NNN_descripcion_breve.sql
│││  │
│││  └── Descripción en snake_case
│└┴───── Número secuencial con ceros (001, 002, 003...)
```

---

## 👨‍💻 Autor

<div align="center">

<br/>

```
╔══════════════════════════════════════╗
║         REINER JIMÉNEZ               ║
║         Desarrollador Full Stack     ║
║         R.DEV                        ║
╚══════════════════════════════════════╝
```

[![LinkedIn](https://img.shields.io/badge/LinkedIn-Reiner%20Jiménez-0077B5?style=for-the-badge&logo=linkedin&logoColor=white)](https://www.linkedin.com/in/reiner-jairo-jim%C3%A9nez-huaman-9234a9388/)

<br/>

*Desarrollado con ❤️ para negocios gastronómicos modernos*

<br/>

© 2024–2026 · R.DEV · Todos los derechos reservados

</div>

---

<div align="center">

**¿Encontraste un bug? ¿Tienes una sugerencia?**  
Contacta al desarrollador a través de LinkedIn

<br/>

*RestSaaS — Gestión inteligente para restaurantes modernos* 🍽️

</div>
