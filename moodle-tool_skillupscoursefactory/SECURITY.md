# Seguridad y privacidad

Notifica problemas por un canal privado al equipo administrador del repositorio. Incluye la versión, los permisos y pasos reproducibles con datos sintéticos.

La vista previa y las acciones requieren sesión y capacidad de sistema. Se revalidan los permisos de origen, categoría, visibilidad y matriculación con la identidad del solicitante. Las operaciones ajenas requieren la capacidad de gestión. Confirmar, cancelar, reintentar y exportar mediante el panel requiere una solicitud POST con clave de sesión.

Moodle gestiona las copias temporales de backup y su limpieza. El importador acepta tablas acotadas y valores literales. La herramienta elimina su copia temporal y el borrador del archivo al procesar la carga. El historial conserva el solicitante, la petición y los IDs de empleados para matriculación y recuperación. El archivo exportado omite los IDs de empleados y neutraliza valores que una hoja de cálculo pudiera interpretar como fórmulas.

Privacy API exporta y elimina atribuciones y referencias personales de Course Factory. Las solicitudes de matriculación aún pendientes que pierden una referencia por una solicitud de privacidad se cancelan. Las matrículas ejecutadas quedan bajo el subsistema de matriculación de Moodle. Los registros de auditoría se gestionan mediante la retención del almacén de logs del sitio.
