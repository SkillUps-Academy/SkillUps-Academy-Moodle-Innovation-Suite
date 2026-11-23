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
 * Spanish Helpdesk strings.
 *
 * @package    local_skillupshelpdesk
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'No tienes acceso a este recurso de Helpdesk.';
$string['action_catalog'] = 'Catálogo actualizado';
$string['action_comment'] = 'Respuesta pública registrada';
$string['action_exportreport'] = 'Informe agregado exportado';
$string['action_internalnote'] = 'Nota interna registrada';
$string['action_manualescalation'] = 'Escalamiento manual registrado';
$string['action_notificationfailed'] = 'Se agotaron los reintentos del aviso';
$string['action_opened'] = 'Ticket abierto';
$string['action_rating'] = 'Encuesta de satisfacción enviada';
$string['action_resolvebreach'] = 'Plazo de resolución incumplido';
$string['action_responsebreach'] = 'Plazo de primera respuesta incumplido';
$string['action_triage'] = 'Clasificación actualizada';
$string['active'] = 'Activo';
$string['adminhistory'] = 'Actividad administrativa reciente';
$string['all'] = 'Todos';
$string['anonymous'] = 'Anónimo';
$string['applyfilters'] = 'Aplicar filtros';
$string['assignedtome'] = 'Asignados a mí';
$string['attachments'] = 'Adjuntos';
$string['attachments_help'] = 'Hasta tres archivos PDF, PNG, JPEG o de texto por mensaje. Se admiten hasta 5 MB por archivo o el límite inferior del sitio. La descarga requiere acceso al ticket y al mensaje.';
$string['busy'] = 'Este registro se está actualizando. Inténtalo de nuevo en unos instantes.';
$string['cataloglimit'] = 'Este catálogo alcanzó su límite de 500 registros.';
$string['catalogsaved'] = 'Registro del catálogo guardado.';
$string['catalogues'] = 'Catálogos de soporte';
$string['comment'] = 'Responder';
$string['commentsaved'] = 'Mensaje guardado.';
$string['content'] = 'Contenido';
$string['conversation'] = 'Conversación';
$string['courseid'] = 'Curso #{$a}';
$string['days'] = 'Últimos {$a} días';
$string['description'] = 'Describe el incidente';
$string['details'] = 'Detalles';
$string['editentry'] = 'Editar registro';
$string['escalate'] = 'Escalar ticket';
$string['escalationrecent'] = 'Este ticket se escaló recientemente. Espera 15 minutos antes de volver a intentarlo.';
$string['escalationsaved'] = 'Escalamiento registrado.';
$string['escalationuserid'] = 'ID del supervisor de escalamiento';
$string['escalationuserid_desc'] = 'Indica un usuario Moodle con permiso de supervisión. Cero usa al administrador principal. Los avisos contienen un mensaje genérico y un enlace al portal.';
$string['eventoperation'] = 'Operación de Helpdesk registrada';
$string['exportcsv'] = 'Descargar CSV';
$string['exportjson'] = 'Descargar JSON';
$string['firstdue'] = 'Vencimiento de primera respuesta';
$string['firstresponse'] = 'Primera respuesta técnica';
$string['goals'] = 'Respuesta {$a->response} min · Resolución {$a->resolution} min';
$string['group'] = 'Grupo';
$string['groupby'] = 'Agrupar informe por';
$string['history'] = 'Historial';
$string['holidays'] = 'Feriados';
$string['holidays_desc'] = 'Usa fechas locales en formato AAAA-MM-DD separadas por comas o saltos de línea. Hasta 200 fechas.';
$string['hours'] = 'Horario laboral';
$string['hours_desc'] = 'Usa días ISO del 1 (lunes) al 7 (domingo). Escribe una línea por día laboral. Ejemplo 1=09:00-12:00,13:00-17:00. Incluye al menos siete horas por semana.';
$string['inactive'] = 'Inactivo';
$string['initialrequired'] = 'Selecciona otro estado inicial activo antes de desactivar o modificar este.';
$string['initialstate'] = 'Estado inicial';
$string['internal'] = 'Nota exclusiva para técnicos';
$string['internalnote'] = 'Nota interna';
$string['invalidattachment'] = 'Adjunta hasta tres archivos locales PDF, PNG, JPEG o de texto dentro de los límites. El contenido debe coincidir con la extensión.';
$string['invalidbody'] = 'Escribe un mensaje de hasta 8.000 caracteres.';
$string['invalidcatalog'] = 'El registro seleccionado o su configuración no son válidos.';
$string['invalidholidays'] = 'Verifica las fechas y usa el formato AAAA-MM-DD.';
$string['invalidhours'] = 'Verifica los números de día, el orden de los turnos, los solapamientos y las horas de la semana.';
$string['invalidrating'] = 'Este ticket no admite esa valoración o ya fue valorado.';
$string['invalidtitle'] = 'Escribe un título de hasta 180 caracteres.';
$string['kind_article'] = 'Artículos';
$string['kind_assignee'] = 'Técnico';
$string['kind_campus'] = 'Recinto';
$string['kind_category'] = 'Categoría';
$string['kind_course'] = 'Curso';
$string['kind_priority'] = 'Prioridad';
$string['kind_severity'] = 'Severidad';
$string['kind_status'] = 'Estado';
$string['kind_template'] = 'Plantillas de respuesta';
$string['knowledge'] = 'Base de conocimientos';
$string['license_community'] = 'Community';
$string['license_enterprise'] = 'Enterprise';
$string['license_expired'] = 'Vencida';
$string['license_grace'] = 'Período de gracia sin conexión';
$string['license_professional'] = 'Professional';
$string['licenseexpiry'] = 'Vencimiento de la licencia {$a}';
$string['licensestatus'] = 'Estado de la suscripción — {$a}';
$string['loadtemplate'] = 'Cargar plantilla en la respuesta';
$string['manage'] = 'Administración';
$string['messageerased'] = 'Contenido del mensaje retirado.';
$string['messageprovider:ticketupdate'] = 'Actualizaciones de Helpdesk';
$string['metric_closed'] = 'Resueltos';
$string['metric_open'] = 'Abiertos';
$string['metric_overdue'] = 'Con incumplimiento de SLA';
$string['metric_total'] = 'Total';
$string['modified'] = 'Última actualización';
$string['mytickets'] = 'Mis tickets';
$string['name'] = 'Nombre';
$string['newentry'] = 'Añadir registro';
$string['noarticles'] = 'No hay artículos publicados que coincidan con la búsqueda.';
$string['nocourse'] = 'Sin curso asociado';
$string['noentries'] = 'Este catálogo está vacío.';
$string['nohistory'] = 'No hay actividad que mostrar.';
$string['nomessages'] = 'No hay mensajes que mostrar en esta página.';
$string['noreport'] = 'Ningún ticket coincide con los filtros del informe.';
$string['notickets'] = 'Ningún ticket coincide con estos filtros.';
$string['notificationbody'] = 'Hay una actualización en SkillUps Helpdesk. Inicia sesión en Moodle y abre el portal de soporte para revisar los tickets a los que tienes acceso.';
$string['notifications'] = 'Habilitar avisos';
$string['notifications_desc'] = 'Encola avisos genéricos mediante la mensajería de Moodle. El proveedor tiene el correo desactivado inicialmente y puede seguir preferencias del sitio o el usuario.';
$string['notificationsubject'] = 'Actualización de SkillUps Helpdesk';
$string['notrecorded'] = 'Pendiente';
$string['openticket'] = 'Abrir ticket';
$string['overdue'] = 'SLA incumplido';
$string['period'] = 'Fecha de creación';
$string['pluginname'] = 'SkillUps Helpdesk';
$string['portallinks'] = 'Navegación de Helpdesk';
$string['privacy:field:action'] = 'Nombre de la operación auditada.';
$string['privacy:field:assigneeid'] = 'Identificador del técnico asignado.';
$string['privacy:field:attempts'] = 'Cantidad de intentos de envío.';
$string['privacy:field:availableat'] = 'Fecha del próximo intento de envío.';
$string['privacy:field:body'] = 'Contenido del mensaje de soporte.';
$string['privacy:field:courseid'] = 'Identificador del curso asociado.';
$string['privacy:field:details'] = 'Datos estructurados de la operación.';
$string['privacy:field:firstdue'] = 'Plazo de primera respuesta.';
$string['privacy:field:firstresponse'] = 'Fecha de primera respuesta.';
$string['privacy:field:formerassignee'] = 'Identificador del técnico anterior.';
$string['privacy:field:internal'] = 'Indicador de visibilidad exclusiva para técnicos.';
$string['privacy:field:newassignee'] = 'Identificador del nuevo técnico.';
$string['privacy:field:ratedat'] = 'Fecha de valoración.';
$string['privacy:field:rating'] = 'Valoración de satisfacción.';
$string['privacy:field:requesterid'] = 'Identificador del solicitante.';
$string['privacy:field:resolvebreach'] = 'Fecha de detección del incumplimiento de resolución.';
$string['privacy:field:resolvedat'] = 'Fecha de resolución.';
$string['privacy:field:resolvedue'] = 'Plazo de resolución.';
$string['privacy:field:responsebreach'] = 'Fecha de detección del incumplimiento de respuesta.';
$string['privacy:field:ticketid'] = 'Identificador del ticket asociado.';
$string['privacy:field:timecreated'] = 'Fecha de creación.';
$string['privacy:field:timemodified'] = 'Fecha de actualización.';
$string['privacy:field:title'] = 'Asunto del ticket.';
$string['privacy:field:userid'] = 'Identificador del operador, autor o destinatario.';
$string['privacy:files'] = 'Adjuntos privados de tickets.';
$string['privacy:table:audit'] = 'Historial de soporte y configuración.';
$string['privacy:table:outbox'] = 'Envío de avisos genéricos pendientes.';
$string['privacy:table:post'] = 'Mensajes de soporte y notas de técnicos.';
$string['privacy:table:ticket'] = 'Solicitantes, asignaciones y mediciones de SLA.';
$string['processtask'] = 'Procesar SLA y avisos de Helpdesk';
$string['purgetask'] = 'Eliminar tickets vencidos de Helpdesk';
$string['ratedcount'] = 'Valoraciones';
$string['ratelimit'] = 'Hay demasiados envíos recientes. Inténtalo más tarde.';
$string['ratingmean'] = 'Valoración media';
$string['ratingsaved'] = 'Gracias por valorar el soporte recibido.';
$string['ratingvalue'] = 'Valoración de satisfacción {$a}/5';
$string['reports'] = 'Informes';
$string['requiredcatalog'] = 'Este catálogo debe conservar al menos una opción utilizable.';
$string['resolutiongoal'] = 'Objetivo de resolución en minutos laborales';
$string['resolvebreaches'] = 'Incumplimientos de resolución';
$string['resolvedat'] = 'Resolución registrada';
$string['resolvedticket'] = 'Este ticket resuelto es de solo lectura. Abre otro ticket si necesitas más ayuda.';
$string['resolvedue'] = 'Vencimiento de resolución';
$string['responsebreaches'] = 'Incumplimientos de primera respuesta';
$string['responsegoal'] = 'Objetivo de primera respuesta en minutos laborales';
$string['retention'] = 'Retención de tickets cerrados en días';
$string['retention_desc'] = 'La tarea diaria elimina tickets terminales vencidos y sus adjuntos. Los tickets activos se conservan.';
$string['satisfaction'] = '¿Qué tan satisfecho quedaste? 1 es el mínimo y 5 es el máximo.';
$string['savetriage'] = 'Guardar clasificación';
$string['search'] = 'Buscar';
$string['searchknowledge'] = 'Buscar artículos';
$string['searchtitle'] = 'Buscar por título';
$string['seed_closed'] = 'Cerrado';
$string['seed_critical'] = 'Impacto crítico';
$string['seed_defaultcampus'] = 'Recinto principal';
$string['seed_general'] = 'Soporte general';
$string['seed_major'] = 'Impacto alto';
$string['seed_minor'] = 'Impacto bajo';
$string['seed_normal'] = 'Normal';
$string['seed_open'] = 'Abierto';
$string['seed_progress'] = 'En progreso';
$string['seed_resolved'] = 'Resuelto';
$string['seed_technical'] = 'Incidente técnico';
$string['seed_urgent'] = 'Urgente';
$string['seed_waiting'] = 'En espera de información';
$string['selectrating'] = 'Selecciona tu valoración';
$string['selecttemplate'] = 'Selecciona una plantilla de respuesta';
$string['sendcomment'] = 'Enviar respuesta';
$string['sendsurvey'] = 'Enviar valoración';
$string['settings'] = 'Configuración del sitio';
$string['skillupshelpdesk:configure'] = 'Administrar catálogos y configuración de Helpdesk';
$string['skillupshelpdesk:request'] = 'Abrir y consultar tickets de soporte propios';
$string['skillupshelpdesk:supervise'] = 'Supervisar y asignar todos los tickets';
$string['skillupshelpdesk:work'] = 'Trabajar en tickets de soporte asignados';
$string['staffqueue'] = 'Bandeja de técnicos';
$string['statusinuse'] = 'La condición de resolución de un estado existente es fija. Crea otro estado para usar una condición distinta.';
$string['systemactor'] = 'Sistema';
$string['terminal'] = 'Resuelve el ticket';
$string['ticketcreated'] = 'Ticket abierto.';
$string['ticketnumber'] = 'Ticket #{$a}';
$string['tickets'] = 'Tickets';
$string['ticketupdated'] = 'Ticket actualizado.';
$string['ticketviews'] = 'Vistas del ticket';
$string['timezone'] = 'Zona horaria del SLA';
$string['timezone_desc'] = 'Zona horaria que se usa para turnos y feriados al fijar los plazos de nuevos tickets.';
$string['title'] = 'Título';
$string['triage'] = 'Clasificación y asignación';
$string['unassigned'] = 'Sin asignar';
$string['unavailableuser'] = 'Cuenta asignada no disponible';
$string['view'] = 'Vista de tickets';
