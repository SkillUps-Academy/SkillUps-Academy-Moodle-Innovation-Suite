# Arquitectura

El informe usa el contexto de sistema de Moodle. Las capacidades separan consulta, inventario, verificación manual, seguimiento y exportación. El motor lee APIs de roles, permisos efectivos, eventos y configuración. Los únicos datos de seguridad que modifica están en las tablas del plugin.

Los controles devuelven evidencia estructurada y su cobertura. La coordinación usa un lock por sitio y una transacción para guardar instantáneas y cambios de estado. Si se alcanza el límite de un control, los hallazgos previos del control se conservan hasta una ejecución completa. Los estados de revisión quedan separados de su vigencia técnica.

Los observadores conservan metadatos de una lista explícita de eventos. Los cambios de configuración registran el nombre de una opción aprobada y omiten sus valores. Las consultas sensibles y exportaciones dejan trazabilidad. Las notificaciones de Moodle contienen solo un aviso y un enlace al informe protegido.

[Access API](https://moodledev.io/docs/5.3/apis/subsystems/access) · [Events API](https://moodledev.io/docs/5.3/apis/subsystems/events)
