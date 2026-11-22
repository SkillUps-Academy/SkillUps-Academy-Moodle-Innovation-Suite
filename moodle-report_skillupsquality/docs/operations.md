# Operación del auditor

En **Historial de auditorías del curso**, solicita una revisión. La fila en cola cambia de estado cuando cron ejecuta el trabajo. Abre la revisión para consultar los resultados, elige una auditoría anterior para comparar y usa el filtro por tipo para separar problemas comprobables de recomendaciones. Los valores de evidencia usan el idioma y la zona del usuario. El archivo CSV incluye el ID del curso y de la auditoría, fecha UTC, puntuación y evidencias estructuradas.

## Catálogo

| Regla | Tipo | Gravedad inicial | Evidencia |
| --- | --- | --- | --- |
| `emptycourse` | Comprobable | Alta | Curso sin módulos de contenido ni etiquetas con contenido. |
| `emptycontent` | Comprobable | Media | Página Moodle sin texto visible ni contenido multimedia declarado. |
| `dates` | Comprobable | Alta | Primer par de fechas en orden inverso en el curso, una Tarea o un Cuestionario. Las fechas desactivadas se excluyen. |
| `grade` | Comprobable | Alta | Ítem con calificación numérica cuyo mínimo es igual o superior al máximo. |
| `quizgrade` | Comprobable | Alta | Cuestionario con nota máxima positiva y suma de puntuaciones de preguntas igual o inferior a cero. |
| `rubric` | Comprobable | Alta | Tarea con método de rúbrica activo cuyo formulario de calificación carece de estado listo. |
| `resources` | Comprobable | Media | Un Archivo o Carpeta carece de archivos registrados o tiene archivos locales de cero bytes. |
| `links` | Comprobable | Media | Enlace con sintaxis inválida o esquema ejecutable, o archivo local ausente referenciado como `@@PLUGINFILE@@`. |
| `instructions` | Recomendación | Baja | Tarea o Cuestionario visible con descripción vacía. |
| `hidden` | Recomendación | Baja | Actividad o recurso marcado como oculto. |
| `sections` | Recomendación | Baja | Sección visible, distinta de la general, sin módulos visibles ni resumen con contenido. |
| `completion` | Recomendación | Media | Seguimiento de finalización activo sin criterios de finalización del curso. |

La puntuación comienza en 100 y resta una vez por cada **regla comprobable** con hallazgos. Los pesos son 25 para crítica, 15 para alta, 8 para media y 3 para baja. El mínimo es cero. Cuando solo hay reglas de diseño activas, la puntuación figura sin dato. La comparación automática de hallazgos y puntuación se aplica a revisiones con la misma versión de reglas y política. Las entradas conservan la clasificación y severidad de su revisión.

## Correcciones y seguimiento

Abre un hallazgo para ver su condición, evidencia, objetivo y recomendación. En **Editar plan de corrección**, registra una acción, fecha objetivo opcional y estado. Realiza las correcciones en las pantallas nativas de Moodle. Solicita otra auditoría para actualizar la evidencia en **Planes de corrección**. La tabla diferencia el estado declarado por el operador de lo detectado en la auditoría posterior. La verificación queda pendiente si la regla se desactivó, si un campo excedió la cobertura de enlaces o si falta una auditoría posterior. La ausencia en una revisión describe el resultado de sus comprobaciones y las condiciones cubiertas.

## Cobertura

El catálogo cubre secciones y módulos publicados de Moodle, definiciones de calificación y rúbricas activas de Tarea. Las reglas de fechas comprueban configuración base. Las excepciones de usuarios y grupos, dependencias de plugins de terceros y contenidos externos quedan fuera del alcance. Los adjuntos se verifican por sus metadatos registrados y el análisis de enlaces se limita a los campos de los tipos nativos contemplados y a contenidos inferiores a 8193 caracteres. Las comprobaciones de enlaces carecen de peticiones HTTP y no miden disponibilidad remota. El límite operativo es de 2000 módulos por curso y 50 cursos por solicitud de lote.
