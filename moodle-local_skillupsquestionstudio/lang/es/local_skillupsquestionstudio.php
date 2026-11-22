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
 * SkillUps Question Studio local skillupsquestionstudio.
 *
 * @package    local_skillupsquestionstudio
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'No puedes realizar esta operación de Studio.';
$string['aiunavailable'] = 'La IA de Moodle no está disponible. Comprueba tu permiso, el consentimiento de transferencia y la aceptación de la política de IA de Moodle.';
$string['all'] = 'Todos los tipos';
$string['answerarea'] = 'Espacio de respuesta';
$string['answerpool'] = 'Opciones de respuesta';
$string['approve'] = 'Aprobar esta revisión';
$string['approved'] = 'Aprobada';
$string['approvefirst'] = 'Aprueba al menos una revisión vigente para crear una evaluación.';
$string['assessment'] = 'Vista previa de evaluación';
$string['assessmentintro'] = 'Revisa esta selección ordenada de preguntas aprobadas antes de exportarla o importarla.';
$string['bloom'] = 'Nivel de Bloom';
$string['bloom_analyze'] = 'Analizar';
$string['bloom_apply'] = 'Aplicar';
$string['bloom_create'] = 'Crear';
$string['bloom_evaluate'] = 'Evaluar';
$string['bloom_remember'] = 'Recordar';
$string['bloom_understand'] = 'Comprender';
$string['busy'] = 'El proyecto está ocupado. Inténtalo en breve.';
$string['category'] = 'Categoría del banco';
$string['choices'] = 'Opciones, una por línea';
$string['competency'] = 'Competencia del curso';
$string['confirmdelete'] = 'Confirmar eliminación del proyecto';
$string['consent'] = 'Autorizar transferencia de la fuente';
$string['consentdesc'] = 'Autorizo el envío de un fragmento al proveedor de IA configurado en Moodle y confirmo que la política institucional lo permite.';
$string['correct'] = 'Respuesta correcta';
$string['correctkey'] = 'Correcta';
$string['count'] = 'Cantidad de preguntas';
$string['createvariant'] = 'Crear versión';
$string['dashboard'] = 'Proyectos de Studio';
$string['dashboardintro'] = 'Prepara evaluaciones revisadas y publica preguntas aprobadas en el banco del curso.';
$string['date'] = 'Última actualización';
$string['deleteconfirm'] = 'Elimina fuentes, borradores, historial y versiones de Studio. Las preguntas del banco de Moodle se administran por separado.';
$string['deleteproject'] = 'Eliminar proyecto';
$string['draft'] = 'Borrador';
$string['duplicate'] = 'El proyecto ya tiene una pregunta con el mismo enunciado normalizado.';
$string['duplicatesource'] = 'El archivo ya está en cola o disponible en este proyecto.';
$string['editconflict'] = 'Esta pregunta cambió. Recárgala antes de guardar o revisar.';
$string['edition_community'] = 'Community';
$string['edition_enterprise'] = 'Enterprise';
$string['edition_expired'] = 'Caducada';
$string['edition_grace'] = 'Periodo de gracia sin conexión';
$string['edition_professional'] = 'Professional';
$string['editor'] = 'Editor de preguntas';
$string['emptydocument'] = 'No se encontró texto seleccionable en el documento.';
$string['enableai'] = 'Habilitar generación con IA de Moodle';
$string['enableaidesc'] = 'Permite a los proveedores de IA configurados en Moodle procesar fragmentos con autorización.';
$string['eventstudiochanged'] = 'Operación de SkillUps Question Studio';
$string['evidence'] = 'Ver texto fuente';
$string['export'] = 'Exportar Moodle XML';
$string['exportstats'] = 'Exportar CSV agregado';
$string['extractionfailed'] = 'Falló la extracción del documento.';
$string['feedback'] = 'Retroalimentación general';
$string['filter'] = 'Filtrar';
$string['generate'] = 'Generar borradores';
$string['generationfailed'] = 'La generación no devolvió borradores utilizables.';
$string['generationqueued'] = 'Generación en cola.';
$string['generator'] = 'Generador de borradores';
$string['hidekey'] = 'Ocultar clave de respuestas';
$string['historical'] = 'Estás consultando una revisión histórica.';
$string['history'] = 'Historial de revisiones';
$string['importbank'] = 'Importar preguntas aprobadas';
$string['importfailed'] = 'Moodle no pudo importar las preguntas aprobadas.';
$string['invalidanswers'] = 'Comprueba la cantidad de respuestas, que sean distintas y la respuesta correcta. Las listas de emparejamiento deben tener la misma longitud.';
$string['invalidcategory'] = 'Selecciona una categoría accesible del banco de preguntas de este curso.';
$string['invalidcompetency'] = 'La competencia seleccionada no está disponible en este curso.';
$string['invalidcourse'] = 'Selecciona un curso válido.';
$string['invaliddocument'] = 'Selecciona un PDF, DOCX, PPTX o TXT UTF-8 válido de hasta 8 MiB.';
$string['invalidquestion'] = 'Comprueba los campos obligatorios de la pregunta y su clasificación.';
$string['invalidvariant'] = 'Selecciona de 1 a 30 preguntas. El proyecto necesita al menos esa cantidad de preguntas aprobadas.';
$string['itemlimit'] = 'Este proyecto ya contiene 400 preguntas.';
$string['joblimit'] = 'Espera a que termine el trabajo en curso o vuelve mañana si alcanzaste el límite diario.';
$string['jobs'] = 'Generaciones recientes';
$string['kind'] = 'Tipo de pregunta';
$string['left'] = 'Elementos a emparejar, uno por línea';
$string['level'] = 'Dificultad';
$string['level_easy'] = 'Fácil';
$string['level_hard'] = 'Difícil';
$string['level_medium'] = 'Media';
$string['name'] = 'Título de la pregunta';
$string['nearduplicate'] = 'Revisa una posible pregunta casi duplicada antes de aprobar.';
$string['newproject'] = 'Crear proyecto';
$string['newquestion'] = 'Nueva pregunta';
$string['nocategories'] = 'Crea o abre un banco de preguntas de este curso y verifica el permiso para añadir preguntas a una categoría.';
$string['noprojects'] = 'Crea un proyecto para empezar.';
$string['noquestions'] = 'No hay preguntas que coincidan con los filtros.';
$string['nosources'] = 'Aún no se han añadido documentos.';
$string['notassigned'] = 'Sin asignar';
$string['pdfunavailable'] = 'La extracción de PDF requiere que la administración del sitio configure Poppler pdftotext.';
$string['pluginname'] = 'SkillUps Question Studio';
$string['preview'] = 'Vista previa de la pregunta';
$string['privacy:account'] = 'Cuenta autora, revisora o publicadora.';
$string['privacy:ai'] = 'Pueden enviarse fragmentos autorizados al proveedor de IA configurado en Moodle.';
$string['privacy:document'] = 'Nombre del archivo o texto fuente extraído con autorización.';
$string['privacy:files'] = 'Las cargas temporales usan la API de archivos de Moodle.';
$string['privacy:question'] = 'Contenido de la revisión, clave y clasificación.';
$string['privacy:records'] = 'Datos del proyecto de curso de Question Studio.';
$string['privacy:request'] = 'Configuración de una solicitud de generación.';
$string['produced'] = 'Borradores creados';
$string['project'] = 'Proyecto';
$string['projectdeleted'] = 'Se eliminó el proyecto. Las preguntas publicadas siguen en el banco de Moodle.';
$string['projectintro'] = 'Gestiona las fuentes, los borradores de preguntas y las decisiones de revisión de esta evaluación.';
$string['projectname'] = 'Nombre del proyecto';
$string['projects'] = 'Proyectos';
$string['prompt'] = 'Enunciado';
$string['promptchoice'] = '¿Qué descripción corresponde a {$a}?';
$string['promptessay'] = 'Explica {$a} con tus propias palabras e ilústralo con un ejemplo.';
$string['promptmatch'] = 'Empareja estos conceptos con sus descripciones: {$a}.';
$string['promptrubric'] = 'Comprueba la precisión de la definición, la pertinencia del ejemplo y que se justifique cómo el ejemplo ilustra el concepto. Referencia: {$a}';
$string['promptshort'] = 'Nombra el concepto descrito aquí: {$a}';
$string['prompttruth'] = 'Verdadero o falso: {$a->term} se describe como «{$a->statement}».';
$string['provider'] = 'Generador u origen';
$string['provider_ai'] = 'Borradores con IA de Moodle';
$string['provider_local'] = 'Borradores locales';
$string['provider_manual'] = 'Preguntas manuales';
$string['publish'] = 'Publicar en el banco del curso';
$string['publishedcount'] = 'Preguntas importadas a Moodle: {$a}';
$string['questions'] = 'Gestor de preguntas';
$string['refresh'] = 'Actualizar proyecto';
$string['remove'] = 'Eliminar texto fuente';
$string['removesource'] = 'Eliminar fuente';
$string['removesourceconfirm'] = 'Elimina el texto extraído y la carga pendiente de este documento. Los borradores existentes se conservan.';
$string['renewal'] = 'Identificador de instalación para renovar la licencia SkillUps';
$string['reopen'] = 'Volver a borrador';
$string['retention'] = 'Conservación del texto documental en días';
$string['retentiondesc'] = 'Elimina el texto extraído tras 1 a 365 días. Las revisiones de preguntas permanecen en sus proyectos.';
$string['review'] = 'Revisión docente';
$string['reviewintro'] = 'Comprueba el enunciado y la clave con el material docente antes de aprobar esta revisión.';
$string['revision'] = 'Revisión';
$string['right'] = 'Respuestas del emparejamiento, en el orden de los elementos';
$string['rubric'] = 'Guía de calificación';
$string['saved'] = 'Revisión del borrador guardada.';
$string['saverevision'] = 'Guardar revisión';
$string['short'] = 'Respuestas aceptadas, una por línea';
$string['showkey'] = 'Mostrar clave de respuestas';
$string['skillupsquestionstudio:manage'] = 'Gestionar proyectos de preguntas del curso';
$string['skillupsquestionstudio:publish'] = 'Exportar preguntas aprobadas o publicar en bancos de Moodle';
$string['skillupsquestionstudio:review'] = 'Revisar y aprobar revisiones de preguntas';
$string['skillupsquestionstudio:useai'] = 'Generar borradores con IA de Moodle';
$string['source'] = 'Fuente';
$string['sourcefile'] = 'Documento fuente';
$string['sourcelimit'] = 'Este proyecto alcanzó el límite de 30 documentos.';
$string['sourcequeued'] = 'Documento en cola para extracción.';
$string['sourceremoved'] = 'Se retiraron el texto y la carga pendiente de la fuente.';
$string['sources'] = 'Documentos fuente';
$string['sourcesintro'] = 'Importa PDF, DOCX, PPTX o texto UTF-8. Cada archivo puede tener hasta 8 MiB.';
$string['sourceunavailable'] = 'La fuente aún no está lista o no está disponible.';
$string['stalevariant'] = 'Cambió una aprobación o competencia. Crea otra versión con las preguntas aprobadas actuales.';
$string['state_failed'] = 'Falló';
$string['state_queued'] = 'En cola';
$string['state_ready'] = 'Listo';
$string['state_working'] = 'En proceso';
$string['status'] = 'Estado';
$string['subscription'] = 'Suscripción';
$string['taskmaintenance'] = 'Recuperar trabajos y limpiar fuentes de Question Studio';
$string['type_essay'] = 'Ensayo';
$string['type_match'] = 'Emparejamiento';
$string['type_multichoice'] = 'Selección múltiple';
$string['type_shortanswer'] = 'Respuesta corta';
$string['type_truefalse'] = 'Verdadero/falso';
$string['unit'] = 'Unidad';
$string['upload'] = 'Cargar documento';
$string['variantlimit'] = 'Este proyecto alcanzó el límite de 100 versiones de evaluación.';
$string['variantname'] = 'Nombre de la versión';
$string['variants'] = 'Versiones de evaluación';
$string['waitsource'] = 'Carga un documento y espera la extracción. Ya puedes crear preguntas manuales.';
