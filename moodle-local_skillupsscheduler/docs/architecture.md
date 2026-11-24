# Arquitectura de SkillUps Scheduler

Scheduler es un plugin local porque agenda cursos de varias categorías y recintos. Reutiliza matrícula, roles, grupos, calendario y mensajería de Moodle. El módulo de asistencia de la suite sirvió de referencia para el tratamiento de grupos, calendario y permisos. El código de Moodle 5.0.7 de XAMPP estaba disponible para inspección con PHP 8.2.12. Las pruebas de instalación se realizan en bases independientes.

## Diseño

1. Configurar recintos vinculados a una categoría Moodle, ciclos con límites de fechas, aulas, turnos y bloques semanales.
2. Crear borradores individuales o recurrentes en la zona horaria del recinto. El coordinador necesita una capacidad en la categoría y una habilitación explícita en ese recinto.
3. Validar semestre, bloque, docente, capacidad y superposiciones. Una transacción con bloqueo Moodle a nivel de instalación serializa reservas que comparten docentes entre recintos. La revisión de cada sesión protege contra formularios obsoletos.
4. Publicar en el calendario del curso o grupo y registrar el motivo de cada cambio. Entregar avisos desde una cola mediante cron. Mantener el calendario privado del plugin y exportar ICS aplicando las mismas reglas de lectura.
5. Validar con datos sintéticos, preparar material de revisión y empaquetar el ZIP.

## Modelo de datos

Las tablas `local_skillupsscheduler_*` distinguen recintos (`site`), ciclos (`cycle`), aulas (`room`), bloques y turnos (`block`), operadores autorizados (`grant`), sesiones (`slot`), revisiones (`audit`) y cola de cambios (`queue`). Una serie guarda ocurrencias independientes con un identificador común. Una sesión mantiene su curso y grupo durante su ciclo de vida. La sustitución cambia al docente titular de esa ocurrencia. Una sesión cancelada conserva historial y libera recursos.

| Tabla | Entidad y controles |
| --- | --- |
| `site` | Zona IANA y categoría Moodle que delimita el recinto |
| `cycle` | Fechas inclusivas y período superior opcional del mismo recinto |
| `room` | Tipo de aula, capacidad y enlace HTTPS para espacios virtuales |
| `block` | Nombre del turno, día ISO y minutos de inicio y fin desde medianoche |
| `grant` | Habilitación única por usuario y recinto, adicional a las capacidades Moodle |
| `slot` | Curso, grupo, docente, recursos, tiempos UTC, revisión y estado. Serie y ordinal únicos |
| `audit` | Actor, operación, motivo y estado anterior y posterior por revisión |
| `queue` | Un aviso por revisión, cursor de destinatarios y reintentos con espera creciente |

Los cruces de reservas usan intervalos semiabiertos. Una sesión de curso completo colisiona con cualquier grupo de su curso. Una sesión de grupo específico colisiona con el mismo grupo nativo o con una sesión de curso completo. Las reservas de docentes, aulas físicas y aulas virtuales se verifican por separado. La cancelación retira la entrada nativa y permite reutilizar los recursos.

## Referencias de implementación

- [Plugins locales](https://moodledev.io/docs/5.3/apis/plugintypes/local)
- [Calendar API](https://moodledev.io/docs/5.3/apis/core/calendar)
- [Lock API](https://moodledev.io/docs/5.0/apis/core/lock)
- [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy)
