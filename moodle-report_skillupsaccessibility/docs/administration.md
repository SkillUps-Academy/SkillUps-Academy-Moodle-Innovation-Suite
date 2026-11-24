# Instalación y administración

1. Instala el ZIP mediante Moodle o despliega la carpeta `skillupsaccessibility` en `report` dentro de la raíz web. Con raíz web separada, el destino es `public/report/skillupsaccessibility`.
2. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle.
3. Mantén activo cron. Las acciones manuales usan tareas Adhoc de Moodle.
4. Para habilitar el escaneo programado, indica en los ajustes del informe una cuenta activa con `bulk` en el sistema y los permisos nativos y del plugin en los cursos pertinentes. Configura el intervalo, entre 1 y 90 días. Cero como ID desactiva el disparador.
5. Los CSV requieren el formato de datos CSV de Moodle habilitado.

Las capacidades usan el prefijo `report/skillupsaccessibility:`. El informe exige además acceso activo y `moodle/course:manageactivities`. Los archivos del curso se consultan mediante el navegador de archivos con `moodle/course:managefiles` donde corresponda.

| Capacidad | Ámbito y uso |
| --- | --- |
| `view` | Consultar auditorías y detalles autorizados del curso |
| `scan` | Encolar el escaneo de un curso |
| `review` | Cambiar estado, asignar responsable y adjuntar evidencia |
| `export` | Descargar resultados de cursos autorizados |
| `bulk` | Encolar y consultar auditorías de una categoría o del sitio en su contexto |

Para actualizar, respalda la base y Moodledata y ejecuta la CLI de actualización. Esta primera versión incluye el punto de entrada XMLDB. Desinstalar retira resultados y archivos de evidencia del informe. Los contenidos didácticos permanecen en Moodle.
