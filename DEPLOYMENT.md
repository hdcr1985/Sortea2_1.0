# DEPLOYMENT: Produccion y Variables de Entorno

**Proyecto:** proyecto_base  
**Actualizado:** 2026-11-07  
**Alcance:** variables de produccion, TOTP, sesiones, base de datos y checklist.  
**Documento canonico:** este archivo. `MEMORY.md` lo referencia en el ADR-030.

---

## 1. Tareas programadas

`proyecto_base` **no tiene workers SMS, archivos `.bat` ni `Sms_worker`**. No se
deben programar en Windows Task Scheduler los `.bat` de VerifyCode2.0 contra
este proyecto.

La configuracion de Task Scheduler de SMS esta documentada en:

```text
C:\xampp\htdocs\VerifyCode2.0\DEPLOYMENT.md
```

Si en el futuro se agrega un worker a proyecto_base, crear un documento propio
con el archivo, comando, frecuencia, cuenta, timeout, logs y variables.

---

## 2. Variables obligatorias de `.env`

El archivo `.env` es la fuente autoritativa. No subirlo al repositorio.

| Variable | Valor de produccion | Nota |
| --- | --- | --- |
| `CI_ENV` | `production` | Desactiva comportamiento de desarrollo |
| `APP_BASE_URL` | `https://dominio-real/` | Debe terminar en `/` |
| `APP_ENCRYPTION_KEY` | Clave aleatoria de 32 bytes o mas | No reutilizar el valor de ejemplo |
| `APP_EMAIL_USERAGENT` | Nombre real de la aplicacion | Identificacion de correos |
| `CURL_CA_BUNDLE` | Ruta valida al bundle CA | Necesario para pagos/HTTPS |
| `DB_HOSTNAME` | Host real de la base de datos | No usar `127.0.0.1` si la DB es remota |
| `DB_PORT` | Puerto real | Normalmente `3306` |
| `DB_DATABASE` | Base de datos de produccion | No usar la base de desarrollo |
| `DB_USERNAME` | Usuario de base de datos de minimo privilegio | No usar `root` |
| `DB_PASSWORD` | Contrasena robusta | No reutilizar la de desarrollo |
| `DB_CHARSET` | `utf8mb4` | Coincidir con la base y las tablas |
| `DB_COLLATION` | `utf8mb4_unicode_ci` | Coincidir con la base |
| `SESSION_DRIVER` | `files` | Mantener el driver actual |
| `SESSION_COOKIE_NAME` | Nombre unico, por ejemplo `proyecto_base_session` | Evitar el valor generico |
| `SESSION_EXPIRATION` | `7200` o el valor aprobado | Segundos |
| `SESSION_SAVE_PATH` | Ruta fuera del web root y escribible | Por ejemplo `C:\sessions\proyecto_base` |
| `SESSION_MATCH_IP` | `false` | Evitar expulsiones por proxies/NAT |
| `SESSION_TIME_TO_UPDATE` | `300` | Segundos |
| `COOKIE_DOMAIN` | Dominio real | Vacio solo si se usa host-only |
| `COOKIE_PATH` | `/` | Ruta de la cookie |
| `COOKIE_SECURE` | `true` | Obligatorio con HTTPS |
| `COOKIE_HTTPONLY` | `true` | Evita acceso desde JavaScript |
| `SIMULATED_CLIENT_IP` | Vacio | Nunca dejar una IP simulada en produccion |

---

## 3. Secretos que no van en `.env`

Configurar desde la interfaz administrativa o la base de datos:

- Claves de Stripe/BTCPay en la configuracion de pasarelas.
- Servidor SMTP en **Configuracion de Correos**.
- Planes, precios y creditos en sus modulos.
- Roles y permisos en **Permisos**.
- Secretos TOTP: se generan y cifran automaticamente por usuario.

No guardar secretos en `DEPLOYMENT.md`, `MEMORY.md` ni en Git.

---

## 4. Rotacion de `APP_ENCRYPTION_KEY`

`APP_ENCRYPTION_KEY` cifra los secretos TOTP. Cambiarla invalida los secretos
existentes.

Antes de produccion:

1. Respaldar la base de datos y el `.env`.
2. Generar una clave nueva y unica para proyecto_base.
3. Si no hay usuarios con TOTP, guardar la clave y continuar.
4. Si ya hay usuarios con TOTP:
   - Implementar un script de re-cifrado con clave anterior y nueva; o
   - Desactivar/reinscribir TOTP de los usuarios afectados.
5. Actualizar el `.env`, reiniciar PHP/Apache y probar login TOTP.
6. No borrar la clave anterior hasta confirmar que no quedan secretos
   cifrados con ella.

Generacion de una clave en PowerShell 7:

```powershell
$bytes = New-Object byte[] 32
[Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
[Convert]::ToHexString($bytes).ToLower()
```

No reutilizar la misma clave entre proyecto_base y VerifyCode2.0.

`application/config/config.php` conserva un fallback publico de ejemplo. No se
debe depender de el: el `.env` debe sobrescribirlo siempre.

---

## 5. PHP, servidor y permisos

| Elemento | Valor de produccion |
| --- | --- |
| `date.timezone` | `UTC` o la zona oficial aprobada |
| Reloj del servidor | Sincronizado por NTP; critico para TOTP |
| `display_errors` | `Off` |
| `log_errors` | `On` |
| `error_log` | Ruta escribible fuera del web root |
| HTTPS | Certificado valido y redireccion desde HTTP |
| Apache | `AllowOverride All` si se usa `.htaccess` |
| IIS | `web.config` con las mismas reglas de bloqueo |
| Acceso web a `.env` | Bloqueado |
| `application/logs` | Escribible por Apache |
| `application/cache` | Escribible por Apache |

---

## 6. Checklist de produccion

- [ ] Respaldar base de datos, `.env` y archivos de configuracion.
- [ ] Aplicar todas las migraciones pendientes, en orden.
- [ ] Verificar especialmente `2026-11-07_add_totp_2fa.sql`.
- [ ] Configurar `.env` de produccion.
- [ ] Rotar `APP_ENCRYPTION_KEY` y re-cifrar o reinscribir TOTP si aplica.
- [ ] Activar HTTPS, cookies seguras y CA bundle.
- [ ] Configurar NTP y zona horaria.
- [ ] Desactivar errores en pantalla y herramientas de desarrollo.
- [ ] Bloquear `.env` desde HTTP.
- [ ] Probar login normal, TOTP y codigo de recuperacion.
- [ ] Revisar `application/logs/` despues del despliegue.

---

## 7. Verificacion posterior al despliegue

1. Confirmar que la aplicacion carga con el dominio y HTTPS reales.
2. Probar login, logout, recuperacion de contrasena y TOTP.
3. Confirmar que los pagos y webhooks usan credenciales de produccion.
4. Revisar que no haya errores fatales en `application/logs/`.
5. Confirmar que el reloj del servidor esta sincronizado.

---

## 8. Rollback operativo

- Restaurar `.env` y la base de datos desde el respaldo si el cambio de clave o
  las migraciones fallan.
- Si se restaura una base con secretos TOTP cifrados, restaurar tambien la
  `APP_ENCRYPTION_KEY` con la que fueron cifrados.
- Documentar el incidente y la decision en `MEMORY.md` al cerrar.
