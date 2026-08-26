# Changelog

Todos los cambios relevantes de Formularios CodePTY se documentan en este archivo.

## 0.6.9

- Cambiado el identificador del asunto al formato `TIMESTAMP-XXXX`, con timestamp Unix y
  cuatro caracteres generados mediante `random_int()`.
- Añadido al asunto final el nombre saneado del remitente, sin controles ni saltos de línea
  y limitado a 80 caracteres.
- Añadida la clave de cuatro caracteres al asunto del correo de validación.

## 0.6.8

- Añadido al inicio del asunto un identificador único generado por el servidor.
- El identificador combina timestamp UTC y un sufijo criptográfico para evitar colisiones.

## 0.6.7

- Añadidos botones independientes de teléfono, WhatsApp y combinado solo para smartphones.
- Añadidas validación de números, accesibilidad y advertencias administrativas.
- Añadidas pruebas para las ocho combinaciones posibles de configuración.

## 0.6.6

- Publicación de prueba para validar la actualización automática desde WordPress.
- La acción manual de comprobar actualizaciones vuelve a consultar GitHub inmediatamente.

## 0.6.5

- Añadido el actualizador manual mediante releases públicas de GitHub.
- Añadida validación estricta del asset `formularios_.zip`.
- Añadidas pruebas locales del actualizador y generación segura del paquete.
- Preparada como primera versión que incorpora el actualizador desde GitHub.

## 0.6.4

- Simplificado el plugin al formulario verificado enviado exclusivamente por email.
- Eliminado el almacenamiento permanente de consultas y el antiguo sistema de expedientes.
