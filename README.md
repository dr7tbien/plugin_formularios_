# Formularios CodePTY

Plugin dedicado exclusivamente a enviar por email el formulario de contacto publicado con
el shortcode `[codepty_formulario_contacto]`.

## Formulario de contacto general

El shortcode `[codepty_formulario_contacto]` envía cada consulta directamente mediante
`wp_mail()` al destinatario definido en `CODEPTY_CONTACT_EMAIL`. DR Sendmail gestiona el
transporte SMTP de forma independiente.

WordPress no guarda el contenido de la consulta en base de datos, transients, sesiones ni
archivos. Los datos escritos permanecen en el navegador mientras el visitante completa la
verificación y el envío.

El formulario incluye verificación del email mediante una clave temporal de cuatro
caracteres, nonce de WordPress, campo honeypot, comprobación firmada del tiempo de llenado
y límites por IP y email. La consulta solamente puede enviarse después de validar la clave.

El visitante completa primero su consulta y pulsa **Enviar consulta por email**. El formulario se
sustituye entonces por cuatro casillas para la clave recibida. Al pulsar **Confirmar y
enviar mensaje**, el servidor valida la clave y envía la consulta. El servidor conserva
temporalmente solo hashes, caducidad, intentos y contadores necesarios para la verificación
y protección contra abuso; nunca conserva el contenido del mensaje.

### Página de origen

Cada correo incluye la **Página de origen**, es decir, la página
interna de CodePTY que contenía el shortcode cuando el visitante inició el envío. El
navegador coloca `window.location.href` en un campo oculto y lo incluye en las peticiones
del formulario; así la captura no depende de `HTTP_REFERER`, que puede perderse en AJAX.

El servidor no confía directamente en ese campo: admite únicamente URLs HTTP o HTTPS cuyo
host y puerto coincidan con WordPress y elimina parámetros de consulta y fragmentos. Si el
valor está vacío, malformado o pertenece a otro dominio, el correo indica **No identificado**.

El correo final contiene nombre, teléfono, email, mensaje, página de origen y fecha/hora.
No genera identificadores adicionales.

### Configuración del destinatario

El destinatario se define antes de cargar el plugin:

```php
define('CODEPTY_CONTACT_EMAIL', 'codepty0@gmail.com');
```

Si la constante falta o no contiene un email válido, el formulario no intenta enviar y
muestra un error genérico al visitante. Los administradores reciben un aviso claro en el
panel de WordPress.

DR Sendmail es responsable del transporte SMTP. `formularios_` no contiene credenciales
SMTP ni configura el servidor de correo.

### Botones de teléfono y WhatsApp

El formulario por email funciona siempre de manera independiente. Debajo puede aparecer el
texto **También puedes contactarnos aquí:** seguido de alternativas discretas que JavaScript
muestra exclusivamente cuando detecta un smartphone. En escritorio, tabletas y navegadores
sin JavaScript permanecen ocultas.

La configuración se lee exclusivamente desde `wp-config.php`:

```php
define('CODEPTY_SHOW_WHATSAPP_BUTTON_ON_SMARTPHONES', false);
define('CODEPTY_SHOW_PHONE_BUTTON_ON_SMARTPHONES', false);
define('CODEPTY_SHOW_PHONE_WHATSAPP_BUTTON_ON_SMARTPHONES', true);

define('CODEPTY_CONTACT_WHATSAPP', '+507 6672 6470');
define('CODEPTY_CONTACT_PHONE', '+507 6672 6470');
```

Cada opción se evalúa independientemente. Una constante ausente o con valor distinto del
booleano `true` equivale a desactivada:

| WhatsApp | Teléfono | Combinado | Resultado en smartphone |
|---|---|---|---|
| `false` | `false` | `false` | Ningún botón |
| `true` | `false` | `false` | WhatsApp |
| `false` | `true` | `false` | Teléfono |
| `true` | `true` | `false` | WhatsApp y teléfono |
| `false` | `false` | `true` | Combinado |
| `true` | `false` | `true` | WhatsApp y combinado |
| `false` | `true` | `true` | Teléfono y combinado |
| `true` | `true` | `true` | Los tres botones |

El botón individual de WhatsApp usa `CODEPTY_CONTACT_WHATSAPP`; el individual de teléfono
usa `CODEPTY_CONTACT_PHONE`. El combinado siempre usa `CODEPTY_CONTACT_WHATSAPP` para sus dos
zonas: solo el icono azul de teléfono abre `tel:`, mientras que el icono verde de WhatsApp,
el número y el resto abren `wa.me`. Son enlaces hermanos accesibles, nunca enlaces anidados.

Los números admiten `+`, espacios, puntos, guiones y paréntesis, pero deben representar un
número internacional de 8 a 15 dígitos que no comience por cero. Una opción activa con un
número ausente o inválido no genera enlaces; solo los administradores reciben un aviso.

## Actualizaciones desde GitHub

El plugin consulta la última release pública estable de:

```text
https://github.com/dr7tbien/plugin_formularios_
```

WordPress compara la etiqueta normalizada de la release con la versión instalada mediante
`version_compare()`. Solo muestra una actualización cuando la versión remota es superior.
Las respuestas se guardan temporalmente durante seis horas; los errores se recuerdan durante
treinta minutos para no insistir contra GitHub. La caché se elimina al terminar una
actualización del plugin.

Se ignoran drafts, prereleases, etiquetas distintas de `vX.Y.Z`/`X.Y.Z`, releases sin el
asset exacto `formularios_.zip` y enlaces que no pertenezcan a la ruta de releases de este
repositorio en `github.com`. Si GitHub no responde, el formulario sigue funcionando.

La instalación que actualmente funciona en `codepty.com` debe actualizarse manualmente una
última vez con una versión que ya incluya este actualizador. Las releases posteriores podrán
instalarse desde **Plugins > Actualizaciones**.

## Publicación manual de una versión

Esta es la receta completa para publicar cada mejora. En el ejemplo se publica `0.6.7`;
cambiar ese valor por la versión que corresponda.

### 1. Actualizar versión y documentación

Editar manualmente `formularios_.php` y escribir la misma versión en ambos lugares:

```php
 * Version: 0.6.7
define('FORMULARIOS_PW_VERSION', '0.6.7');
```

Añadir la nueva entrada a `CHANGELOG.md`, actualizar `README.md` cuando proceda y regenerar
el árbol documental:

```bash
cd "/home/torpedo/Local Sites/codepty/app/public/wp-content/plugins/formularios_"
wp dr-readme update --target="$(pwd)" --block=TREE
```

### 2. Ejecutar pruebas y generar el ZIP

```bash
php tests/run.php
find . -name '*.php' -not -path './.git/*' -print0 | xargs -0 -n1 php -l
node --check assets/js/contact-form.js
./scripts/build-release.sh /tmp/formularios_.zip
unzip -t /tmp/formularios_.zip
```

El resultado debe indicar la versión nueva y `No errors detected`. El ZIP debe contener
`formularios_/formularios_.php`; ninguna ruta puede quedar fuera de `formularios_/`.

### 3. Publicar código, tag y release

Definir una sola vez la versión de esta publicación:

```bash
VERSION=0.6.7
```

Revisar, confirmar y subir los cambios:

```bash
git status --short
git add -A
git commit -m "Preparar versión $VERSION"
git push origin main
```

Crear y publicar el tag:

```bash
git tag --list "v$VERSION"
git tag -a "v$VERSION" -m "Formularios CodePTY $VERSION"
git push origin "v$VERSION"
```

El primer comando no debe devolver un tag existente. Si ya existe, detenerse y revisar la
versión en lugar de sobrescribirlo.

Crear la release pública y adjuntar el ZIP:

```bash
gh release create "v$VERSION" /tmp/formularios_.zip \
  --repo dr7tbien/plugin_formularios_ \
  --title "Formularios CodePTY $VERSION" \
  --notes "Publicación de Formularios CodePTY $VERSION."
```

Comprobar la release:

```bash
gh release view "v$VERSION" --repo dr7tbien/plugin_formularios_
```

La release no debe ser draft ni prerelease y debe contener un asset llamado exactamente
`formularios_.zip`.

### 4. Actualizar WordPress

En `codepty.com`, abrir **Escritorio > Actualizaciones**, pulsar **Comprobar de nuevo** y
actualizar **Formularios CodePTY**. Después, confirmar en **Plugins instalados** que aparece
la nueva versión.

La comprobación equivalente mediante WP-CLI es:

```bash
wp plugin list --update=available
wp plugin update formularios_
wp plugin get formularios_ --field=version
```

El script solo crea un ZIP local. No cambia versiones, no ejecuta Git y no publica nada.
Excluye `.git`, pruebas y scripts; además bloquea nombres de archivos privados y patrones
habituales de claves antes de empaquetar.

## Pruebas y diagnóstico

Ejecutar las pruebas aisladas del actualizador:

```bash
php tests/run.php
```

Estas cubren ausencia de releases, versiones igual/inferior/superior, drafts, prereleases,
errores remotos, URL de descarga no autorizada, caché y limpieza tras actualizar.

Si WordPress no muestra una actualización, comprobar:

- que la release sea pública y estable;
- que la etiqueta sea exactamente `vX.Y.Z` y superior a la versión instalada;
- que exista el asset `formularios_.zip`;
- que el ZIP tenga una única carpeta raíz llamada `formularios_`;
- que WordPress pueda realizar solicitudes HTTPS a `api.github.com` y `github.com`;
- que hayan transcurrido seis horas o se haya limpiado el transient
  `formularios_pw_github_release` antes de repetir la consulta.

Una respuesta 404 significa normalmente que aún no existe una release pública. Los errores
o timeouts de GitHub no se muestran al visitante y no impiden enviar formularios.

## Datos históricos

Versiones anteriores gestionaban expedientes, adjuntos y consultas almacenadas. El código
de esas funciones ya no forma parte del plugin. La actualización no elimina automáticamente:

- tablas o filas históricas de WordPress
- archivos de `app/private/codepty-presencia-web`
- la clave maestra anterior

Estos elementos pueden revisarse y limpiarse posteriormente mediante una operación de
mantenimiento expresamente autorizada. El plugin actual no los lee ni escribe y ya no
depende de `CODEPTY_PW_STORAGE_DIR` ni de `CODEPTY_PW_MASTER_KEY_FILE`.

## Comando dr-readme

Desde este directorio:

```bash
wp dr-readme update --target="$(pwd)" --block=TREE
```

<!-- TREE:START -->
├── assets
│   └── js
│       └── contact-form.js
│           + isSmartphone()
│           │   # Detecta teléfonos sin clasificar tabletas como smartphones.
│           + setBusy()
│           │   # Sincroniza el estado ocupado, el texto y la accesibilidad de un botón.
│           + setInitialError()
│           │   # Muestra un error asociado al formulario inicial.
│           + setVerificationStatus()
│           │   # Actualiza el aviso y el aspecto de las casillas de clave.
│           + commonRequestBody()
│           │   # Construye la carga común para las operaciones AJAX del formulario.
│           + request()
│           │   # Ejecuta una operación AJAX y normaliza las respuestas de WordPress.
│           + clearCode()
│           │   # Vacía y rehabilita las cuatro casillas de verificación.
│           + showVerification()
│           │   # Sustituye el formulario inicial por la pantalla de la clave.
│           + showSuccess()
│           │   # Presenta la confirmación tras el procesamiento real del servidor.
│           + sendCode()
│           │   # Solicita por email una clave temporal para esta consulta.
│           + submitAuthorized()
│           │   # Envía la consulta después de que el servidor autorizó el email.
├── formularios_.php
│   + autoload_formularios_pw_files()
│   │   # Carga manualmente las clases base del plugin.
├── includes
│   ├── class-formularios-pw-contact-buttons.php
│   │   + Formularios_PW_Contact_Buttons()
│   │   │   # Genera alternativas de contacto exclusivas para smartphones.
│   │   + register()
│   │   │   # Registra avisos administrativos para configuraciones incompletas.
│   │   + render()
│   │   │   # Devuelve los botones solicitados con enlaces seguros y accesibles.
│   │   + render_admin_notice()
│   │   │   # Avisa si un botón activo carece de un número válido.
│   │   + configuration()
│   │   │   # Lee constantes y construye la configuración efectiva.
│   │   + resolve_configuration()
│   │   │   # Resuelve de forma independiente las tres opciones solicitadas.
│   │   + normalize_number()
│   │   │   # Convierte un teléfono internacional a formato seguro.
│   │   + constant_is_true()
│   │   │   # Considera activada solo una constante booleana con valor true.
│   │   + button_data()
│   │   │   # Construye los datos comunes de un botón ya validado.
│   │   + phone_icon()
│   │   │   # Devuelve el icono vectorial de teléfono.
│   │   + whatsapp_icon()
│   │   │   # Devuelve el icono vectorial de WhatsApp.
│   ├── class-formularios-pw-contact-form.php
│   │   + Formularios_PW_Contact_Form()
│   │   │   # Coordina renderizado, verificación y envío del contacto público.
│   │   + register()
│   │   │   # Registra shortcode, endpoints públicos y carga de recursos.
│   │   + register_assets()
│   │   │   # Declara CSS, JavaScript y configuración pública del formulario.
│   │   + render()
│   │   │   # Genera una instancia accesible con estados inicial, verificación y éxito.
│   │   + input()
│   │   │   # Imprime un campo de texto común preservando valores devueltos tras un error.
│   │   + handle_submit()
│   │   │   # Valida y entrega por email una consulta autorizada sin almacenarla.
│   │   + handle_send_code()
│   │   │   # Genera y envía al visitante una clave temporal de cuatro caracteres.
│   │   + handle_invalidate_code()
│   │   │   # Revoca clave y autorización al regresar para cambiar el email.
│   │   + handle_verify_code()
│   │   │   # Valida la clave y autoriza temporalmente la consulta y el email.
│   │   + validate()
│   │   │   # Comprueba los datos obligatorios del recorrido de email.
│   │   + posted_values()
│   │   │   # Extrae y sanitiza los campos públicos recibidos por POST.
│   │   + submit_failure()
│   │   │   # Devuelve un fallo JSON uniforme sin conservar los campos recibidos.
│   │   + submit_success()
│   │   │   # Devuelve éxito JSON sin crear estado persistente adicional.
│   │   + guard_verification_ajax()
│   │   │   # Rechaza operaciones de clave con nonce ausente o caducado.
│   │   + posted_submission_id()
│   │   │   # Recupera y valida el UUID que identifica esta instancia.
│   │   + generate_code()
│   │   │   # Crea una clave de cuatro caracteres sin símbolos visualmente ambiguos.
│   │   + send_verification_email()
│   │   │   # Envía la clave de un solo uso al email del visitante.
│   │   + email_fingerprint()
│   │   │   # Seudonimiza un email para límites y vinculaciones temporales.
│   │   + code_transient_key()
│   │   │   # Deriva la clave de transient que guarda el desafío temporal.
│   │   + verified_transient_key()
│   │   │   # Deriva la clave de transient de la autorización verificada.
│   │   + is_submission_verified()
│   │   │   # Confirma que UUID y email comparten autorización vigente.
│   │   + form_started_token()
│   │   │   # Firma la hora de renderizado para detectar envíos instantáneos.
│   │   + is_valid_form_started_token()
│   │   │   # Comprueba firma y antigüedad razonable del formulario.
│   │   + resolve_origin()
│   │   │   # Valida y describe la página interna declarada por el navegador.
│   │   + normalize_origin_url()
│   │   │   # Reduce una URL al esquema, host, puerto y ruta del sitio actual.
│   │   + send_email()
│   │   │   # Entrega la consulta al destinatario operativo configurado.
│   │   + configured_recipient()
│   │   │   # Devuelve el destinatario configurado cuando es válido.
│   │   + render_configuration_notice()
│   │   │   # Avisa a administradores si falta el destinatario.
│   ├── class-formularios-pw-plugin.php
│   │   + Formularios_PW_Plugin()
│   │   │   # Coordina el formulario público enviado exclusivamente por email.
│   │   + instance()
│   │   │   # Devuelve la instancia única del coordinador del plugin.
│   │   + run()
│   │   │   # Registra el formulario público y el actualizador desde GitHub.
│   ├── class-formularios-pw-rate-limit.php
│   │   + Formularios_PW_Rate_Limit()
│   │   │   # Aplica límites temporales para reducir abuso automatizado.
│   │   + allow()
│   │   │   # Evalúa si una clave supera el umbral dentro de una ventana temporal.
│   │   + fingerprint_from_request()
│   │   │   # Construye huella de cliente a partir de IP y token hash.
│   └── class-formularios-pw-updater.php
│       + Formularios_PW_Updater()
│       │   # Integra releases públicas de GitHub con el actualizador de WordPress.
│       + register()
│       │   # Conecta comprobación, información y limpieza de caché con WordPress.
│       + filter_update()
│       │   # Devuelve una actualización solo cuando la release estable es superior.
│       + filter_plugin_information()
│       │   # Muestra información básica de la release en WordPress.
│       + clear_cache_after_upgrade()
│       │   # Invalida la release guardada después de actualizar el plugin.
│       + clear_release_cache()
│       │   # Permite que una comprobación manual consulte nuevamente GitHub.
│       + get_release()
│       │   # Obtiene y almacena temporalmente la última release pública válida.
│       + normalize_release()
│       │   # Valida y reduce una respuesta remota a campos confiables.
│       + is_allowed_package_url()
│       │   # Limita descargas al ZIP esperado dentro del repositorio.
│       + is_repository_url()
│       │   # Comprueba que una URL informativa pertenece al repositorio público.
│       + cache_failure()
│       │   # Evita repetir inmediatamente una consulta fallida a GitHub.
├── tests
│   └── run.php
│       + WP_Error()
│       + add_filter()
│       + add_action()
│       + get_site_transient()
│       + set_site_transient()
│       + delete_site_transient()
│       + wp_remote_get()
│       + is_wp_error()
│       + wp_remote_retrieve_response_code()
│       + wp_remote_retrieve_body()
│       + sanitize_text_field()
│       + sanitize_textarea_field()
│       + esc_url_raw()
│       + wp_parse_url()
│       + wpautop()
│       + esc_html()
│       + esc_attr()
│       + esc_url()
│       + test_release()
│       + test_response()
│       + reset_test_state()
│       + expect_true()
└── uninstall.php
    + formularios_pw_uninstall_cleanup()
    │   # Elimina cron de retención para evitar ejecuciones huérfanas.
<!-- TREE:END -->
