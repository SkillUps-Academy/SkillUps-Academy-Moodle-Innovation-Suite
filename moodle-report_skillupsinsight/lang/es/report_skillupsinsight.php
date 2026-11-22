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
 * Spanish strings.
 *
 * @package    report_skillupsinsight
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['above'] = 'Igual o superior a la meta';
$string['actions'] = 'Acciones';
$string['addsched'] = 'Agregar programación o meta';
$string['alertsubject'] = 'Un indicador de Insight alcanzó su umbral';
$string['allprograms'] = 'Todos los programas visibles';
$string['applyfilters'] = 'Aplicar filtros';
$string['awaiting'] = 'El período está a la espera de su primera captura publicada. Consulta de nuevo después de la agregación.';
$string['begins'] = 'Inicio (UTC)';
$string['below'] = 'Igual o inferior a la meta';
$string['breakdown'] = 'Desglose de cursos autorizados';
$string['builderhelp'] = 'Selecciona indicadores y dimensiones para un informe guardado privado. Cada apertura aplica los permisos vigentes del propietario.';
$string['building'] = 'Calculando';
$string['busy'] = 'La operación está en curso. Vuelve a intentarlo en unos instantes.';
$string['captured'] = 'Publicada el {$a}';
$string['capturedate'] = 'Publicación de la captura';
$string['category'] = 'Categoría';
$string['compare'] = 'Período de comparación';
$string['comparisoncoverage'] = 'Cobertura de comparación';
$string['configuration'] = 'Configuración de SkillUps Insight';
$string['constructor'] = 'Constructor de informes';
$string['course'] = 'Curso';
$string['coverage'] = 'Cursos capturados';
$string['coveragedetail'] = 'Cobertura: {$a->current} de {$a->total} cursos autorizados.';
$string['createperiod'] = 'Crear período';
$string['daily'] = 'Cada día';
$string['dashboard'] = 'Panel ejecutivo';
$string['delete'] = 'Eliminar';
$string['deletehelp'] = 'Confirma la eliminación de este informe guardado y sus programaciones.';
$string['deletewatchhelp'] = 'Confirma la eliminación de esta programación o meta.';
$string['deltametric'] = '{$a} (variación)';
$string['digest'] = 'Informe recurrente';
$string['digestsubject'] = 'Informe de Insight disponible';
$string['dimension'] = 'Grupo';
$string['direction'] = 'Tipo de notificación o meta';
$string['download'] = 'Descargar informe';
$string['edit'] = 'Editar';
$string['ends'] = 'Fin (UTC)';
$string['eventreport'] = 'Se accedió a la analítica institucional';
$string['exportlimit'] = 'La exportación supera {$a} filas. Reduce el alcance de los filtros o agrupa los cursos.';
$string['format'] = 'Formato';
$string['freeze'] = 'Congelar captura';
$string['frozen'] = 'Congelada';
$string['goallabel'] = '{$a->metric} · {$a->direction} {$a->target}';
$string['group'] = 'Agrupar por';
$string['history'] = 'Capturas publicadas';
$string['indicators'] = 'Indicadores';
$string['institution'] = 'Institución';
$string['intervaldays'] = 'Frecuencia';
$string['invalidperiod'] = 'Selecciona un período académico válido y distinto o una captura publicada.';
$string['invalidrequest'] = 'La operación de analítica solicitada no es válida.';
$string['lastcheck'] = 'Última comprobación el {$a}';
$string['latest'] = 'Período académico más reciente';
$string['licensecommunity'] = 'Community';
$string['licenseenterprise'] = 'Enterprise';
$string['licenseexpired'] = 'Vencida';
$string['licenseexpires'] = 'Vencimiento: {$a}';
$string['licensegrace'] = 'Período de gracia sin conexión';
$string['licenseinstallation'] = 'Identificador de instalación: {$a}';
$string['licenseprofessional'] = 'Professional';
$string['licensestatus'] = 'Estado de la suscripción';
$string['limit'] = 'Se alcanzó el máximo de registros para esta cuenta.';
$string['messageprovider:reportdue'] = 'Informes programados y alertas de indicadores';
$string['metric'] = 'Indicador';
$string['metric_academic'] = 'Nota media numérica de curso';
$string['metric_active'] = 'Estudiantes con eventos registrados';
$string['metric_completion'] = 'Tasa de finalización de curso';
$string['metric_educators'] = 'Matrículas docentes activas';
$string['metric_enrolled'] = 'Matrículas estudiantiles activas';
$string['metric_newlearners'] = 'Nuevas matrículas estudiantiles';
$string['metric_participation'] = 'Participación estudiantil';
$string['metric_teaching'] = 'Docentes con eventos de enseñanza';
$string['metricdelta'] = 'Variación';
$string['metriclimits'] = 'La actividad se mide mediante eventos de Moodle en el período. Los conteos entre cursos representan pares persona–curso. Un guion indica datos ausentes, no elegibles u ocultados.';
$string['metricvalue'] = 'Valor';
$string['monthly'] = 'Cada 30 días';
$string['mypresets'] = 'Mis informes guardados';
$string['myschedules'] = 'Programaciones y metas guardadas';
$string['name'] = 'Nombre';
$string['nextcheck'] = 'Próxima comprobación el {$a}';
$string['nocmp'] = 'Sin comparación';
$string['nocourses'] = 'Ningún curso de este ámbito coincide con los filtros seleccionados.';
$string['noperiods'] = 'Todavía no se han configurado períodos académicos.';
$string['noticemessage'] = 'Abre tu informe privado en Moodle para consultar sus datos actuales.';
$string['notificationfailed'] = 'Moodle no pudo entregar la notificación programada de Insight.';
$string['openreport'] = 'Abrir informe';
$string['outofscope'] = 'No tienes acceso a este ámbito de analítica.';
$string['overview'] = 'Resumen';
$string['paused'] = 'En pausa';
$string['pending'] = 'Pendiente';
$string['percent'] = '{$a}%';
$string['period'] = 'Período';
$string['periodadmin'] = 'Períodos académicos';
$string['periodhelp'] = 'Usa fechas UTC. Se incluyen el primer y último día seleccionados. Un período puede abarcar hasta 731 días.';
$string['periods'] = 'Períodos académicos configurados';
$string['pluginname'] = 'SkillUps Insight Analytics';
$string['points'] = '{$a} pp';
$string['priorcoverage'] = 'Comparación: {$a->current} de {$a->total} cursos autorizados.';
$string['priormetric'] = '{$a} (comparación)';
$string['privacy:metadata:preset'] = 'Título y filtros del informe guardado de un propietario.';
$string['privacy:metadata:settings'] = 'Preferencias de informes guardadas.';
$string['privacy:metadata:status'] = 'Fecha de próxima ejecución o última evaluación de la regla.';
$string['privacy:metadata:timecreated'] = 'Fecha de creación del informe.';
$string['privacy:metadata:userid'] = 'Propietario del informe o la programación.';
$string['privacy:metadata:watch'] = 'Programación o umbral y estado de evaluación de un informe de un propietario.';
$string['privacy:path'] = 'SkillUps Insight Analytics';
$string['program'] = 'Programa';
$string['programfield'] = 'Campo de programa del curso';
$string['programfielddesc'] = 'Nombre corto de un campo personalizado de curso de texto de una línea y visible.';
$string['reached'] = 'Meta alcanzada';
$string['ready'] = 'Disponible';
$string['refresh'] = 'Poner actualización en cola';
$string['retention'] = 'Retención del historial de capturas (días)';
$string['retentiondesc'] = 'Entre 7 y 365 días. Se conserva la última captura publicada de cada período.';
$string['saved'] = 'Cambios guardados.';
$string['savepreset'] = 'Guardar informe';
$string['schedulehelp'] = 'Elige un enlace de informe recurrente o verifica un umbral. Para un umbral, indica la meta como conteo o porcentaje según el indicador.';
$string['scheduling'] = 'Programación y metas';
$string['skillupsinsight:educator'] = 'Contabilizar como docente en analítica';
$string['skillupsinsight:export'] = 'Exportar analítica autorizada';
$string['skillupsinsight:learner'] = 'Contabilizar como estudiante en analítica';
$string['skillupsinsight:manage'] = 'Gestionar períodos académicos';
$string['skillupsinsight:personalise'] = 'Guardar informes de analítica personales';
$string['skillupsinsight:schedule'] = 'Programar informes y seguimiento de indicadores';
$string['skillupsinsight:view'] = 'Ver analítica institucional autorizada';
$string['smallcell'] = 'Denominador mínimo';
$string['smallcelldesc'] = 'Oculta indicadores sujetos al umbral cuando la población es menor que este valor, entre 1 y 100. Los conteos de matrícula y docentes siguen disponibles.';
$string['status'] = 'Estado';
$string['tablecompare'] = 'Anterior {$a->prior} · Variación {$a->delta}';
$string['target'] = 'Meta';
$string['taskaggregate'] = 'Publicar capturas de Insight Analytics';
$string['taskdeliver'] = 'Evaluar informes y metas de Insight';
$string['toggle'] = 'Pausar o reanudar';
$string['unauthorized'] = 'Permiso revocado';
$string['unavailable'] = 'Datos no disponibles';
$string['unmapped'] = 'Sin programa asignado';
$string['unreached'] = 'Meta pendiente';
$string['weekly'] = 'Cada 7 días';
