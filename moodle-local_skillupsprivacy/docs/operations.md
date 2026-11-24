# Gestión de solicitudes y conservación

## Bandejas y decisiones

- Usa **Registro de casos** para registrar un caso independiente, filtrar los casos asignados o vencidos y consultar el historial.
- Usa **Solicitudes de Moodle → Registrar seguimiento** para vincular una exportación o eliminación ya registrada. El vínculo verifica tipo y titular y admite un solo expediente. Los estados interno y nativo tienen ciclos independientes.
- El ID del titular corresponde al usuario de Moodle. El ID del contexto es `0` para el sitio o el identificador de un contexto de categoría, curso o del mismo titular. El responsable inicial es el operador si se deja vacío.
- **Acceso** representa la revisión del derecho de consulta. **Exportación** identifica la entrega de datos por un sistema autorizado. **Eliminación** y **Anonimización** representan solicitudes de supresión o desidentificación, respectivamente.
- Abierto pasa a En revisión. El operador con capacidad de decisión puede aprobar la remisión o rechazar. Las decisiones admiten reapertura antes del cierre y requieren un motivo breve. Si el caso aprobado tiene solicitud nativa, el cierre exige que Moodle registre un estado terminal coherente con el tipo. En casos independientes, el operador documenta la confirmación del sistema responsable antes de cerrar.

## Conservación

Consulta la finalidad efectiva de Moodle en **Política de retención**. Un contexto eliminado o protegido bloquea liberar retención o aprobar una remisión destructiva. Una reserva posterior devuelve un caso destructivo aprobado a revisión. Un propósito sin protección o ausente en Moodle exige igualmente una decisión humana y la comprobación de otros contextos y sistemas institucionales. Conserva registros académicos, financieros o sujetos a otras obligaciones conforme al calendario aprobado por la institución. Anota la decisión en términos operativos, sin introducir datos académicos, financieros, secretos, documentos ni información identificativa libre en los motivos.

La aprobación del expediente es interna. El operador usa la herramienta oficial de Moodle o el sistema responsable para cualquier exportación, eliminación o anonimización de datos originales. Los plazos del plugin se configuran como objetivos operativos. Las declaraciones de extensiones y los informes se utilizan como evidencias técnicas para una revisión de cumplimiento a cargo de la institución.

## Seguimiento e informes

Las tarjetas cuentan casos activos, vencidos y con retención pendiente de resolución por reserva. **Informes** cruza las cuatro clases de solicitud con sus estados locales. Excluye expedientes desidentificados. La auditoría de accesos incluye consultas a expedientes, inventario, políticas, informes y descargas. El detalle incluye las transiciones y recordatorios. El CSV estándar incluye ID de expediente, categoría, estado, retención, fechas UTC y estado nativo numérico. Los IDs del titular y responsable, el ID nativo y los motivos se mantienen fuera del archivo. La institución controla el acceso y conservación de los archivos descargados.

Las alertas pueden informar antes y después del plazo. El mensaje contiene un enlace al registro y texto genérico. Si una entrega falla o queda incierta, revisa Moodle Messaging y el historial. La tarea conserva la reclamación de entrega para no enviar duplicados de manera automática.

[Captura de un caso con retención](screenshots/case.png) · [Captura del inventario](screenshots/inventory.png)
