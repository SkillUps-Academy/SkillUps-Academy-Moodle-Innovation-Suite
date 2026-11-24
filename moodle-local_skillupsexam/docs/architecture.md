# Arquitectura técnica

## Integración nativa

El componente local gestiona el calendario institucional y sus registros propios. Las capacidades, los contextos y la disponibilidad de Moodle acotan las operaciones. El control de grupo se comprueba también sobre cada participante al leer, guardar y descargar.

- **Quiz.** `mod_quiz\quiz_settings` proporciona la configuración base y el estado de preguntas. `quiz_get_user_attempts()` recupera metadatos de intentos que no son vistas previas. Se exige `mod/quiz:viewreports`. [Código oficial de Quiz](https://github.com/moodle/moodle/tree/MOODLE_503_STABLE/public/mod/quiz)
- **Assign.** `assign` proporciona las fechas base y el último estado de entrega individual o grupal. Se exige `mod/assign:grade`. El anonimato vigente impide devolver estados y calificaciones identificables. [Código oficial de Assign](https://github.com/moodle/moodle/tree/MOODLE_503_STABLE/public/mod/assign)
- **Gradebook.** `grade_item` y `grade_grade` leen resultados finales disponibles. La lectura exige `moodle/grade:viewall`. Los resultados ocultos requieren `moodle/grade:viewhidden`. Una nota pendiente de recálculo se deja vacía. [Código oficial de Gradebook](https://github.com/moodle/moodle/tree/MOODLE_503_STABLE/public/lib/grade)
- **Permisos.** Se usan matrícula, contexto, grupos y disponibilidad nativos. [Access API](https://moodledev.io/docs/5.3/apis/subsystems/access) · [Availability API](https://moodledev.io/docs/5.3/apis/subsystems/availability)

Las alertas se calculan a partir de las fechas generales. Los ajustes individuales, las restricciones de acceso, los intentos y las entregas se administran en el módulo Moodle de origen. Las acciones del plugin escriben únicamente en sus propias tablas y en la mensajería mediante su API.

## Modelo de datos

| Tabla con prefijo `local_skillupsexam_` | Contenido y relación |
|---|---|
| `period` | Categoría Moodle, nombre, inicio y fin. |
| `room` | Categoría Moodle, recinto, nombre y aforo. |
| `exam` | Curso, categoría del catálogo a través de período y espacio, referencia opcional a grupo y actividad, personal, ventana, modalidad, inscripción y revisión. |
| `seat` | Inscripción operativa manual, única por convocatoria y participante. |
| `mark` | Último estado de asistencia, nota y actor por convocatoria y participante. |
| `case` | Incidencia, severidad, supervisor y participante opcional. |
| `audit` | Actores, sujetos, motivos y cambios de estado. |
| `queue` | Un aviso por tipo y revisión, estado, cursor, reintentos y hora de próximo intento. |

Las operaciones de reserva se serializan con Moodle Lock API y se confirman mediante transacciones DML delegadas. Se compara la revisión del formulario con la almacenada. El identificador de solicitud evita repetir el alta de la misma convocatoria. El aforo mide la lista elegible al guardar el examen o agregar una inscripción manual. La modalidad de matrículas activas recalcula la lista consultando Moodle, por lo que futuras altas externas al plugin pueden cambiar la ocupación.

## Privacidad y retención

Las referencias a cuentas, la asistencia, las incidencias y la auditoría se tratan en el contexto de curso. El proveedor exporta datos del sujeto autorizado y desvincula o elimina datos tras una decisión aprobada en Moodle. El almacén de convocatorias conserva el registro estructural sin las referencias eliminadas. Al borrar un curso, se eliminan también las convocatorias y sus registros propios. La retención general se aplica mediante el flujo autorizado de Moodle Privacy API y la política institucional; esta versión no incluye una purga independiente por antigüedad. [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy)

Los avisos contienen la fecha de convocatoria y un enlace de Moodle. Canal, preferencia y retención de mensajes son administrados por Moodle. La cola omite revisiones antiguas y conserva el cursor después de cada aceptación. Una interrupción inmediatamente después de una aceptación puede repetir ese aviso en el siguiente intento. Los metadatos de intentos, estados de entregas y notas se consultan bajo demanda y se incluyen únicamente en las vistas o descargas autorizadas. No se envían a SkillUps Cloud.

## Dependencias y edición

Se utilizan componentes nativos de Moodle y capacidades nativas de PHP. No se distribuyen bibliotecas de terceros. La edición Community contiene todos los flujos documentados. Esta versión no incluye una edición premium ni un servicio de licencias.
