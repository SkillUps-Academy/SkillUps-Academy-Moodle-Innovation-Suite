# Operación de Gamification Pro

## Reglas de puntos

- **Actividad verificada.** Selecciona cero para cualquier actividad elegible o el ID de un módulo para una regla específica. Moodle debe tener una finalización automática vigente, exitosa y sin intervención de override. Las finalizaciones manuales, con resultado no aprobado o anuladas antes de procesar la tarea quedan excluidas.
- **Curso verificado.** Usa el ID de fuente cero. La tarea verifica la finalización oficial del curso para esa persona.
- **Learning Path.** Usa el ID del hito declarado por el proveedor local y habilita su integración en el curso.

La evidencia debe corresponder al curso y a una persona matriculada activamente con permiso de acumular. Una regla se evalúa en el procesamiento y su ventana significa inicio incluido y fin excluido. Una regla específica tiene prioridad sobre una general. Ante empate gana la regla activa de menor ID. Los topes por regla cuentan premios por día UTC o en total. El tope diario del curso suma puntos de todas las reglas sin fraccionar el premio.

Solo se otorga una vez por curso, persona, tipo y fuente. Repetir el evento, cambiar la regla o recrear el registro físico de finalización no duplica un premio. Los puntos ya concedidos permanecen en el historial tras modificar la configuración o el estado de finalización. Al activar el curso empieza un nuevo período elegible para eventos nuevos y no se recorren finalizaciones previas. La comprobación por Learning Path usa su fecha persistida.

## Metas, equipos y recompensas

Una misión, insignia o desafío puede contar puntos ganados, XP o actividades verificadas en el período indicado. Las insignias internas admiten nombre, descripción y emblema seleccionable. Los eventos temporales requieren comienzo y fin. Los desafíos y eventos de equipo usan el grupo registrado al conceder los puntos. Para reclamar, el estudiante debe pertenecer en ese momento al equipo, haber aportado a la métrica y el equipo debe tener al menos dos contribuyentes. Cada logro se reclama una vez y durante su vigencia.

El nivel se calcula como `1 + parte_entera(XP / XP por nivel)`. Los puntos ganados y XP se conservan por separado del saldo. Un canje con coste positivo reserva el saldo y una unidad de inventario, si aplica. La entrega queda pendiente para que un gestor la confirme fuera de cualquier automatización de Moodle. Cambiar los valores del catálogo no modifica la cantidad ya reservada. El historial distingue logros, canjes pendientes y entregas.

## Clasificaciones

El curso primero habilita las clasificaciones y cada estudiante decide si participa. Solo los participantes con consentimiento vigente, matrícula activa y permiso de acumular pueden acceder. La retirada quita de las tablas el alias y sus contribuciones desde ese momento. Las tablas muestran los 100 mejores registros a partir de los puntos y XP del curso. Los grupos separados restringen a los participantes a su propio equipo, salvo permiso Moodle para acceder a todos los grupos. La tabla de equipos conserva únicamente equipos visibles con tres o más miembros vigentes, contribuyentes y con consentimiento.
