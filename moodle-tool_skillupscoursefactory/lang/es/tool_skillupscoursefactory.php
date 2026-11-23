<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Language strings for SkillUps Course Factory.
 *
 * @package    tool_skillupscoursefactory
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Acción';
$string['allrows'] = 'Mostrar todas las filas';
$string['ambiguous'] = 'La restauración pudo quedar incompleta. Revisa el curso oculto en Moodle antes de decidir cómo continuar.';
$string['assignment'] = 'Matricular usuario ID {$a->userid} con rol ID {$a->role}';
$string['attemptlimit'] = 'La fila alcanzó su límite de reintentos.';
$string['attempts'] = 'Intentos';
$string['batchsize'] = 'Tamaño del lote';
$string['busy'] = 'Hay otra operación activa. Inténtalo de nuevo en breve.';
$string['canceljob'] = 'Cancelar trabajo pendiente';
$string['cancelledrow'] = 'La fila se detuvo antes de realizar más cambios.';
$string['category'] = 'Categoría de destino';
$string['configuration'] = 'Configuración';
$string['confirm'] = 'Confirmar operación';
$string['coordinatorrole'] = 'Rol para matricular coordinadores';
$string['coordinators'] = 'IDs de coordinadores separados por punto y coma';
$string['count'] = 'Cantidad';
$string['courseid'] = 'ID de curso';
$string['created'] = 'Creado el';
$string['cronhelp'] = 'El cron de Moodle despacha la operación en el momento programado.';
$string['csv'] = 'Descargar CSV';
$string['denied'] = 'La operación, su origen o su destino no están disponibles para este usuario.';
$string['dispatch'] = 'Despachar operaciones programadas de Course Factory';
$string['duplicate'] = 'El nombre corto o identificador ya está en uso.';
$string['emptycourse'] = 'Crear un curso vacío';
$string['emptyimport'] = 'La operación no tiene filas válidas listas para confirmar.';
$string['enddate'] = 'Fecha de fin (AAAA-MM-DD)';
$string['eventoperation'] = 'Operación de Course Factory';
$string['excel'] = 'Descargar Excel';
$string['executionerror'] = 'Moodle no pudo completar esta fase. Revisa el curso y los recursos del servidor antes de reintentar.';
$string['expires'] = 'Vence el {$a}';
$string['formula'] = 'Usa valores literales en la primera hoja. Las celdas con fórmulas o errores se rechazan.';
$string['fullname'] = 'Nombre del curso';
$string['hidden'] = 'Mantener oculto';
$string['history'] = 'Historial de operaciones';
$string['idnumber'] = 'Código institucional';
$string['immediate'] = 'Siguiente ejecución de cron';
$string['import'] = 'Importar CSV o XLSX';
$string['importdesc'] = 'Revisa una hoja de cursos antes de programarla.';
$string['importlimit'] = 'La cantidad de filas supera el tamaño elegido o permitido.';
$string['installationid'] = 'ID de instalación {$a}';
$string['invalidaction'] = 'La operación solicitada ya no está disponible.';
$string['invaliddate'] = 'Usa fechas válidas y coloca la fecha de fin a partir de la fecha de inicio.';
$string['invalidfile'] = 'El archivo no es un CSV o XLSX compatible, o supera un límite de seguridad.';
$string['invalidheader'] = 'Usa los nombres de columna documentados una sola vez e incluye las tres columnas obligatorias.';
$string['invalidrow'] = 'Revisa los nombres obligatorios, longitudes, IDs numéricos y visibilidad.';
$string['invalidschedule'] = 'Elige una fecha de ejecución dentro de un año.';
$string['invalidstaff'] = 'Revisa los usuarios, permisos de matriculación y roles configurados en el sitio.';
$string['invalidtemplate'] = 'Elige un origen o una plantilla permitida.';
$string['job'] = 'Operación';
$string['jobdetails'] = 'Detalle de la operación';
$string['jobref'] = 'Operación {$a}';
$string['license'] = 'Estado de licencia';
$string['license_community'] = 'Edición Community';
$string['license_enterprise'] = 'Edición Enterprise';
$string['license_expired'] = 'Licencia vencida';
$string['license_grace'] = 'Período de gracia de la licencia';
$string['license_professional'] = 'Edición Professional';
$string['line'] = 'Fila';
$string['manualmissing'] = 'El destino debe tener una instancia activa de matriculación manual.';
$string['maxbatch'] = 'Máximo de registros por solicitud';
$string['maxbatchdesc'] = 'Elige el tamaño máximo que admite la instalación.';
$string['nohistory'] = 'Todavía no hay operaciones.';
$string['norows'] = 'No hay filas para esta vista.';
$string['notemplate'] = 'Sin plantilla seleccionada';
$string['onlyerrors'] = 'Mostrar solo errores';
$string['period'] = 'Período académico';
$string['periodfield'] = 'Campo de curso para el período';
$string['periodfielddesc'] = 'Nombre corto opcional de un campo personalizado de texto del curso. Sin esta configuración, el período se conserva en el historial.';
$string['periodunavailable'] = 'El campo de período configurado no existe, tiene otro tipo o no se puede editar.';
$string['phase'] = 'Fase';
$string['phase_done'] = 'Finalizada';
$string['phase_new'] = 'Pendiente de creación';
$string['phase_prepared'] = 'Curso oculto creado';
$string['phase_restored'] = 'Pendiente de configuración final';
$string['phase_restoring'] = 'Restauración iniciada';
$string['pluginname'] = 'SkillUps Course Factory';
$string['preview'] = 'Preparar vista previa';
$string['previewhelp'] = 'Revisa el destino y los roles. Confirma para procesar las filas válidas. Las inválidas quedan en el informe.';
$string['privacy:enrol'] = 'Moodle gestiona las matrículas resultantes.';
$string['privacy:job'] = 'Registra el usuario que solicitó una operación.';
$string['privacy:row'] = 'Contiene roles e identificadores de matriculación solicitados.';
$string['privacy:staff'] = 'IDs de usuarios y roles que se matricularán en el destino.';
$string['privacy:time'] = 'Fecha de solicitud.';
$string['privacy:userid'] = 'ID de la persona solicitante.';
$string['published'] = 'Hacer visible';
$string['purge'] = 'Purgar metadatos vencidos de Course Factory';
$string['reason'] = 'Resultado o error';
$string['removetemplate'] = 'Quitar referencia de plantilla';
$string['restorecheck'] = 'Las comprobaciones de restauración de Moodle detectaron errores. Revisa la configuración del origen y destino.';
$string['results'] = 'Resultados por curso';
$string['retention'] = 'Retención del historial en días';
$string['retentiondesc'] = 'Las operaciones completadas se conservan entre 30 y 730 días. Los borradores vencen a los siete días.';
$string['retry'] = 'Reintentar esta fila';
$string['roledefault'] = 'Usar el valor predeterminado del rol';
$string['runat'] = 'Inicio programado';
$string['savetemplate'] = 'Guardar plantilla';
$string['scheduledfor'] = 'Inicio programado {$a}';
$string['settings'] = 'Configuración de Course Factory';
$string['sheet'] = 'Archivo CSV o XLSX';
$string['sheethelp'] = 'Usa un CSV UTF-8 separado por comas o la primera hoja de un archivo XLSX. Las columnas obligatorias son fullname, shortname y category.';
$string['shortname'] = 'Nombre corto';
$string['single'] = 'Crear o clonar un curso';
$string['singledesc'] = 'Selecciona un origen o crea un curso vacío.';
$string['skillupscoursefactory:manage'] = 'Administrar plantillas y consultar o detener operaciones del sitio';
$string['skillupscoursefactory:operate'] = 'Previsualizar y ejecutar operaciones de Course Factory';
$string['source'] = 'Curso de origen';
$string['sourceoverride'] = 'ID de origen si no aparece en la lista';
$string['startdate'] = 'Fecha de inicio (AAAA-MM-DD)';
$string['state'] = 'Estado';
$string['state_attention'] = 'Requiere revisión manual';
$string['state_cancelled'] = 'Cancelada';
$string['state_done'] = 'Completada';
$string['state_draft'] = 'Vista previa';
$string['state_failed'] = 'Fallida';
$string['state_invalid'] = 'Inválida';
$string['state_pending'] = 'Lista';
$string['state_queued'] = 'En cola';
$string['stopping'] = 'Se está cancelando el trabajo pendiente. Una restauración iniciada puede terminar.';
$string['submitted'] = 'Se actualizó la operación.';
$string['summary'] = 'Resumen de la operación';
$string['targetchanged'] = 'El destino registrado fue renombrado, movido, mostrado o eliminado. Revísalo antes de continuar.';
$string['teacherrole'] = 'Rol para matricular docentes';
$string['teachers'] = 'IDs de docentes separados por punto y coma';
$string['templatehelp'] = 'Una plantilla hace referencia a un curso existente. Cada solicitud usa el contenido de origen disponible al ejecutarse.';
$string['templateid'] = 'ID de plantilla';
$string['templatename'] = 'Nombre de la plantilla';
$string['templates'] = 'Plantillas institucionales';
$string['templatesaved'] = 'Se guardó la plantilla institucional.';
$string['visible'] = 'Mostrar el curso al completar todos los pasos';
