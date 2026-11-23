# Instalación y configuración

La plataforma de referencia es Moodle 5.3 LTS. La versión mínima es Moodle 5.0. Se requieren Core Backup/Restore, `enrol_manual`, `dataformat_csv` y `dataformat_excel`. La importación XLSX usa OpenSpout distribuido con Moodle. El ZIP no requiere instalar bibliotecas adicionales.

1. Instala el ZIP desde **Administración del sitio → Plugins → Instalar plugins**. Para instalación manual, copia `skillupscoursefactory` a `admin/tool` bajo la raíz pública de Moodle. Desde Moodle 5.1, en una distribución estándar, usa `public/admin/tool`. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz.
2. Abre **Administración del sitio → Cursos → SkillUps Course Factory**. Busca también **Configuración de Course Factory** para ajustar el máximo de registros, la retención, los roles y el campo de período.
3. Ejecuta cron cada minuto. La capacidad predeterminada de una solicitud es de 50 registros. Los tamaños disponibles son 10, 50, 100, 200 y 500, limitados por la configuración del sitio. Course Factory usa un solo ejecutor de clúster y procesa hasta diez creaciones simples o una clonación por tarea Adhoc.
4. Para matricular, habilita el método manual. El destino debe tener una instancia activa y el solicitante debe poder matricular y asignar el rol. El rol predeterminado para docentes es Docente con edición. Para coordinadores configura un rol de ámbito de curso apto para la institución.
5. Para escribir el período en el curso, crea un campo personalizado de curso de tipo texto y registra su nombre corto en **Campo de curso para el período**. El campo debe ser editable por el operador. El valor de período también se conserva en el historial.
6. Crea referencias reutilizables desde **Plantillas institucionales**. Cada referencia apunta a un curso existente y autorizado. La copia toma el contenido disponible cuando empieza la restauración.

## Permisos y retención

`tool/skillupscoursefactory:operate` habilita las solicitudes propias. `tool/skillupscoursefactory:manage` habilita la gestión de plantillas y la consulta o cancelación de operaciones del sitio. Ambos permisos se reservan a los administradores de forma predeterminada. La delegación requiere permisos nativos de backup en el origen y de creación, configuración, restauración, visibilidad y matriculación en los contextos correspondientes. La activación de una tarea nunca sustituye esas autorizaciones.

El historial se conserva 180 días por defecto, entre 30 y 730 días según configuración. Los borradores sin confirmar vencen tras siete días. Las tareas de mantenimiento y la desinstalación eliminan metadatos del plugin. Los cursos provisionados mantienen su ciclo de vida habitual en Moodle.

## Licencia opcional

Community incluye creación, clonación, importación CSV/XLSX e informes CSV. La exportación XLSX de informes requiere la autorización firmada `aggregate_export` de una licencia anual Professional o Enterprise. Configura `$CFG->tool_skillupscoursefactory_entitlement` y `$CFG->tool_skillupscoursefactory_licensepublickey`. La firma Ed25519 y la clave pública se codifican en base64. El formato del documento es `base64(JSON).base64(firma)`.

El contrato JSON contiene `version` igual a 1, `component` igual a `tool_skillupscoursefactory`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. Emisión y vencimiento son segundos Unix con un máximo de 366 días. `sites` contiene hashes SHA-256 de las URLs canónicas autorizadas. Consulta el identificador y la vigencia en los ajustes. `$CFG->tool_skillupscoursefactory_revoked` admite una lista de IDs revocados y `$CFG->tool_skillupscoursefactory_gracedays` hasta 30 días de gracia. La verificación usa Sodium y opera localmente.
