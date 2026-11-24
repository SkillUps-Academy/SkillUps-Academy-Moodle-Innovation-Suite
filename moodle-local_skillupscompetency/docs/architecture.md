# Arquitectura de SkillUps Competency Manager

Las entidades que Moodle ya mantiene siguen en Core. Los marcos representan áreas, las competencias raíz pueden representar dominios y sus descendientes forman la jerarquía. Moodle conserva códigos, descripciones, escalas, asociaciones a cursos y actividades y calificaciones vigentes. El catálogo enlaza a los editores y al intercambio CSV nativos. Los indicadores de logro complementan la escala de cada competencia.

El plugin incorpora programas delimitados por categoría Moodle, sus cursos, períodos y objetivos por competencia. Una evaluación es una propuesta con trazabilidad, evidencia opcional y un estado de revisión. La aprobación autorizada de un coordinador llama la API nativa de calificación de competencias. El flujo no escribe tablas Core ni modifica notas del libro de calificaciones. Las rúbricas activas de tareas Moodle sirven de origen para la evaluación por criterios y se conserva el resultado de la selección como evidencia de la propuesta.

El panel consulta matrículas y grupos nativos, muestra nivel actual por curso y objetivo del período, y conserva evaluaciones del plugin para seguimiento histórico. Los informes institucionales recorren los cursos autorizados del programa. La Privacy API y la File API emplean contexto de curso para los datos personales.

## Modelo de datos

Las extensiones usan el prefijo `local_skillupscompetency_`.

| Tabla | Datos y controles |
| --- | --- |
| `prog` | Programa, código único en categoría, nombre y descripción |
| `map` | Relación única entre programa y curso |
| `term` | Período con nombre único por programa y fechas inclusivas |
| `goal` | Competencia nativa, escala, nivel esperado y marca transversal por período |
| `ind` | Texto de indicadores por competencia, escala y nivel |
| `assess` | Estudiante, autor, revisor, alcance, nivel base y propuesto, decisión, fechas y evidencia de rúbrica. Identificador de propuesta único |

Las evaluaciones pasan de pendiente a aprobada o rechazada. Una aprobación obsoleta se bloquea. La observación, la retroalimentación y las evidencias se mantienen en el alcance del curso. Solo la aprobación envía una calificación a `core_competency\api::grade_competency_in_course`. Moodle aplica la opción del curso que determina si la calificación se propaga al plan de aprendizaje.

El informe cuenta una fila por estudiante, curso y competencia vinculada. Los objetivos sin vínculo aparecen en la matriz con conteo cero. La brecha se calcula como `max(0, nivel esperado - nivel vigente)` en posiciones de la misma escala. Una competencia sin evaluación mantiene registro pendiente, sin brecha numérica. Si el identificador de escala cambia, el objetivo requiere revisión explícita. Seleccionar un período histórico recupera sus metas y compara contra el nivel vigente. La historia registra las revisiones realizadas en SkillUps con sus fechas, sin reconstruir el estado nativo en una fecha anterior.

La rúbrica toma la asignación de Moodle como referencia. Normaliza la suma de puntuaciones entre el mínimo y el máximo de los criterios y la lleva a la posición más cercana de la escala. La definición y las calificaciones de la tarea siguen en Moodle. La evaluación conserva los criterios, niveles elegidos y puntos como evidencia. Esta versión integra las rúbricas de `mod_assign`.

- [API de competencias en Moodle 5.0](https://github.com/moodle/moodle/blob/MOODLE_500_STABLE/competency/classes/api.php)
- [API de calificación avanzada en Moodle 5.0](https://github.com/moodle/moodle/blob/MOODLE_500_STABLE/grade/grading/lib.php)
- [File API](https://moodledev.io/docs/5.3/apis/subsystems/files)
- [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy)

## Plan de verificación

1. Comprobar catálogo, escalas, asociaciones a cursos y módulos con Moodle 5.0 y 5.3.
2. Verificar autoevaluación, solicitud docente, revisión y calificación nativa con matriz sintética.
3. Probar lectura y exportación con cuentas de cursos y grupos distintos, evidencia privada y rúbrica real de una tarea.
4. Validar el instalador, registrar resultados y publicar el código y el ZIP.
