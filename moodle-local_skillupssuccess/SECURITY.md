# Seguridad y privacidad

El seguimiento empieza al activar un curso. Moodle valida contexto, permiso y matrícula. Los paneles identificados requieren visibilidad de todos los grupos y acceso nativo a notas, registros y tareas visibles. El tutor ve únicamente sus casos asignados y el gestor con capacidad específica ve los casos autorizados del curso. Las muestras para evaluación independiente se muestran por separado. Las mutaciones web requieren POST y sesskey. Las asignaciones e intervenciones validan la revisión esperada bajo un bloqueo de curso.

El plugin almacena cinco señales académicas, resultados derivados, asignaciones, notas de apoyo y muestras prospectivas opcionales. No copia trabajos, eventos crudos, IP ni identificadores demográficos. El análisis conserva fechas, versiones y configuración de reglas, y procedencia del artefacto estadístico. Los eventos de auditoría registran actor, contexto, operación y referencia técnica, sin señales ni notas. Moodle administra la retención de sus registros de auditoría.

Los informes omiten identificadores y notas de personas. Los conteos se publican en bandas y se ocultan celdas y tasas con soporte insuficiente. Las agrupaciones se limitan a curso y programa con permisos vigentes. El acceso sigue restringido debido al riesgo residual de inferencias al cruzar agregados externos. Una licencia opcional permite exportar el agregado con los mismos controles.

Moodle Privacy API permite exportar y eliminar datos del plugin. La eliminación de un curso borra su política y derivados. La eliminación de un estudiante borra sus observaciones, casos e intervenciones. Al eliminar un miembro del personal se retiran atribuciones, notas de su autoría y asignaciones. Cron elimina las observaciones y muestras vencidas, y elimina los casos resueltos o sin observación reciente cuando superan la retención.

Los responsables del sitio deben definir la base de tratamiento y el acceso antes de activar la recolección o el estudio. Mantén las notas limitadas a información académica necesaria. El plugin realiza inferencia local con un artefacto configurado por el sitio y conserva el control de decisiones académicas en Moodle y su personal autorizado.

[Informar una vulnerabilidad](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/security)
