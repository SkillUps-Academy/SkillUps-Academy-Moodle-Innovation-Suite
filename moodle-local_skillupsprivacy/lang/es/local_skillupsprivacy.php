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
 * Spanish strings for SkillUps Privacy Manager.
 *
 * @package    local_skillupsprivacy
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['accessretention'] = 'Conservación de la auditoría del panel en días';
$string['accessretentionhelp'] = 'Limpieza programada de accesos al panel y a exportaciones. Rango efectivo de 30 a 730 días.';
$string['activecount'] = 'Casos activos';
$string['actor'] = 'Operador';
$string['archived'] = 'Este caso de privacidad ya no está disponible.';
$string['assess'] = 'Revisar la retención de datos';
$string['busy'] = 'El registro está ocupado. Intenta la operación de nuevo.';
$string['casedata'] = 'Detalles del caso';
$string['casedetail'] = 'Caso de privacidad #{$a}';
$string['caseid'] = 'ID del caso';
$string['componentsearch'] = 'Componente o nombre de la extensión';
$string['confirmdecision'] = 'Confirmar decisión interna';
$string['deadline'] = 'Plazo operativo';
$string['decision'] = 'Registrar una decisión';
$string['decisionhelp'] = 'La aprobación interna autoriza la remisión. Revisa todos los sistemas afectados y las obligaciones de conservación si la solicitud implica eliminación o anonimización.';
$string['duedays'] = 'Plazo operativo predeterminado en días';
$string['duedayshelp'] = 'Plazo interno inicial para casos nuevos. Rango efectivo de 1 a 365 días.';
$string['dueonly'] = 'Solo vencidos';
$string['edition'] = 'Edición';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Licencia vencida';
$string['edition_grace'] = 'Período de gracia sin conexión';
$string['edition_professional'] = 'Professional';
$string['empty'] = 'Esta vista no contiene registros.';
$string['eventcaseactivity'] = 'Operación del registro de privacidad registrada';
$string['evidence'] = 'Descargar CSV de evidencias';
$string['extended'] = 'Incluir revisión y marcas de tiempo de conciliación';
$string['filter'] = 'Aplicar filtros';
$string['heldcount'] = 'Casos activos en retención';
$string['history'] = 'Historial del caso';
$string['intakehelp'] = 'Usa los ID de Moodle. El alcance de revisión puede ser el sitio, una categoría, un curso o el contexto de usuario del titular. Usa Solicitudes de Moodle para vincular una exportación o eliminación nativa.';
$string['invalidcase'] = 'Revisa el titular, el tipo de solicitud y el contexto de revisión.';
$string['invaliddecision'] = 'Esta decisión no está disponible para el caso actual.';
$string['invalidroute'] = 'Selecciona un responsable de privacidad autorizado y un plazo válido.';
$string['inventoryhelp'] = 'Este inventario muestra las declaraciones técnicas de las extensiones de Moodle instaladas. Revisa sus prácticas de gestión de datos por separado.';
$string['kind'] = 'Tipo de solicitud';
$string['kind_access'] = 'Acceso';
$string['kind_anonymize'] = 'Anonimización';
$string['kind_delete'] = 'Eliminación';
$string['kind_export'] = 'Exportación';
$string['licenseend'] = 'Vencimiento de licencia';
$string['messagebody'] = 'Un caso asignado está próximo a vencer o ha superado su plazo operativo. Consulta tus casos en el registro de privacidad.';
$string['messageprovider:deadline'] = 'Avisos de vencimiento de casos de privacidad';
$string['messagesubject'] = 'Vencimiento de un caso de privacidad';
$string['metadata_declared'] = 'Declaración de privacidad disponible';
$string['metadata_incomplete'] = 'Declaración incompleta';
$string['metadata_nodata'] = 'Declara que no gestiona datos personales';
$string['mine'] = 'Asignados a mí';
$string['native_approved'] = 'Aprobada en Moodle';
$string['native_awaiting'] = 'Pendiente de aprobación';
$string['native_cancelled'] = 'Cancelada';
$string['native_complete'] = 'Completada';
$string['native_deleted'] = 'Eliminación completada';
$string['native_expired'] = 'Descarga vencida';
$string['native_missing'] = 'Referencia no disponible';
$string['native_pending'] = 'Pendiente';
$string['native_preprocessing'] = 'En preparación';
$string['native_processing'] = 'En proceso';
$string['native_ready'] = 'Disponible para descarga';
$string['native_rejected'] = 'Rechazada';
$string['native_unknown'] = 'Estado desconocido';
$string['native_unlinked'] = 'Caso independiente';
$string['nativehelp'] = 'Moodle gestiona las decisiones nativas, el procesamiento y la entrega de archivos.';
$string['nativeid'] = 'Solicitud de Moodle';
$string['nativeinvalid'] = 'La referencia de Moodle no coincide con el titular y el tipo de solicitud.';
$string['nativelinked'] = 'Esta solicitud de Moodle ya está en seguimiento.';
$string['nativepending'] = 'Confirma la finalización en la solicitud vinculada de Moodle antes de cerrar el caso.';
$string['nativestate'] = 'Estado en Moodle';
$string['newstate'] = 'Nuevo estado interno';
$string['nopurpose'] = 'Moodle no tiene una finalidad efectiva para esta selección.';
$string['notice_failed'] = 'Fallido';
$string['notice_sending'] = 'En envío';
$string['notice_sent'] = 'Enviado';
$string['notice_skipped'] = 'Omitido';
$string['notice_uncertain'] = 'Entrega incierta';
$string['notifyenabled'] = 'Activar avisos de vencimiento en Moodle';
$string['notifyenabledhelp'] = 'Envía un aviso genérico previo al vencimiento y otro cuando vence el plazo si el responsable sigue autorizado.';
$string['notrecorded'] = 'No disponible';
$string['op_created'] = 'Caso registrado';
$string['op_export'] = 'Evidencias descargadas';
$string['op_notified'] = 'Aviso procesado';
$string['op_refresh'] = 'Actualización manual de estado';
$string['op_retention'] = 'Evaluación de retención registrada';
$string['op_routed'] = 'Asignación o plazo modificado';
$string['op_synced'] = 'Estado de Moodle actualizado';
$string['op_transition'] = 'Decisión registrada';
$string['op_viewaudit'] = 'Auditoría de accesos consultada';
$string['op_viewcase'] = 'Caso consultado';
$string['op_viewintake'] = 'Formulario de registro consultado';
$string['op_viewinventory'] = 'Inventario consultado';
$string['op_viewnative'] = 'Solicitudes nativas consultadas';
$string['op_viewpolicy'] = 'Política de retención consultada';
$string['op_viewregistry'] = 'Registro consultado';
$string['op_viewreport'] = 'Informe consultado';
$string['opencase'] = 'Abrir caso en seguimiento';
$string['opennative'] = 'Abrir solicitudes nativas de Moodle';
$string['openpolicy'] = 'Abrir el registro de datos de Moodle';
$string['operation'] = 'Operación';
$string['overduecount'] = 'Casos vencidos';
$string['owner'] = 'ID de usuario del responsable';
$string['pluginname'] = 'SkillUps Privacy Manager';
$string['policyhelp'] = 'Consulta la finalidad efectiva en Moodle para un contexto de sitio, categoría o curso.';
$string['previous'] = 'Anterior';
$string['privacy:metadata:actorid'] = 'Usuario que realizó la operación.';
$string['privacy:metadata:archived'] = 'Indica si el caso está fuera de acceso operativo.';
$string['privacy:metadata:audit'] = 'Historial de accesos privilegiados y decisiones.';
$string['privacy:metadata:case'] = 'Caso interno de privacidad.';
$string['privacy:metadata:caseid'] = 'Referencia al caso interno.';
$string['privacy:metadata:checkedat'] = 'Fecha de la última comprobación de estado.';
$string['privacy:metadata:contextid'] = 'Alcance en Moodle de la revisión de retención.';
$string['privacy:metadata:createdby'] = 'Responsable que registró el caso.';
$string['privacy:metadata:due'] = 'Plazo operativo.';
$string['privacy:metadata:kind'] = 'Tipo de solicitud institucional.';
$string['privacy:metadata:level'] = 'Nivel de urgencia del aviso.';
$string['privacy:metadata:message'] = 'Avisos genéricos de vencimiento enviados por la mensajería de Moodle.';
$string['privacy:metadata:nativeid'] = 'Referencia a la solicitud nativa.';
$string['privacy:metadata:nativestatus'] = 'Último estado observado de la solicitud.';
$string['privacy:metadata:note'] = 'Motivo breve de la decisión, de acceso restringido.';
$string['privacy:metadata:notice'] = 'Registro de entrega de avisos de vencimiento.';
$string['privacy:metadata:operation'] = 'Operación registrada para rendición de cuentas.';
$string['privacy:metadata:ownerid'] = 'Responsable asignado al caso.';
$string['privacy:metadata:previous'] = 'Valor operativo anterior.';
$string['privacy:metadata:priorid'] = 'Responsable anterior.';
$string['privacy:metadata:result'] = 'Valor operativo resultante.';
$string['privacy:metadata:retention'] = 'Evaluación interna de la retención.';
$string['privacy:metadata:revision'] = 'Revisión del caso para el control de concurrencia.';
$string['privacy:metadata:state'] = 'Estado del flujo interno.';
$string['privacy:metadata:status'] = 'Resultado de entrega del aviso.';
$string['privacy:metadata:targetid'] = 'Nuevo responsable.';
$string['privacy:metadata:timecreated'] = 'Fecha de creación.';
$string['privacy:metadata:timemodified'] = 'Fecha de la última edición.';
$string['privacy:metadata:userid'] = 'El titular o destinatario del aviso.';
$string['protected'] = 'Un contexto de revisión protegido o no disponible impide liberar la retención de este caso.';
$string['protection'] = 'Protegido en Moodle';
$string['purpose'] = 'Finalidad en Moodle';
$string['reason'] = 'Motivo';
$string['reasonrequired'] = 'Ingresa un motivo de entre 1 y 300 caracteres.';
$string['refresh'] = 'Actualizar estado de Moodle';
$string['register'] = 'Registrar caso';
$string['registered'] = 'Fecha de registro';
$string['reminders'] = 'Avisos';
$string['reporthelp'] = 'Los recuentos reflejan el registro operativo actual y excluyen los casos eliminados. El informe documenta la actividad interna.';
$string['restricted'] = 'El acceso requiere ser responsable de privacidad o administrador del sitio de Moodle y tener la capacidad correspondiente de SkillUps.';
$string['result'] = 'Resultado';
$string['retention'] = 'Evaluación de retención';
$string['retention_cleared'] = 'Retención revisada para remisión';
$string['retention_held'] = 'Retención obligatoria';
$string['retention_pending'] = 'Evaluación pendiente';
$string['retentionperiod'] = 'Período de conservación';
$string['retentionrequired'] = 'Registra una evaluación favorable de retención antes de aprobar la remisión.';
$string['reviewcontext'] = 'ID del contexto de revisión (0 para el sitio)';
$string['routing'] = 'Asignación y plazo';
$string['saveassessment'] = 'Guardar evaluación';
$string['saved'] = 'Se actualizó el caso.';
$string['skillupsprivacy:decide'] = 'Evaluar la retención, resolver casos y consultar accesos privilegiados';
$string['skillupsprivacy:export'] = 'Descargar evidencias de operaciones de privacidad';
$string['skillupsprivacy:inventory'] = 'Consultar declaraciones de Privacy API y retención';
$string['skillupsprivacy:manage'] = 'Registrar y asignar casos de privacidad';
$string['skillupsprivacy:view'] = 'Consultar el registro de privacidad';
$string['stale'] = 'Este caso cambió o fue cerrado. Recárgalo antes de continuar.';
$string['state'] = 'Estado interno';
$string['state_approved'] = 'Aprobado para remisión';
$string['state_closed'] = 'Cerrado';
$string['state_open'] = 'Abierto';
$string['state_rejected'] = 'Rechazado';
$string['state_review'] = 'En revisión';
$string['subject'] = 'ID de usuario del titular';
$string['systemactor'] = 'Sistema u operador anonimizado';
$string['taskreconcile'] = 'Conciliar casos de privacidad y enviar avisos';
$string['track'] = 'Registrar seguimiento';
$string['updaterouting'] = 'Guardar asignación';
$string['view_audit'] = 'Auditoría de accesos';
$string['view_cases'] = 'Registro de casos';
$string['view_inventory'] = 'Inventario de datos';
$string['view_native'] = 'Solicitudes de Moodle';
$string['view_policy'] = 'Política de retención';
$string['view_reports'] = 'Informes';
$string['warningdays'] = 'Antelación de los avisos en días';
$string['warningdayshelp'] = 'Avisar a responsables de casos activos que venzan en este período. Rango efectivo de 1 a 30 días.';
