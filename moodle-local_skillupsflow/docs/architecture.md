# SkillUps Flow Automation

## Diseño y orden de implementación

El componente solicitado `local_skillupsflow` tiene ámbito de sitio, por lo que se integra como plugin local con páginas administrativas, observadores registrados y tareas Moodle. La instalación XAMPP de referencia contiene Moodle 5.0.7 y PHP 8.2.12 sin una configuración activa. El objetivo principal es Moodle 5.3 LTS. Los contenedores aislados se usan para las pruebas sobre bases soportadas. El componente estaba libre en la instalación local y la búsqueda pública no encontró una coincidencia. Moodle Marketplace debe confirmar la disponibilidad al registrar la ficha.

Workflow block, Automat(e) y Autoflow son referencias de producto. El editor de SkillUps usa tarjetas, conexiones y formularios propios sobre el tema Moodle. Reutiliza las APIs de eventos, Lock, DML, tareas, capacidades, notificaciones, matrículas y privacidad.

1. Validar una definición con un disparador y un grafo dirigido acíclico, acotado y completamente alcanzable.
2. Guardar revisiones de solo lectura y publicar una por flujo. Las ejecuciones quedan vinculadas a su revisión y cuenta ejecutora.
3. Admitir ejecuciones con claves únicas. Usar bloqueos Moodle para admisión y ejecución, un trabajo ad hoc por paso y un reconciliador programado.
4. Validar las capacidades del ejecutor en cada paso y las capacidades nativas sobre los objetivos de cada acción.
5. Ejecutar cambios Moodle y transición de estado en una misma transacción cuando el efecto es transaccional. Las notificaciones guardan su intención de envío antes de llamar al canal de Moodle. Un resultado ambiguo queda visible para revisión y nunca se reenvía automáticamente.
6. Añadir constructor, historial, decisiones humanas, plantillas JSON, licencias opcionales y documentación. Probar recuperación, concurrencia, permisos y aislamiento de entornos antes de empaquetar.

Referencias: [Task API 5.3](https://moodledev.io/docs/5.3/apis/subsystems/task), [Lock API](https://moodledev.io/docs/5.0/apis/core/lock), [Autoflow](https://marketplace.moodle.com/plugins/tool_autoflow) y [Workflow block](https://marketplace.moodle.com/plugins/398).
