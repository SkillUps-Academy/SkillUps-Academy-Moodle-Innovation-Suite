# Seguridad y privacidad

Los informes, vistas históricas, exportaciones y tareas validan el ámbito antes de leer capturas. El análisis de un curso completo requiere capacidad de ver todos sus grupos y permisos nativos para notas y finalización. Los agregados de categoría incluyen los cursos que superan ambos controles y los accesos correspondientes de categoría. Programas, períodos comparados, ID de captura y filtros nunca amplían la autorización. El campo de programa respeta la visibilidad nativa del dato.

Las capturas almacenan valores aditivos por curso, generación y período. No incluyen IDs de estudiantes, notas individuales ni eventos sin agregar. La supresión por denominador reduce el acceso a cohortes pequeñas. Usuarios autorizados con otros informes y filtros podrían inferir diferencias entre agregados. Las matrículas y los conteos docentes se mantienen disponibles para los permisos definidos.

El almacenamiento del plugin contiene títulos y filtros de informes privados y sus programaciones ligadas al propietario. Moodle Privacy API exporta y elimina esos registros. Se registra la consulta de paneles, la exportación, las operaciones de configuración y las ejecuciones programadas mediante eventos que incluyen fecha, actor, contexto, acción e ID de período. Los registros de Moodle tienen su propia retención. Los identificadores de cursos, períodos, programas y filtros escritos por docentes se consideran datos sujetos a las políticas de la institución.

CSV y XLSX neutralizan prefijos de fórmulas y aplican autorización de nuevo. PDF usa texto escapado. Las exportaciones se generan mediante Moodle Dataformat y el plugin no almacena copias de descargas. Las notificaciones se envían exclusivamente al propietario y contienen un enlace con autorización vigente, sin resultados ni adjuntos.

[Informar una vulnerabilidad](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/security)
