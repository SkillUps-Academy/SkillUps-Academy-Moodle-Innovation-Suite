# Compatibilidad de contenido

El mínimo declarado es Moodle 5.0. El destino debe tener una versión de Moodle igual o posterior al origen y versiones compatibles de los módulos seleccionados. Restore realiza una precomprobación adicional sobre cada destino. Los avisos o errores nativos bloquean la fila antes de aplicar el plan de restauración. Por ejemplo, un rol del respaldo sin equivalente asignable en Moodle se informa con el mensaje de Restore. Los mapeos explícitos del plan se usan para las matrículas locales posteriores.

| Contenido | Comportamiento |
|---|---|
| Cursos y categorías | Crea cursos ocultos nuevos en categorías existentes. El operador elige una categoría por lote. |
| Secciones y actividades | Selecciona todo el curso o usa ID explícitos para una fila. Compara el inventario con el contenido del MBZ. |
| Recursos y archivos | Usa archivos del MBZ. Verifica las huellas del paquete y sus archivos. Las referencias de repositorios externos que requieran el sistema de origen se tratan como incompatibilidad. |
| Preguntas | Usa respaldo y restauración nativos. Se verifican los tipos de pregunta que aparecen en el archivo y las dependencias que Restore pueda resolver. Revisa los módulos de banco necesarios para las actividades del plan. |
| Fechas y ajustes | Conserva las fechas del curso si se seleccionan. Moodle aplica su propia lógica de restauración de fechas de actividades. Los ajustes seleccionables comprenden finalización y modo de grupos. |
| Grupos | Conserva las definiciones nativas cuando la opción está activa. |
| Cuentas y roles | Aplica solo las asignaciones manuales explícitas a cuentas locales existentes y roles asignables del curso destino. |

Los respaldos se generan con usuarios excluidos. La herramienta traslada contenido de curso y configuraciones seleccionadas. Las contraseñas, registros de actividad, entregas, notas históricas, matrículas masivas, membresías de grupo, permisos de sitio y ajustes externos quedan fuera de este flujo. Los bloques, calendarios, filtros, insignias y tipos de configuración adicionales también se excluyen por política de respaldo. Utiliza el informe de Moodle y los procesos de gobernanza institucional para casos que requieran otra estrategia.

El diseño no intenta sustituir las migraciones de versión en sitio ni utilizar APIs inexistentes para disparar Backup/Restore en instalaciones sin el componente de origen. [Backup API](https://moodledev.io/docs/5.3/apis/subsystems/backup) · [Restore API](https://moodledev.io/docs/5.0/apis/subsystems/backup/restore) · [External Services](https://moodledev.io/docs/5.3/apis/subsystems/external)
