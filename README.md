# Formularios Presencia Web CodePTY

Plugin independiente para gestionar expedientes de clientes de Presencia Web con:

- ficha interna sin crear usuarios WordPress para clientes
- formulario externo por enlace secreto revocable
- formulario interno de entrevista (11 apartados)
- almacenamiento cifrado por expediente en archivos `.pty`
- índice mínimo en base de datos y auditoría de acciones relevantes
- formulario reutilizable de consultas generales mediante `[codepty_formulario_contacto]`

## Formulario de contacto general

El shortcode `[codepty_formulario_contacto]` guarda cada consulta cifrada. En móviles abre
WhatsApp con el mensaje preparado y en otros dispositivos envía un correo. Las consultas
pueden revisarse en **Presencia Web > Consultas generales** por usuarios con la capacidad
`manage_codepty_presencia`.

El formulario incluye verificación del email mediante una clave temporal de cuatro
caracteres, nonce de WordPress, campo honeypot, comprobación firmada del tiempo de llenado
y límites por IP y email. La consulta solamente puede enviarse después de validar la clave.

El visitante completa primero su consulta y pulsa **Enviar mensaje**. El formulario se
sustituye entonces por cuatro casillas para la clave recibida. Al pulsar **Confirmar y
enviar mensaje**, el servidor valida la clave y procesa la consulta en una única acción
visible. Los datos se conservan si hay que corregir, reenviar la clave o cambiar el email.

En smartphones, el formulario recomienda WhatsApp y abre la aplicación con un mensaje
preparado sin solicitar verificación ni registrar la consulta como recibida. El visitante
puede cambiar a email sin perder sus datos; ese recorrido sí exige la clave temporal. En
ordenadores y tabletas solo se ofrece el formulario verificado por email.

Los destinos provisionales se definen globalmente y pueden reemplazarse en `wp-config.php`
antes de cargar el plugin:

```php
define('CODEPTY_CONTACT_WHATSAPP', '+507 6123-4567');
define('CODEPTY_CONTACT_EMAIL', 'consultas@example.com');
```

## Requisitos de despliegue seguro

1. Definir directorio privado fuera de raíz web (opcional si el fallback ya queda fuera):

```php
define('CODEPTY_PW_STORAGE_DIR', '/ruta/privada/codepty-presencia-web');
```

2. Definir clave maestra fuera del plugin/WordPress/Git:

```php
define('CODEPTY_PW_MASTER_KEY_FILE', '/ruta/segura/master.key');
```

La clave debe ser de 32 bytes en binario o base64/base64-url.

## Política de retención (preparada)

La purga automática está desactivada por defecto. Para habilitarla:

```php
add_filter('formularios_pw_retention_enabled', '__return_true');
add_filter('formularios_pw_retention_days', fn() => 365);
```

Las consultas generales usan el mismo plazo, modificable con el filtro
`formularios_pw_contact_retention_days`. La purga permanece desactivada mientras no se
habilite explícitamente la retención general.

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
│           │   # Detecta teléfonos mediante Client Hints y agentes móviles conocidos.
│           + setBusy()
│           │   # Sincroniza el estado ocupado, el texto y la accesibilidad de un botón.
│           + setChannel()
│           │   # Cambia entre WhatsApp y email sin perder los valores escritos.
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
│           + continueToWhatsApp()
│           │   # Abre WhatsApp con un mensaje preparado sin registrar la consulta.
│           + submitAuthorized()
│           │   # Envía la consulta después de que el servidor autorizó el email.
├── formularios_.php
│   + autoload_formularios_pw_files()
│   │   # Carga manualmente las clases base del plugin.
├── includes
│   ├── class-formularios-pw-activator.php
│   │   + Formularios_PW_Activator()
│   │   │   # Ejecuta tareas de instalación, actualización y apagado del plugin.
│   │   + activate()
│   │   │   # Crea tablas, prepara permisos, rutas de reescritura y cron del plugin.
│   │   + deactivate()
│   │   │   # Limpia hooks temporales sin eliminar datos de expedientes.
│   ├── class-formularios-pw-admin.php
│   │   + Formularios_PW_Admin()
│   │   │   # Gestiona panel interno, expedientes, tokens y formulario de entrevista.
│   │   + register()
│   │   │   # Conecta menús, acciones POST internas y estilos administrativos.
│   │   + register_menu()
│   │   │   # Registra la entrada de menú del módulo de expedientes.
│   │   + enqueue_assets()
│   │   │   # Carga estilos para hacer legible el panel administrativo del plugin.
│   │   + render_page()
│   │   │   # Muestra listado de expedientes o detalle según parámetros de consulta.
│   │   + handle_create_case()
│   │   │   # Procesa alta de expediente, genera enlace secreto y estado enviado.
│   │   + handle_save_internal()
│   │   │   # Guarda formulario interno de entrevista y metadatos operativos.
│   │   + handle_change_status()
│   │   │   # Cambia estado desde acción rápida del listado administrativo.
│   │   + handle_regenerate_token()
│   │   │   # Revoca tokens activos y crea un nuevo enlace secreto.
│   │   + handle_revoke_token()
│   │   │   # Revoca un token concreto por hash sin exponer su valor original.
│   │   + render_header()
│   │   │   # Abre contenedor y título principal del área del plugin.
│   │   + render_footer()
│   │   │   # Cierra contenedor principal de la interfaz administrativa.
│   │   + render_create_form()
│   │   │   # Dibuja formulario rápido para crear ficha y enlace secreto.
│   │   + render_list()
│   │   │   # Muestra tabla resumida con cliente, estado, responsable y fechas.
│   │   + render_detail()
│   │   │   # Muestra vista completa de un expediente con formularios y acciones.
│   │   + render_owner_select()
│   │   │   # Construye selector de usuario responsable para el expediente.
│   │   + render_status_select()
│   │   │   # Construye selector para transición manual de estado.
│   │   + render_key_value()
│   │   │   # Imprime fila simple de etiqueta y contenido textual escapado.
│   │   + render_attachments()
│   │   │   # Muestra metadatos de adjuntos externos sin exponer archivos directamente.
│   │   + recent_audit()
│   │   │   # Devuelve últimos eventos de auditoría del expediente actual.
│   │   + status_labels()
│   │   │   # Define etiquetas legibles de los estados operativos del expediente.
│   │   + status_label()
│   │   │   # Obtiene etiqueta legible para un valor de estado concreto.
│   │   + textarea_row()
│   │   │   # Genera una fila de tabla con textarea para formularios internos extensos.
│   │   + guard_admin_post()
│   │   │   # Verifica permisos y nonce antes de procesar acciones internas.
│   │   + redirect_admin()
│   │   │   # Redirige al panel del plugin con argumentos de resultado.
│   │   + token_notice_key()
│   │   │   # Calcula clave transient por usuario para mostrar token recién generado.
│   │   + render_token_notice_if_any()
│   │   │   # Muestra token/plink generado y lo elimina del almacenamiento temporal.
│   ├── class-formularios-pw-audit.php
│   │   + Formularios_PW_Audit()
│   │   │   # Registra eventos relevantes para trazabilidad de expedientes.
│   │   + log()
│   │   │   # Inserta un evento de auditoría con actor, tipo y metadatos mínimos.
│   │   + normalize_meta()
│   │   │   # Reduce metadatos a tipos seguros para almacenamiento en auditoría.
│   ├── class-formularios-pw-contact-admin.php
│   │   + Formularios_PW_Contact_Admin()
│   │   │   # Presenta consultas cifradas al equipo autorizado.
│   │   + register()
│   │   │   # Registra menú y recursos de la pantalla privada de consultas.
│   │   + register_menu()
│   │   │   # Añade Consultas generales como subpágina de Presencia Web.
│   │   + enqueue_assets()
│   │   │   # Carga estilos solo dentro de la pantalla de consultas.
│   │   + render()
│   │   │   # Comprueba permisos y muestra listado o detalle de una consulta.
│   │   + render_list()
│   │   │   # Dibuja la tabla de consultas recientes sin descifrar sus payloads.
│   │   + render_detail()
│   │   │   # Descifra y presenta una consulta seleccionada.
│   │   + field()
│   │   │   # Imprime un campo de detalle escapando contenido y saltos de línea.
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
│   │   │   # Valida, almacena cifrada y entrega por email una consulta autorizada.
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
│   │   │   # Devuelve un fallo uniforme por AJAX o mediante estado redirigido.
│   │   + submit_success()
│   │   │   # Devuelve éxito por AJAX o redirige con un estado efímero.
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
│   │   + validated_return_url()
│   │   │   # Obtiene una URL local limpia para el fallback por redirección.
│   │   + is_local_url()
│   │   │   # Comprueba que una URL pertenece al mismo host de WordPress.
│   │   + resolve_origin()
│   │   │   # Describe la página desde la que se inició la consulta.
│   │   + send_email()
│   │   │   # Entrega la consulta al destinatario operativo configurado.
│   │   + redirect_with_state()
│   │   │   # Conserva un estado breve y redirige sin exponer sus datos.
│   │   + consume_state()
│   │   │   # Recupera y elimina el estado efímero señalado por la URL.
│   ├── class-formularios-pw-contact-repository.php
│   │   + Formularios_PW_Contact_Repository()
│   │   │   # Persiste consultas cifradas y su índice operativo mínimo.
│   │   + create()
│   │   │   # Cifra una consulta, crea su índice y registra el evento de recepción.
│   │   + set_delivery_status()
│   │   │   # Actualiza el resultado de entrega y lo deja en auditoría.
│   │   + list_recent()
│   │   │   # Devuelve consultas recientes sin descifrar su contenido.
│   │   + get()
│   │   │   # Recupera la fila de índice de una consulta concreta.
│   │   + purge_before()
│   │   │   # Elimina consultas, payloads cifrados y auditoría anteriores al umbral.
│   ├── class-formularios-pw-crypto.php
│   │   + Formularios_PW_Crypto()
│   │   │   # Cifra y descifra cargas JSON con Sodium XChaCha20-Poly1305.
│   │   + encrypt_json()
│   │   │   # Cifra un array y devuelve un sobre serializable en JSON.
│   │   + decrypt_json()
│   │   │   # Descifra un sobre y devuelve el array original del expediente.
│   │   + master_key()
│   │   │   # Obtiene la clave maestra desde constantes o variables de entorno externas.
│   │   + aad()
│   │   │   # Construye datos autenticados adicionales para vincular el contexto de cifrado.
│   │   + read_master_key_source()
│   │   │   # Lee la fuente de clave maestra desde constantes o entorno.
│   │   + maybe_create_wordpress_managed_key()
│   │   │   # Crea una clave interna en opciones de WordPress si no existe otra fuente.
│   │   + wordpress_option_name()
│   │   │   # Devuelve el nombre de opción usada para la clave interna gestionada por WordPress.
│   │   + decode_key()
│   │   │   # Interpreta la clave en base64-url, base64 estándar o texto binario literal.
│   ├── class-formularios-pw-db.php
│   │   + Formularios_PW_DB()
│   │   │   # Define y crea las tablas mínimas de índice, tokens y auditoría.
│   │   + table_cases()
│   │   │   # Devuelve el nombre completo de la tabla de expedientes.
│   │   + table_tokens()
│   │   │   # Devuelve el nombre completo de la tabla de tokens.
│   │   + table_audit()
│   │   │   # Devuelve el nombre completo de la tabla de auditoría.
│   │   + table_payloads()
│   │   │   # Devuelve el nombre completo de la tabla de payloads cifrados en DB.
│   │   + table_contacts()
│   │   │   # Devuelve el nombre completo de la tabla de consultas generales.
│   │   + create_tables()
│   │   │   # Crea o actualiza las tablas necesarias del plugin.
│   │   + ensure_tables()
│   │   │   # Comprueba el esquema del plugin y lo crea si falta alguna tabla.
│   │   + schema_is_complete()
│   │   │   # Verifica si todas las tablas del plugin ya existen.
│   │   + table_exists()
│   │   │   # Comprueba si existe una tabla concreta del plugin.
│   ├── class-formularios-pw-permissions.php
│   │   + Formularios_PW_Permissions()
│   │   │   # Gestiona capacidades para administradores y equipo interno.
│   │   + grant_capability()
│   │   │   # Asigna la capacidad del plugin a administrator y equipocodepty.
│   │   + current_user_can_manage()
│   │   │   # Indica si el usuario actual puede operar el panel interno.
│   ├── class-formularios-pw-plugin.php
│   │   + Formularios_PW_Plugin()
│   │   │   # Coordina el arranque de componentes administrativos, públicos y de retención.
│   │   + instance()
│   │   │   # Devuelve la instancia única del coordinador del plugin.
│   │   + run()
│   │   │   # Registra los servicios del plugin para panel interno, formulario público y limpieza programada.
│   ├── class-formularios-pw-public-form.php
│   │   + Formularios_PW_Public_Form()
│   │   │   # Expone el formulario externo por token secreto sin usuarios WordPress.
│   │   + register()
│   │   │   # Registra rewrite, query var y resolución de la vista pública.
│   │   + register_rewrite_rules()
│   │   │   # Define la ruta pública amigable para token externo.
│   │   + add_query_var()
│   │   │   # Declara la query var personalizada para resolver tokens.
│   │   + maybe_render()
│   │   │   # Atiende la solicitud del formulario externo si la URL incluye token.
│   │   + merge_external_form_payload()
│   │   │   # Sanitiza campos externos y adjuntos antes de persistir expediente.
│   │   + process_uploaded_files()
│   │   │   # Recorre archivos del campo materials y almacena adjuntos cifrados.
│   │   + passes_honeypot()
│   │   │   # Verifica campo trampa anti-bot para rechazar envíos automatizados.
│   │   + post_value()
│   │   │   # Obtiene valor POST des-escapado para una clave dada.
│   │   + render_error_page()
│   │   │   # Muestra una salida mínima para token inválido o acceso bloqueado.
│   │   + render_form_page()
│   │   │   # Renderiza el formulario externo editable y mensajes de confirmación.
│   │   + count_post_fields()
│   │   │   # Cuenta los valores recibidos por POST para depuración.
│   │   + count_files()
│   │   │   # Cuenta los bloques de subida recibidos por FILES para depuración.
│   │   + count_keys()
│   │   │   # Cuenta claves visibles de arrays y objetos para depuración.
│   │   + log()
│   │   │   # Envía una traza al error_log con prefijo estable del plugin.
│   ├── class-formularios-pw-rate-limit.php
│   │   + Formularios_PW_Rate_Limit()
│   │   │   # Aplica límites temporales para reducir abuso automatizado.
│   │   + allow()
│   │   │   # Evalúa si una clave supera el umbral dentro de una ventana temporal.
│   │   + fingerprint_from_request()
│   │   │   # Construye huella de cliente a partir de IP y token hash.
│   ├── class-formularios-pw-repository.php
│   │   + Formularios_PW_Repository()
│   │   │   # Centraliza operaciones de índice, tokens, estados y consultas del panel.
│   │   + create_case()
│   │   │   # Inserta un expediente nuevo con índice mínimo y estado inicial.
│   │   + list_cases()
│   │   │   # Devuelve la lista de expedientes activos ordenados por actualización.
│   │   + get_case_by_uid()
│   │   │   # Recupera un expediente por su identificador público interno.
│   │   + update_owner()
│   │   │   # Actualiza el responsable interno del expediente.
│   │   + update_status()
│   │   │   # Cambia estado de expediente y sincroniza fechas de hito.
│   │   + create_token()
│   │   │   # Crea token secreto, almacena solo hash y devuelve token plano una sola vez.
│   │   + revoke_active_tokens()
│   │   │   # Revoca todos los tokens vigentes de un expediente.
│   │   + revoke_token_by_hash()
│   │   │   # Revoca un token específico usando su hash almacenado.
│   │   + list_case_tokens()
│   │   │   # Lista tokens de un expediente para gestión y revocación.
│   │   + find_case_by_token_plain()
│   │   │   # Valida un token plano y devuelve expediente junto a registro de token.
│   │   + mark_token_used()
│   │   │   # Incrementa uso y última fecha de acceso de un token válido.
│   │   + get_case_payload()
│   │   │   # Lee y descifra el contenido del expediente desde almacenamiento privado.
│   │   + save_case_payload()
│   │   │   # Cifra y guarda el contenido actualizado del expediente.
│   │   + hash_token()
│   │   │   # Convierte un token plano en hash irreversible para persistencia.
│   │   + random_hex()
│   │   │   # Genera identificadores hexadecimales aleatorios para índice y archivos.
│   │   + base64url_random()
│   │   │   # Genera token URL-safe de longitud alta para acceso externo.
│   ├── class-formularios-pw-retention.php
│   │   + Formularios_PW_Retention()
│   │   │   # Prepara política de conservación y purga programada extensible.
│   │   + register()
│   │   │   # Conecta el callback de limpieza al evento cron del plugin.
│   │   + schedule()
│   │   │   # Programa la tarea diaria si aún no existe en el calendario de WordPress.
│   │   + unschedule()
│   │   │   # Elimina la tarea programada al desactivar el plugin.
│   │   + run_cleanup()
│   │   │   # Ejecuta la purga según política configurada sin borrar por defecto.
│   │   + delete_case_files()
│   │   │   # Elimina archivos cifrados de expediente y adjuntos durante purga aprobada.
│   └── class-formularios-pw-storage.php
│       + Formularios_PW_Storage()
│       │   # Gestiona lectura y escritura cifrada de expedientes y adjuntos privados.
│       + allowed_mimes()
│       │   # Devuelve los MIME permitidos para materiales del cliente.
│       + storage_dir()
│       │   # Obtiene el directorio base privado para expedientes.
│       + use_db_backend()
│       │   # Determina si el plugin guarda payloads cifrados en base de datos.
│       + ensure_storage_ready()
│       │   # Crea estructura privada base cuando aún no existe.
│       + read_case_payload()
│       │   # Descifra y devuelve el contenido completo de un expediente.
│       + write_case_payload()
│       │   # Cifra y persiste el contenido del expediente de forma atómica.
│       + write_contact_payload()
│       │   # Guarda una consulta de contacto cifrada en el almacén privado compartido.
│       + read_contact_payload()
│       │   # Recupera y descifra una consulta de contacto.
│       + delete_contact_payload()
│       │   # Elimina definitivamente el payload cifrado de una consulta purgada.
│       + store_uploaded_file()
│       │   # Valida y cifra un archivo cargado, devolviendo su metadato seguro.
│       + default_payload()
│       │   # Construye la estructura inicial de un expediente nuevo.
│       + detect_mime_type()
│       │   # Detecta MIME real desde contenido para evitar suplantación de extensiones.
│       + case_file_path()
│       │   # Resuelve la ruta física del archivo .pty de un expediente.
│       + attachment_file_path()
│       │   # Resuelve la ruta física de un adjunto cifrado del expediente.
│       + delete_case_artifacts()
│       │   # Elimina payloads de expediente y adjuntos según backend activo.
│       + read_case_payload_db()
│       │   # Recupera y descifra payload de expediente guardado en DB.
│       + write_case_payload_db()
│       │   # Cifra y persiste payload de expediente en DB.
│       + upsert_payload()
│       │   # Inserta o actualiza un payload cifrado en tabla dedicada.
│       + get_payload_row()
│       │   # Busca un payload cifrado por expediente y referencia de objeto.
└── uninstall.php
    + formularios_pw_uninstall_cleanup()
    │   # Elimina cron de retención para evitar ejecuciones huérfanas.
<!-- TREE:END -->
