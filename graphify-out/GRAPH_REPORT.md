# Graph Report - system-restaurant  (2026-11-11)

## Corpus Check
- 136 files · ~110,505 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 5 file(s) not represented in the graph (top: (none) 2, .css 2, .example 1)

## Summary
- 654 nodes · 782 edges · 91 communities (29 shown, 62 thin omitted)
- Extraction: 96% EXTRACTED · 4% INFERRED · 0% AMBIGUOUS · INFERRED: 28 edges (avg confidence: 0.87)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `393a2e93`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- 🍽️ RestSaaS — Sistema de Gestión para Restaurantes
- menu_productos.php
- comanda.php
- main.js
- cobrar.php
- atencion/dashboard.php
- cocina/dashboard.php
- db.php
- finanzas.php
- ConexionReportePrueba
- Hallazgos de auditoría
- usuarios.php
- caja.php
- admin/historial.php
- atencion/historial.php
- sesion_cgi.py
- Ola 1 — Estabilizar (1–2 semanas)
- reportes_financieros.php
- Entrega técnica de la Ola 1
- limite_login.php
- Base de datos e integridad del dinero
- Arquitectura, operación, capacidad y evolución
- ola_1_contexto_http.mjs
- Resumen ejecutivo — Restaurant SaaS
- Diseño de cambios SQL para preparar durante la remediación
- Matriz de endpoints y modelo de amenazas
- Veredicto para el segundo restaurante
- Cobertura, hipótesis y límites
- Mejoras al encargo para futuras auditorías
- 09_validacion_entrega.md
- pruebas/README.md
- 12_aceptacion_integral_ola_1.md
- Preparación del entorno real de ensayo de Ola 1
- Auditoría final del paquete de ensayo de Ola 1

## God Nodes (most connected - your core abstractions)
1. `Hallazgos de auditoría` - 23 edges
2. `🍽️ RestSaaS — Sistema de Gestión para Restaurantes` - 16 edges
3. `Entrega técnica de la Ola 1` - 15 edges
4. `autenticar_login()` - 10 edges
5. `Base de datos e integridad del dinero` - 10 edges
6. `Ola 1 — Estabilizar (1–2 semanas)` - 10 edges
7. `ConexionReportePrueba` - 9 edges
8. `jsonResponse()` - 8 edges
9. `importe_centimos()` - 8 edges
10. `normalizar_pagos()` - 8 edges

## Surprising Connections (you probably didn't know these)
- `H-020 — Sesiones no revocadas al desactivar (resultado)` --references--> `requireRole()`  [INFERRED]
  docs/auditoria/05_plan_de_remediacion.md → config/db.php
- `H-010 — XSS almacenado (resultado)` --references--> `agregarValorRender()`  [INFERRED]
  docs/auditoria/05_plan_de_remediacion.md → roles/admin/menu_productos.php
- `H-010 — XSS almacenado (resultado)` --references--> `abrirModalOpcion()`  [INFERRED]
  docs/auditoria/05_plan_de_remediacion.md → assets/js/main.js
- `{closure#1}()` --calls--> `importe_centimos()`  [INFERRED]
  roles/atencion/cobrar.php → config/finanzas.php
- `Hallazgos por inspección antes de las correcciones` --references--> `cobros_periodo()`  [INFERRED]
  docs/auditoria/12_aceptacion_integral_ola_1.md → includes/reportes_financieros.php

## Import Cycles
- None detected.

## Communities (91 total, 62 thin omitted)

### Community 0 - "🍽️ RestSaaS — Sistema de Gestión para Restaurantes"
Cohesion: 0.04
Nodes (48): 🟠 Admin — Gestión del Restaurante, 🏪 Administración, Aislamiento Multi-Tenant, 🔌 API Endpoints, 🏗️ Arquitectura del Sistema, 🟡 Atención — Punto de Venta (POS), Auditoría, Autenticación y Autorización (+40 more)

### Community 1 - "menu_productos.php"
Cohesion: 0.20
Nodes (10): agregarGrupo(), agregarValor(), agregarValorRender(), aplicarFiltrosAdmin(), buscarAdmin(), cargarGruposExistentes(), editarProducto(), filtrarCategoria() (+2 more)

### Community 2 - "comanda.php"
Cohesion: 0.19
Nodes (13): actualizarCountItems(), actualizarEstadoCobrar(), agregarProducto(), aplicarFiltros(), buscarPlatos(), enviarACocina(), filtrarCat(), limpiarEnvioPendiente() (+5 more)

### Community 4 - "main.js"
Cohesion: 0.08
Nodes (19): abrirModalOpcion(), Loading, Modal, mostrarOpcionesSecuenciales(), Toast, H-000 — Token API versionado (resultado), H-010 — XSS almacenado (resultado), H-018 — Instalador de producción incompleto (resultado) (+11 more)

### Community 5 - "cobrar.php"
Cohesion: 0.26
Nodes (14): activos, buscarDocumento(), calcularDiferencia(), centimosCobro(), {closure#1}(), confirmarCobro(), limpiarCamposCliente(), METODOS (+6 more)

### Community 6 - "atencion/dashboard.php"
Cohesion: 0.20
Nodes (5): agruparItemsDetalle(), CAJA_ABIERTA, crearPedidoYIr(), iniciarPedido(), verDetalle()

### Community 7 - "cocina/dashboard.php"
Cohesion: 0.29
Nodes (9): agruparItems(), aplicarModoFS(), cambiarEstadoItem(), cargarPedidosAhora(), construirSnapshot(), detectarCambiosYAlertar(), renderPedidos(), reproducirTimbre() (+1 more)

### Community 8 - "db.php"
Cohesion: 0.17
Nodes (15): {closure#1}(), {closure#2}(), es_peticion_ajax(), es_peticion_api(), getDB(), jsonResponse(), registrarAuditoria(), requireRole() (+7 more)

### Community 9 - "finanzas.php"
Cohesion: 0.14
Nodes (25): bloquear_pedido(), bloquear_restaurante(), calcular_cobro(), {closure#1}(), decimal_centimos(), eliminar_mesa_sin_pedido(), error_financiero(), exigir_turno_abierto() (+17 more)

### Community 10 - "ConexionReportePrueba"
Cohesion: 0.10
Nodes (7): ConexionReportePrueba, ConsultaReportePrueba, registrarAuditoria(), verificar_reporte(), comprobar_cobro(), ConexionCobroDeEnsayo, SubtotalDeEnsayo

### Community 11 - "Hallazgos de auditoría"
Cohesion: 0.08
Nodes (23): [H-000] .env con token no vacío está versionado, [H-001] Las correcciones de ventas reescriben cajas cerradas, [H-002] Reportes atribuyen ventas y gastos a fechas diferentes, [H-003] Login sin limitación propia de intentos, [H-004] La aplicación no fija política de sesión, [H-005] Cambiar restaurante por GET afecta a pestañas antiguas, [H-006] Borrados y edición de opciones alteran el histórico, [H-007] Agregar ítems puede terminar después del cobro o cancelación (+15 more)

### Community 12 - "usuarios.php"
Cohesion: 0.38
Nodes (3): editarUsuario(), nuevoUsuario(), ocultarPassword()

### Community 14 - "caja.php"
Cohesion: 0.47
Nodes (3): abrirCaja(), cerrarCaja(), showToast()

### Community 60 - "sesion_cgi.py"
Cohesion: 0.06
Nodes (15): comprobar(), ejecutar(), cookie_sesion(), Formulario, hay_secreto(), main(), publicar(), registrar() (+7 more)

### Community 61 - "Ola 1 — Estabilizar (1–2 semanas)"
Cohesion: 0.10
Nodes (19): H-001: entrega y reversa, H-002: entrega y reversa, H-003: entrega y reversa, H-004: entrega y reversa, H-005: entrega y reversa, H-006: entrega y reversa, H-007: entrega y reversa, H-008: entrega y reversa (+11 more)

### Community 62 - "reportes_financieros.php"
Cohesion: 0.12
Nodes (18): Aceptación integral de la Ola 1, Alcance y entorno, Correcciones y resultados posteriores a la auditoría, Hallazgos por inspección antes de las correcciones, Migraciones y reversión, Orden de bloqueos, Pruebas ejecutadas en la auditoría inicial, Pruebas integrales pendientes (+10 more)

### Community 63 - "Entrega técnica de la Ola 1"
Cohesion: 0.13
Nodes (15): Entrega técnica de la Ola 1, H-001 · Caja y reversos, H-002 · Fechas y responsables, H-003 · Intentos y contraseñas, H-004 · Sesiones, H-005 · Contexto de restaurante, H-006 · Histórico de catálogo, H-007 · Concurrencia e idempotencia (+7 more)

### Community 64 - "limite_login.php"
Cohesion: 0.33
Nodes (10): autenticar_login(), bloquear_limite_login(), clave_cuenta_login(), clave_ip_login(), espera_login(), estado_login_vigente(), guardar_limite_login(), normalizar_identificador_login() (+2 more)

### Community 65 - "Base de datos e integridad del dinero"
Cohesion: 0.18
Nodes (10): Backup y restauración dentro del hosting actual, Base de datos e integridad del dinero, Consultas e índices propuestos, Crecimiento: escenario explícito, no tráfico medido, Estrategia por etapa, Migraciones y consolidación, Modelo observado, Reconciliación por pantalla (+2 more)

### Community 66 - "Arquitectura, operación, capacidad y evolución"
Cohesion: 0.22
Nodes (8): Arquitectura, operación, capacidad y evolución, Capacidad de polling y límite del proveedor, Costos y punto de cambio, Estado actual y cambios que sí reducen riesgo, Incrementos compatibles ahora, Observabilidad y pruebas mínimas, Privacidad y documentos fiscales, Robustez y frontend

### Community 67 - "ola_1_contexto_http.mjs"
Cohesion: 0.15
Nodes (9): almacen, entorno, envios, funciones, vista, llamadas, plantilla, valores (+1 more)

### Community 68 - "Resumen ejecutivo — Restaurant SaaS"
Cohesion: 0.29
Nodes (6): Alcance y mejoras incorporadas a la auditoría, Archivos de lectura recomendada, Diez prioridades del negocio, Evaluación orientativa (0–10), Resumen ejecutivo — Restaurant SaaS, Ruta para mejorar sin reescribir

### Community 69 - "Diseño de cambios SQL para preparar durante la remediación"
Cohesion: 0.29
Nodes (6): 1. Reparar instalador vacío (H-018), 2. Movimientos ligados a turno (H-001), 3. Índices (H-008), 4. Registro de migraciones (H-015), 5. Snapshot y validaciones (H-006/H-023), Diseño de cambios SQL para preparar durante la remediación

### Community 70 - "Matriz de endpoints y modelo de amenazas"
Cohesion: 0.33
Nodes (5): Flujo de sesión, Inventario de responsabilidades, Lectura de los controles, Matriz de endpoints y modelo de amenazas, Modelo de amenazas

### Community 71 - "Veredicto para el segundo restaurante"
Cohesion: 0.33
Nodes (5): Condiciones obligatorias de salida, Decisión al terminar Fase 1, Escenarios pendientes de ejecución, Reversa operativa, Veredicto para el segundo restaurante

### Community 72 - "Cobertura, hipótesis y límites"
Cohesion: 0.33
Nodes (5): Archivos generados y exclusiones explícitas, Autoevaluación de la entrega, Cobertura, hipótesis y límites, Comprobaciones y evidencias guardadas, Hipótesis originales

### Community 79 - "12_aceptacion_integral_ola_1.md"
Cohesion: 0.06
Nodes (36): 22 archivos nuevos, incluido este informe, 58 archivos modificados, Estado de validación, Los cuatro scripts SQL, Ola 1 · Informe resumido de cambios, Consultas y procedimiento manual, Diferencias y riesgos, Esquema esperado (+28 more)

### Community 80 - "Preparación del entorno real de ensayo de Ola 1"
Cohesion: 0.20
Nodes (10): 1. Inventario y requisitos, 2. Bases, cuentas y configuración privada, 3. Verificar identidad, motor y aislamiento, 4. Importación manual en phpMyAdmin, 5. Datos sintéticos y concurrencia, 6. Respaldo, restauración y conciliación, 7. Riesgos y estado al entregar, Preparación del entorno real de ensayo de Ola 1 (+2 more)

### Community 83 - "Auditoría final del paquete de ensayo de Ola 1"
Cohesion: 0.09
Nodes (18): Alcance y método, Auditoría final del paquete de ensayo de Ola 1, Decisión y límites, Hallazgos, Resultado por objetivo, Archivos del cambio, Comprobaciones locales, Próximo paso manual (+10 more)

## Knowledge Gaps
- **62 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `guardar_gasto()` connect `finanzas.php` to `db.php`, `reportes_financieros.php`?**
  _High betweenness centrality (0.099) - this node is a cross-community bridge._
- **Should `🍽️ RestSaaS — Sistema de Gestión para Restaurantes` be split into smaller, more focused modules?**
  _Cohesion score 0.04081632653061224 - nodes in this community are weakly interconnected._
- **Why does `fecha_reporte()` connect `reportes_financieros.php` to `finanzas.php`?**
  _High betweenness centrality (0.092) - this node is a cross-community bridge._
- **Should `main.js` be split into smaller, more focused modules?**
  _Cohesion score 0.07956989247311828 - nodes in this community are weakly interconnected._
- **Why does `Aceptación integral de la Ola 1` connect `reportes_financieros.php` to `12_aceptacion_integral_ola_1.md`?**
  _High betweenness centrality (0.092) - this node is a cross-community bridge._
- **Should `finanzas.php` be split into smaller, more focused modules?**
  _Cohesion score 0.13911290322580644 - nodes in this community are weakly interconnected._
- **Should `ConexionReportePrueba` be split into smaller, more focused modules?**
  _Cohesion score 0.10052910052910052 - nodes in this community are weakly interconnected._