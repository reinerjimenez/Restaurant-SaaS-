# Paquete de publicación de MESORA

Esta lista describe qué preparar para el directorio público de InfinityFree. No equivale a una prueba de despliegue ni autoriza subir archivos.

## Incluir

- `index.php`, `api/`, `auth/`, `config/`, `includes/` y `roles/`.
- Recursos runtime: `assets/` (sin logos de tenant descartados), `.htaccess` después de validar sus directivas en el servidor.
- Un archivo `.env` generado en el hosting con los valores exactos del panel InfinityFree. No copiar el `.env` local ni subirlo a Git.

## Excluir

- `docs/`, `tests/`, `scripts/`, `graphify-out/`, `.git/`, todos los archivos `Database/*.sql`, respaldos, logs, inventarios y resultados de auditoría.
- `.env.example` si revela configuraciones internas; en todo caso nunca contiene contraseñas reales.

## Comprobaciones antes de habilitar tráfico

1. Confirmar que el nombre completo de base y el host coinciden con MySQL Databases del panel; InfinityFree gratuito no usa `localhost` para MySQL y antepone el usuario de cuenta al esquema.
2. La guarda de 008 permite nombres completos prefijados y exige esquema seleccionado vacío. Importar solo una base vacía que coincida exactamente con `DB_NAME`.
3. Verificar HTTPS, cookies de sesión, permisos de escritura, logs y reglas `.htaccess` con el entorno efectivo.
4. Solicitar por HTTP `/docs/`, `/.git/`, `/Database/`, `/tests/`, `/scripts/`, `/graphify-out/` y `/.env`; todos deben devolver 403/404 sin contenido privado.
5. Confirmar los flujos críticos en el hosting y tener un respaldo restaurable antes de habilitar usuarios.

InfinityFree gratuito anuncia PHP 8.4 y no permite conexiones MySQL remotas desde el equipo local. La cuenta debe probarse desde la propia aplicación desplegada y phpMyAdmin del panel, nunca copiando credenciales del `.env` local.
