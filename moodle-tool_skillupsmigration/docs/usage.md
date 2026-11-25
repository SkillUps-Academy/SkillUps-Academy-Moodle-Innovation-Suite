# Manual de migración

## Inventariar y planificar

En destino, abre **SkillUps Course Migration** desde Administración del sitio. Comprueba el origen registrado. La navegación muestra ID de cursos, categorías y, dentro de cada curso, los ID de secciones y actividades. Crea una migración y elige la categoría de destino. Cada línea contiene `id_origen,nombre_corto_destino`. El lote admite hasta diez cursos y una categoría de destino. Usa lotes separados para distribuir cursos en distintas categorías.

Para un único curso puedes limitar secciones y actividades con sus ID de origen separados por comas. Una actividad solicitada cuya sección quedó fuera del plan produce un error. Al omitir estos filtros se considera el curso completo. Selecciona archivos, grupos, fechas y ajustes compatibles.

El mapeo opcional usa una línea por cuenta con `usuario_origen,usuario_destino,rol_origen,rol_destino`. Usa los nombres cortos de roles. Origen comprueba que la cuenta esté matriculada y tenga el rol de curso indicado. Destino solo admite cuentas activas existentes y roles de curso asignables del arquetipo estudiante, docente o docente editor. El proceso aplica la matrícula después de verificar la restauración.

## Simular y restaurar

Pulsa **Ejecutar simulación** y confirma. Cron solicita el MBZ nativo al origen, lo transfiere, verifica su huella y muestra los resultados. La simulación prepara archivos temporales sin crear cursos de destino.

Revisa las incidencias. Para editar selecciones o corregir un cambio de inventario, crea un plan nuevo. El reintento de preparación se ofrece para filas bloqueadas sin curso de destino y conserva la selección anterior. El lote admite confirmación cuando todos sus cursos pendientes tienen un MBZ verificado.

Pulsa **Confirmar restauración**. Moodle reserva un curso oculto nuevo por fila, ejecuta su precomprobación nativa y aplica Restore. El informe compara la estructura, los grupos y las fechas seleccionadas. Conserva los cursos ocultos para la revisión del responsable. La publicación posterior se gestiona en el curso de Moodle.

## Interrupciones y diferencias

El trabajo previo al inicio de Restore admite reintentos acotados. Si una tarea se interrumpe dentro de la ejecución nativa, la fila se marca para revisión manual y conserva el enlace al curso oculto. La cancelación detiene trabajo que todavía no ha empezado y conserva los destinos ya creados. El historial registra acciones y estados con identificadores internos.
