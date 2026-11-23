# Arquitectura

Cada itinerario se administra desde un curso de acceso. Los nodos de su grafo apuntan a cursos y actividades Moodle de cualquier categoría para los que el diseñador tiene permiso. El motor valida todo el grafo al guardar y publicar, aplica un orden topológico y bloquea las conexiones circulares. Las versiones publicadas son inmutables. El estudiante queda vinculado a una versión al iniciar y el traslado a la versión vigente requiere confirmación individual de un gestor con alcance sobre ese estudiante.

Cada nodo admite prerrequisitos AND u OR, un indicador obligatorio y condiciones simultáneas de finalización, nota mínima y competencia lograda. El motor comprueba matrícula activa, cursos ocultos, grupos y disponibilidad de actividad antes de leer la evidencia. Lee únicamente la finalización y los resultados persistidos en Moodle. La configuración nativa conserva su propio ciclo de vida.

El progreso guarda estados y transiciones por versión, sin copiar calificaciones ni resultados académicos individuales. La revisión manual y el cron aplican el mismo evaluador. Los informes filtran por curso de entrada y grupos y muestran estados de acceso y avance. La integración opcional entrega pruebas vigentes de finalización a proveedores locales autorizados.

Fuentes oficiales. [Access API](https://moodledev.io/docs/5.3/apis/subsystems/access), [Enrolment API](https://moodledev.io/docs/5.2/apis/subsystems/enrol), [Activity Completion](https://moodledev.io/docs/5.1/apis/core/activitycompletion) y [Competency API](https://phpdoc.moodledev.io/5.0/df/d98/group__core__competency.html).
