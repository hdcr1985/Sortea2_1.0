# Proyecto Base - Sistema Administrativo CodeIgniter 3 + Metronic

Starter kit administrativo reutilizable para construir sistemas personales o
empresariales (ERP, CRM, inventarios, facturación, citas, paneles de control,
etc.) sobre **CodeIgniter 3**, **Metronic**, **MySQL/MariaDB** y **XAMPP**.

El proyecto ya resuelve el núcleo transversal: autenticación, RBAC, menús
dinámicos, layout Metronic, CRUD canónico, auditoría, correo, notificaciones,
reportes, temas claro/oscuro y un sistema multi-agente de desarrollo.

> Documentos relacionados:
> - `AGENTS.md`: reglas maestras y arquitectura multi-agente.
> - `MEMORY.md`: memoria histórica, ADRs, inventario y estado real de la BD.
> - `.agents/contracts/PROJECT_CONTRACTS.md`: contratos de handoff, permisos y cierre.
> - `SKILL.md` y `.agents/skills/`: procedimientos ejecutables por habilidad.

---

## Tabla de contenido

1. [Qué hace el proyecto](#1-qué-hace-el-proyecto)
2. [Stack y requisitos](#2-stack-y-requisitos)
3. [Instalación](#3-instalación)
4. [Estructura del repositorio](#4-estructura-del-repositorio)
5. [Arquitectura de ejecución](#5-arquitectura-de-ejecución)
6. [Enrutamiento dinámico](#6-enrutamiento-dinámico)
7. [Autenticación y sesiones](#7-autenticación-y-sesiones)
8. [Roles, permisos y RBAC](#8-roles-permisos-y-rbac)
9. [Patrón canónico de módulos CRUD](#9-patrón-canónico-de-módulos-crud)
10. [Contratos HTTP y DataTables](#10-contratos-http-y-datatables)
11. [Reglas de frontend Metronic](#11-reglas-de-frontend-metronic)
12. [Reglas de seguridad](#12-reglas-de-seguridad)
13. [Base de datos y migraciones](#13-base-de-datos-y-migraciones)
14. [Sistema multi-agente](#14-sistema-multi-agente)
15. [Inventario de módulos](#15-inventario-de-módulos)
16. [Operación y verificación](#16-operación-y-verificación)
17. [Limitaciones conocidas](#17-limitaciones-conocidas)
18. [Licencia y componentes de terceros](#18-licencia-y-componentes-de-terceros)

---

## 1. Qué hace el proyecto

### Núcleo funcional

- **Autenticación tradicional** por usuario y contraseña, con `password_hash`
  y migración automática de hashes MD5 heredados.
- **Autenticación de dos pasos (2FA)** por correo, con código de 6 dígitos,
  expiración, reenvío y bloqueo persistente por fuerza bruta (5 intentos / 15
  minutos).
- **OAuth 2.0** con Google y Apple, activable desde el módulo de tema. Los
  usuarios con 2FA habilitado deben usar el flujo usuario/contraseña + 2FA.
- **Registro público** opcional, con verificación de correo y rol restringido
  (`1004` por defecto).
- **Recuperación de contraseña** con token de un solo uso, hash en BD,
  expiración y límite de envíos por ventana de tiempo.
- **Sesiones con expiración por inactividad real** (sliding expiration),
  heartbeat de actividad, expulsión si el usuario es desactivado y cierre
  remoto de sesiones.
- **RBAC deny-by-default**: cada controlador administrativo debe estar
  registrado en `cab_menu` y asignado al rol. El rol `1000` es la única
  excepción global.
- **Menús dinámicos** desde base de datos, sin registrar módulos en
  `routes.php`.
- **Layout Metronic** con sidebar oscuro estilo demo1, header, notificaciones,
  breadcrumbs, banner global y modo claro/oscuro en tiempo real.
- **Configuración centralizada** de identidad corporativa, monedas, tasa de
  cambio, mantenimiento, banner, tema y correo.
- **Auditoría** de acciones administrativas en `cab_bitacora` y de inicios de
  sesión en `cab_bitacora_ip`.
- **Reportes** de bitácora, logins, sesiones activas, errores del log de
  CodeIgniter y Google Analytics 4.
- **Correo transaccional y promocional** con plantillas HTML editables, envío
  de prueba, grupos de destinatarios y transporte SMTP con TLS moderno.
- **Media protegida**: avatares y logos se sirven mediante `Media.php`, nunca
  por acceso directo a `upload/`.
- **Geolocalización de IP** con bases MaxMind/DB-IP (país, ciudad, ASN, ISP).

### Módulos administrativos incluidos

| Área | Módulos |
| --- | --- |
| Seguridad | Usuarios, Perfil, Permisos (roles), Menús, Notificaciones |
| Configuración | Configuración General, Tema, Correos SMTP, Google Analytics |
| Comunicaciones | Plantillas de correo, Correos promocionales |
| Reportes | Bitácora, Logins, Sesiones activas, Errores (logs), Google Analytics 4 |
| Núcleo | Dashboard, Media, Errores, Autenticación |

### Qué NO incluye el repositorio

- **No incluye un dump de la base de datos base** (`cab_usuarios`,
  `cab_grupopermi`, `cab_menu`, `cab_permisologia`, `cab_permiaccion`,
  `cab_bitacora`, etc.). Las migraciones de `database/` son incrementales:
  asumen que esas tablas ya existen.
- **No incluye `.env`** por seguridad. Solo se versiona `.env.example`.
- **No incluye `vendor/`** (dependencias Composer) ni `node_modules/`.
- **No incluye un usuario administrador con contraseña por defecto**. Debes
  crear el Admin Master en la base de datos.

---

## 2. Stack y requisitos

| Componente | Versión / requisito | Notas |
| --- | --- | --- |
| PHP | `>= 8.5.12` según `composer.json`; entorno verificado: **8.5.12** | CodeIgniter 3 funciona en 7.4+, pero el proyecto se prueba en 8.5 |
| Extensiones PHP | `mysqli` y `mbstring` (requeridas en `composer.json`); `openssl`, `curl`, `fileinfo`, `gd`, `intl`, `zip` | `gd` se usa para optimizar avatares; si falta, se guarda el archivo validado |
| Base de datos | MySQL / MariaDB; verificado: **MariaDB 11.8.9** | El esquema mezcla InnoDB/utf8mb4 y tablas heredadas MyISAM/utf8mb3 |
| Servidor web | Apache con `mod_rewrite` y `mod_headers`; IIS usa `web.config` | XAMPP es el entorno de referencia |
| Git | Con **Git LFS** instalado | Necesario para la base GeoIP de 124 MB |
| Composer | Opcional | El runtime no depende de `vendor/`; las librerías externas están en `third_party/` |
| Navegador | Navegador moderno con soporte ES6 | Metronic, Bootstrap 5, DataTables, SweetAlert2 |

---

## 3. Instalación

### 3.1 Clonar con Git LFS

```bash
git lfs install
git clone https://github.com/hdcr1985/proyecto_base.git
cd proyecto_base
git lfs pull
```

El archivo `third_party/geoip/dbip-city-lite-2026-05.mmdb` está versionado con
Git LFS. Si no se descarga, el sistema sigue funcionando, pero la auditoría de
logins no podrá enriquecer país/ciudad/ASN.

### 3.2 Preparar la base de datos

1. Crea la base de datos (por ejemplo `proyecto_base`) con `utf8mb4`.
2. Importa el **esquema base** de tu instalación existente. Debe contener al
   menos:
   - `cab_usuarios`
   - `cab_grupopermi`
   - `cab_menu`
   - `cab_permisologia`
   - `cab_permiaccion`
   - `cab_bitacora`
   - `cab_bitacora_ip`
3. Aplica las migraciones de `database/` en orden cronológico:

```bash
mysql -u root -p proyecto_base < database/2026-05-02_add_men_inicio_to_cab_menu.sql
# ... repetir en orden por fecha ...
mysql -u root -p proyecto_base < database/2026-10-28_security_hardening_rbac.sql
```

> Regla del proyecto: **no modifiques una migración que ya pudo haberse
> aplicado**. Crea una nueva con fecha `YYYY-MM-DD_descripcion.sql`. Las
> migraciones deben ser idempotentes (`INSERT ... WHERE NOT EXISTS`,
> `INSERT IGNORE`, `CREATE TABLE IF NOT EXISTS`).

### 3.3 Configurar variables de entorno

```bash
cp .env.example .env
```

Edita `.env` con los valores de tu entorno. Mínimo recomendado:

| Variable | Ejemplo | Obligatoria |
| --- | --- | --- |
| `CI_ENV` | `development` local / `production` en servidor | Sí |
| `APP_BASE_URL` | `http://localhost/proyecto_base/` | Sí |
| `APP_ENCRYPTION_KEY` | Cadena aleatoria única de 32+ caracteres | Sí |
| `DB_HOSTNAME` | `127.0.0.1` | Sí |
| `DB_PORT` | `3306` | Sí |
| `DB_USERNAME` | `root` | Sí |
| `DB_PASSWORD` | Tu contraseña | Sí |
| `DB_DATABASE` | `proyecto_base` | Sí |
| `DB_CHARSET` | `utf8mb4` | Recomendado |
| `DB_COLLATION` | `utf8mb4_unicode_ci` | Recomendado |
| `SESSION_SAVE_PATH` | `C:/xampp/sessions/proyecto_base` | Muy recomendado |
| `SESSION_SAMESITE` | `Lax` | Opcional (por defecto `Lax`) |

Advertencias:

- `.env` está en `.gitignore`. **Nunca lo subas al repositorio.**
- El fallback de `application/config/config.php` para `SESSION_SAVE_PATH` es
  una ruta heredada (`rose_sessions` fuera del proyecto). Define
  `SESSION_SAVE_PATH` explícitamente y crea el directorio con permisos de
  escritura para el usuario de Apache.
- `application/config/database.php` conserva credenciales por defecto de
  desarrollo. En producción define todas las variables `DB_*` en `.env`.
- El cargador de `.env` está implementado en `index.php` (`load_env_file` y
  `env_value`). No usa una librería externa: admite comentarios con `#`,
  comillas simples/dobles y convierte `true`, `false`, `null` y `empty`.

### 3.4 Permisos de escritura

El usuario de Apache debe poder escribir en:

```text
application/cache/
application/logs/
upload/theme/
upload/usuarios/
<SESSION_SAVE_PATH>
```

### 3.5 Configurar Apache

- Habilita `mod_rewrite` y `mod_headers`.
- Permite `.htaccess` con `AllowOverride All` en el directorio del proyecto.
- El `.htaccess` raíz ya hace lo siguiente:
  - Redirige todas las peticiones a `index.php`.
  - Bloquea el acceso HTTP directo a `application/`, `system/`, `database/`,
    `.git/` y `upload/`.
  - Bloquea `.env*`, `composer.*`, `MEMORY.md`, `AGENTS.md`, `README.md`,
    `web.config` y archivos `*.sql|md|ini|log|lock|bak|dist|swp|old`.
  - Envía cabeceras `X-Content-Type-Options`, `X-Frame-Options`,
    `Referrer-Policy`, `Permissions-Policy`,
    `X-Permitted-Cross-Domain-Policies` y `Cross-Origin-Opener-Policy`.

En IIS, `web.config` cumple la función equivalente.

### 3.6 Crear el Admin Master

El sistema no crea contraseñas por defecto. Genera un hash y crea el usuario:

```bash
php -r "echo password_hash('CambiaEstaClave', PASSWORD_DEFAULT), PHP_EOL;"
```

```sql
INSERT INTO cab_usuarios
    (usu_id, usu_nombre, usu_apellido, usu_usuario, usu_correo, usu_clave,
     usu_tipoper, usu_status, usu_created_at, usu_updated_at)
VALUES
    ('000001', 'Admin', 'Master', 'admin', 'admin@example.com', '<HASH_GENERADO>',
     '1000', '1', NOW(), NOW());
```

> `usu_id` es alfanumérico con ceros a la izquierda. No lo trates como entero.

### 3.7 Verificar la instalación

1. Abre `APP_BASE_URL` (por ejemplo `http://localhost/proyecto_base/`).
2. Inicia sesión en `/login`.
3. Verifica el dashboard y que el menú se construya desde `cab_menu`.
4. Comprueba que `upload/`, `application/` y `.env` devuelvan HTTP 403.
5. Ejecuta una validación de sintaxis:

```bash
php -l application/core/MY_Controller.php
php -l application/controllers/Usuarios.php
php -l application/models/Usuarios_model.php
```

---

## 4. Estructura del repositorio

```text
proyecto_base/
├── .agents/                  Perfiles, skills y contratos del sistema multi-agente
├── .github/                  Instrucciones para GitHub Copilot
├── .opencode/                Adaptadores de agentes para OpenCode
├── application/
│   ├── cache/                Caché de CodeIgniter (ignorada)
│   ├── config/               Configuración: routes, database, config, hooks, autoload
│   ├── controllers/          Controladores HTTP
│   ├── core/MY_Controller.php Núcleo propio: sesión, RBAC, render, JSON, bitácora
│   ├── helpers/              network_helper.php (resolución de IP)
│   ├── hooks/                Security_headers.php (CSP, HSTS, X-Powered-By)
│   ├── language/             Idiomas de CodeIgniter
│   ├── libraries/            MY_Email, Geoip_service, Oauth_service, Ga4_reporting
│   ├── logs/                 Logs de ejecución (ignorados)
│   ├── models/               Modelos CI3
│   ├── third_party/          Librerías internas de la aplicación
│   └── views/
│       ├── auth/             Login, registro, recuperación, 2FA
│       ├── emails/           Plantillas de correo editables y de respaldo
│       ├── layouts/main.php  Layout global Metronic
│       ├── pages/            Vistas por módulo
│       └── partials/         _head, _header, _sidebar, _scripts, _csrf, etc.
├── assets/                   CSS, JS, plugins, fuentes y media de Metronic
├── database/                 Migraciones SQL incrementales
├── system/                   Framework CodeIgniter 3
├── third_party/              MaxMind DB Reader + bases GeoIP
├── upload/                   Almacenamiento privado de avatares y logos
├── .env.example              Plantilla de variables de entorno
├── .htaccess                 Reglas Apache
├── web.config                Reglas IIS
├── index.php                 Front controller y cargador de .env
├── composer.json             Metadatos y requisitos de PHP
├── AGENTS.md                 Reglas maestras del proyecto
├── MEMORY.md                 Memoria histórica y ADRs
├── SKILL.md                  Catálogo de skills
└── README.md                 Este documento
```

---

## 5. Arquitectura de ejecución

```text
Petición HTTP
   │
   ▼
index.php  ── carga .env, define ENVIRONMENT, arranca CodeIgniter
   │
   ▼
routes.php ── rutas fijas (login, dashboard, media, etc.)
   │           + rutas dinámicas leídas de cab_menu
   ▼
Controlador (extiende MY_Controller)
   │
   ├── _verificar_sesion()          Sesión activa + inactividad + usu_status
   ├── carga tema y configuración   cab_configuracion_tema / cab_configuracion_general
   ├── modo mantenimiento           Bloquea a roles != 1000
   ├── get_arbol_por_usuario()      Menú filtrado por rol
   ├── _verificar_acceso_url()      Deny-by-default por controlador/menú
   │
   ├── render($vista, $datos)       Inyecta la vista en layouts/main.php
   ├── json($data, $status)         Respuesta JSON con código HTTP
   ├── module_path($path)           URL base del módulo
   ├── autorizar_accion(...)        Permiso CRUD por acción
   └── bitacora($accion, $detalle)  Auditoría en cab_bitacora
```

### Utilidades de `MY_Controller`

| Método | Uso |
| --- | --- |
| `render($vista, $datos)` | Renderiza la vista dentro del layout Metronic |
| `json($data, $status)` | Emite JSON con el código HTTP indicado |
| `module_path($path)` | Construye la URL del módulo actual sin rutas quemadas |
| `bitacora($accion, $detalle)` | Registra una mutación en `cab_bitacora` |
| `tiene_permiso($men_id, $accion)` | Consulta la matriz RBAC |
| `autorizar_accion($men_id, $accion, $msg)` | Corta con 403 JSON o redirección |
| `require_table($tabla, $datatable)` | Valida que una tabla exista antes de operar |
| `validar_celular_ve()` / `normalizar_celular()` | Validación centralizada de telefonía |

### Flujo de sesión

- Cada petición protegida pasa por `_verificar_sesion()`.
- Si no hay sesión: `401` JSON para AJAX o redirección a `login`.
- Si se superó el tiempo de inactividad configurado en
  `ConfiguracionGeneral` (`sesion_inactividad_minutos`, 120 por defecto), se
  destruye la sesión.
- Las peticiones con cabecera `X-Background-Poll: 1` (feed de notificaciones)
  **no renuevan** la inactividad.
- El frontend envía un heartbeat a `/ping-sesion` como máximo cada 2 minutos
  mientras detecta actividad del usuario.
- Si `usu_status != 1`, la sesión se destruye de inmediato.

---

## 6. Enrutamiento dinámico

`application/config/routes.php` solo contiene rutas fijas o públicas:

- `login`, `login/entrar`, `logout`
- `recuperar-clave`, `nueva-clave`
- `dos-factores`
- `registro`, `verificar-correo`
- `auth/oauth-google`, `auth/oauth-apple`
- `dashboard`, `ping-sesion`, `sin-permisos`, `mantenimiento`
- `media/usuario/:id`, `media/theme/:archivo`
- `notificaciones/feed`, `notificaciones/marcar-leida/:id`,
  `notificaciones/marcar-todas`

Los módulos administrativos **no se registran en `routes.php`**. Se resuelven
dinámicamente desde `cab_menu`:

| Columna | Ejemplo | Función |
| --- | --- | --- |
| `men_url` | `Usuarios/index` | Controlador y acción real |
| `men_urlamigable` | `usuarios` | URL pública amigable |
| `sub_men_id` | `0` o `men_id` padre | Jerarquía del árbol |
| `men_tipo` | `0` raíz / `1` submenú | Tipo de nodo |
| `men_orden` | `10` | Orden visual |
| `men_inicio` | `0` / `1` | Página de inicio tras login |
| `men_externo` | `0` / `1` | Enlace externo |
| `men_imgmenu` | `ki-duotone ki-users` | Icono KeenIcons |

Las rutas generadas permiten:

```text
/usuarios                  -> Usuarios/index
/usuarios/datos/000001     -> Usuarios/datos/000001
/usuarios/crear            -> Usuarios/crear
```

En las vistas, la URL base se obtiene así:

```php
const BASE_URL = "<?php echo base_url($url_base . '/'); ?>";
```

No escribas rutas fijas como `/usuarios/` dentro de un módulo. Usa
`module_path()` en el controlador y `$url_base` en la vista.

---

## 7. Autenticación y sesiones

### Login tradicional

- Endpoint: `POST /login/entrar`.
- Campo de entrada: `usuario` (nombre de usuario, no correo) y `clave`.
- Busca en `cab_usuarios` por `usu_usuario` y `usu_status = '1'`.
- Verifica con `password_verify`.
- Si detecta un hash MD5 heredado de 32 caracteres, compara con
  `hash_equals` y re-hashea inmediatamente con `password_hash`.
- Si `password_needs_rehash()` lo indica, actualiza el hash.
- Registra `LOGIN_FALLIDO`, `LOGIN_REHASH`, `LOGIN_2FA`, etc. en bitácora.

### 2FA

- Se activa por usuario con `cab_usuarios.usu_2fa = 1`.
- Genera un código aleatorio de 6 dígitos y lo envía por correo.
- La sesión temporal vive en `usuario_2fa` hasta verificar el código.
- Intentos fallidos: máximo **5**; al quinto se bloquea la cuenta **15
  minutos** con `usu_2fa_intentos` y `usu_2fa_bloqueado_hasta`.
- El bloqueo se guarda en base de datos, por lo que no se evade con incógnito.
- Al bloquear se envía un correo de alerta y se registra
  `LOGIN_2FA_BLOQUEO_FUERZA_BRUTA`.
- Un usuario con 2FA activo no puede entrar por Google o Apple.

### Registro público

- Se habilita en `ConfiguracionTema` (`public_registration_enabled`).
- El rol asignado se configura en `public_registration_role_id`; por defecto
  `1004` (Registrado), con acceso solo al dashboard.
- `Usuarios_model::rol_registro_publico_valido()` rechaza roles con menús
  administrativos sensibles.
- El correo se verifica mediante token con hash en
  `cab_usuario_verificacion`.
- El usuario queda activo (`usu_status = '1'`) al confirmar el correo.

### Recuperación de contraseña

- Token aleatorio de 32 bytes; en BD se guarda solo el hash SHA-256.
- Expiración y límite de envíos por ventana de una hora.
- El enlace se invalida al usarse.

### OAuth

- Configurable desde `ConfiguracionTema` (Google y Apple).
- Si el correo ya existe, valida que la cuenta esté activa y registra
  `LOGIN_GOOGLE` / `LOGIN_APPLE`.
- Si no existe y el registro público está activo, crea el usuario con el rol
  público y lo deja verificado.
- El `id_token` de Apple se verifica criptográficamente (RS256, JWKS, `iss`,
  `aud`, `exp`, `iat`).

### Sesiones

- Driver: archivos (`files`) por defecto.
- `SESSION_TIME_TO_UPDATE=0`: no se regenera el ID a mitad de uso para evitar
  sesiones huérfanas por concurrencia AJAX.
- La regeneración se hace una sola vez al autenticar con éxito.
- `SESSION_SAMESITE` por defecto `Lax`.
- El monitor de sesiones activas lee directamente los archivos
  `ci_session*`; no usa una tabla histórica.

---

## 8. Roles, permisos y RBAC

### Tablas

| Tabla | Función | Claves relevantes |
| --- | --- | --- |
| `cab_grupopermi` | Roles | `per_id`, `per_nombre`, `per_tipo`, `per_activo` |
| `cab_menu` | Módulos y rutas | `men_id`, `sub_men_id`, `men_url`, `men_urlamigable` |
| `cab_permisologia` | Asignación rol-menú | `mis_id`, `mis_permiso`, `mis_menu`, `mis_activo` |
| `cab_permiaccion` | Acciones CRUD del menú | `acc_id`, `acc_consultar`, `acc_incluir`, `acc_modificar`, `acc_eliminar`, `acc_imprimir`, `acc_extra1`, `acc_extra2` |

`cab_permisologia.mis_id` y `cab_permiaccion.acc_id` usan una clave compuesta
del tipo `<rol>_<menu>` (ejemplo: `1000_71`). No existe una tabla
`cab_permisos`; ese nombre es incorrecto.

### Roles del sistema

| `per_id` | Nombre | Nivel jerárquico | Alcance |
| --- | --- | --- | --- |
| `1000` | Admin Master | 0 (máximo) | Acceso global; única excepción a la asignación explícita de menús |
| `1001` | Administrador | 1 | Consulta de reportes y CRUD de correos promocionales según la matriz |
| `1002` | Usuario | 2 | Dashboard y módulos asignados |
| `1003` | Soporte | 2 | Dashboard y módulos asignados |
| `1004` | Registrado | 3 | Solo dashboard; rol del registro público |

Reglas de jerarquía implementadas en `Usuarios.php`:

- Nadie puede asignar un rol de nivel igual o superior al propio, salvo el
  Admin Master.
- Nadie puede editar o eliminar cuentas de nivel igual o superior, salvo su
  propia cuenta.
- Solo el Admin Master puede cambiar la contraseña de otro usuario.
- Un usuario no puede cambiar su propio rol.
- Un usuario no puede desactivarse ni eliminarse a sí mismo.
- Ningún usuario no-master puede asignar, modificar o eliminar cuentas `1000`.

### Cómo funciona la autorización

1. **Acceso al módulo (deny-by-default)**: `MY_Controller::_verificar_acceso_url()`
   comprueba que el controlador esté registrado en `cab_menu`
   (`Menu_model::controlador_registrado()`). Si no lo está, bloquea.
2. **Acceso del rol**: `usuario_tiene_acceso_controlador()` valida que el rol
   tenga el menú asignado y activo en `cab_permisologia`.
3. **Acción CRUD**: cada endpoint de mutación llama
   `autorizar_accion($men_id, 'incluir|consultar|modificar|eliminar', $msg)`.
4. **Excepción Master**: el rol `1000` omite la asignación explícita.

Rutas libres (no pasan por RBAC): `dashboard`, `ping-sesion`, `logout`,
`sin-permisos`, `perfil`, `mantenimiento`, `media`, `errors`. El feed de
notificaciones se declara en `$metodos_libres`.

### IDs de menú de referencia

Estos IDs corresponden a las migraciones y al código actual. **Verifícalos en
tu base de datos antes de reutilizarlos**, porque pueden variar entre
instalaciones:

| `men_id` | Módulo |
| --- | --- |
| `25` | Menús |
| `26` | Permisos |
| `71` | Usuarios |
| `73` | Notificaciones |
| `93` | Reportes de logins |
| `113` | Dashboard |
| `114` | Configuración de tema |
| `115` | Reportes de bitácora |
| `116` | Reportes de sesiones activas |
| `117` | Configuración de correos |
| `118` | Correos promocionales |
| `132` | Configuración general |
| `133` | Plantillas de correo |
| `137` | Reporte de Errores (logs) |

---

## 9. Patrón canónico de módulos CRUD

Todo módulo administrativo nuevo debe seguir el patrón del módulo **Usuarios**.

### 9.1 Paso 0: inspección visual

Antes de maquetar, revisa el proyecto de referencia:

```text
c:\xampp\htdocs\proyecto_base_metronic\dist\html\
```

La fuente **preferida** es `demo10/`, pero se pueden reutilizar componentes de
`demo1/` a `demo9/`. Busca la pantalla afín:

- `demo10/store-client/`: e-commerce, inventario, catálogo, órdenes.
- `demo10/account/billing/`: facturación, planes, transacciones.
- `demo10/account/members/`: roles, permisos, tablas de miembros.
- `demo10/account/security/`: sesiones, logs, control de acceso.
- `demo10/network/user-table/`: tablas de usuarios y filtros.
- `demo1/`: sidebar oscuro, header y widgets base.

### 9.2 Paso 1: migración SQL

Crea `database/YYYY-MM-DD_descripcion.sql` con:

- Tabla principal `cab_<modulo>` y tablas de detalle `det_<modulo>_*`.
- Prefijos de columna cortos: `<pref>_id`, `<pref>_nombre`, `<pref>_status`,
  `<pref>_eliminado`, `<pref>_created_at`, `<pref>_updated_at`.
- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci` para
  tablas nuevas.
- Índices en columnas de búsqueda y claves foráneas.
- Registro del módulo en `cab_menu`.
- Asignación en `cab_permisologia` y `cab_permiaccion` para el rol `1000`.
- Idempotencia con `NOT EXISTS` / `INSERT IGNORE`.

Plantilla de registro de menú:

```sql
INSERT INTO cab_menu (
    men_nombre, men_descripcion, men_url, men_urlamigable, men_imgmenu,
    men_tipo, men_orden, sub_men_id, men_externo, men_fecha, men_hora
)
SELECT
    'Mi Módulo',
    'Descripción del módulo',
    'MiModulo/index',
    'mi-modulo',
    'ki-duotone ki-element-11',
    0, 999, 0, '0', CURDATE(), CURTIME()
WHERE NOT EXISTS (
    SELECT 1 FROM cab_menu
    WHERE TRIM(BOTH '/' FROM men_url) = 'MiModulo/index'
       OR TRIM(BOTH '/' FROM men_urlamigable) = 'mi-modulo'
);
```

Después de insertar el menú, crea la asignación en `cab_permisologia` y sus
acciones en `cab_permiaccion`. El orden importa: primero la asignación, luego
las acciones.

### 9.3 Paso 2: modelo

El modelo extiende `CI_Model`, concentra las consultas y expone el contrato
DataTables:

```php
class MiModulo_model extends CI_Model
{
    private $tabla = 'cab_mi_modulo';

    public function count_todos() { /* total sin filtro */ }

    public function count_filtrados($search = '') { /* total con filtro */ }

    public function get_datatable($start, $length, $search = '', $order_col = 0, $order_dir = 'asc') { /* filas paginadas */ }

    public function get_por_id($id) { /* registro */ }

    public function crear(array $data) { /* insert */ }

    public function actualizar($id, array $data) { /* update */ }

    public function eliminar($id) { /* borrado lógico */ }

    private function _datatable_query($search = '') { /* query base reutilizable */ }
}
```

Reglas:

- Usa query builder con *binding* o escape; nunca concatenes entrada del
  usuario.
- Define una lista blanca de columnas ordenables.
- `recordsTotal` es el total sin filtro; `recordsFiltered` es el total con
  filtro.
- Trata los IDs de usuario como `string`, nunca como `int`.

### 9.4 Paso 3: controlador

El controlador extiende `MY_Controller` e implementa:

| Método | Tipo | Responsabilidad |
| --- | --- | --- |
| `__construct()` | — | Cargar modelo, assets, `page_title` y `breadcrumb` |
| `index()` | GET | Preparar datos y `render()` |
| `tabla()` | POST AJAX | Respuesta DataTables server-side |
| `datos($id)` | GET AJAX | Datos para el modal de edición |
| `crear()` | POST AJAX | Validar, persistir, auditar, responder JSON |
| `actualizar($id)` | POST AJAX | Validar, persistir, auditar, responder JSON |
| `eliminar($id)` | POST AJAX | Borrado lógico, auditar, responder JSON |

Helpers privados obligatorios:

- `_recoger_post()`
- `_validar(array $data, $id = 0)`
- `_mapear_data_guardado()`
- `_formatear_filas_datatable(array $rows)`

Esqueleto mínimo:

```php
class MiModulo extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('MiModulo_model');
        $this->page_styles = ['assets/plugins/custom/datatables/datatables.bundle.css'];
        $this->page_vendor_scripts = ['assets/plugins/custom/datatables/datatables.bundle.js'];
        $this->page_title = 'Mi Módulo';
        $this->breadcrumb = [
            ['titulo' => 'Inicio', 'url' => base_url('dashboard')],
            ['titulo' => 'Mi Módulo', 'url' => ''],
        ];
    }

    public function index()
    {
        $this->render('pages/mi_modulo/index', []);
    }

    public function tabla()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());

        $draw   = (int)$this->input->post('draw');
        $start  = (int)$this->input->post('start');
        $length = (int)$this->input->post('length');
        $search = (string)($this->input->post('search')['value'] ?? '');
        $order  = $this->input->post('order');

        $order_column = (int)($order[0]['column'] ?? 0);
        $order_dir    = (string)($order[0]['dir'] ?? 'asc');

        $rows = $this->MiModulo_model->get_datatable(
            $start, $length, $search, $order_column, $order_dir
        );

        $this->json([
            'draw'            => $draw,
            'recordsTotal'    => $this->MiModulo_model->count_todos(),
            'recordsFiltered' => $this->MiModulo_model->count_filtrados($search),
            'data'            => $this->_formatear_filas_datatable($rows),
        ]);
    }

    public function crear()
    {
        if (!$this->input->is_ajax_request()) redirect($this->module_path());
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            return $this->json(['ok' => FALSE, 'msg' => 'Método no permitido.'], 405);
        }

        $this->autorizar_accion(<MEN_ID>, 'incluir', 'No tienes permiso para crear.');

        $data = $this->_recoger_post();
        $errores = $this->_validar($data);

        if (!empty($errores)) {
            return $this->json(['ok' => FALSE, 'errores' => $errores], 422);
        }

        $id = $this->MiModulo_model->crear($this->_mapear_data_guardado($data));

        if ($id) {
            $this->bitacora('MIMODULO_CREAR', 'ID: ' . $id . ' | ' . $data['mim_nombre']);
            return $this->json(['ok' => TRUE, 'msg' => 'Registro creado correctamente.', 'id' => $id]);
        }

        return $this->json(['ok' => FALSE, 'msg' => 'Error al guardar el registro.'], 500);
    }
}
```

### 9.5 Paso 4: vista

Ubicación: `application/views/pages/<modulo>/index.php`.

Debe incluir:

- Card contenedora `card card-flush shadow-sm`.
- Tabla con `table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4`.
- Buscador integrado en el header.
- Botón de nuevo registro `btn btn-sm btn-primary` con icono `ki-plus`.
- Modal independiente con jerarquía `modal-dialog` → `modal-content` →
  `modal-header` / `modal-body` / `modal-footer`.
- Función `limpiar_modal()` que restablezca el formulario y el título.
- DataTables inicializado con `processing`, `serverSide` y `ajax`.
- Envío con `FormData` y `fetch`, incluyendo el token CSRF del layout.
- Escape de HTML en todas las columnas generadas en el servidor.

### 9.6 Paso 5: permisos y auditoría

- Registra el menú en `cab_menu`.
- Asígnalo en `cab_permisologia`.
- Crea la fila de acciones en `cab_permiaccion`.
- Llama `autorizar_accion()` en **cada** mutación.
- Llama `bitacora()` inmediatamente después de persistir con éxito, con el
  formato `<MODULO>_<ACCION>` en mayúsculas.
- Si el módulo sube archivos, valida MIME real, tamaño y extensión, y sírvelos
  mediante `Media.php`.

### 9.7 Nomenclatura de auditoría

| Acción | Ejemplo |
| --- | --- |
| Crear | `CLIENTE_CREAR` |
| Actualizar | `CLIENTE_ACTUALIZAR` |
| Eliminar | `CLIENTE_ELIMINAR` |
| Cambio de estado | `CLIENTE_CAMBIAR_ESTADO` |
| Proceso de negocio | `VENTA_PROCESAR`, `PEDIDO_CAMBIAR_ESTADO` |

El detalle debe incluir el ID y un resumen legible de los campos clave:

```php
$this->bitacora(
    'CLIENTE_ACTUALIZAR',
    'ID: 120 | Nombre: Juan Pérez | RIF: V-12345678 | Acción: Registro actualizado'
);
```

---

## 10. Contratos HTTP y DataTables

### DataTables server-side

Respuesta obligatoria:

```json
{
  "draw": 1,
  "recordsTotal": 150,
  "recordsFiltered": 25,
  "data": []
}
```

- `recordsTotal`: total de registros no eliminados, **sin filtro**.
- `recordsFiltered`: total tras aplicar la búsqueda.
- `data`: filas ya formateadas (HTML permitido solo si está escapado).

### Endpoints JSON

| Endpoint | Éxito | Error esperado |
| --- | --- | --- |
| `datos($id)` | `200` `{ok: true, data: {...}}` | `404` `{ok: false, msg: ...}` |
| `crear()` | `200` `{ok: true, msg: ...}` | `422` `{ok: false, errores: {...}}` |
| `actualizar($id)` | `200` `{ok: true, msg: ...}` | `422` / `404` / `403` |
| `eliminar($id)` | `200` `{ok: true, msg: ...}` | `404` / `403` / `422` |

Reglas adicionales:

- Las mutaciones solo aceptan `POST`. Un `GET` devuelve `405` o redirección.
- Sin sesión: `401` en AJAX.
- Sin permiso: `403` JSON (`autorizar_accion` / deny-by-default).
- Error inesperado: `500` sin trazas, SQL, rutas físicas ni secretos.
- No devuelvas el stack trace ni el mensaje crudo de MySQL.

---

## 11. Reglas de frontend Metronic

### Estructura visual

- El layout global es `application/views/layouts/main.php`.
- El sidebar sigue el patrón oscuro de Metronic demo1
  (`assets/css/demo1-dark-sidebar.css`).
- Los componentes nuevos deben inspirarse en
  `proyecto_base_metronic/dist/html/`, preferentemente `demo10/`.
- No introduzcas un layout distinto por módulo: usa el layout global.

### Tema claro/oscuro

- El tema se controla con `data-bs-theme="light|dark"` y `localStorage`.
- Usa clases semánticas:
  - Texto: `text-gray-900`, `text-gray-800`, `text-gray-700`, `text-muted`,
    `text-primary`.
  - Contenedores: `card card-flush`, `bg-body`, `bg-light`,
    `bg-light-primary`.
  - Bordes: `border-gray-200`, `separator`, `table-row-dashed`.
- **Prohibido** usar colores rígidos en atributos `style`:
  - `style="background: #fff;"`
  - `style="color: #000;"`
- Para estilos propios, consume variables CSS del tema:
  - `var(--bs-primary)`
  - `var(--bs-body-bg)`
  - `var(--bs-card-bg)`
  - `var(--app-header-bg)`

### Botones y modales

- Todo botón de acción con texto debe usar `btn-sm` e incluir un icono
  KeenIcons:
  - Guardar: `ki-check`
  - Cancelar: `ki-cross`
  - Volver: `ki-arrow-left`
  - Enviar: `ki-send`
  - Nuevo: `ki-plus`
  - Eliminar: `ki-trash`
  - Exportar: `ki-exit-up`
- Los modales deben ser independientes por entidad.
- Usa `limpiar_modal()` al abrir/cerrar para evitar datos residuales.
- No uses variables globales ocultas para el estado del formulario.
- Las confirmaciones destructivas usan SweetAlert2 con `text:`, no `html:`,
  cuando interpolan datos del usuario.

### Tablas

Clase estándar:

```html
<table class="table table-row-dashed table-row-gray-200 align-middle gs-0 gy-4">
```

- Encabezados: `text-start text-gray-500 fw-bold fs-7 text-uppercase`.
- Estados: badges con viñeta (`bullet bullet-dot bg-success`).
- Acciones: botones compactos con tooltip de Bootstrap.

---

## 12. Reglas de seguridad

### Autorización

- Todo controlador administrativo debe estar registrado en `cab_menu`.
- Todo endpoint de mutación debe llamar `autorizar_accion()` después de
  validar método y AJAX.
- El rol `1000` conserva privilegios globales; ningún otro rol puede
  gestionarlo.
- No confíes solo en ocultar botones en la vista: la autorización se valida en
  el servidor.

### Entrada y salida

- Usa `$this->input->post($campo, TRUE)` y valida tipo, longitud y formato.
- Usa consultas parametrizadas o query builder; nunca concatenes SQL con
  entrada del usuario.
- Escapa toda salida HTML con `htmlspecialchars($valor, ENT_QUOTES, 'UTF-8')`.
- No devuelvas datos sensibles en JSON (`usu_clave`, tokens, rutas físicas).
- No muestres mensajes crudos de MySQL ni trazas al usuario.

### CSRF

- `csrf_protection = TRUE` en `application/config/config.php`.
- Token: `csrf_token`; cookie: `csrf_cookie`.
- `partials/_csrf.php` inyecta el token en `fetch`, `XMLHttpRequest` y
  formularios POST del mismo origen.
- El partial se incluye desde `partials/_head.php` y `auth/_auth_top.php`.
- Los flujos de `Auth` están excluidos porque usan sus propios tokens.
- Una vista administrativa nueva **no** debe crear formularios POST fuera del
  layout sin token.

### Archivos

- Los avatares se guardan en `upload/usuarios/<id>/`.
- `upload/.htaccess` y la regla de rewrite bloquean el acceso directo.
- `Media::usuario()` valida sesión, detecta MIME real con `finfo` y responde
  `nosniff`.
- Tipos permitidos para avatares: JPG, PNG y WEBP; máximo 3 MB.
- Los logos del tema se sirven por `Media::theme()` con lista blanca de MIME.

### Sesiones y cookies

- No modifiques `MY_Controller.php` ni `Auth.php` sin revisión del Coordinador.
- Usa `session->sess_destroy()` al expirar o desactivar un usuario.
- No guardes secretos en la sesión.
- Configura `SESSION_SAVE_PATH` fuera del directorio público si es posible.
- `COOKIE_HTTPONLY` y `COOKIE_SECURE` vienen en `false` en `.env.example`.
  Actívalos en producción; `COOKIE_SECURE` requiere HTTPS.

### Auditoría

- Toda mutación exitosa se registra en `cab_bitacora`.
- Los logins y eventos de seguridad se registran en `cab_bitacora_ip` y
  `cab_bitacora`.
- No registres contraseñas, tokens completos ni datos sensibles en el detalle.

### Cabeceras

- `.htaccess` y `web.config` aplican cabeceras a estáticos.
- `application/hooks/Security_headers.php` agrega CSP, HSTS (solo HTTPS) y
  elimina `X-Powered-By` en respuestas dinámicas.
- La CSP se omite para `/media/` porque la respuesta es binaria.

---

## 13. Base de datos y migraciones

### Convenciones

- Archivo: `database/YYYY-MM-DD_descripcion.sql`.
- Tabla principal: `cab_<modulo>`.
- Tabla de detalle: `det_<modulo>_<entidad>`.
- Columna de estado: `<pref>_status` (`1` activo / `0` inactivo).
- Columna de borrado lógico: `<pref>_eliminado` (`0` visible / `1` eliminado).
- Marcas de tiempo: `<pref>_created_at`, `<pref>_updated_at`.
- IDs de usuario: texto, nunca `INT` (`cab_usuarios.usu_id` es `char(100)`;
  las referencias en tablas de detalle usan `VARCHAR(50)`).
- Tablas nuevas: `utf8mb4` / `utf8mb4_unicode_ci`, preferentemente InnoDB.
- No asumas que todo el esquema comparte motor, colación o claves foráneas.

### Tablas del núcleo

| Tabla | Función |
| --- | --- |
| `cab_usuarios` | Usuarios, credenciales, rol, 2FA, estado |
| `cab_grupopermi` | Roles |
| `cab_menu` | Módulos, rutas y jerarquía del menú |
| `cab_permisologia` | Asignación rol-menú |
| `cab_permiaccion` | Acciones CRUD por asignación |
| `cab_bitacora` | Auditoría de acciones |
| `cab_bitacora_ip` | Auditoría de logins y geolocalización |
| `cab_usuario_verificacion` | Tokens de verificación y recuperación |
| `cab_configuracion_tema` | Branding, colores, logos, OAuth, registro público |
| `cab_configuracion_general` | Identidad, monedas, mantenimiento, banner, inactividad |
| `cab_configuracion_correo` | Transporte SMTP |
| `cab_plantillas_correo` | Plantillas transaccionales HTML |
| `cab_correos_promocionales` | Campañas promocionales |
| `cab_correos_promocionales_destinatarios` | Destinatarios de campañas |
| `cab_correo_grupos` | Grupos de destinatarios |
| `det_correo_grupos_usuarios` | Miembros de grupos |
| `cab_notifications_system` | Notificaciones del sistema |
| `cab_notifications_system_user` | Notificaciones por usuario |
| `cab_google_analytics` | Configuración GA4 y reportes (`ga_reporte_activo`, `ga_property_id`) |

### Migraciones incluidas

| Archivo | Contenido |
| --- | --- |
| `2026-05-02_add_men_inicio_to_cab_menu.sql` | Columna `men_inicio` |
| `2026-05-02_create_cab_configuracion_tema.sql` | Configuración de tema |
| `2026-05-02_create_notifications_tables.sql` | Notificaciones |
| `2026-05-05_create_cab_configuracion_correo.sql` | Configuración SMTP + menú |
| `2026-05-05_create_cab_correos_promocionales.sql` | Campañas + destinatarios |
| `2026-05-23_alter_cab_usuarios_add_apellido.sql` | Apellido de usuario |
| `2026-10-23_create_cab_usuario_verificacion.sql` | Tokens de verificación |
| `2026-10-23_upgrade_cab_usuario_verificacion_token_types.sql` | Tipos de token |
| `2026-10-25_add_2fa_lockout_to_cab_usuarios.sql` | Bloqueo 2FA |
| `2026-10-25_add_dark_mode_colors_cab_configuracion_tema.sql` | Paleta oscura |
| `2026-10-25_add_sesion_inactividad_to_cab_configuracion_general.sql` | Inactividad |
| `2026-10-25_add_usu_2fa_to_cab_usuarios.sql` | Bandera 2FA |
| `2026-10-25_alter_det_correo_grupos_usuarios_varchar.sql` | IDs de usuario como texto |
| `2026-10-25_create_cab_configuracion_general.sql` | Configuración general + menú |
| `2026-10-25_create_cab_correo_grupos.sql` | Grupos de destinatarios |
| `2026-10-25_create_cab_plantillas_correo.sql` | Plantillas de correo + menú |
| `2026-10-27_add_ga4_reporting.sql` | Columnas de reporte GA4 + menú |
| `2026-10-27_create_google_analytics.sql` | Configuración GA4 + menú |
| `2026-10-28_create_reporte_errores.sql` | Visor de logs, configuración y menú/permiso del rol `1000` |
| `2026-10-28_security_hardening_rbac.sql` | Rol `1004`, matriz RBAC, revocaciones |

### Estado conocido del esquema

- `cab_usuarios.usu_id` es `char(100)` con ceros a la izquierda.
- `usu_status` es `char(2)`.
- `cab_usuarios` no tiene `usu_eliminado` ni `usu_fechareg`.
- `cab_usuario_verificacion` usa clave compuesta
  (`uver_usuario_id`, `uver_tipo`) y `uver_token_hash`.
- Solo hay una clave foránea declarada: la de
  `cab_correos_promocionales_destinatarios`.
- La matriz RBAC se normalizó el 2026-10-28. Aun así, verifica la acción
  específica antes de mutar datos de roles personalizados.
- `Usuarios_model::eliminar()` hace un `DELETE` físico. La regla de borrado
  lógico aplica a módulos nuevos; no describe el comportamiento actual del
  módulo Usuarios.

---

## 14. Sistema multi-agente

El proyecto está preparado para ser trabajado por agentes de IA con roles
separados. Los perfiles están en `.agents/agents/` y las skills en
`.agents/skills/`.

| Agente | Skill principal | Entregable | Alcance |
| --- | --- | --- | --- |
| `orchestrator` | Todas según alcance | Plan, aceptación y cierre | Coordinación general |
| `module-architect` | `module-architecture` | Contrato de arquitectura | Alcance, datos, permisos, riesgos |
| `database-specialist` | `db-migration` | Migración y validación de esquema | `database/*.sql` |
| `backend-specialist` | `backend-ci3` | Modelo, controlador y contrato HTTP | `application/controllers`, `models`, librerías autorizadas |
| `frontend-specialist` | `frontend-metronic` | Vista, JS, DataTables y modales | `application/views`, `assets` |
| `security-specialist` | `security-audit` | Hallazgos por severidad | Revisión; no corrige en silencio |
| `qa-specialist` | `qa-module` | Matriz de pruebas y veredicto | Revisión; no modifica código sin autorización |

### Fases

```text
ARCHITECTURE
   ├── DATABASE  ─┐
   └── BACKEND   ─┴── FRONTEND ── SECURITY ── QA ── CLOSE
```

- `DATABASE` y `BACKEND` pueden ir en paralelo solo después de aceptar
  `ARCHITECTURE`.
- `FRONTEND` inicia con el contrato de arquitectura y los endpoints aprobados.
- `SECURITY` revisa la implementación completa antes de `QA`.
- `QA` ejecuta pruebas estáticas, funcionales y de regresión.
- `CLOSE` declara `READY` solo si Seguridad y QA no tienen bloqueos.

### Estados de una fase

- `ACCEPTED`
- `ACCEPTED_WITH_RISKS` (el riesgo y su responsable deben quedar documentados)
- `REWORK`
- `BLOCKED`

### Contrato de handoff

Cada entrega debe incluir:

```text
phase: ARCHITECTURE | DATABASE | BACKEND | FRONTEND | SECURITY | QA | CLOSE
status: ACCEPTED | ACCEPTED_WITH_RISKS | REWORK | BLOCKED
summary: descripción breve
files_created: lista de rutas
files_modified: lista de rutas
contract_changes: endpoints, tablas, payloads o permisos modificados
validation: pruebas ejecutadas y resultado
evidence: comandos, archivos, respuestas o capturas
risks: riesgos residuales y severidad
pending: trabajo pendiente o pasos manuales
handoff_to: siguiente agente o Coordinador
```

Una prueba no ejecutada debe declararse `NOT_RUN` con su limitación. Nunca se
reporta como aprobada.

### Reglas de aislamiento

- Ningún agente modifica `.env`, credenciales o secretos.
- No se modifican migraciones que puedan estar aplicadas; se crea una nueva.
- `MY_Controller.php`, `Auth.php` y el núcleo de CodeIgniter requieren revisión
  explícita del Coordinador.
- Solo el Coordinador actualiza `MEMORY.md` al cerrar.
- Cada agente inspecciona primero el código y las convenciones existentes.
- Cada agente informa archivos modificados, validaciones, riesgos y pendientes.

### Skills disponibles

| Skill | Ruta | Cuándo usarla |
| --- | --- | --- |
| `create-crud-module` | `.agents/skills/create-crud-module/SKILL.md` | Crear un módulo CRUD completo |
| `db-migration` | `.agents/skills/db-migration/SKILL.md` | Diseñar o revisar migraciones |
| `security-audit` | `.agents/skills/security-audit/SKILL.md` | Auditar RBAC, sesiones y archivos |
| `module-architecture` | `.agents/skills/module-architecture/SKILL.md` | Antes de cambios multi-capa |
| `backend-ci3` | `.agents/skills/backend-ci3/SKILL.md` | Modelos, controladores y endpoints |
| `frontend-metronic` | `.agents/skills/frontend-metronic/SKILL.md` | Vistas, JS, DataTables y temas |
| `qa-module` | `.agents/skills/qa-module/SKILL.md` | Verificación antes del cierre |

### Detección por herramienta

- **OpenCode**: `opencode.json` registra contratos y skills; los adaptadores
  están en `.opencode/agents/`.
- **Claude Code**: `CLAUDE.md` importa las instrucciones y contratos.
- **Gemini CLI**: `GEMINI.md` hace lo equivalente.
- **GitHub Copilot**: `.github/copilot-instructions.md`.
- **Herramientas compatibles con `AGENTS.md`**: usan el archivo raíz.

---

## 15. Inventario de módulos

| Módulo | Controlador | Modelo | Vista | Responsabilidad |
| --- | --- | --- | --- | --- |
| Dashboard | `Home.php` | `Usuarios_model` | `pages/dashboard.php` | Métricas, actividad reciente y logins |
| Usuarios | `Usuarios.php` | `Usuarios_model` | `pages/usuarios/index.php` | CRUD canónico, roles, avatares, 2FA |
| Perfil | `Perfil.php` | `Usuarios_model` | `pages/perfil/index.php` | Datos propios, clave, sesiones y actividad |
| Menús | `Menus.php` | `Menus_model` | `pages/menus/index.php` | Árbol de navegación y rutas amigables |
| Permisos | `Permisos.php` | `Permisos_model` | `pages/permisos/index.php`, `asignar.php` | Roles y matriz CRUD |
| Configuración General | `ConfiguracionGeneral.php` | `Configuracion_general_model` | `pages/configuracion_general/index.php` | Empresa, monedas, mantenimiento, banner, visor de logs |
| Configuración de Tema | `ConfiguracionTema.php` | `Configuracion_tema_model` | `pages/configuracion_tema/index.php` | Colores, tipografía, logos, OAuth, registro |
| Configuración de Correos | `ConfiguracionCorreos.php` | `Configuracion_correos_model` | `pages/configuracion_correos/index.php` | SMTP y remitente |
| Plantillas de Correo | `PlantillasCorreo.php` | `Plantillas_correo_model` | `pages/plantillas_correo/index.php` | Plantillas transaccionales y envío de prueba |
| Correos Promocionales | `CorreosPromocionales.php` | `Correos_promocionales_model` | `pages/correos_promocionales/index.php` | Campañas, grupos y destinatarios |
| Notificaciones | `Notificaciones.php` | `Notificaciones_model` | `pages/notificaciones/index.php` | Centro de alertas y feed del header |
| Reportes de Bitácora | `ReportesBitacora.php` | `Reportes_bitacora_model` | `pages/reportes_bitacora/index.php` | Auditoría de acciones |
| Reportes de Logins | `ReportesLogins.php` | `Reportes_logins_model` | `pages/reportes_logins/index.php` | Historial de conexiones y geolocalización |
| Sesiones Activas | `ReportesSesionesActivas.php` | `Reportes_sesiones_activas_model` | `pages/reportes_sesiones_activas/index.php` | Monitor, cierre y baneo de sesiones |
| Reporte de Errores | `ReportesErrores.php` | `Reportes_errores_model` | `pages/reportes_errores/index.php` | Visor de solo lectura de `application/logs` |
| Google Analytics | `GoogleAnalytics.php` | `Google_analytics_model` | `pages/google_analytics/index.php` | Configuración GA4 |
| Reportes GA4 | `ReportesGoogleAnalytics.php` | `Google_analytics_model` | `pages/reportes_google_analytics/index.php` | Métricas GA4 |
| Autenticación | `Auth.php` | `Usuarios_model`, `Menus_model`, `Configuracion_tema_model`, `Configuracion_correos_model`, `Configuracion_general_model`, `Plantillas_correo_model` | `auth/*` | Login, registro, 2FA, recuperación, OAuth |
| Media | `Media.php` | — | — | Streaming seguro de avatares y logos |
| Errores | `Errors.php` | `Configuracion_general_model` | `pages/error_404.php`, `sin_permisos.php`, `mantenimiento.php` | Páginas de error y mantenimiento |

`Welcome.php` es un controlador heredado de CodeIgniter y no forma parte del
flujo administrativo.

---

## 16. Operación y verificación

### Comandos útiles

```bash
# Estado del repositorio
git status
git lfs ls-files

# Verificar sintaxis PHP
php -l application/core/MY_Controller.php
php -l application/controllers/Usuarios.php

# Ver qué archivos ignora Git y por qué
git check-ignore -v .env
git status --ignored --short

# Aplicar una migración
mysql -u root -p proyecto_base < database/2026-10-28_security_hardening_rbac.sql
```

### Checklist de QA para un módulo nuevo

- [ ] `php -l` sin errores en controlador y modelo.
- [ ] El controlador extiende `MY_Controller`.
- [ ] El módulo está registrado en `cab_menu` y asignado en
      `cab_permisologia`.
- [ ] Existe la fila en `cab_permiaccion` con las acciones correctas.
- [ ] `tabla()` responde `draw`, `recordsTotal`, `recordsFiltered`, `data`.
- [ ] `datos()` devuelve `200`/`404` según corresponda.
- [ ] `crear()`/`actualizar()` devuelven `422` con `errores` cuando falla la
      validación.
- [ ] Las mutaciones solo aceptan `POST` y llaman `autorizar_accion()`.
- [ ] Cada mutación exitosa registra bitácora.
- [ ] La vista funciona en modo claro y oscuro.
- [ ] No hay colores rígidos en `style`.
- [ ] Los botones con texto usan `btn-sm` e icono.
- [ ] El modal se limpia con `limpiar_modal()`.
- [ ] Los datos se escapan con `htmlspecialchars`.
- [ ] Un rol sin permiso recibe `403` o redirección a `sin-permisos`.
- [ ] Un `GET` a un endpoint de mutación no ejecuta la acción.

### Solución de problemas

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| Redirección constante a `login` | Sesión no persistida o ruta de sesiones sin escritura | Revisa `SESSION_SAVE_PATH` y permisos |
| El menú no muestra un módulo | Falta `cab_menu`, `cab_permisologia` o `mis_activo` | Revisa las tres tablas |
| `403` en un módulo nuevo | Controlador no registrado en `cab_menu` | Registra el menú y asigna el rol |
| `recordsTotal` incorrecto | Se usó el conteo filtrado | Separa `count_todos()` y `count_filtrados()` |
| `419`/`403` en POST AJAX | Falta token CSRF | Incluye el layout con `partials/_csrf.php` |
| Error de conexión a BD | `.env` incompleto o credenciales heredadas | Define todas las variables `DB_*` |
| GeoIP vacío | Falta el `.mmdb` de LFS | Ejecuta `git lfs pull` |
| Logos o avatares no cargan | `upload/` sin permisos o `Media` sin sesión | Revisa permisos y sesión activa |

---

## 17. Limitaciones conocidas

1. **No hay dump del esquema base** en el repositorio. Sin las tablas del núcleo
   (`cab_usuarios`, `cab_grupopermi`, `cab_menu`, RBAC), el sistema no arranca.
2. **`.env` no se versiona**. Cada entorno debe crear el suyo desde
   `.env.example`.
3. **`SESSION_SAVE_PATH` tiene un fallback heredado** (`rose_sessions`). En una
   instalación limpia debes definirlo explícitamente.
4. **Credenciales por defecto en `database.php`** y una `encryption_key` por
   defecto en `config.php`. En producción deben sobrescribirse desde `.env`.
5. **`Usuarios_model::eliminar()` hace borrado físico**, aunque la convención
   para módulos nuevos es borrado lógico.
6. **`composer.lock` está ignorado**. Para builds reproducibles conviene
   versionarlo.
7. **No hay suite de pruebas automatizada incluida** (PHPUnit no está
   configurado para la aplicación). La verificación es estática y manual según
   la skill `qa-module`.
8. **El esquema mezcla motores y colaciones** heredadas. No asumas uniformidad.
9. **Las bases GeoIP tienen licencias propias** (ver sección 18).
10. **`MEMORY.md` y `README.md` están bloqueados por `.htaccess`** para acceso
    HTTP; son documentación de repositorio, no páginas web.
11. **Cookies de sesión sin `HttpOnly` ni `Secure` por defecto**:
    `COOKIE_HTTPONLY` y `COOKIE_SECURE` son `false` en `.env.example`. En
    producción con HTTPS deben activarse; de lo contrario la cookie es legible
    por JavaScript y puede viajar por HTTP.

---

## 18. Licencia y componentes de terceros

- **CodeIgniter 3**: MIT. Ver `license.txt`.
- **Metronic**: requiere licencia de uso según tu adquisición. Los assets
  incluidos provienen del paquete Metronic del proyecto.
- **DB-IP City Lite** (`dbip-city-lite-2026-05.mmdb`): licencia Creative
  Commons Attribution 4.0. Requiere atribución a DB-IP.
- **MaxMind GeoLite2 ASN** (`GeoLite2-ASN.mmdb`): sujeto a la licencia y
  términos de MaxMind.
- **MaxMind DB Reader PHP**: licencia incluida en
  `third_party/maxmind-db-reader/LICENSE`.
- **KeenIcons, Bootstrap, DataTables, SweetAlert2, Select2, Flatpickr,
  CKEditor, TinyMCE y demás plugins**: cada uno conserva su licencia original
  dentro de `assets/`.

Antes de distribuir el proyecto, revisa y conserva los avisos de licencia de
los componentes de terceros.
