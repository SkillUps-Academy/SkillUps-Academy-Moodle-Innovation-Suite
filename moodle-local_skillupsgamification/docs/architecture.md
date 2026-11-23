# Arquitectura

El plugin local añade una vista de progreso y administración a cada curso. Se activa por curso. Usa Course Completion y Activity Completion como fuentes nativas y grupos Moodle para equipos. Las operaciones tienen alcance de curso y los resultados personales se consultan solo para la cuenta actual.

Los observadores nativos ponen una referencia mínima en una tarea ad hoc. Al ejecutarla se comprueban la matrícula vigente, la capacidad de acumular, las exclusiones, la fuente persistida, la ventana y los límites. Se usa un bloqueo por curso y una clave única que impide repetir el mismo logro aunque cambie el registro físico de finalización o se repita la tarea. Los movimientos son inmutables. Una finalización manual, anulada o que Moodle marca como no aprobada no genera puntos de actividad.

El panel calcula puntos ganados, saldo de recompensas y XP. Las metas individuales y por equipo usan únicamente movimientos verificados. Un equipo es un grupo de un agrupamiento Moodle. El equipo asociado se registra al otorgar y las metas de equipo requieren aportación y pertenencia actual. Las recompensas tienen coste, disponibilidad y entrega administrativa con una única solicitud por persona y recompensa. Las insignias se emiten dentro del plugin.

La clasificación se habilita en cada curso y requiere consentimiento individual. Usa alias, exige acceso de curso y respeta grupos separados. Learning Path es opcional y solo se integra mediante un proveedor local verificado. La API de privacidad gestiona el historial de logros y preferencias.

Fuentes oficiales consultadas. [Eventos Moodle](https://phpdoc.moodledev.io/5.0/d0/df8/namespacecore_1_1event.html), [Activity Completion](https://moodledev.io/docs/5.1/apis/core/activitycompletion), [Groups API](https://moodledev.io/docs/5.3/apis/subsystems/group) y [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy).
