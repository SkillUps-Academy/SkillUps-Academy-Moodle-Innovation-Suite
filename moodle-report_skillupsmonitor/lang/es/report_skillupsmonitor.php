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
 * Strings for SkillUps System Monitor.
 *
 * @package    report_skillupsmonitor
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['acknowledge'] = 'Marcar como revisado';
$string['acknowledged'] = 'Se registró la revisión del incidente.';
$string['acktime'] = 'Revisado el';
$string['activeaccounts'] = 'Cuentas con acceso a Moodle registrado en las últimas 24 horas';
$string['adhoc'] = 'Ad hoc';
$string['alertchecks'] = 'Comprobaciones con notificaciones';
$string['alertchecksdesc'] = 'Selecciona qué categorías de incidentes envían una notificación al cambiar de estado.';
$string['allchecks'] = 'Todas las comprobaciones';
$string['apply'] = 'Aplicar filtros';
$string['availabilityhelp'] = 'La disponibilidad usa los intervalos observados accesibles y no disponibles. El mantenimiento y la falta de observaciones se informan por separado. La cobertura incluye el mantenimiento observado.';
$string['availabilitysummary'] = 'Disponibilidad interna y cobertura';
$string['backlist'] = 'Volver a incidentes';
$string['badrequest'] = 'La solicitud de monitoreo no es válida o no está disponible.';
$string['busy'] = 'El monitor se está actualizando. Inténtalo de nuevo en breve.';
$string['capturedlabel'] = 'Última observación {$a}';
$string['check'] = 'Comprobación';
$string['check_adhoc'] = 'Cola de tareas ad hoc';
$string['check_components'] = 'Instalación de componentes base';
$string['check_coverage'] = 'Intervalo sin observaciones';
$string['check_cron'] = 'Señal de cron';
$string['check_failures'] = 'Intentos fallidos de tareas recientes';
$string['check_latency'] = 'Latencia interna';
$string['check_readiness'] = 'Disponibilidad de la base de datos de Moodle';
$string['check_scheduled'] = 'Cola de tareas programadas';
$string['checks'] = 'Comprobaciones de la última observación programada';
$string['component'] = 'Componente';
$string['component_delete'] = 'Eliminación pendiente';
$string['component_disabled'] = 'Desactivado';
$string['component_downgrade'] = 'Código anterior detectado';
$string['component_missing'] = 'Archivos ausentes';
$string['component_new'] = 'Instalación pendiente';
$string['component_nodb'] = 'Presente sin metadatos de base de datos';
$string['component_upgrade'] = 'Actualización pendiente';
$string['component_uptodate'] = 'Actualizado';
$string['componentnotice'] = 'El estado de componentes describe su instalación y configuración en Moodle. Utiliza sondas de servicio para comprobar su ejecución de forma independiente.';
$string['components'] = 'Componentes base';
$string['configuration'] = 'Configuración del monitor';
$string['configuration_croncli'] = 'Cron restringido a CLI';
$string['configuration_cronconfigured'] = 'Cron habilitado en Moodle';
$string['configuration_debughidden'] = 'Presentación de depuración desactivada';
$string['configuration_https'] = 'URL canónica con HTTPS';
$string['configuration_logreader'] = 'Lector estándar de registros de Moodle disponible';
$string['configuration_maintenancemode'] = 'Mantenimiento activo';
$string['configuration_scrapeconfigured'] = 'Monitoreo externo habilitado con resumen del token configurado';
$string['configuration_tasklogger'] = 'Registro nativo de tareas disponible en la base de datos';
$string['countfloor'] = 'Al menos {$a}';
$string['courses'] = 'Cursos sin contar la página de inicio';
$string['cronminutes'] = 'Umbral de aviso de cron en minutos';
$string['cronminutesdesc'] = 'Antigüedad máxima del último inicio de cron antes de avisar, entre 2 y 120 minutos.';
$string['cronunobserved'] = 'Moodle todavía no ha registrado un inicio de cron.';
$string['dashboard'] = 'Panel';
$string['delivery'] = 'Entrega de notificaciones';
$string['detected'] = 'Incidente declarado';
$string['diagnostics'] = 'Diagnóstico';
$string['dueminutes'] = 'Umbral de retraso de tareas en minutos';
$string['dueminutesdesc'] = 'Margen tras el inicio programado, entre 1 y 120 minutos.';
$string['duration'] = 'Duración';
$string['error_emailfailed'] = 'Falló el envío de un correo';
$string['error_scandataerror'] = 'Error de antivirus al analizar datos';
$string['error_scanfileerror'] = 'Error de antivirus al analizar un archivo';
$string['errorlimits'] = 'El diagnóstico muestra hasta 50 resultados por lista. Los permisos de Moodle controlan el acceso a los detalles de tareas y eventos.';
$string['eventcaption'] = 'Eventos de error seleccionados de Moodle en las últimas 24 horas';
$string['eventmonitor'] = 'Operación de monitoreo';
$string['events'] = 'Eventos de error autorizados';
$string['eventunavailable'] = 'El lector estándar de registros de Moodle no está disponible.';
$string['failures'] = 'Observaciones para declarar un incidente';
$string['failuresdesc'] = 'Advertencias o errores consecutivos, entre 1 y 12.';
$string['firstobserved'] = 'Primera observación';
$string['freshsample'] = 'La última observación está dentro de la antigüedad configurada.';
$string['heartbeat'] = 'Observación de SkillUps System Monitor';
$string['history'] = 'Historial de disponibilidad';
$string['history_available'] = 'Porcentaje de disponibilidad observada';
$string['history_coverage'] = 'Porcentaje de cobertura';
$string['history_down'] = 'Observaciones no disponibles';
$string['history_expected'] = 'Intervalos de cinco minutos esperados';
$string['history_maintenance'] = 'Observaciones en mantenimiento';
$string['history_p95us'] = 'Percentil 95 de latencia interna en microsegundos';
$string['history_p95us_raw'] = 'Latencia interna en microsegundos';
$string['history_unknown'] = 'Intervalos desconocidos o sin observación';
$string['history_up'] = 'Observaciones accesibles';
$string['hours'] = 'Período';
$string['hourslabel'] = 'Últimas {$a} horas';
$string['incidentdetail'] = 'Detalle del incidente';
$string['incidents'] = 'Incidentes';
$string['internalnotice'] = 'Las observaciones internas cubren Moodle y su base de datos. Utiliza una sonda independiente para la disponibilidad percibida y un agente externo para CPU, memoria, disco o servicios del sistema.';
$string['intervalend'] = 'Fin del período UTC excluido';
$string['intervalstart'] = 'Inicio del período UTC';
$string['invalidwindow'] = 'Selecciona un período disponible para el informe.';
$string['lastcronlabel'] = 'Último inicio de cron {$a}';
$string['latencyms'] = 'Umbral de latencia interna en milisegundos';
$string['latencymsdesc'] = 'Avisa cuando la comprobación de Moodle a la base de datos supera esta duración, entre 10 y 10000 ms.';
$string['licensecommunity'] = 'Edición Community';
$string['licenseenterprise'] = 'Edición Enterprise';
$string['licenseexpired'] = 'Licencia vencida';
$string['licenseexpires'] = 'Vence el {$a}';
$string['licensegrace'] = 'Período de gracia de la licencia';
$string['licenseinstallation'] = 'Identificador de instalación {$a}';
$string['licenseprofessional'] = 'Edición Professional';
$string['licensestatus'] = 'Estado de licencia';
$string['livecron'] = 'Señal de cron';
$string['messageprovider:incident'] = 'Incidentes de monitoreo operativo';
$string['metric'] = 'Indicador';
$string['milliseconds'] = '{$a} ms';
$string['monitorcomponents'] = 'Comprobar componentes base';
$string['monitorcomponentsdesc'] = 'Lee el estado de instalación y activación de una lista fija de componentes de Moodle.';
$string['nativetasklogs'] = 'Abrir registros de tareas de Moodle';
$string['noevents'] = 'No se encontraron eventos de error coincidentes en las últimas 24 horas.';
$string['noincidents'] = 'Ningún incidente coincide con estos filtros.';
$string['nosamples'] = 'Pendiente de la primera observación programada.';
$string['noscheduledissues'] = 'No se encontraron tareas programadas atrasadas o en reintento.';
$string['notaskerrors'] = 'No se encontraron intentos fallidos de tareas en las últimas 24 horas.';
$string['notice_coverage'] = 'Se reanudaron las observaciones en {$a}';
$string['notice_opened'] = 'Incidente de monitoreo abierto en {$a}';
$string['notice_recovered'] = 'Incidente de monitoreo recuperado en {$a}';
$string['noticebody'] = 'Abre el informe de incidentes en Moodle para consultar los detalles.';
$string['notifyenabled'] = 'Activar notificaciones de incidentes';
$string['notifyenableddesc'] = 'Entrega las transiciones de incidentes mediante la mensajería de Moodle.';
$string['observed'] = 'Observado el';
$string['openincidents'] = 'Incidentes abiertos';
$string['overdue'] = 'Atrasada';
$string['overview'] = 'Resumen del monitoreo';
$string['percent'] = '{$a} %';
$string['periodlabel'] = 'Desde {$a->from} hasta {$a->until}, sin incluir el final.';
$string['pluginname'] = 'SkillUps System Monitor';
$string['privacy:metadata:incident'] = 'Los incidentes conservan el autor y la fecha de revisión hasta su eliminación o vencimiento.';
$string['privacy:metadata:messaging'] = 'La mensajería de Moodle gestiona las notificaciones enviadas a operadores designados.';
$string['privacy:metadata:notice'] = 'Registros de entrega para destinatarios autorizados.';
$string['privacy:metadata:phase'] = 'Transición del incidente que creó la notificación.';
$string['privacy:metadata:recipients'] = 'La configuración del sitio almacena los identificadores de destinatarios designados.';
$string['privacy:metadata:state'] = 'Estado de entrega de la notificación.';
$string['privacy:metadata:time'] = 'Fecha de la acción personal o del registro de entrega.';
$string['privacy:metadata:userid'] = 'Identificador asociado a una notificación o revisión de incidente.';
$string['privacy:path'] = 'Monitoreo operativo';
$string['probelatency'] = 'Latencia de la comprobación interna';
$string['queue_adhocdue'] = 'Tareas ad hoc atrasadas';
$string['queue_adhocfailed'] = 'Tareas ad hoc con retraso de reintento';
$string['queue_disabled'] = 'Tareas programadas desactivadas';
$string['queue_due'] = 'Tareas programadas atrasadas';
$string['queue_enabled'] = 'Tareas programadas activas';
$string['queue_failed'] = 'Tareas programadas con retraso de reintento';
$string['queue_running'] = 'Tareas programadas en ejecución';
$string['queuesummary'] = 'Colas de tareas de Moodle';
$string['queuewarn'] = 'Umbral de cantidad de tareas atrasadas';
$string['queuewarndesc'] = 'Avisa cuando la cola ad hoc atrasada alcanza esta cantidad, entre 1 y 1000.';
$string['readiness'] = 'Disponibilidad interna de Moodle';
$string['recentfailures'] = 'Intentos fallidos de tareas en los últimos 15 minutos';
$string['recipients'] = 'Destinatarios de incidentes';
$string['recipientsdesc'] = 'Introduce hasta 20 identificadores de usuarios de Moodle separados por comas o espacios. Cada destinatario debe tener acceso al monitor del sitio.';
$string['recovered'] = 'Recuperación registrada';
$string['recoveries'] = 'Observaciones para confirmar la recuperación';
$string['recoveriesdesc'] = 'Observaciones saludables consecutivas, entre 1 y 12.';
$string['retention'] = 'Retención en días';
$string['retentiondesc'] = 'Retención para muestras e incidentes cerrados, entre 30 y 365 días.';
$string['retrying'] = 'Espera de reintento';
$string['sampletimeline'] = 'Observaciones registradas';
$string['schedulecaption'] = 'Tareas programadas atrasadas o en reintento';
$string['scheduled'] = 'Programada';
$string['scrapeenabled'] = 'Permitir monitoreo externo autenticado';
$string['scrapeenableddesc'] = 'Requiere HTTPS en la URL canónica y el resumen SHA-256 del token en una variable de entorno del servidor.';
$string['seconds'] = '{$a} segundos';
$string['severity'] = 'Gravedad';
$string['skillupsmonitor:manage'] = 'Confirmar la revisión de incidentes';
$string['skillupsmonitor:view'] = 'Consultar informes de monitoreo del sitio';
$string['stale'] = 'Umbral sin observaciones en minutos';
$string['staledesc'] = 'Antigüedad a partir de la cual el monitor muestra datos desactualizados, entre 10 y 1440 minutos.';
$string['stalesample'] = 'La última observación programada está desactualizada. Revisa cron y la disponibilidad externa.';
$string['state'] = 'Estado';
$string['status_ambiguous'] = 'Envío sin confirmar';
$string['status_claimed'] = 'Envío en curso';
$string['status_down'] = 'No disponible internamente';
$string['status_error'] = 'Error';
$string['status_failed'] = 'Error de envío';
$string['status_maintenance'] = 'Mantenimiento';
$string['status_ok'] = 'Saludable';
$string['status_open'] = 'Abierto';
$string['status_pending'] = 'Pendiente';
$string['status_recovered'] = 'Recuperado';
$string['status_sent'] = 'Aceptado por mensajería';
$string['status_skipped'] = 'Omitido';
$string['status_unknown'] = 'Desconocido';
$string['status_up'] = 'Accesible internamente';
$string['status_warning'] = 'Advertencia';
$string['summarycsv'] = 'Descargar resumen CSV';
$string['taskclass'] = 'Clase de la tarea';
$string['taskerrorcaption'] = 'Intentos fallidos de tareas en las últimas 24 horas';
$string['tasklogunavailable'] = 'El registro predeterminado de tareas en la base de datos de Moodle no está disponible.';
$string['tasknotify'] = 'Entregar notificaciones de SkillUps System Monitor';
$string['taskpurge'] = 'Purgar observaciones vencidas de SkillUps System Monitor';
$string['tasksample'] = 'Recopilar observaciones de SkillUps System Monitor';
$string['tasksdetail'] = 'Diagnóstico de tareas';
$string['timelinecsv'] = 'Descargar cronología CSV';
$string['type'] = 'Tipo';
$string['usage'] = 'Indicadores de actividad de Moodle';
$string['value'] = 'Valor';
$string['view'] = 'Estado del incidente';
$string['view_all'] = 'Todos los incidentes';
