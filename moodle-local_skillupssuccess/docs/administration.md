# Instalación y configuración

1. Verifica el SHA-256 y sube el ZIP desde `releases/skillupssuccess` en **Administración del sitio → Plugins → Instalar plugins**. Por archivos, el destino es `local/skillupssuccess` de la raíz pública de Moodle. Moodle incluye Assignment. La instalación no requiere Composer.
2. Completa Notificaciones o ejecuta `php admin/cli/upgrade.php --non-interactive`. Activa cron. La tarea `local_skillupssuccess\task\scan` procesa cursos habilitados y la tarea `local_skillupssuccess\task\retention` aplica retención.
3. En **Administración del sitio → Plugins → Plugins locales → SkillUps Student Success Predictor**, ajusta retención (180 días por defecto, rango 45–730), tamaño mínimo de celda (10 por defecto, rango 5–100) y nombre corto del campo de programa. Se puede reutilizar el campo visible de curso `insightprogram`.
4. Configura la retención del almacén estándar para cubrir al menos 14 días. Revisa que los totales numéricos del libro y las fechas efectivas de Assignment correspondan al uso previsto.
5. En cada curso autorizado, abre **Reglas y activación del curso**, ajusta umbrales y habilita la recopilación. La activación por curso es explícita. El estudio y el modelo tienen controles separados.

## Roles

El acceso identificado requiere `local/skillupssuccess:view`, acceso actual al curso, todos los grupos, libro de calificaciones, registros y calificación de las actividades Assignment visibles. Crea un rol de tutor matriculado con los permisos nativos necesarios y sin `:manage` para limitar el panel a casos asignados. Con `:manage` se ven y asignan casos autorizados del curso. `:configure` permite editar los umbrales. `:assess` habilita la cola de calidad y conviene asignarla a un equipo independiente. `:aggregate` autoriza los informes por contexto. Limita las asignaciones según las políticas institucionales.

## Modelo opcional

Community puede utilizar inferencia estadística sin una licencia. Valida un artefacto institucional aprobado antes de habilitar la IA estadística del sitio y el control del curso. En `config.php`, `$CFG->local_skillupssuccess_model` recibe un arreglo con `approved` booleano, `target` igual a `independent_support_need_14d`, `version` identificativa, `intercept`, `weights` y `threshold` entre 0 y 1. `weights` usa exclusivamente `inactive`, `overdue`, `grade`, `decline` y `participation`. Intercepto y pesos quedan entre −30 y 30. La escala está fija en la metodología. No se distribuye un artefacto entrenado.

La opción de sitio **Habilitar inferencia estadística aprobada** verifica la especificación en cada lote. Ante deshabilitación o formato inválido, el motor determinístico sigue disponible. La activación se controla además en cada curso. La configuración y versión se pueden rotar junto con un nuevo protocolo validado.

## Licencias opcionales

El panel, casos y métricas web están disponibles en Community. Una licencia Ed25519 Professional o Enterprise con `aggregate_export` habilita el CSV desidentificado de calidad. Usa `$CFG->local_skillupssuccess_entitlement` con `base64(JSON).base64(firma)` y `$CFG->local_skillupssuccess_licensepublickey` con la clave pública Base64. Los campos firmados son `component: "local_skillupssuccess"`, `version: 1`, `id`, `tier`, `issued`, `expires`, `sites` (SHA-256 de las URLs sin barra final) y `features`. La validez máxima es 366 días. Sodium reutiliza la verificación dentro de la solicitud. El panel muestra estado e ID.

Configura `$CFG->local_skillupssuccess_gracedays` de 0 a 30 y `$CFG->local_skillupssuccess_revoked` para revocación offline. Renovación y transferencia se realizan con una nueva licencia del emisor. La expiración conserva las funciones Community y los datos del curso.

## Retiro

Desinstala en Moodle o con `php admin/cli/uninstall_plugins.php --plugins=local_skillupssuccess --run`. Retira la carpeta y completa la actualización. La desinstalación elimina los registros del plugin. Matrículas, tareas y notas de Moodle permanecen bajo sus subsistemas nativos.
