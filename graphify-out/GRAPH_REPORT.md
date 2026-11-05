# Graph Report - system-restaurant  (2026-11-05)

## Corpus Check
- 60 files · ~100,494 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 4 file(s) not represented in the graph (top: .css 2, (none) 1, .zip 1)

## Summary
- 232 nodes · 220 edges · 60 communities (14 shown, 46 thin omitted)
- Extraction: 98% EXTRACTED · 2% INFERRED · 0% AMBIGUOUS · INFERRED: 4 edges (avg confidence: 0.85)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `ffbe1a47`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- 🍽️ RestSaaS — Sistema de Gestión para Restaurantes
- menu_productos.php
- comanda.php
- mesas.php
- main.js
- cobrar.php
- atencion/dashboard.php
- cocina/dashboard.php
- db.php
- 🔐 Seguridad
- ✨ Características Principales
- ⚙️ Instalación y Configuración
- usuarios.php
- caja.php

## God Nodes (most connected - your core abstractions)
1. `🍽️ RestSaaS — Sistema de Gestión para Restaurantes` - 16 edges
2. `🔐 Seguridad` - 8 edges
3. `✨ Características Principales` - 7 edges
4. `⚙️ Instalación y Configuración` - 7 edges
5. `renderNuevosItems()` - 6 edges
6. `🖥️ Módulos del Sistema` - 5 edges
7. `renderizarGrupo()` - 4 edges
8. `obtenerTotalFinal()` - 4 edges
9. `detectarCambiosYAlertar()` - 4 edges
10. `renderPedidos()` - 4 edges

## Surprising Connections (you probably didn't know these)
- None detected - all connections are within the same source files.

## Import Cycles
- None detected.

## Communities (60 total, 46 thin omitted)

### Community 0 - "🍽️ RestSaaS — Sistema de Gestión para Restaurantes"
Cohesion: 0.07
Nodes (26): 🟠 Admin — Gestión del Restaurante, 🏪 Administración, 🔌 API Endpoints, 🏗️ Arquitectura del Sistema, 🟡 Atención — Punto de Venta (POS), 👨‍💻 Autor, 🗄️ Base de Datos, 🟢 Cocina — Kitchen Display System (KDS) (+18 more)

### Community 1 - "menu_productos.php"
Cohesion: 0.20
Nodes (10): agregarGrupo(), agregarValor(), agregarValorRender(), aplicarFiltrosAdmin(), buscarAdmin(), cargarGruposExistentes(), editarProducto(), filtrarCategoria() (+2 more)

### Community 2 - "comanda.php"
Cohesion: 0.20
Nodes (10): actualizarCountItems(), actualizarEstadoCobrar(), agregarProducto(), aplicarFiltros(), buscarPlatos(), filtrarCat(), nuevosItems, quitarGrupo() (+2 more)

### Community 3 - "mesas.php"
Cohesion: 0.22
Nodes (8): CAJA_ABIERTA, clickMesa(), crearPedidoYIr(), eliminarItemDetalle(), iniciarPedido(), refreshMesaCard(), renderDetalle(), verDetalle()

### Community 4 - "main.js"
Cohesion: 0.18
Nodes (5): abrirModalOpcion(), Loading, Modal, mostrarOpcionesSecuenciales(), Toast

### Community 5 - "cobrar.php"
Cohesion: 0.27
Nodes (11): activos, buscarDocumento(), calcularDiferencia(), confirmarCobro(), limpiarCamposCliente(), METODOS, obtenerTotalFinal(), onDocumentoInput() (+3 more)

### Community 6 - "atencion/dashboard.php"
Cohesion: 0.20
Nodes (5): agruparItemsDetalle(), CAJA_ABIERTA, crearPedidoYIr(), iniciarPedido(), verDetalle()

### Community 7 - "cocina/dashboard.php"
Cohesion: 0.27
Nodes (9): agruparItems(), aplicarModoFS(), cambiarEstadoItem(), cargarPedidosAhora(), construirSnapshot(), detectarCambiosYAlertar(), renderPedidos(), reproducirTimbre() (+1 more)

### Community 8 - "db.php"
Cohesion: 0.31
Nodes (6): getDB(), jsonResponse(), registrarAuditoria(), requireRole(), validateCsrfToken(), verifyCsrfProtection()

### Community 9 - "🔐 Seguridad"
Cohesion: 0.25
Nodes (8): Aislamiento Multi-Tenant, Auditoría, Autenticación y Autorización, Headers HTTP de Seguridad, Prevención de SQL Injection, Protección CSRF, Protección de Archivos Sensibles, 🔐 Seguridad

### Community 10 - "✨ Características Principales"
Cohesion: 0.29
Nodes (7): ✨ Características Principales, 💰 Control de Caja y Turnos, 📊 Dashboard Inteligente, 🧾 Facturación Electrónica, 🗺️ Mapa Interactivo de Mesas, 👨‍🍳 Panel de Cocina (KDS), 📈 Reportes y Analytics

### Community 11 - "⚙️ Instalación y Configuración"
Cohesion: 0.29
Nodes (7): ⚙️ Instalación y Configuración, Paso 1 — Clonar o Copiar el Proyecto, Paso 2 — Configurar la Base de Datos, Paso 3 — Configurar Variables de Entorno, Paso 4 — Verificar Módulos Apache, Paso 5 — Acceder al Sistema, Requisitos del Sistema

### Community 12 - "usuarios.php"
Cohesion: 0.38
Nodes (3): editarUsuario(), nuevoUsuario(), ocultarPassword()

### Community 14 - "caja.php"
Cohesion: 0.47
Nodes (3): abrirCaja(), cerrarCaja(), showToast()

## Knowledge Gaps
- **46 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `🍽️ RestSaaS — Sistema de Gestión para Restaurantes` connect `🍽️ RestSaaS — Sistema de Gestión para Restaurantes` to `🔐 Seguridad`, `✨ Características Principales`, `⚙️ Instalación y Configuración`?**
  _High betweenness centrality (0.039) - this node is a cross-community bridge._
- **Should `🍽️ RestSaaS — Sistema de Gestión para Restaurantes` be split into smaller, more focused modules?**
  _Cohesion score 0.07407407407407407 - nodes in this community are weakly interconnected._
- **Why does `🔐 Seguridad` connect `🔐 Seguridad` to `🍽️ RestSaaS — Sistema de Gestión para Restaurantes`?**
  _High betweenness centrality (0.012) - this node is a cross-community bridge._
- **Why does `✨ Características Principales` connect `✨ Características Principales` to `🍽️ RestSaaS — Sistema de Gestión para Restaurantes`?**
  _High betweenness centrality (0.010) - this node is a cross-community bridge._