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
 * @package    local_skillupscertify
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['active'] = 'Activa';
$string['allgroups'] = 'Todos los grupos accesibles';
$string['allstatuses'] = 'Todos los estados';
$string['alreadyissued'] = 'Ya existe un certificado válido para esta plantilla y este titular.';
$string['auditdays'] = 'Retención de bitácora en días';
$string['auditdaysdesc'] = 'Mínimo 30 días. Afecta la bitácora local.';
$string['backtemplates'] = 'Volver a plantillas';
$string['batch_cancelled'] = 'Cancelado';
$string['batch_finished'] = 'Finalizado';
$string['batch_queued'] = 'En cola';
$string['batchdays'] = 'Retención de lotes cerrados en días';
$string['batchdaysdesc'] = 'Mínimo 7 días. Afecta los lotes y los resultados individuales.';
$string['batchheading'] = 'Lote de emisión';
$string['batchnumber'] = 'Lote {$a}';
$string['batchpending'] = 'Este curso ya tiene tres lotes en cola.';
$string['batchsize'] = 'Selecciona un grupo con entre 1 y {$a} estudiantes matriculados.';
$string['body'] = 'Texto del certificado';
$string['bulk'] = 'Emisión por grupo';
$string['bulkhelp'] = 'El lote comprueba los requisitos de cada estudiante antes de emitir el PDF.';
$string['busy'] = 'El registro se está actualizando. Inténtalo en unos instantes.';
$string['code'] = 'Código de verificación';
$string['color'] = 'Color de acento';
$string['color_green'] = 'Verde bosque';
$string['color_navy'] = 'Azul marino';
$string['color_wine'] = 'Burdeos';
$string['comparepdf'] = 'Selecciona un PDF para compararlo';
$string['confirmbatch'] = 'Autorizo la emisión para los estudiantes de este grupo que cumplan los requisitos.';
$string['confirmreissue'] = 'Autorizo la reemisión y el retiro del certificado anterior si sigue vigente.';
$string['confirmrevoke'] = 'Autorizo la revocación de este certificado.';
$string['confirmsingle'] = 'Autorizo la emisión para este estudiante.';
$string['defaultbody'] = 'Ha completado satisfactoriamente {course}.';
$string['download'] = 'Descargar PDF';
$string['edition'] = 'Revisión {$a}';
$string['enabled'] = 'Habilitar esta plantilla para emitir certificados';
$string['eventrecorded'] = 'Operación de certificados registrada';
$string['evidence'] = 'Evidencia para emitir';
$string['export'] = 'Exportar CSV';
$string['fields'] = 'Campos disponibles';
$string['filelimit'] = 'Selecciona un PDF de hasta 3 MB.';
$string['filter'] = 'Aplicar filtros';
$string['fingerprint'] = 'Huella del PDF (SHA-256)';
$string['fingerprinthelp'] = 'Compara el archivo completo para detectar alteraciones o un QR copiado. Tu navegador procesa el archivo de forma local.';
$string['group'] = 'Grupo';
$string['history'] = 'Historial de emisión';
$string['housekeeping'] = 'Mantener los registros operativos de SkillUps Certify';
$string['inactive'] = 'Inactiva';
$string['ineligible'] = 'El titular no cumple las condiciones vigentes de la plantilla.';
$string['invaliddesign'] = 'Revisa los campos obligatorios del diseño, sus longitudes y las variables.';
$string['invalidsource'] = 'Selecciona una fuente disponible e indica el ID del itinerario cuando corresponda.';
$string['issued'] = 'Certificado emitido.';
$string['issuedate'] = 'Fecha de emisión';
$string['issueheading'] = 'Emitir certificados';
$string['issuer'] = 'Institución emisora';
$string['issuesingle'] = 'Emitir certificado';
$string['item_existing'] = 'Emitido previamente';
$string['item_issued'] = 'Emitido';
$string['item_queued'] = 'Pendiente';
$string['item_skipped'] = 'Sin requisitos cumplidos o no disponible';
$string['label'] = 'Nombre de la plantilla';
$string['landscape'] = 'Horizontal';
$string['license_community'] = 'Comunidad';
$string['license_enterprise'] = 'Empresarial';
$string['license_expired'] = 'Vencido';
$string['license_grace'] = 'Período de gracia';
$string['license_professional'] = 'Profesional';
$string['licenseexpiry'] = 'La licencia vence el {$a}';
$string['licensestatus'] = 'Plan {$a}';
$string['match'] = 'El archivo coincide con el PDF original archivado.';
$string['mine'] = 'Mis certificados';
$string['mismatch'] = 'El archivo no coincide con el PDF original archivado.';
$string['navigation'] = 'Navegación de certificados';
$string['newtemplate'] = 'Nueva plantilla';
$string['noactive'] = 'Crea o habilita una plantilla para comenzar a emitir.';
$string['nobatches'] = 'No hay lotes en este alcance.';
$string['norecords'] = 'No hay certificados ni titulares en esta selección.';
$string['notemplates'] = 'Este curso no tiene plantillas de certificados.';
$string['notlatest'] = 'Solo se puede reemitir la edición más reciente.';
$string['notverified'] = 'No se pudo verificar una credencial con este código.';
$string['orientation'] = 'Orientación de página';
$string['pathid'] = 'ID del itinerario de Learning Path';
$string['pdfinvalid'] = 'El PDF del certificado falta, fue alterado o no es válido.';
$string['pluginname'] = 'SkillUps Certify & Verify';
$string['portrait'] = 'Vertical';
$string['previewpdf'] = 'Vista previa en PDF';
$string['privacy:metadata:action'] = 'Tipo de operación.';
$string['privacy:metadata:audit'] = 'Registro de emisión, revocación, exportación y cambios de plantillas.';
$string['privacy:metadata:batch'] = 'Lotes de emisión y operadores.';
$string['privacy:metadata:batchid'] = 'Identificador del lote.';
$string['privacy:metadata:courseid'] = 'El curso emisor.';
$string['privacy:metadata:files'] = 'El plugin usa archivos Moodle del contexto de curso para conservar los PDF originales.';
$string['privacy:metadata:issue'] = 'Credenciales emitidas y su estado de verificación.';
$string['privacy:metadata:issueid'] = 'Identificador de la credencial.';
$string['privacy:metadata:item'] = 'Resultados por titular en la emisión por lotes.';
$string['privacy:metadata:objectid'] = 'Referencia interna de la operación.';
$string['privacy:metadata:pdfhash'] = 'Huella del PDF archivado.';
$string['privacy:metadata:reason'] = 'Motivo administrativo de revocación.';
$string['privacy:metadata:snapshot'] = 'El diseño, nombre, fechas y evidencia al emitir.';
$string['privacy:metadata:state'] = 'Estado del procesamiento.';
$string['privacy:metadata:status'] = 'El estado actual de verificación.';
$string['privacy:metadata:tasks'] = 'El servicio de tareas conserva el operador y la referencia del lote para la emisión asíncrona.';
$string['privacy:metadata:timecreated'] = 'Fecha de creación del registro.';
$string['privacy:metadata:timeissued'] = 'Fecha de emisión.';
$string['privacy:metadata:timemodified'] = 'Fecha de actualización del registro.';
$string['privacy:metadata:timerevoked'] = 'Fecha de retiro del certificado.';
$string['privacy:metadata:timeupdated'] = 'Fecha de actualización del resultado del titular.';
$string['privacy:metadata:tokenhash'] = 'Hash unidireccional del código de verificación.';
$string['privacy:metadata:userid'] = 'El identificador del usuario de Moodle.';
$string['publicmeta'] = 'Publicar el título, emisor y fecha de emisión en el verificador QR';
$string['publicverification'] = 'Habilitar la verificación pública por QR';
$string['publicverificationdesc'] = 'Quien tenga el enlace verá el estado, la referencia y la huella del PDF. La plantilla controla los campos adicionales.';
$string['queued'] = 'Lote de emisión en cola.';
$string['reason'] = 'Motivo de revocación (privado)';
$string['reasonrequired'] = 'Indica un motivo de hasta 240 caracteres.';
$string['recentbatches'] = 'Lotes de emisión recientes';
$string['recipient'] = 'Titular';
$string['record'] = 'Detalle del certificado';
$string['reference'] = 'Referencia';
$string['refresh'] = 'Actualizar progreso';
$string['registry'] = 'Registro de certificados';
$string['reissue'] = 'Reemitir certificado';
$string['reissued'] = 'Se emitió un certificado de reemplazo.';
$string['reissuehelp'] = 'El reemplazo usa la plantilla activa y vuelve a comprobar los requisitos.';
$string['revision'] = 'Revisión';
$string['revoke'] = 'Revocar certificado';
$string['revokedate'] = 'Fecha de retiro';
$string['revokedmessage'] = 'Se revocó el certificado.';
$string['samplename'] = 'Titular de ejemplo';
$string['sampleonly'] = 'Solo vista previa';
$string['signer'] = 'Nombre o cargo del firmante (opcional)';
$string['single'] = 'Emisión individual';
$string['skillupscertify:export'] = 'Exportar registros de certificados';
$string['skillupscertify:issue'] = 'Emitir certificados de estudiantes';
$string['skillupscertify:manage'] = 'Administrar plantillas de certificados';
$string['skillupscertify:revoke'] = 'Revocar o reemplazar certificados';
$string['skillupscertify:viewregistry'] = 'Consultar el registro privado de certificados';
$string['source_course'] = 'Finalización del curso en Moodle';
$string['source_manual'] = 'Autorización manual para un estudiante activo';
$string['source_pathway'] = 'Finalización verificada de Learning Path';
$string['staledesign'] = 'Otra persona guardó una revisión. Recarga antes de editar.';
$string['startbatch'] = 'Poner lote de emisión en cola';
$string['status'] = 'Estado';
$string['status_replaced'] = 'Reemplazado';
$string['status_revoked'] = 'Revocado';
$string['status_valid'] = 'Válido';
$string['template'] = 'Plantilla';
$string['templatelimit'] = 'Este curso alcanzó el límite de plantillas.';
$string['templates'] = 'Plantillas de certificados';
$string['templatesaved'] = 'Se guardó la revisión de la plantilla.';
$string['title'] = 'Título del certificado';
$string['unavailable'] = 'El certificado o la operación no está disponible para ti.';
$string['username'] = 'Nombre de usuario en Moodle';
$string['verificationdisabled'] = 'La verificación pública está deshabilitada en este sitio.';
$string['verify'] = 'Verificar certificado';
$string['verifybutton'] = 'Verificar';
$string['visualpreview'] = 'Vista previa del diseño';
