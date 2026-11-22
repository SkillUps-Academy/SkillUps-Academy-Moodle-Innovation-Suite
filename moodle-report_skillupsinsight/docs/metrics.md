# Diccionario de indicadores

Cada captura corresponde a un período académico con intervalo UTC de inicio inclusivo y fin exclusivo. La interfaz incluye la fecha final seleccionada extendiendo el límite hasta la medianoche siguiente. Los intervalos de eventos y cambios observables terminan, como máximo, al comenzar la generación. La condición de cuenta, de matrícula, de habilitación y los valores vigentes del libro y finalización se consultan durante el lote de cada curso. Un lote largo puede abarcar diferentes horas de observación.

## Poblaciones y lecturas

- **Estudiantes elegibles**. Usuarios distintos en la consulta de matrícula activa de Moodle con `report/skillupsinsight:learner` en el curso. Se excluyen cuentas eliminadas, suspendidas o sin confirmar. La capacidad se concede inicialmente al arquetipo estudiante. Se usa el estado observado de la matrícula, incluidos sus límites de vigencia, y no se reconstruyen bajas eliminadas.
- **Docentes elegibles**. Mismo criterio para `report/skillupsinsight:educator`, inicialmente concedida a los arquetipos docente y docente sin edición. Una cuenta con ambas capacidades puede participar en ambos grupos.
- **Evento válido**. Fila del lector estándar activo, con el mismo curso e intervalo temporal, de origen web o servicio web. Quedan fuera eventos anónimos, sesión de suplantación, cron, CLI y los del propio informe. El actor corresponde al campo `userid`.
- **Muestra del libro y finalización**. Solo estudiantes elegibles. Para nota se lee el ítem numérico total del curso y su nota final vigente. Para finalización se requieren seguimiento habilitado y al menos un criterio nativo.

| Indicador | Fórmula por curso | Fuente | Restricción |
| --- | --- | --- | --- |
| Matrículas estudiantiles activas | Número de estudiantes elegibles en la captura | Enrolment/Access API | Es un stock observado durante la captura. Puede variar en una nueva captura del mismo período. |
| Nuevas matrículas estudiantiles | Estudiantes elegibles cuya fecha más antigua de creación de matrícula conservada en el curso pertenece al período | Matrículas y métodos de matrícula de Moodle | Mide alta registrada, no fecha efectiva de inicio. La baja o eliminación de una matrícula reduce la posibilidad de reconstrucción. |
| Estudiantes con eventos registrados | Estudiantes elegibles distintos con al menos un evento válido | Log estándar | El conteo puede ser parcial por retención, registros desactivados o trabajo fuera de línea. Cada evento acredita una acción y no tiempo de dedicación. |
| Participación estudiantil | 100 × estudiantes elegibles con al menos un evento participativo ÷ estudiantes elegibles en cursos con log habilitado | Eventos estándar de envío de tarea, intento de cuestionario entregado, publicación o discusión de foro y envío de Feedback | Está limitada a `assessable_submitted`, `submission_created`, `attempt_submitted`, `post_created`, `discussion_created` y `response_submitted` de los componentes expresamente enumerados en el código. Es participación observable y puede incluir guardados iniciales según la actividad. |
| Matrículas docentes activas | Número de docentes elegibles en la captura | Enrolment/Access API | Excluye administradores sin matrícula docente. Personas con varios cursos aparecen en cada uno. |
| Docentes con eventos de enseñanza | Docentes elegibles distintos con al menos una acción registrada de calificar, crear o actualizar módulos o publicar en foros | Log estándar | Usa `core:user_graded`, `core:course_module_created`, `core:course_module_updated`, `mod_assign:submission_graded` y publicaciones/discusiones de foro. Quedan fuera trabajo externo y correcciones sin eventos retenidos. |
| Nota media numérica de curso | Suma de 100 × (nota final − mínimo) ÷ (máximo − mínimo), dividida por observaciones válidas | Totales de curso del libro de calificaciones | Excluye escalas, notas nulas y libros pendientes de recálculo. La nota final vigente debe tener su última modificación dentro del intervalo. No reconstruye una nota anterior que fue sobrescrita. |
| Tasa de finalización | 100 × estudiantes elegibles con finalización registrada dentro del período ÷ estudiantes elegibles en cursos con finalización habilitada y criterios | Finalización de cursos | Mide la fecha de finalización registrada. No incluye finalizaciones previas al período. Un curso sin criterios queda fuera del denominador. |

## Agregación, cobertura y periodicidad

- Cron inicia una nueva generación diaria por período no congelado y procesa en lotes de hasta 30 cursos por período. Publica la generación cuando concluye y conserva la publicada anterior durante el trabajo. Un administrador puede poner una actualización en cola, congelar una captura publicada o reabrir el período.
- En categorías, programas e institución los conteos suman pares **persona–curso**. La misma persona en tres cursos cuenta tres veces. Los porcentajes suman numeradores y denominadores y calculan la razón resultante. Por ejemplo, 2 de 2 y 2 de 8 dan 4 de 10, es decir 40 %.
- Las comparaciones restan el valor del período de referencia al valor seleccionado. En porcentajes la variación se expresa en puntos porcentuales. El panel muestra la cobertura de ambos períodos, ya que cursos nuevos, eliminados o reasignados pueden cambiar los conjuntos.
- La clasificación de curso y programa, visibilidad y autorización se evalúan al consultar. Mover un curso cambia su agrupación incluso al consultar capturas antiguas. Las capturas conservan los numeradores y denominadores que se calcularon en su momento.
- Un guion representa datos sin captura, fuente no disponible, denominador nulo o supresión. El valor de matrícula 0 es un cero observado. La supresión mínima por defecto es 5 para celdas de actividad, enseñanza y porcentajes. No se suprimen los conteos de matrícula.
- Cuando Moodle carece de un lector estándar activo, las tres métricas de eventos se marcan como no disponibles. Si el lector existe pero el período es anterior a la retención del log, el conteo puede resultar en cero aun si hubo actividad. Se recomienda conservar los registros del período hasta publicar y congelar su captura.

[Logging de Moodle](https://moodledev.io/docs/5.3/apis/subsystems/logging) · [Finalización de actividades](https://moodledev.io/docs/5.3/apis/core/activitycompletion)
