# Seguridad y privacidad

Reporta vulnerabilidades de forma privada a los administradores del repositorio con la versión y datos sintéticos.

Las páginas requieren autenticación y capacidad del sistema. Las acciones usan sesskey. Cada consulta autorizada y exportación deja registro en Moodle y en la bitácora del plugin. La evidencia identificable requiere permiso adicional. CSV protege contra fórmulas iniciadas por valores de los registros. El motor de auditoría lee los datos nativos. Las tareas en cola, los eventos de auditoría y las notificaciones usan servicios de Moodle. El análisis se limita a configuraciones, permisos, accesos y eventos de la aplicación.

Los observadores almacenan tipos de eventos, fechas, referencias, contexto y los identificadores de quienes participaron. Los cambios de configuración guardan únicamente nombres permitidos de opciones. La bitácora excluye valores de configuración, contraseñas, contenido de sesiones e IP. Las notificaciones contienen un aviso general y un enlace protegido.

La API de privacidad exporta evidencia del usuario en el contexto de sistema y omite identidades de terceros. La eliminación aprobada retira los hallazgos, el inventario y las referencias de alertas del usuario, y anonimiza referencias de su actividad o autoría. Los datos nativos de Moodle y los mensajes siguen sus propias políticas. Una auditoría posterior puede volver a observar hechos si su fuente permanece en Moodle.
