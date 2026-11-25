# Arquitectura y modelo de datos

El origen publica un servicio web Moodle restringido. El destino usa la lista de métodos de `core_webservice_get_site_info` y seis funciones de operación del origen con un contrato versionado. La API devuelve información de curso, solicita archivos y ofrece su estado y rangos de hasta 256 KiB por respuesta. Los datos del token se obtienen en el destino desde el entorno y no se incluyen en artefactos ni en los registros.

| Tabla con prefijo `tool_skillupsmigration_` | Uso |
|---|---|
| `job` | Actor, categoría destino, referencia del origen, huella del registro, estado y número de ejecución. |
| `item` | Curso origen, opciones, inventario, resultado de preparación, destino reservado e identidad del respaldo. |
| `artifact` | Solicitud idempotente del origen, dueño, opciones, estado, tamaño y SHA-256. |
| `audit` | Operador, fase, lote, fila y fecha. |

El origen llama a Backup API en modo general con usuarios excluidos. Guarda temporalmente el archivo en su área File API privada. El destino conserva el avance de transferencia en `moodledata` con un nombre derivado del identificador criptográfico. Verifica cantidad y huella de los bytes, disposición y tamaños del archivo extraído, dependencias, configuración del respaldo y contenido seleccionado antes de pedir autorización de ejecución. El cron retira los materiales vencidos. Moodle Lock API protege tareas y mutaciones de la misma fila o lote.

Cuando el operador confirma, el destino asigna un único curso oculto y refresca sus permisos. Precomprobación y restauración usan Moodle Restore sobre ese curso. Se registra el estado de restauración antes de invocar el plan nativo. Tras una interrupción en ese límite, el sistema deja el curso para revisión manual y no repite el plan sobre el mismo destino. El cotejo posterior verifica secciones, ubicación de actividades, grupos y fechas solicitadas. El mapeo usa la matrícula manual oficial y se aplica tras ese cotejo.

Privacy API exporta referencias del operador y sus mapeos locales aprobados. Una supresión autorizada puede retirar respaldos privados, cancelar operaciones activas y anonimizar referencias. Los cursos creados y sus matrículas se administran desde los procesos nativos de Moodle.
