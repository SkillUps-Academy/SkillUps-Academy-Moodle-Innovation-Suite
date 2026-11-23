# Arquitectura de Course Factory

Se inspeccionó Moodle 5.0.7 y el código de `tool_uploadcourse`, incluida su restauración con `MODE_IMPORT`. La referencia de validación es Moodle 5.3. La extensión se instala como herramienta de administración y reutiliza los controladores Backup y Restore, Course API, File API, Lock API, Task API y matriculación manual de Moodle.

La vista previa normaliza los registros y conserva una instantánea en tablas del plugin. Una confirmación protegida autoriza su despacho, de inmediato o a una hora concreta. Cada fila tiene un origen opcional y una identidad única de destino. Una transacción guarda el curso oculto y su referencia de trabajo. El motor publica un punto de control antes de restaurar. Una ejecución interrumpida durante la restauración se deriva a revisión humana y el curso queda oculto. Las fases verificables se pueden reintentar sin crear otro curso.

Un despachador programado selecciona los trabajos vencidos y usa tareas Adhoc. Los bloqueos serializan la ejecución de Course Factory. La cancelación se comprueba entre cursos y antes de comenzar una restauración. El panel muestra los resultados por fila, con códigos de error acotados. No se conserva el archivo importado como parte del historial.

Los roles se resuelven según la política del sitio. Los controladores operan con la identidad del solicitante y sin datos de participantes. Las asignaciones se hacen con `enrol_manual`, cuando el solicitante puede matricular y asignar el rol en el destino.

[Backup API](https://moodledev.io/docs/5.3/apis/subsystems/backup) · [Restore API](https://moodledev.io/docs/5.0/apis/subsystems/backup/restore) · [Enrolment API](https://moodledev.io/docs/5.3/apis/subsystems/enrol)
