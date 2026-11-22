# Instalación y administración

La plataforma de referencia es Moodle 5.3 LTS. La compatibilidad mínima es Moodle 5.0. Usa la versión de PHP y base de datos admitida por tu rama de Moodle. Se requieren los formatos nativos `dataformat_csv` y `dataformat_excel`. El ZIP es autocontenido y no requiere Composer en el servidor de producción.

1. Instala el ZIP desde **Administración del sitio → Plugins → Instalar plugins**. Para instalación manual, copia la carpeta `skillupsquality` a `report` bajo la raíz pública de Moodle. En la distribución estándar de Moodle 5.1 y posteriores, usa `public/report/skillupsquality`. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle.
2. Busca **Política de calidad de cursos** en Administración del sitio. Activa las reglas que correspondan a la institución y ajusta su gravedad. Los cambios se aplican a solicitudes nuevas. La revisión conserva la versión de motor, reglas activas y severidades.
3. Ejecuta cron cada minuto. Moodle encola un trabajo Adhoc por curso. La tarea de mantenimiento revisa estados desactualizados cada hora. Un trabajo sin resolver después de dos días se marca como fallido. El historial se conserva 180 días por defecto, con rango de 30 a 730 días. Al eliminar un curso se depuran sus auditorías y planes en la tarea de mantenimiento.
4. Abre **Administración del sitio → Informes → SkillUps Course Quality Inspector** para el panel del sitio. En un curso, abre **Informes → SkillUps Course Quality Inspector**. Los gestores de categoría también pueden abrir el informe desde la categoría autorizada o con `index.php?category=ID`.

## Delegación y alcance

| Capacidad | Alcance |
| --- | --- |
| `report/skillupsquality:view` | Consultar toda la configuración de un curso, incluidos los recursos ocultos. Requiere acceso al curso y `moodle/course:update`. |
| `report/skillupsquality:run` | Solicitar auditoría en un curso autorizado. |
| `report/skillupsquality:plan` | Crear o editar planes de corrección en un curso autorizado. |
| `report/skillupsquality:batch` | Iniciar lotes en una categoría autorizada. Para listas que cruzan categorías se requiere el permiso en el sistema. |

Los valores iniciales conceden consulta, auditoría y gestión de planes a docentes con edición y gestores dentro de su ámbito. Los gestores también reciben la capacidad de lotes dentro del ámbito asignado. Usa el ID de la categoría que figura en las páginas de gestión de Moodle y el ID del curso que figura en su URL. El panel del sitio muestra hasta 50 cursos con permiso de auditoría. También se puede entrar directamente desde el menú de un curso.

## Licencia local

Community incluye motor, reglas, evidencias, auditorías por categoría o lote, comparación, planes y exportación CSV. Con una licencia anual Professional o Enterprise firmada, la autorización `aggregate_export` habilita el formato Excel en informes. La configuración muestra el estado, vencimiento e identificador del sitio para solicitar la emisión o renovación al equipo de SkillUps Academy.

Configura `$CFG->report_skillupsquality_entitlement` con el documento firmado y `$CFG->report_skillupsquality_licensepublickey` con la clave pública Ed25519 en base64. El contrato es `base64(JSON).base64(firma)`, con los campos `version` igual a 1, `component` igual a `report_skillupsquality`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. Usa segundos Unix y como máximo 366 días de vigencia. `sites` contiene hashes SHA-256 del `wwwroot` canónico de las instalaciones autorizadas. `$CFG->report_skillupsquality_revoked` recibe un arreglo de IDs revocados y `$CFG->report_skillupsquality_gracedays` hasta 30 días de gracia. La verificación usa Sodium local y mantiene Community operativa en cualquier estado.

La desinstalación elimina datos y configuración del plugin. Descarga previamente los resultados que necesites conservar.
