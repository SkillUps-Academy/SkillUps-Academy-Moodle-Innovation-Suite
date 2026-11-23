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
 * Language strings for Smart Enrollment Manager.
 *
 * @package    tool_skillupsenrollment
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Operación';
$string['action_enrol'] = 'Matricular o añadir un rol';
$string['action_invalid'] = 'Entrada sin resolver';
$string['action_reactivate'] = 'Reactivar matrícula';
$string['action_role'] = 'Añadir un rol a una matrícula existente';
$string['action_suspend'] = 'Suspender matrícula';
$string['activeenrolment'] = 'Estado de matrícula activo';
$string['allrows'] = 'Mostrar todas las filas';
$string['applyfilter'] = 'Aplicar filtros';
$string['attemptlimit'] = 'La fila alcanzó su límite de reintentos.';
$string['attempts'] = 'Intentos';
$string['batchsize'] = 'Límite de filas de este lote';
$string['busy'] = 'Hay otra operación de matriculación activa. Inténtalo de nuevo en breve.';
$string['canceljob'] = 'Cancelar trabajo pendiente';
$string['changed'] = 'La matrícula cambió después de la simulación. Crea una simulación nueva para esta fila.';
$string['confirm'] = 'Confirmar filas válidas';
$string['count'] = 'Cantidad';
$string['courseid'] = 'ID del curso';
$string['cronhelp'] = 'El cron de Moodle procesa el lote a partir de la hora programada.';
$string['csv'] = 'Descargar CSV';
$string['defaultmethod'] = 'Usar método manual si no se especifica una instancia';
$string['denied'] = 'Este registro u operación no está disponible para el usuario actual.';
$string['dispatch'] = 'Despachar lotes de matriculación programados';
$string['duplicate'] = 'Otra fila válida apunta al mismo usuario e instancia de matriculación.';
$string['emptyimport'] = 'El lote no tiene filas válidas listas para confirmar.';
$string['errors'] = 'Errores';
$string['eventoperation'] = 'Operación de lote de matriculación';
$string['excel'] = 'Descargar Excel';
$string['executionerror'] = 'Moodle no pudo aplicar esta fila. Revisa el sitio y reinténtala si procede.';
$string['expires'] = 'Vence el {$a}';
$string['forecastchange'] = 'Cambio solicitado pendiente';
$string['forecastnoop'] = 'El estado actual ya coincide con la solicitud';
$string['formula'] = 'Usa valores literales en la primera hoja. Las celdas con fórmulas o errores se rechazan.';
$string['groupfailed'] = 'Moodle no pudo añadir el miembro al grupo. Se revirtieron los cambios de esta fila.';
$string['groupid'] = 'ID del grupo';
$string['groupinactive'] = 'Reactiva la matrícula antes de añadir un grupo en un lote nuevo.';
$string['groupname'] = 'ID de grupo {$a}';
$string['history'] = 'Historial de lotes';
$string['immediate'] = 'Siguiente ejecución de cron';
$string['import'] = 'Importar CSV o XLSX';
$string['importdesc'] = 'Revisa los cambios previstos y los errores antes de autorizar el lote.';
$string['importlimit'] = 'La cantidad de filas supera el tamaño de lote elegido o permitido.';
$string['installationid'] = 'ID de instalación {$a}';
$string['instanceid'] = 'ID de la instancia de matriculación';
$string['invalidaction'] = 'La operación solicitada ya no está disponible.';
$string['invalidcourse'] = 'Elige una referencia de curso válida.';
$string['invalidfactory'] = 'El resultado de Course Factory no está disponible. Usa una fila completada y autorizada.';
$string['invalidfile'] = 'El archivo no es un CSV o XLSX compatible, o supera un límite de seguridad.';
$string['invalidgroup'] = 'El grupo no está disponible, pertenece a otro curso o no se puede administrar.';
$string['invalidheader'] = 'Usa solo los encabezados documentados e incluye action.';
$string['invalidmethod'] = 'Elige una instancia de matriculación nativa activa que admita la acción.';
$string['invalidrole'] = 'El rol seleccionado no está disponible o no se puede asignar en este curso.';
$string['invalidrow'] = 'Revisa la acción solicitada y los campos admitidos.';
$string['invalidschedule'] = 'Elige una fecha de ejecución dentro de un año.';
$string['invaliduser'] = 'Elige un usuario local elegible de Moodle.';
$string['job'] = 'Lote';
$string['jobdetails'] = 'Detalle del lote de matriculación';
$string['jobid'] = 'ID del lote';
$string['jobref'] = 'Lote {$a}';
$string['license'] = 'Estado de licencia';
$string['license_community'] = 'Edición Community';
$string['license_enterprise'] = 'Edición Enterprise';
$string['license_expired'] = 'Licencia vencida';
$string['license_grace'] = 'Período de gracia';
$string['license_professional'] = 'Edición Professional';
$string['line'] = 'Fila';
$string['manual'] = 'Matriculación manual';
$string['maxbatch'] = 'Máximo de filas por lote';
$string['maxbatchdesc'] = 'Define el máximo de filas que admite un lote simulado.';
$string['method'] = 'Método de matriculación';
$string['methodname'] = 'Método {$a}';
$string['missingenrolment'] = 'Sin matrícula en esta instancia';
$string['modified'] = 'Última actualización';
$string['noenrolment'] = 'El método seleccionado carece de una matrícula para este usuario.';
$string['nohistory'] = 'Todavía no hay lotes de matriculación.';
$string['norows'] = 'No hay filas visibles para este filtro.';
$string['onlyerrors'] = 'Mostrar solo errores';
$string['pluginname'] = 'SkillUps Smart Enrollment Manager';
$string['preview'] = 'Simular';
$string['previewhelp'] = 'Revisa el estado actual y los efectos previstos. Confirma para procesar las filas válidas.';
$string['privacy:detail'] = 'Acción autorizada y evidencia de matriculación.';
$string['privacy:enrol'] = 'Moodle gestiona las matrículas resultantes.';
$string['privacy:job'] = 'Guarda el solicitante y la programación del lote.';
$string['privacy:operator'] = 'Usuario que solicitó el lote.';
$string['privacy:participant'] = 'Usuario afectado por la solicitud de matriculación.';
$string['privacy:row'] = 'Guarda la identidad y el resultado de cada fila.';
$string['privacy:time'] = 'Fecha de registro o modificación de datos operativos.';
$string['purge'] = 'Purgar metadatos vencidos de matriculación';
$string['reason'] = 'Resultado o error';
$string['reporthelp'] = 'Filtra el historial por ID de curso, usuario o lote. Los informes muestran las acciones gestionadas por este plugin.';
$string['reports'] = 'Informes de matriculación';
$string['results'] = 'Resultados de matriculación';
$string['retention'] = 'Retención del historial en días';
$string['retentiondesc'] = 'Conserva operaciones completadas entre 30 y 730 días. Los borradores vencen tras siete días.';
$string['retry'] = 'Reintentar esta fila';
$string['role'] = 'ID o nombre corto del rol';
$string['rolename'] = 'ID de rol {$a}';
$string['runat'] = 'Inicio programado';
$string['self'] = 'Automatriculación';
$string['settings'] = 'Configuración de Smart Enrollment Manager';
$string['sheet'] = 'Archivo CSV o XLSX';
$string['sheethelp'] = 'Usa CSV UTF-8 separado por comas o la primera hoja XLSX. Incluye action, una referencia de usuario y una de curso.';
$string['shortname'] = 'Nombre corto del curso';
$string['simulation'] = 'Evidencia de simulación';
$string['single'] = 'Simular un registro';
$string['singledesc'] = 'Comprueba una matrícula, un rol o un cambio de estado.';
$string['singlehelp'] = 'Introduce una referencia de usuario y una de curso. El rol admite el ID o nombre corto de un rol de Moodle.';
$string['skillupsenrollment:manage'] = 'Consultar y cancelar lotes de matriculación del sitio';
$string['skillupsenrollment:operate'] = 'Simular y ejecutar operaciones de matriculación';
$string['state'] = 'Estado';
$string['state_applied'] = 'Aplicada';
$string['state_cancelled'] = 'Cancelada';
$string['state_done'] = 'Procesado';
$string['state_draft'] = 'Simulación';
$string['state_failed'] = 'Fallida';
$string['state_invalid'] = 'Inválida';
$string['state_noop'] = 'Sin cambios necesarios';
$string['state_pending'] = 'Lista';
$string['state_queued'] = 'En cola';
$string['stopping'] = 'Se están cancelando las acciones pendientes.';
$string['submitted'] = 'Se actualizó el lote.';
$string['summary'] = 'Resumen del lote';
$string['suspendedenrolment'] = 'Estado de matrícula suspendido';
$string['userid'] = 'ID del usuario';
$string['username'] = 'Nombre de usuario';
