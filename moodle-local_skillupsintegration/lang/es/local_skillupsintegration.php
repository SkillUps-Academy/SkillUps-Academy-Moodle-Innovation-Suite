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
 * Spanish strings for SkillUps Integration Hub.
 *
 * @package    local_skillupsintegration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addconnector'] = 'Agregar conector';
$string['allconnectors'] = 'Todos los conectores';
$string['approve'] = 'Aprobar y aplicar';
$string['approvedby'] = 'Operador que aprobó';
$string['audit'] = 'Auditoría operativa';
$string['auditdays'] = 'Días de conservación del historial y la auditoría';
$string['auditdayshelp'] = 'Controla el historial operativo y el período de deduplicación de eventos. Rango efectivo de 30 a 730 días.';
$string['auth_contract'] = 'Bearer y eventos entrantes firmados opcionales';
$string['auth_google'] = 'OAuth 2.0 delegado';
$string['auth_inbound'] = 'Tokens REST de Moodle o webhooks firmados';
$string['auth_microsoft'] = 'OAuth 2.0 de aplicación';
$string['authorization'] = 'Aprobación de la aplicación';
$string['authorize'] = 'Autorizar';
$string['availability'] = 'Activación';
$string['categoryid'] = 'ID de la categoría de destino';
$string['categoryid_help'] = 'Ingresa una categoría de cursos de Moodle existente. Los cursos se crean ocultos.';
$string['configintro'] = 'Selecciona el proveedor externo autorizado y la categoría de Moodle. Aprovisiona secretos desde el entorno del servidor. El conector genera simulaciones antes de escribir en Moodle.';
$string['connector'] = 'Conector';
$string['connectors'] = 'Conectores';
$string['courses'] = 'Incluir cursos';
$string['dashboard'] = 'Panel de integraciones';
$string['date'] = 'Fecha de registro';
$string['decision'] = 'Acción prevista';
$string['decision_conflict'] = 'Conflicto';
$string['decision_create'] = 'Crear';
$string['decision_invalid'] = 'Fila inválida';
$string['decision_same'] = 'Sin cambios';
$string['decision_update'] = 'Actualizar';
$string['disabled'] = 'Desactivado';
$string['editconnector'] = 'Editar conector';
$string['edition'] = 'Edición';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Vencida';
$string['edition_grace'] = 'Período de gracia sin conexión';
$string['edition_professional'] = 'Professional';
$string['empty'] = 'No hay registros que coincidan.';
$string['emptysources'] = 'Agrega un conector para empezar a recibir o simular registros.';
$string['enabled'] = 'Activado';
$string['endpoint'] = 'Endpoint o inquilino del proveedor';
$string['endpoint_help'] = 'En Google usa my_customer o un dominio Workspace. En Microsoft usa el UUID del inquilino Entra. Para SIS y RR. HH. usa la URL base HTTPS de un gateway autorizado.';
$string['entity'] = 'Entidad';
$string['entity_course'] = 'Curso';
$string['entity_unknown'] = 'Entidad desconocida';
$string['entity_user'] = 'Usuario';
$string['err_busy'] = 'El conector está ocupado o tiene demasiadas simulaciones pendientes. Inténtalo más tarde.';
$string['err_changed'] = 'Moodle cambió después de preparar la simulación.';
$string['err_collision'] = 'Un registro de Moodle ya utiliza este usuario, correo o nombre corto de curso.';
$string['err_config'] = 'Revisa la configuración y los ámbitos seleccionados del conector.';
$string['err_confirm'] = 'Revisa la simulación y confirma la aprobación.';
$string['err_credential'] = 'Falló la autenticación del proveedor o las credenciales no están disponibles.';
$string['err_duplicate'] = 'El origen envió esta identidad más de una vez.';
$string['err_endpoint'] = 'El endpoint o la continuación queda fuera del origen HTTPS autorizado.';
$string['err_entity'] = 'El tipo de entidad no está disponible para este conector.';
$string['err_identity'] = 'Cambió un campo de identidad protegido o el destino del curso.';
$string['err_limit'] = 'Un cuerpo, página o simulación superó los límites de procesamiento.';
$string['err_localchange'] = 'Los campos gestionados en Moodle se editaron desde la última aplicación.';
$string['err_mapping'] = 'El mapeo contiene un campo, una ruta o una operación que no se admite.';
$string['err_missing'] = 'Falta un objeto de Moodle vinculado anteriormente.';
$string['err_nothing'] = 'Esta simulación no tiene cambios aplicables.';
$string['err_permission'] = 'El operador carece de autorización para esta operación o destino.';
$string['err_processing'] = 'No se pudo procesar esta operación.';
$string['err_protected'] = 'La cuenta vinculada está protegida o no está disponible.';
$string['err_record'] = 'Un registro tiene campos obligatorios ausentes o inválidos.';
$string['err_replay'] = 'Esta referencia de evento ya corresponde a otro contenido.';
$string['err_response'] = 'Los datos no coinciden con el contrato JSON previsto.';
$string['err_signature'] = 'No se pudo autenticar la solicitud firmada.';
$string['err_stale'] = 'El conector o la simulación cambió o caducó. Obtén una simulación vigente antes de aplicar.';
$string['err_storage'] = 'Un error temporal de almacenamiento interrumpió esta operación.';
$string['err_unavailable'] = 'El conector, la ejecución o la operación seleccionada no está disponible.';
$string['err_unreachable'] = 'No se pudo contactar al proveedor mediante una conexión TLS autorizada.';
$string['err_upstream'] = 'El servicio externo rechazó la solicitud o no está disponible temporalmente.';
$string['error'] = 'Error';
$string['evidence'] = 'Descargar evidencia de auditoría CSV';
$string['field_email'] = 'Correo electrónico';
$string['field_firstname'] = 'Nombre';
$string['field_fullname'] = 'Nombre completo del curso';
$string['field_lastname'] = 'Apellido';
$string['field_shortname'] = 'Nombre corto del curso';
$string['field_username'] = 'Nombre de usuario';
$string['health'] = 'Última comprobación del conector';
$string['history'] = 'Historial de ejecuciones';
$string['items'] = 'Filas de la simulación';
$string['latency'] = '{$a} ms';
$string['mapping'] = 'Mapeo de campos JSON';
$string['mapping_help'] = 'Configura la entidad y el campo canónico, con una ruta de origen y transformaciones opcionales. Las operaciones disponibles son trim, lower, upper y collapse. Vacío usa los valores predeterminados del proveedor.';
$string['name'] = 'Nombre del conector';
$string['nextretry'] = 'Siguiente reintento';
$string['op_approved'] = 'Aplicación autorizada';
$string['op_backoff'] = 'Reintento programado';
$string['op_configured'] = 'Configuración guardada';
$string['op_done'] = 'Ejecución completada';
$string['op_expired'] = 'Ejecución caducada';
$string['op_exported'] = 'Auditoría exportada';
$string['op_failed'] = 'Ejecución fallida';
$string['op_partial'] = 'Ejecución completada con incidencias';
$string['op_previewed'] = 'Simulación iniciada';
$string['op_retried'] = 'Ejecución reintentada';
$string['op_sealed'] = 'Simulación lista';
$string['op_superseded'] = 'Ejecución sustituida';
$string['operator'] = 'ID del operador';
$string['origin'] = 'Origen';
$string['origin_pull'] = 'Conector externo';
$string['origin_rest'] = 'Servicio REST de Moodle';
$string['origin_webhook'] = 'Webhook firmado';
$string['pluginname'] = 'SkillUps Integration Hub';
$string['policy'] = 'Política de conflictos';
$string['policy_help'] = 'Puedes conservar las ediciones locales o preferir los valores nuevos de campos gestionados del origen. Ambas políticas rechazan simulaciones desactualizadas, cambios de identidad y colisiones.';
$string['policy_preserve'] = 'Conservar ediciones de Moodle';
$string['policy_source'] = 'Preferir los valores del origen en una nueva simulación';
$string['privacy:external'] = 'Los proveedores configurados pueden recibir solicitudes de autenticación y parámetros de consulta. Desde ellos se importan los datos de directorio y cursos.';
$string['privacy:field:action'] = 'Código del evento operativo.';
$string['privacy:field:actorid'] = 'El operador que envió o cambió la integración.';
$string['privacy:field:approverid'] = 'El operador que aprobó los cambios nativos.';
$string['privacy:field:continuation'] = 'Continuación temporal y opaca del proveedor.';
$string['privacy:field:enabled'] = 'Indica si el conector está activo.';
$string['privacy:field:entity'] = 'Indica si el registro representa un usuario o un curso.';
$string['privacy:field:errorcode'] = 'Código estructurado de error.';
$string['privacy:field:externalkey'] = 'Hash de la identidad del origen.';
$string['privacy:field:finger'] = 'Resumen hash de valores gestionados en la última aplicación.';
$string['privacy:field:name'] = 'Nombre descriptivo de la integración.';
$string['privacy:field:options'] = 'Ajustes de endpoint y mapeo.';
$string['privacy:field:origin'] = 'Método de envío.';
$string['privacy:field:ownerid'] = 'Cuenta responsable de la recepción firmada.';
$string['privacy:field:payload'] = 'Valores mapeados previstos para revisión.';
$string['privacy:field:pushuserid'] = 'Cuenta REST de Moodle autorizada.';
$string['privacy:field:requesthash'] = 'Resumen hash del evento original para idempotencia.';
$string['privacy:field:requestkey'] = 'Referencia hash de idempotencia del evento.';
$string['privacy:field:runid'] = 'Referencia de la simulación.';
$string['privacy:field:sourceid'] = 'Referencia del conector.';
$string['privacy:field:state'] = 'Resultado del flujo.';
$string['privacy:field:subject'] = 'Hash del correo de una cuenta temporal.';
$string['privacy:field:targetid'] = 'ID de Moodle de un usuario o curso gestionado por el origen.';
$string['privacy:field:timecreated'] = 'Fecha de registro.';
$string['privacy:field:type'] = 'Familia del proveedor.';
$string['privacy:field:userid'] = 'El usuario identificado por un registro temporal.';
$string['privacy:table:audit'] = 'Historial de operaciones de integración.';
$string['privacy:table:item'] = 'Datos canónicos temporales de usuario o curso y su decisión.';
$string['privacy:table:link'] = 'Identidad seudonimizada de origen vinculada a un objeto creado por un conector.';
$string['privacy:table:run'] = 'Simulación y registro de autorización de aplicación.';
$string['privacy:table:src'] = 'Configuración del conector y cuentas operativas autorizadas.';
$string['proposed'] = 'Campos propuestos';
$string['pushuserid'] = 'ID del usuario de servicio REST';
$string['pushuserid_help'] = 'Cuenta de Moodle con capacidad de recepción. Déjalo vacío cuando la recepción REST esté desactivada para este conector.';
$string['records'] = 'Filas recibidas del origen';
$string['refresh'] = 'Actualizar estado';
$string['result'] = 'Resultado';
$string['retry'] = 'Reintentar ejecución';
$string['reviewed'] = 'Revisé los cambios propuestos y autorizo esta simulación para modificar Moodle.';
$string['rundetail'] = 'Ejecución de integración #{$a}';
$string['runid'] = 'ID de la ejecución';
$string['saved'] = 'Se actualizó la integración.';
$string['secretref'] = 'Referencia de credenciales del entorno';
$string['secretref_help'] = 'Ingresa el nombre de la variable SKILLUPS_HUB_ aprovisionada. Al editar, déjala vacía para conservar la referencia actual. Aprovisiona las claves fuera de Moodle.';
$string['simulate'] = 'Ejecutar simulación';
$string['skillupsintegration:apply'] = 'Aprobar y aplicar simulaciones de integración';
$string['skillupsintegration:ingest'] = 'Enviar eventos para un conector REST designado';
$string['skillupsintegration:manage'] = 'Configurar conectores de integración';
$string['skillupsintegration:preview'] = 'Generar y revisar simulaciones de integración';
$string['skillupsintegration:view'] = 'Consultar estado y auditoría de integraciones';
$string['stagedays'] = 'Días de conservación de datos de simulación';
$string['stagedayshelp'] = 'Los datos, cursores y resultados por fila caducan al finalizar este período. Rango efectivo de 1 a 30 días.';
$string['state_applied'] = 'Aplicada';
$string['state_applying'] = 'Aplicando';
$string['state_conflict'] = 'Conflicto';
$string['state_done'] = 'Completada';
$string['state_expired'] = 'Caducada';
$string['state_failed'] = 'Fallida';
$string['state_healthy'] = 'Contacto con el origen correcto';
$string['state_new'] = 'Pendiente de primera comprobación';
$string['state_partial'] = 'Completada con incidencias';
$string['state_planned'] = 'Planificada';
$string['state_queued'] = 'En cola';
$string['state_ready'] = 'Lista para revisión';
$string['state_same'] = 'Sin cambios';
$string['state_superseded'] = 'Sustituida';
$string['taskdispatch'] = 'Distribuir tareas y aplicar la retención de integraciones';
$string['type'] = 'Tipo de proveedor';
$string['type_google'] = 'Google Workspace';
$string['type_hr'] = 'Sistemas de recursos humanos';
$string['type_inbound'] = 'REST y webhooks';
$string['type_microsoft'] = 'Microsoft 365';
$string['type_sis'] = 'Sistemas académicos';
$string['users'] = 'Incluir usuarios';
$string['webhook'] = 'Aceptar webhooks firmados';
