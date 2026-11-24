# Seguridad y privacidad

La administración de catálogos emplea una capacidad de sistema. La planificación, publicación, consulta institucional y auditoría requieren la capacidad correspondiente en la categoría Moodle del recinto y una habilitación específica en ese recinto. Una sesión publicada se limita a su audiencia autorizada. Un planificador que choca con una reserva ajena recibe el tipo de conflicto sin los identificadores de la reserva.

Los formularios de escritura validan la sesión Moodle. Las revisiones obsoletas se rechazan y las reservas concurrentes se serializan mediante Moodle Lock API. La recurrencia guarda el lote en una sola transacción. El enlace del aula virtual se entrega a la audiencia en el detalle autenticado y se omite en el ICS y el calendario nativo.

La Privacy API declara asignaciones docentes, habilitaciones, actores y motivos del historial, cola de avisos y las integraciones con calendario y mensajería. La eliminación de datos de un docente vuelve a borrador las sesiones futuras publicadas afectadas, de modo que puedan reasignarse. La eliminación del curso en Moodle activa la limpieza de los registros propios del planificador. Evita almacenar datos de estudiantes en títulos o motivos de cambios. Los equipos institucionales deben definir la conservación de las evidencias de auditoría mediante sus políticas de Moodle.

Comunica problemas mediante el [canal privado de seguridad del repositorio](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/security/advisories/new).
