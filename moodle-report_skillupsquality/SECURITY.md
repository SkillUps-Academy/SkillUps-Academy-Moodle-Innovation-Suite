# Seguridad y privacidad

Para comunicar un problema de seguridad, usa un canal privado del equipo administrador del repositorio. Indica versión, permisos y pasos mínimos de reproducción con datos sintéticos.

El informe requiere la capacidad `report/skillupsquality:view` y acceso de edición integral mediante `moodle/course:update` en el contexto del curso auditado. Ejecutar y gestionar planes requiere capacidades adicionales. La autorización de categoría habilita la elección de cursos dentro de su ámbito y cada curso se autoriza por separado antes de encolar. La tarea vuelve a verificar el permiso y la cuenta del solicitante. Las escrituras web y la exportación validan clave de sesión. Las referencias de resultados, hallazgos y planes se resuelven por el curso propietario.

Las reglas guardan identificadores del curso, actividad, sección o ítem, fechas de configuración, indicadores y recuentos. Las exportaciones omiten enlaces introducidos, textos de editores, archivos, autores y notas de actuación. Las comprobaciones de enlaces usan sintaxis y metadatos locales, sin tráfico saliente. El motor lee definiciones de calificación y rúbricas de actividades. Las notas de alumnos, entregas y rúbricas cumplimentadas quedan fuera de los datos consultados.

Moodle Privacy API exporta solicitudes y planes atribuibles al operador. La eliminación anonimiza el identificador y borra sus notas en los planes. Los eventos de Moodle siguen la retención del almacén de registros configurado en el sitio. Los planes permanecen vinculados al curso para mantener el seguimiento aunque se eliminen revisiones antiguas. Evita incluir datos personales en notas de corrección.
