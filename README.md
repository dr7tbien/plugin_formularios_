# Formularios Presencia Web CodePTY

Plugin independiente para gestionar expedientes de clientes de Presencia Web con:

- ficha interna sin crear usuarios WordPress para clientes
- formulario externo por enlace secreto revocable
- formulario interno de entrevista (11 apartados)
- almacenamiento cifrado por expediente en archivos `.pty`
- índice mínimo en base de datos y auditoría de acciones relevantes

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

## Comando dr-readme

Desde este directorio:

```bash
wp dr-readme update --target="$(pwd)" --block=TREE
```

<!-- TREE:START -->
<!-- TREE:END -->
