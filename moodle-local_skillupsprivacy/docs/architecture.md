# Arquitectura y datos

El plugin local añade una capa de gobierno sobre `tool_dataprivacy`. Lee cuatro campos de la solicitud nativa (referencia, titular, tipo y estado) y una fecha para su bandeja. Consulta finalidades efectivas a través de la API de Moodle. La sincronización actualiza el estado observado e ignora referencias desaparecidas o que ya no coincidan. Se conservan las decisiones, solicitudes y archivos nativos en Moodle. El inventario utiliza el registro de declaraciones de proveedores de la Privacy API.

| Tabla propia | Datos | Conservación y acceso |
| --- | --- | --- |
| `local_skillupsprivacy_case` | Titular, responsables, decisión, contexto, plazo y referencia nativa | Disponible a operadores autorizados durante el seguimiento. Se desidentifica si una operación de privacidad de sistema autorizada incluye al titular. |
| `local_skillupsprivacy_audit` | Actor, acción, marcas de tiempo, cambio y motivo breve en decisiones | Las consultas se purgan por tarea según la configuración. La evidencia vinculada al expediente sigue su ciclo de privacidad. |
| `local_skillupsprivacy_notice` | Destinatario y estado de entrega para una combinación de expediente, plazo y nivel | Clave única de deduplicación. La operación nativa de privacidad retira los datos del destinatario o el expediente según la autorización. |

Cada cambio de operador lleva una revisión de formulario. La persistencia verifica el estado actual bajo un bloqueo Moodle y confirma el cambio junto con la auditoría en una transacción. La reconciliación verifica el vínculo antes de actualizar la instantánea y mantiene la revisión del formulario. Cada ejecución toma hasta 50 casos para conciliación y 50 avisos por nivel. Una entrega que queda en curso tras una hora cambia a incierta. Los accesos privilegiados y las decisiones también emiten eventos Moodle con metadatos mínimos. El período de conservación del almacén de logs estándar se configura por separado en Moodle.

El proveedor `classes/privacy/provider.php` declara las tres tablas y Moodle Messaging. Para la Privacy API, todos los registros propios pertenecen al contexto de sistema. El contexto de revisión de un expediente se usa para consultar una finalidad, según la decisión de conservación aplicable. La exportación aprobada usa rutas por expediente, actividad propia y avisos. Omite referencias nativas, motivos que puedan contener información de otros y los identificadores de otros usuarios. Cuando el solicitante actuó como operador sobre datos de otro titular, la exportación presenta su función y la fecha.

La operación de privacidad aprobada elimina historial y avisos del expediente del titular, limpia sus identificadores, contexto particular y referencia nativa, y excluye su registro desidentificado de las vistas y el CSV. Quita también las referencias a esa persona como responsable o actor en otros casos, incluidas sus justificaciones. El registro residual conserva clase, estado y fechas operativas. Aplica una política aprobada para copias de seguridad y descargas, que quedan bajo custodia de la institución. Los registros de origen académicos o financieros quedan en sus sistemas de origen. La integración de este plugin con la Privacy API actúa sobre tablas propias.

La edición Community no realiza conexiones externas. Las licencias opcionales se verifican en la instalación. Moodle Messaging puede entregar avisos genéricos por sus canales habilitados. El ZIP no distribuye bibliotecas de terceros.

[Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy) · [Moodle Data registry](https://docs.moodle.org/503/en/admin/tool/dataprivacy/dataregistry)
