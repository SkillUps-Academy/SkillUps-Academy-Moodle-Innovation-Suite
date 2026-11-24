# Seguridad y privacidad

El informe valida el curso, la sección y el módulo con los permisos actuales de Moodle. La cola almacena la identidad autorizada y revalida su acceso antes de recopilar fuentes. Cada informe y descarga vuelve a comprobar el acceso. Las operaciones de escritura usan una clave de sesión y el bloqueo del curso.

El motor maneja fuentes de autor. Las entregas de estudiantes, foros, mensajes, bancos de preguntas, páginas externas y archivos enlazados en repositorios externos quedan fuera del análisis. HTML y XML se procesan localmente sin ejecución de código, y los paquetes Word se leen sin extraer archivos.

Se almacenan identificadores Moodle, un nombre de fuente, selecciones estructurales, huellas y resultados. Los operadores pueden adjuntar archivos a una revisión. La Privacy API exporta referencias autorizadas, anonimiza actores y responsables y retira las notas y evidencias aportadas por el usuario en el ámbito aprobado. Al eliminar un curso, Moodle elimina su contexto y el observador limpia los registros propios. La desinstalación borra las evidencias mediante File API.

Reporta problemas mediante el [canal de seguridad de la suite](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/security/advisories/new).
