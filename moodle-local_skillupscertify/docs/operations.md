# Diseño y operación

## Diseñar una plantilla

Escribe el título, institución, texto y, si corresponde, el nombre o cargo del firmante. Elige orientación y color. En el texto se pueden insertar `{recipient}`, `{course}`, `{issuer}`, `{issuedate}`, `{completiondate}` y `{serial}`. La vista previa del navegador usa datos de ejemplo. Usa la vista previa PDF para revisar el resultado A4 antes de guardar.

Elige **Finalización del curso en Moodle**, **Autorización manual para un estudiante activo** o **Finalización verificada de Learning Path**. Guardar crea una revisión. Deshabilitar la plantilla detiene las nuevas emisiones, incluidos los lotes pendientes que todavía no hayan emitido.

## Emitir y administrar

- Individualmente, selecciona la plantilla, indica el nombre de usuario de Moodle y confirma.
- Para una emisión por lote, selecciona un grupo y confirma el alcance. Se aceptan hasta 200 estudiantes activos y hasta tres lotes pendientes por curso. Consulta los resultados individuales después de que cron procese el lote. Cada lote conserva la revisión con la que se encoló.
- El registro permite filtrar por grupo y estado, exportar en CSV y consultar el historial. Revocar requiere motivo privado y confirmación. Reemitir usa la revisión vigente, comprueba los requisitos y genera una nueva referencia y un nuevo QR. La edición previa mantiene sus bytes y su estado revocado o reemplazado.

## Verificar un documento

Abre el QR o introduce los 48 caracteres del código de verificación impreso. Comprueba que la página pertenezca al dominio del emisor y que muestre el estado esperado. Para detectar modificaciones en el PDF o un QR copiado, selecciona el archivo localmente en el verificador y compara su huella. También puedes comparar el SHA-256 calculado con una herramienta de confianza. El estado refleja la vigencia según el registro; la huella compara el archivo exacto.
