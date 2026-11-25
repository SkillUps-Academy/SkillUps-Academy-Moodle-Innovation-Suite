# Arquitectura y modelo de datos

Las campañas, plantillas y segmentos tienen un contexto de sistema o categoría Moodle. Los filtros se resuelven mediante DML parametrizado y APIs nativas de contexto, matrícula, cohortes y grupos. Los recintos y programas se representan como asociaciones a categorías. Los conjuntos explícitos conservan IDs de cuentas verificadas.

| Tabla con prefijo `local_skillupscommunication_` | Uso |
|---|---|
| `camp` | Ámbito, contenido, responsables, criterios, revisión, estado y contador de ejecución. |
| `recipient` | Fila única por comunicación y cuenta, estado, intentos y referencia Moodle. |
| `log` | Eventos editoriales y operativos por comunicación. |
| `template` | Asuntos, mensajes y tipos reutilizables por ámbito. |
| `segment` | Programa, recinto o definición de conjunto. |
| `member` | Cuentas de los conjuntos personalizados. |

Un resumen criptográfico enlaza la vista previa con la revisión y la lista ordenada de cuentas. La confirmación guarda el padrón y la aprobación exige que siga coincidiendo con la selección actual. Cada lote revalida emisor, aprobador, cuenta y pertenencia. Después de aprobar, el motor puede omitir cuentas que perdieron acceso. Conserva el padrón aprobado como límite y no agrega cuentas nuevas.

## Distribución y resultados

Moodle Lock API serializa el estado de una campaña. Una fila pasa a «En transferencia» en DML antes de llamar a un canal. La llamada se hace fuera de la transacción que congela o aprueba. El trabajador protege la ejecución con un contador de lote. La aceptación y los resultados ambiguos son terminales para el reintento. Tras reiniciarse un proceso interrumpido durante una transferencia, esa fila se registra como incierta. Los rechazos con evidencia de que la transferencia no empezó reciben hasta tres intentos automáticos con espera exponencial. Los reintentos manuales recuperables tienen un máximo total de seis intentos por destinatario.

El valor devuelto por Moodle Messaging registra aceptación y puede incluir el ID de notificación. Moodle canaliza el mensaje aplicando sus preferencias y procesadores. Una aceptación de Moodle puede existir aunque un procesador quede deshabilitado por configuración o preferencias. La referencia acredita el registro en Moodle. Un conector autorizado puede declarar entrega confirmada conforme a su contrato. [Message API de Moodle](https://moodledev.io/docs/5.3/apis/core/message) · [Task API de Moodle](https://moodledev.io/docs/5.3/apis/subsystems/task)

## Privacidad y retención

Privacy API lista los ámbitos que contienen datos del usuario y exporta sus registros aprobados. La supresión de un destinatario quita su fila y pertenencias a conjuntos del ámbito autorizado. La supresión de responsables anonimiza las referencias, retira plantillas del autor y cancela la distribución activa que dependía de esas autorizaciones. Al eliminar una categoría, se elimina la información propia del componente en ese contexto. El sitio conserva los registros institucionales hasta su limpieza mediante los procesos autorizados. Moodle Messaging mantiene sus registros nativos según las reglas del sitio. Los conectores externos tienen políticas de retención propias, sujetas a la autorización de la institución.
