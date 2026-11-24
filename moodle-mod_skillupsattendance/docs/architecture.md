# Arquitectura de SkillUps Attendance Pro

Se inspeccionó el código de Moodle 5.0.7 y la documentación de APIs de Moodle 5.3. El módulo usa APIs nativas para matrícula, grupos, capacidades, calendario, archivos, notificaciones, copias de seguridad y calificaciones.

| Tabla | Función |
| --- | --- |
| `skillupsattendance` | Configuración de actividad, umbrales, motivos y habilitación de avisos y calificaciones |
| `skillupsattendance_sess` | Grupo, docente, horario, modalidad, estado, revisión y vínculo con el calendario |
| `skillupsattendance_mark` | Resultado único por sesión y estudiante, origen y marcas de tiempo |
| `skillupsattendance_just` | Solicitud, estado, solicitante, revisor y decisión. File API conserva los adjuntos. |
| `skillupsattendance_audit` | Eventos del módulo, usuario autorizado, sesión y transiciones |
| `skillupsattendance_fail` | Contadores breves de intentos de registro |
| `skillupsattendance_alert` | Deduplicación por actividad, estudiante y umbral |

Las escrituras relacionadas con asistencia usan un bloqueo Moodle por actividad y una transacción delegada. Los índices únicos protegen los registros, intentos y alertas. El cierre toma la nómina activa mediante la API de matrícula, ámbito de grupo y capacidad de registro. Lee lotes de hasta 500 usuarios y agrega las ausencias faltantes. La revisión de un justificante actualiza el resultado efectivo y registra el historial. Las correcciones posteriores requieren capacidad y respetan la misma actividad y grupo.

El calendario nativo vincula cada sesión a su instancia resuelta. Las series mantienen la hora local del operador y limitan el número de ocurrencias. Al cancelar se retira la entrada y se excluyen sus registros del cálculo. Los tokens QR se derivan de un secreto por sesión, la revisión y un tramo de un minuto. Las contraseñas de sesión se guardan hasheadas y la copia de seguridad omite credenciales.

Los informes usan consultas parametrizadas, lectura secuencial de resultados y paginación de la vista. El agregado cronológico calcula las rachas y el porcentaje a partir de estados cerrados. Los datos de varias actividades se consultan solo cuando el operador tiene acceso en cada una. Moodle exporta CSV y XLSX con sus formatos activados. La publicación de calificaciones utiliza Gradebook API, exige autorización y excluye calificaciones bloqueadas o anuladas.

La Privacy API opera en el contexto de módulo. Identifica asistencia, solicitudes, archivos, actoría, destinatarios e intentos. La exportación conserva los datos propios y omite las identidades de otras personas y credenciales. La eliminación autorizada retira las marcas y evidencias propias y anonimiza referencias de otros casos. Moodle administra por separado matrícula, log central y Gradebook.

[Activity modules](https://moodledev.io/docs/5.3/apis/plugintypes/mod) · [Groups API](https://moodledev.io/docs/5.0/apis/subsystems/group) · [Calendar API](https://moodledev.io/docs/5.3/apis/core/calendar) · [File API](https://moodledev.io/docs/5.3/apis/subsystems/files)
