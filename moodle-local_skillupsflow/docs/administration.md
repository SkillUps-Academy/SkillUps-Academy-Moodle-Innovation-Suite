# Instalar y administrar SkillUps Flow Automation

1. Haz una copia de seguridad y ensaya en una versión validada en el [informe de compatibilidad](validation.md).
2. Desde **Administración del sitio → Extensiones → Instalar complementos**, carga el ZIP. Si instalas manualmente, extrae `skillupsflow/` directamente en `local/` dentro de la raíz web de Moodle, con permisos de lectura para el usuario web. En instalaciones con raíz web `public/`, usa `public/local/`.
3. Completa la actualización desde **Notificaciones** o ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle. El paquete incluye los recursos JavaScript compilados. Composer solo se usa para las pruebas de desarrollo de Moodle.
4. Ejecuta el cron habitual de Moodle cada minuto. El reconciliador programa disparadores y recupera ejecuciones pendientes. El detalle del historial muestra la próxima fecha programada y las transiciones registradas.
5. Abre **Administración del sitio → Extensiones → Extensiones locales → SkillUps Flow Automation**.

## Permisos y operaciones

- `local/skillupsflow:manage` permite diseñar, publicar, pausar, lanzar, cancelar y consultar ejecuciones. Está reservado a administradores de sitio por defecto.
- `local/skillupsflow:approve` permite resolver aprobaciones publicadas por otra cuenta. Se puede asignar mediante un rol de sistema a revisores autorizados. La página es `/local/skillupsflow/approvals.php`.
- Cada ejecución conserva la revisión y su autor. El motor exige que el autor permanezca activo y conserve `manage`. Antes de ejecutar una acción, exige también la capacidad nativa sobre el usuario o curso. Si el autor cambia de funciones, guarda y activa una nueva revisión con una cuenta autorizada.
- Pausar detiene la admisión y los pasos pendientes. Al volver a activar el flujo, las ejecuciones pendientes retoman su revisión original. Cancelar un trabajo desde el historial detiene sus pasos futuros. Los cambios ya aplicados se conservan.
- El menú de configuración admite un máximo diario UTC por flujo y la conservación de ejecuciones terminales. Los valores iniciales son 250 ejecuciones y 90 días. Se mantienen hasta 100 ejecuciones abiertas por flujo y se serializan sus pasos.

## Acciones admitidas

| Acción | Requisitos |
| --- | --- |
| Activar o suspender cuenta | `moodle/user:update`. Se protegen las cuentas de administrador, el autor y el invitado. |
| Visibilidad del curso | `moodle/course:visibility` en un curso real. |
| Matrícula manual | Una instancia manual activa, `enrol/manual:enrol`, `moodle/role:assign` y un rol que el autor pueda asignar. Se conserva la matrícula manual existente. |
| Notificación | `moodle/site:sendmessage` y un destinatario válido. Se aplica la configuración de canales de Moodle. |

## Licencias y desinstalación

Community incluye el constructor, los disparadores, las acciones, las aprobaciones y la supervisión. Professional y Enterprise pueden habilitar la exportación CSV de estadísticas mediante una [licencia firmada](licensing.md).

Desinstala el plugin desde **Vista general de extensiones**, retira el directorio y aplica la práctica de copias de seguridad de tu institución. Las tablas, tareas y datos del plugin se rigen por el proceso de desinstalación de Moodle. Las acciones ya realizadas sobre Moodle y las notificaciones siguen sus políticas normales.
