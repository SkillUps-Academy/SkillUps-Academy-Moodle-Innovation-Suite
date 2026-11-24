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
 * @package    report_skillupssecurity
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['about_debug'] = 'Moodle tiene configurada la visualización de mensajes de depuración.';
$string['about_defaultrole'] = 'Un rol predeterminado permite una capacidad administrativa seleccionada en su contexto nativo.';
$string['about_guest'] = 'El botón de invitados o su acceso automático está habilitado.';
$string['about_https'] = 'La URL configurada en Moodle carece del esquema HTTPS.';
$string['about_inactive'] = 'La cuenta activa alcanza el umbral de inactividad configurado.';
$string['about_login'] = 'Moodle permite consultar algunas páginas generales sin iniciar sesión.';
$string['about_passwordpolicy'] = 'La política local de contraseñas de Moodle está deshabilitada.';
$string['about_privilegedinactive'] = 'Una cuenta privilegiada alcanza el umbral de inactividad configurado.';
$string['about_registration'] = 'Hay un método de autorregistro configurado en Moodle.';
$string['about_suspendedprivilege'] = 'Una cuenta suspendida conserva la designación de administrador o asignaciones de rol pertinentes.';
$string['about_unusualscope'] = 'Un arquetipo docente, estudiantil o invitado tiene una asignación explícita de ámbito de sistema.';
$string['account'] = 'Cuenta';
$string['accountlimit'] = 'Límite de cuentas inactivas';
$string['accountlimitdesc'] = 'Máximo de cuentas afectadas por revisión. Rango de 100 a 5000.';
$string['action_capabilities'] = 'Capacidades de rol editadas';
$string['action_configuration'] = 'Opción sensible modificada';
$string['action_exportevents'] = 'Exportación de actividad';
$string['action_exportfindings'] = 'Exportación de hallazgos';
$string['action_exportinventory'] = 'Exportación de inventario';
$string['action_impersonation'] = 'Acceso como otro usuario';
$string['action_queuescan'] = 'Revisión encolada';
$string['action_review'] = 'Revisión de caso';
$string['action_roleassigned'] = 'Rol asignado';
$string['action_rolecreated'] = 'Rol creado';
$string['action_roledeleted'] = 'Rol eliminado';
$string['action_rolepolicy'] = 'Relación de roles editada';
$string['action_roleunassigned'] = 'Rol retirado';
$string['action_roleupdated'] = 'Rol actualizado';
$string['action_viewalerts'] = 'Consulta de alertas';
$string['action_viewdashboard'] = 'Consulta del panel';
$string['action_viewdetail'] = 'Consulta de hallazgo';
$string['action_viewevents'] = 'Consulta de actividad';
$string['action_viewfindings'] = 'Consulta de hallazgos';
$string['action_viewinventory'] = 'Consulta de inventario';
$string['action_viewroles'] = 'Consulta de matriz de roles';
$string['active'] = 'Observado';
$string['actor'] = 'Operador';
$string['alert_failed'] = 'Envío fallido';
$string['alert_sending'] = 'Envío reservado';
$string['alert_sent'] = 'Aceptado por mensajería Moodle';
$string['alert_skipped'] = 'Omitido o rechazado';
$string['alert_unknown'] = 'Envío incierto';
$string['alert_waiting'] = 'Pendiente';
$string['alertbody'] = 'Abre el informe protegido de Security Center para revisar los últimos cambios.';
$string['alerts'] = 'Envíos de alertas';
$string['alertsubject'] = 'Security Center registró nuevos hallazgos';
$string['all'] = 'Todos';
$string['alreadyqueued'] = 'Ya hay una revisión en cola o se solicitó hace poco.';
$string['archetype'] = 'Arquetipo del rol';
$string['assignmentlimit'] = 'Límite de asignaciones explícitas';
$string['assignmentlimitdesc'] = 'Máximo de asignaciones de sistema, categoría y curso de la muestra. Rango de 100 a 10 000.';
$string['busy'] = 'Otra auditoría está actualizando registros. Inténtalo más tarde.';
$string['capabilities'] = 'Capacidades seleccionadas';
$string['complete'] = 'Evaluado dentro del alcance definido';
$string['context'] = 'Contexto';
$string['coverage'] = 'Cobertura de controles';
$string['coveragewarning'] = 'Esta auditoría cubre señales específicas de Moodle. Sus resultados por sí solos no permiten determinar la seguridad del sitio.';
$string['dashboard'] = 'Resumen de seguridad';
$string['date'] = 'Fecha';
$string['detail'] = 'Detalle del hallazgo';
$string['empty'] = 'No hay registros para esta selección.';
$string['eventaccessed'] = 'Operación de informe en Security Center';
$string['events'] = 'Actividad administrativa';
$string['evidence'] = 'Evidencia';
$string['export'] = 'Exportar evidencias en CSV';
$string['exporthelp'] = 'Incluye hasta 10 000 registros de esta selección.';
$string['filter'] = 'Aplicar filtros';
$string['finding'] = 'Hallazgo';
$string['findings'] = 'Hallazgos';
$string['firstseen'] = 'Primera observación';
$string['fix_debug'] = 'Usa diagnóstico privado y deshabilita la visualización de depuración en producción.';
$string['fix_defaultrole'] = 'Revisa la definición del rol y del permiso según el principio de mínimo privilegio.';
$string['fix_guest'] = 'Confirma que se necesita la entrada de invitados y revisa por separado su acceso nativo a los cursos.';
$string['fix_https'] = 'Revisa la terminación HTTPS y la URL canónica con el responsable de infraestructura.';
$string['fix_inactive'] = 'Confirma las necesidades del titular y evalúa si la cuenta debe seguir activa.';
$string['fix_login'] = 'Revisa el contenido público previsto y exige autenticación cuando corresponda.';
$string['fix_passwordpolicy'] = 'Confirma qué métodos usan contraseñas locales y aplica la política institucional.';
$string['fix_privilegedinactive'] = 'Revisa los permisos con el titular y retira en Moodle los privilegios que ya no se necesitan.';
$string['fix_registration'] = 'Confirma la política de registro y revisa los controles de aprobación y supervisión.';
$string['fix_suspendedprivilege'] = 'Revisa las asignaciones conservadas antes de reactivar la cuenta.';
$string['fix_unusualscope'] = 'Verifica el motivo de la asignación y utiliza el contexto más específico que cumpla el requisito.';
$string['history'] = 'Historial de revisión';
$string['inactivedays'] = 'Umbral de inactividad de cuentas';
$string['inactivedaysdesc'] = 'Días desde el último acceso registrado o desde la creación si nunca se registró acceso. Rango de 7 a 730.';
$string['invalidreview'] = 'Selecciona un estado válido e indica un motivo de hasta 250 caracteres.';
$string['inventory'] = 'Inventario de privilegios';
$string['inventorydate'] = 'Inventario recopilado el {$a}';
$string['inventorypartial'] = 'Se alcanzó el límite de asignaciones. El inventario es parcial.';
$string['kind_confirmed'] = 'Hallazgo confirmado';
$string['kind_guide'] = 'Recomendación';
$string['kind_risk'] = 'Configuración de riesgo';
$string['kind'] = 'Clase de evidencia';
$string['lastaccess'] = 'Último acceso registrado';
$string['lastscan'] = 'Última revisión';
$string['lastseen'] = 'Última observación';
$string['messageprovider:risk'] = 'Notificación de hallazgos de seguridad';
$string['metadata'] = 'Metadatos';
$string['navigation'] = 'Navegación del informe de seguridad';
$string['none'] = 'Ninguna';
$string['noscan'] = 'Pendiente de la primera revisión';
$string['noscansyet'] = 'Todavía no se han recopilado datos de cobertura.';
$string['notifyenabled'] = 'Habilitar notificaciones de Moodle';
$string['notifyenableddesc'] = 'Los destinatarios reciben un mensaje genérico al aparecer hallazgos de la prioridad seleccionada.';
$string['notifyseverity'] = 'Prioridad mínima para notificar';
$string['notifyseveritydesc'] = 'Alta o media. La opción media incluye hallazgos de prioridad alta.';
$string['notrecorded'] = 'Sin registro';
$string['operation'] = 'Operación';
$string['partial'] = 'Cobertura parcial';
$string['plan_community'] = 'Comunidad';
$string['plan_enterprise'] = 'Empresarial';
$string['plan_expired'] = 'Vencido';
$string['plan_grace'] = 'Período de gracia';
$string['plan_professional'] = 'Profesional';
$string['plan'] = 'Plan {$a}';
$string['pluginname'] = 'SkillUps Security Center';
$string['presence'] = 'Estado de la observación';
$string['privacy:metadata:action'] = 'Tipo de registro';
$string['privacy:metadata:activity'] = 'Actividad administrativa';
$string['privacy:metadata:actorid'] = 'Operador de Moodle';
$string['privacy:metadata:capabilities'] = 'Permisos seleccionados';
$string['privacy:metadata:contextid'] = 'Contexto Moodle';
$string['privacy:metadata:detail'] = 'Metadatos administrativos permitidos';
$string['privacy:metadata:evidence'] = 'Hechos observados por el control';
$string['privacy:metadata:finding'] = 'Evidencia del hallazgo';
$string['privacy:metadata:findingid'] = 'Referencia del hallazgo';
$string['privacy:metadata:firstseen'] = 'Primera observación';
$string['privacy:metadata:history'] = 'Historial de revisiones';
$string['privacy:metadata:lastaccess'] = 'Último acceso registrado por Moodle';
$string['privacy:metadata:lastseen'] = 'Última observación';
$string['privacy:metadata:message'] = 'Mensajes de Moodle';
$string['privacy:metadata:note'] = 'Motivo de revisión';
$string['privacy:metadata:objectid'] = 'Referencia de operación';
$string['privacy:metadata:outbox'] = 'Envío de alertas';
$string['privacy:metadata:privilege'] = 'Inventario de privilegios';
$string['privacy:metadata:reviewstate'] = 'Estado de revisión';
$string['privacy:metadata:roleid'] = 'Identificador del rol';
$string['privacy:metadata:ruleid'] = 'Control que generó la evidencia';
$string['privacy:metadata:scanid'] = 'Identificador de revisión';
$string['privacy:metadata:state'] = 'Estado del envío';
$string['privacy:metadata:subjectid'] = 'Cuenta Moodle afectada';
$string['privacy:metadata:suspended'] = 'Estado de suspensión';
$string['privacy:metadata:timecreated'] = 'Fecha de creación';
$string['privacy:metadata:timesent'] = 'Fecha del intento de envío';
$string['privacy:metadata:userid'] = 'Cuenta Moodle observada o notificada';
$string['queued'] = 'Se encoló una revisión de seguridad.';
$string['recipients'] = 'IDs de usuarios destinatarios';
$string['recipientsdesc'] = 'Introduce hasta 20 IDs separados por comas. Cada destinatario debe tener permiso de consulta.';
$string['recommendation'] = 'Acción recomendada';
$string['reference'] = 'Referencia';
$string['resolved'] = 'Ya no se observa';
$string['retention'] = 'Retención de evidencia administrativa';
$string['retentiondesc'] = 'Días de retención de eventos, instantáneas anteriores y hallazgos resueltos. Rango de 7 a 730.';
$string['reviewnote'] = 'Motivo de la decisión de revisión';
$string['reviewsaved'] = 'Se registró la revisión.';
$string['reviewstate'] = 'Estado de revisión';
$string['role'] = 'Rol';
$string['roleid'] = 'ID del rol';
$string['roles'] = 'Auditoría de roles';
$string['rolesintro'] = 'Los permisos seleccionados se evalúan en el contexto de sistema. El inventario muestra las asignaciones pertinentes y el contexto de cada comprobación.';
$string['rule_debug'] = 'Depuración de PHP visible';
$string['rule_defaultrole'] = 'Rol predeterminado con permisos sensibles';
$string['rule_guest'] = 'Opciones de entrada de invitados habilitadas';
$string['rule_https'] = 'La URL canónica usa HTTP';
$string['rule_inactive'] = 'Cuenta sin acceso reciente registrado';
$string['rule_login'] = 'Revisar la política de acceso al sitio';
$string['rule_passwordpolicy'] = 'Política de contraseñas de Moodle deshabilitada';
$string['rule_privilegedinactive'] = 'Cuenta privilegiada sin acceso reciente';
$string['rule_registration'] = 'Autorregistro configurado';
$string['rule_suspendedprivilege'] = 'Cuenta suspendida con privilegios registrados';
$string['rule_unusualscope'] = 'Asignación de rol inusual en el sistema';
$string['runscan'] = 'Encolar revisión de seguridad';
$string['savereview'] = 'Guardar revisión';
$string['scanref'] = 'ID de revisión de la observación';
$string['setting'] = 'Opción de configuración';
$string['settings'] = 'Política de Security Center';
$string['severity_high'] = 'Alta';
$string['severity_low'] = 'Baja';
$string['severity_medium'] = 'Media';
$string['severity'] = 'Prioridad';
$string['shortname'] = 'Nombre corto';
$string['signature'] = 'Huella de correlación';
$string['siteadmin'] = 'Administrador del sitio';
$string['skillupssecurity:export'] = 'Exportar evidencia autorizada';
$string['skillupssecurity:inventory'] = 'Consultar evidencia de auditoría identificable';
$string['skillupssecurity:review'] = 'Registrar revisiones de hallazgos';
$string['skillupssecurity:run'] = 'Encolar auditorías de seguridad';
$string['skillupssecurity:view'] = 'Consultar informes de Security Center';
$string['snapshots'] = 'Inventarios históricos';
$string['state_accepted'] = 'Riesgo aceptado';
$string['state_detected'] = 'Detectado';
$string['state_new'] = 'Nuevo';
$string['state_recurred'] = 'Observado nuevamente';
$string['state_resolved'] = 'Ya no se observa';
$string['state_reviewing'] = 'En revisión';
$string['stream'] = 'Origen del evento';
$string['stream_native'] = 'Eventos nativos de Moodle';
$string['stream_report'] = 'Operaciones de Security Center';
$string['subject'] = 'Sujeto';
$string['suspended'] = 'Suspendida';
$string['systemactor'] = 'Sistema';
$string['taskcollect'] = 'Recopilar evidencia de Security Center';
$string['taskmaintain'] = 'Enviar alertas de Security Center y aplicar retención';
$string['threshold'] = 'Umbral en días';
$string['timecreated'] = 'Fecha de creación';
$string['unsupportedexport'] = 'Esta vista no permite exportar.';
