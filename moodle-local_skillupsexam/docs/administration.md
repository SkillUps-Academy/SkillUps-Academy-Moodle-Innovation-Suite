# Instalación y administración

Descarga `skillupsexam-0.9.0-beta.1.zip`, verifica SHA-256 y usa **Administración del sitio → Plugins → Instalar plugins**. Para una instalación manual, extrae la carpeta `skillupsexam` en el directorio público `local` de Moodle y visita **Notificaciones** o ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de código. En despliegues con directorio público separado, el plugin vive en `public/local/skillupsexam`.

El esquema se instala con XMLDB. Las actualizaciones futuras deben conservar el directorio de componente y ejecutar el actualizador de Moodle con copia previa de la base de datos. Para desinstalar usa **Vista general de plugins**. La desinstalación elimina los registros propios del plugin, incluidas convocatorias e incidencias.

## Capacidades

| Capacidad `local/skillupsexam:` | Ámbito | Operación |
|---|---|---|
| `catalog` | Categoría | Crear y editar períodos y espacios. |
| `view` | Curso | Abrir el calendario y las convocatorias visibles. |
| `plan` | Curso | Crear borradores. |
| `edit` | Curso | Editar, publicar y cancelar, gestionar inscripción manual. Permite leer registros operativos del mismo ámbito. |
| `supervise` | Curso | Registrar asistencia e incidencias en convocatorias asignadas. |
| `results` | Curso | Consultar metadatos evaluativos con permisos nativos. |
| `export` | Curso | Exportar cronograma y los datos de participantes que el rol puede consultar. |

El administrador de sitio puede gestionar la configuración y todos los ámbitos. Para un coordinador delega `catalog` donde corresponda y las capacidades de curso `view`, `plan` y `edit`. Añade `export`, `results` y `supervise` según el cargo. Un coordinador con `edit` y `supervise` puede actuar sobre convocatorias de su ámbito. Los arquetipos docentes reciben `view` y `results` por defecto. Para supervisar, otorga `view`, `supervise` y la capacidad nativa de acceso a la evaluación, asegúrate de que el supervisor está matriculado y asígnalo en la convocatoria. En grupos separados, un operador sin acceso a todos los grupos necesita el grupo pertinente. La exportación hereda el ámbito y las capacidades de lectura.

## Cron

La tarea **Procesar avisos de exámenes** procesa cambios y recordatorios. La configuración **Enviar notificaciones de exámenes** controla la entrega. Moodle aplica las preferencias y los canales de mensajería del sitio. Los registros de cron identifican intentos de entrega fallidos por ID de cola. Los avisos agotados permanecen en estado `failed` para diagnóstico técnico.

## Distribución

El código se mantiene en el monorepositorio solicitado. Para un directorio de plugins Moodle que requiera enlace de repositorio de componente, utiliza un repositorio público cuyo directorio raíz sea el contenido de `moodle-local_skillupsexam`. El ZIP ya contiene la raíz `skillupsexam/`. La entrega de la suite no implica registro ni aprobación en el directorio de plugins Moodle.
