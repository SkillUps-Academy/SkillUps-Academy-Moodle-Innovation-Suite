# Seguridad y privacidad

Reporta fallos en privado mediante el canal de seguridad del repositorio. Usa datos sintéticos para reproducir el hallazgo.

- Las operaciones de escritura requieren sesión Moodle, capacidad específica, ámbito de curso y módulo, grupo permitido y validación de `sesskey`. La matrícula activa y el grupo también se vuelven a comprobar en el registro del estudiante.
- El código de acceso se guarda como hash de contraseña. El QR contiene una firma temporal específica de sesión y revisión. El contador de intentos se limita a la pareja sesión-estudiante. Las credenciales de sesión se retiran al cancelar o cerrar.
- Los archivos se almacenan en File API dentro de la actividad. Se validan tamaño, cantidad, extensión y firma de formato. La descarga requiere ser el solicitante elegible o un revisor autorizado para el grupo. Las evidencias se entregan como archivos adjuntos.
- La Privacy API descubre, exporta y elimina la información propia del componente por usuario y contexto de actividad. Los datos de matrícula y las calificaciones pertenecen a los subsistemas nativos. Los avisos contienen texto genérico. Los procesadores configurados y la retención global de logs se administran en Moodle.
- Los resultados y justificaciones se conservan según la política institucional y el ciclo de vida de la actividad. Los contadores temporales caducan y se limpian mediante la tarea programada. Las tablas de auditoría guardan operaciones e identificadores acotados. Los archivos descargados y copias quedan bajo custodia de la institución.
