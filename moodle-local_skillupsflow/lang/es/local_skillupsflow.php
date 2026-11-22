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
 * SkillUps Flow Automation local skillupsflow.
 *
 * @package    local_skillupsflow
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Acciones';
$string['activate'] = 'Activar revisión';
$string['activated'] = 'Revisión activada.';
$string['active'] = 'Activa';
$string['actor'] = 'ID del actor';
$string['actorunavailable'] = 'El autor de esta revisión carece de acceso para ejecutar flujos.';
$string['addnode'] = 'Añadir nodo';
$string['all'] = 'Todos los estados';
$string['and'] = 'Todas (AND)';
$string['approvals'] = 'Aprobaciones';
$string['approvals30'] = 'En aprobación · 30 días';
$string['approvalsintro'] = 'Revisa decisiones pendientes de revisiones creadas por otros usuarios.';
$string['approve'] = 'Aprobar';
$string['body'] = 'Notificación de texto plano';
$string['busy'] = 'Este flujo está ocupado. Inténtalo de nuevo en breve.';
$string['cancelconfirm'] = 'Las acciones completadas se conservan. Confirma la cancelación para detener los pasos futuros.';
$string['cancelrun'] = 'Cancelar ejecución';
$string['canvas'] = 'Lienzo del flujo';
$string['completed30'] = 'Completadas · 30 días';
$string['confirmcancel'] = 'Confirmar cancelación';
$string['count'] = 'Cantidad';
$string['courseid'] = 'ID de curso';
$string['dailymax'] = 'Ejecuciones diarias por flujo';
$string['dailymaxdesc'] = 'Máximo de admisiones por día UTC, entre 1 y 5000. Cada flujo también admite hasta 100 ejecuciones pendientes.';
$string['dashboard'] = 'Panel de flujos';
$string['dashboardintro'] = 'Publica revisiones de flujos y supervisa su ejecución desde un solo lugar.';
$string['date'] = 'Fecha';
$string['decisionsaved'] = 'Decisión registrada.';
$string['decisionunavailable'] = 'La aprobación no está disponible o esta cuenta no puede decidirla.';
$string['delete'] = 'Quitar nodo';
$string['down'] = 'Mover abajo';
$string['downloadsample'] = 'Descargar plantilla de aprobación';
$string['draft'] = 'Última revisión';
$string['due'] = 'Fecha prevista';
$string['editconflict'] = 'Otro editor guardó primero una revisión. Abre nuevamente el flujo antes de guardar.';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Caducada';
$string['edition_grace'] = 'Periodo de gracia sin conexión';
$string['edition_professional'] = 'Professional';
$string['editor'] = 'Constructor de flujos';
$string['editorintro'] = 'Añade y mueve nodos, luego selecciona sus destinos en el inspector. Guarda una revisión antes de activarla.';
$string['emptyapprovals'] = 'No tienes aprobaciones disponibles ahora.';
$string['emptyflows'] = 'Crea un flujo o importa una plantilla para empezar.';
$string['emptyport'] = 'Terminar esta ruta';
$string['emptyruns'] = 'No hay ejecuciones que coincidan con los filtros.';
$string['entry'] = 'Nodo inicial';
$string['eventflowchanged'] = 'Cambió la configuración de un flujo SkillUps';
$string['export'] = 'Exportar plantilla';
$string['exportstats'] = 'Exportar CSV agregado';
$string['failed30'] = 'Fallidas · 30 días';
$string['filter'] = 'Filtrar';
$string['history'] = 'Historial';
$string['historyintro'] = 'Consulta las ejecuciones pasadas o pendientes y sus transiciones registradas.';
$string['import'] = 'Importar plantilla';
$string['imported'] = 'Plantilla importada como borrador.';
$string['inspector'] = 'Inspector de nodos';
$string['invaliddefinition'] = 'Comprueba los campos, el inicio y las conexiones. Debe poder alcanzarse cada nodo y se prohíben los ciclos.';
$string['label'] = 'Etiqueta del nodo';
$string['left'] = 'Mover a la izquierda';
$string['limitnodes'] = 'El flujo ya tiene 40 nodos.';
$string['messageprovider:workflow'] = 'Notificaciones de flujos SkillUps';
$string['messagetitle'] = 'Notificación de un flujo SkillUps';
$string['minutes'] = 'Minutos';
$string['name'] = 'Nombre del flujo';
$string['newflow'] = 'Nuevo flujo';
$string['node'] = 'Nodo';
$string['node_approval'] = 'Aprobación humana';
$string['node_condition'] = 'Condición';
$string['node_delay'] = 'Espera';
$string['node_enrol'] = 'Matricular usuario manualmente';
$string['node_notify'] = 'Enviar notificación';
$string['node_stop'] = 'Terminar flujo';
$string['node_suspend'] = 'Suspender o reactivar cuenta';
$string['node_visibility'] = 'Cambiar visibilidad del curso';
$string['nothingselected'] = 'Selecciona un nodo para configurarlo.';
$string['notificationfailed'] = 'Moodle no pudo confirmar el envío de la notificación.';
$string['one'] = '1 · Cuenta suspendida o curso visible';
$string['operator'] = 'Combinar condiciones';
$string['or'] = 'Cualquiera (OR)';
$string['outcome_actorunavailable'] = 'Cuenta ejecutora no disponible';
$string['outcome_applied'] = 'Acción aplicada';
$string['outcome_approved'] = 'Aprobada';
$string['outcome_awaiting'] = 'Pendiente de decisión';
$string['outcome_cancelled'] = 'Cancelada';
$string['outcome_completed'] = 'Completada';
$string['outcome_delayed'] = 'Espera programada';
$string['outcome_expired'] = 'Aprobación caducada';
$string['outcome_failed'] = 'Intentos agotados';
$string['outcome_forbidden'] = 'Permiso denegado';
$string['outcome_invalidstate'] = 'Estado de ejecución inválido';
$string['outcome_no'] = 'Rama falsa';
$string['outcome_notificationfailed'] = 'Envío sin confirmar';
$string['outcome_queued'] = 'En cola';
$string['outcome_rejected'] = 'Rechazada';
$string['outcome_retry'] = 'Reintento programado';
$string['outcome_sending'] = 'Intento de envío registrado';
$string['outcome_uncertain'] = 'Envío incierto; se evitó el reenvío automático';
$string['outcome_yes'] = 'Rama verdadera';
$string['pause'] = 'Pausar';
$string['paused'] = 'Flujo en pausa.';
$string['pluginname'] = 'SkillUps Flow Automation';
$string['port_error'] = 'En caso de error';
$string['port_next'] = 'Siguiente nodo';
$string['port_no'] = 'Si no se cumple, se rechaza o caduca';
$string['port_yes'] = 'Si se cumple o se aprueba';
$string['privacy:actor'] = 'ID del revisor o administrador.';
$string['privacy:course'] = 'Identificador del curso asociado.';
$string['privacy:definition'] = 'Grafo de flujo y su configuración.';
$string['privacy:flow'] = 'Las revisiones incluyen el autor y pueden incluir ID de objetivos configurados.';
$string['privacy:log'] = 'Transiciones de pasos y decisiones humanas.';
$string['privacy:outcome'] = 'Resultado del paso.';
$string['privacy:run'] = 'Ejecuciones de flujo asociadas a cuentas objetivo.';
$string['privacy:status'] = 'Estado de ejecución.';
$string['privacy:time'] = 'Momento de creación o actualización.';
$string['privacy:userid'] = 'ID de la cuenta objetivo o autora.';
$string['privacy:version'] = 'Revisiones de flujos, salvo supresión autorizada por privacidad.';
$string['protectedtarget'] = 'El objetivo o rol seleccionado está protegido.';
$string['published'] = 'Revisión activa';
$string['reject'] = 'Rechazar';
$string['renew'] = 'Usa este identificador al solicitar una licencia firmada a SkillUps Academy.';
$string['requiresjs'] = 'Habilita JavaScript para usar el constructor visual.';
$string['result'] = 'Resultado';
$string['retentiondays'] = 'Conservación de ejecuciones en días';
$string['retentiondaysdesc'] = 'Elimina ejecuciones terminales y sus registros tras 2 a 365 días.';
$string['revision'] = 'Revisión';
$string['revisions'] = 'Historial de revisiones';
$string['right'] = 'Mover a la derecha';
$string['roleid'] = 'ID de rol asignable';
$string['run'] = 'Ejecutar ahora';
$string['rununavailable'] = 'El flujo está en pausa o alcanzó su límite de admisión.';
$string['saved'] = 'Se guardó la revisión.';
$string['saverevision'] = 'Guardar revisión';
$string['settings'] = 'Configuración de flujos';
$string['skillupsflow:approve'] = 'Decidir aprobaciones de flujos';
$string['skillupsflow:manage'] = 'Gestionar y ejecutar flujos SkillUps';
$string['start'] = 'Hora de inicio (zona horaria del navegador)';
$string['state_approval'] = 'En aprobación';
$string['state_cancelled'] = 'Cancelada';
$string['state_completed'] = 'Completada';
$string['state_failed'] = 'Fallida';
$string['state_queued'] = 'En cola o en espera';
$string['state_sending'] = 'Envío pendiente de confirmación';
$string['status'] = 'Estado';
$string['subscription'] = 'Suscripción';
$string['subscriptiondetails'] = 'Instalación y renovación';
$string['targethint'] = 'Usa 0 para heredar el usuario o curso de la ejecución.';
$string['targetunavailable'] = 'Un objetivo no está disponible o no es apto para esta acción.';
$string['taskdispatch'] = 'Programar y recuperar flujos SkillUps';
$string['taskretention'] = 'Limpiar el historial de flujos SkillUps';
$string['templatefile'] = 'Archivo de plantilla (.json)';
$string['templates'] = 'Plantillas';
$string['templatesintro'] = 'Importa una plantilla JSON como borrador y revisa sus destinos antes de activarla.';
$string['test_activeuser'] = 'La cuenta objetivo está activa';
$string['test_enrolled'] = 'El usuario objetivo está matriculado activamente';
$string['test_hascourse'] = 'Existe el curso objetivo';
$string['test_hasuser'] = 'Existe el usuario objetivo';
$string['test_visiblecourse'] = 'El curso objetivo está visible';
$string['tests'] = 'Condiciones';
$string['transitions'] = 'Registro de ejecución';
$string['trigger'] = 'Disparador';
$string['trigger_course_completed'] = 'Curso completado';
$string['trigger_course_created'] = 'Curso creado';
$string['trigger_interval'] = 'Intervalo fijo';
$string['trigger_manual'] = 'Ejecución manual';
$string['trigger_once'] = 'Programación única';
$string['trigger_user_created'] = 'Usuario creado';
$string['trigger_user_enrolment_created'] = 'Usuario matriculado';
$string['triggerhint'] = 'En eventos, los ID distintos de cero restringen qué eventos coinciden. En ejecuciones manuales y programadas, definen los objetivos.';
$string['up'] = 'Mover arriba';
$string['userid'] = 'ID de usuario';
$string['value'] = 'Valor';
$string['workflows'] = 'Flujos';
$string['zero'] = '0 · Cuenta activa o curso oculto';
