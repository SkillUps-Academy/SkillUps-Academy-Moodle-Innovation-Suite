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
 * Spanish language strings.
 *
 * @package    local_skillupsaigrading
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accessdenied'] = 'No tienes permiso para acceder a esta revisión o entrega.';
$string['aigenerated'] = 'Sugerencia generada por IA. Verifica cada criterio, cita e inferencia antes de utilizarla.';
$string['allowexternal'] = 'Permitir proveedores fuera de la interfaz local';
$string['allowexternal_desc'] = 'Aplica a todo servidor fuera de la configuración exacta de Ollama en la interfaz local. Habilítalo solo según la política de tratamiento de datos de tu institución.';
$string['approve'] = 'Aprobar revisión';
$string['assignmentreviews'] = 'Revisiones de la tarea';
$string['approved'] = 'Aprobada';
$string['backassignment'] = 'Volver a las revisiones de la tarea';
$string['busy'] = 'Esta tarea está procesando una solicitud. Inténtalo en unos instantes.';
$string['compare'] = 'Registrar comparación de nota';
$string['comparison'] = 'Nota de Moodle registrada: {$a->human}% el {$a->date}. Diferencia: {$a->delta} puntos porcentuales.';
$string['consent'] = 'Revisé el extracto y la rúbrica para detectar datos personales y autorizo expresamente enviarlos al proveedor de IA seleccionado.';
$string['create'] = 'Crear revisión';
$string['dailylimit'] = 'Revisiones por tarea en 24 horas';
$string['dailylimit_desc'] = 'Rango permitido de 1 a 500. Incluye revisiones locales y con IA.';
$string['decisions'] = 'Historial de decisiones (últimas 100)';
$string['edit'] = 'Guardar borrador de retroalimentación';
$string['emptydocument'] = 'La fuente no contiene texto extraíble.';
$string['enableai'] = 'Habilitar proveedores de IA';
$string['enableai_desc'] = 'Permite solicitudes asíncronas de IA. La hoja local siempre está disponible.';
$string['eraseduser'] = 'Revisor eliminado';
$string['evaluatedat'] = 'Evaluada el {$a}';
$string['eventaggregate'] = 'Se exportó el informe agregado de evaluación de SkillUps';
$string['eventreviewupdated'] = 'Se actualizó una revisión de evaluación de SkillUps';
$string['excerpt'] = 'Extracto seleccionado';
$string['excerpthelp'] = 'Conserva solo los extractos pertinentes. Elimina identificadores o pasajes sensibles antes de enviar. Separa con saltos de línea los fragmentos copiados de la fuente. La redacción automática de identificadores comunes requiere revisión docente.';
$string['exportsummary'] = 'Descargar CSV de estadísticas';
$string['external'] = 'envío externo';
$string['failed'] = 'Fallida';
$string['failedhelp'] = 'No se pudo completar la solicitud. Verifica la disponibilidad y configuración del proveedor y crea otra revisión.';
$string['feedback'] = 'Retroalimentación editable por el docente';
$string['gap'] = 'Posible omisión';
$string['history'] = 'Revisiones recientes';
$string['historyentry'] = '{$a->date} · {$a->state} · {$a->review}';
$string['humanonly'] = 'Estas revisiones son visibles para correctores autorizados. Guardar, aprobar o rechazar una revisión no publica retroalimentación ni cambia las calificaciones. Registra la decisión final en el calificador de Moodle.';
$string['invaliddocument'] = 'Selecciona un archivo compatible de esta entrega (TXT, DOCX, PPTX o PDF, hasta 8 MB).';
$string['invalidexcerpt'] = 'Selecciona hasta 12.000 caracteres de la fuente mostrada. Cada línea debe aparecer literalmente en la fuente.';
$string['invalidreview'] = 'La operación de revisión o la longitud de la retroalimentación no es válida.';
$string['jobstate'] = 'Intento {$a->attempt} · {$a->state} · {$a->review} · Solicitado {$a->date} · Actualizado {$a->updated}';
$string['licensecommunity'] = 'Community';
$string['licenseenterprise'] = 'Enterprise';
$string['licenseexpired'] = 'Vencida';
$string['licenseexpires'] = 'Renovar antes del {$a}';
$string['licensegrace'] = 'Período de gracia sin conexión';
$string['licenseinstallation'] = 'Identificador de la instalación: {$a}';
$string['licenseprofessional'] = 'Professional';
$string['licensestatus'] = 'Estado de la suscripción';
$string['localworksheet'] = 'Hoja de evidencias local. El docente debe verificar las coincidencias de palabras.';
$string['loopback'] = 'servidor local';
$string['maintenance'] = 'Limpiar revisiones de evaluación interrumpidas o vencidas';
$string['metricai'] = 'Solicitudes con IA';
$string['metricapproved'] = 'Aprobadas';
$string['metriccompared'] = 'Comparaciones de notas';
$string['metriccomparedvalue'] = 'Comparaciones de notas: {$a}';
$string['metricfailed'] = 'Fallidas';
$string['metricmae'] = 'Diferencia absoluta media (puntos porcentuales)';
$string['metricrejected'] = 'Rechazadas';
$string['metrictotal'] = 'Revisiones autorizadas';
$string['nativegrader'] = 'Abrir el calificador de Moodle';
$string['noassignments'] = 'No hay tareas compatibles disponibles para ti en este curso.';
$string['nohistory'] = 'Todavía no hay registros.';
$string['noparticipants'] = 'No hay participantes elegibles para revisar en el ámbito actual.';
$string['norubric'] = 'Selecciona Rúbrica como método de calificación avanzada y publica una con hasta 20 criterios y 15 niveles por criterio.';
$string['nosources'] = 'El intento enviado no contiene fuentes de evidencias compatibles.';
$string['nosubmission'] = 'El estudiante no tiene un intento enviado.';
$string['onlinetext'] = 'Texto en línea';
$string['overview'] = 'Revisa evidencias y sugerencias basadas en rúbricas para las entregas de tareas de este curso.';
$string['participants'] = 'Participantes elegibles';
$string['pdfunavailable'] = 'La extracción de texto PDF requiere que el administrador configure el ejecutable pdftotext de Poppler.';
$string['pending'] = 'Pendiente de revisión';
$string['pluginname'] = 'SkillUps AI Grading Assistant';
$string['points'] = 'Puntos sugeridos: {$a}';
$string['prepare'] = 'Preparar evidencias';
$string['privacy:metadata:action'] = 'Acción docente sobre la revisión.';
$string['privacy:metadata:actor'] = 'Docente que actúa sobre la revisión.';
$string['privacy:metadata:connector'] = 'El proveedor habilitado recibe el extracto autorizado y los criterios de la rúbrica.';
$string['privacy:metadata:decision'] = 'Retroalimentación docente, decisiones y fechas.';
$string['privacy:metadata:excerpt'] = 'Selección del texto de la entrega autorizada por el docente.';
$string['privacy:metadata:feedback'] = 'Retroalimentación docente o registro de comparación.';
$string['privacy:metadata:human'] = 'Porcentaje de la nota en Moodle registrado para comparar.';
$string['privacy:metadata:job'] = 'Copias de revisiones, evidencias seleccionadas pendientes de envío, metadatos del modelo, sugerencias y comparaciones de nota.';
$string['privacy:metadata:provider'] = 'Metadatos de procedencia del proveedor y modelo.';
$string['privacy:metadata:result'] = 'Recomendaciones con evidencias y borrador de retroalimentación.';
$string['privacy:metadata:rubric'] = 'Rúbrica usada para la revisión.';
$string['privacy:metadata:subject'] = 'Estudiante cuya entrega se revisa.';
$string['privacy:metadata:timecreated'] = 'Fecha de la operación.';
$string['privacy:path'] = 'Revisiones de evaluación de SkillUps';
$string['provenance'] = 'Proveedor {$a->provider} · Modelo solicitado {$a->model} · Versión configurada {$a->version} · Modelo reportado {$a->reported} · SHA-256 de rúbrica {$a->hash}';
$string['provider'] = 'Método de procesamiento';
$string['provider_enabled'] = 'Habilitar conector';
$string['provider_model'] = 'ID del modelo';
$string['provider_modelversion'] = 'Versión o revisión desplegada del modelo';
$string['provider_output'] = 'Máximo de tokens de salida (64–4096)';
$string['provider_temperature'] = 'Temperatura (0–2)';
$string['providercompatible'] = 'Servidor compatible';
$string['providerconf'] = 'Las conexiones y los tokens se configuran en config.php y variables de entorno.';
$string['providerlocal'] = 'Hoja de evidencias local sin IA';
$string['provideroff'] = 'El proveedor no está disponible o falta la autorización de envío.';
$string['providerollama'] = 'Ollama';
$string['provideropenai'] = 'OpenAI';
$string['queued'] = 'En cola';
$string['ratelimit'] = 'Esta tarea ha alcanzado su límite de revisiones en las últimas 24 horas.';
$string['rationale'] = 'Justificación por verificar';
$string['ready'] = 'Lista';
$string['refresh'] = 'Actualizar estado';
$string['reject'] = 'Rechazar revisión';
$string['rejected'] = 'Rechazada';
$string['retention'] = 'Retención de revisiones en días';
$string['retention_desc'] = 'Rango permitido de 1 a 365. Cron elimina las revisiones vencidas y sus decisiones.';
$string['reviewjob'] = 'Revisión de {$a}';
$string['rubric'] = 'Rúbrica de la tarea en Moodle';
$string['saved'] = 'Se guardó la decisión de revisión.';
$string['skillupsaigrading:review'] = 'Crear y revisar sugerencias de evaluación';
$string['skillupsaigrading:useai'] = 'Autorizar el procesamiento con IA de las evidencias seleccionadas';
$string['skillupsaigrading:view'] = 'Ver el asistente de evaluación';
$string['source'] = 'Fuente de evidencias de la entrega';
$string['stale'] = 'Desactualizada';
$string['stalesnapshot'] = 'La revisión o decisión está desactualizada. Recarga la página y prepara otra revisión si cambió la entrega o la rúbrica.';
$string['statistics'] = 'Estadísticas de revisión';
$string['statisticsscope'] = 'Estadísticas de las 500 revisiones más recientes en el ámbito de participantes autorizados.';
$string['studenthistory'] = 'Historial de revisiones';
$string['suggested'] = 'Puntuación sugerida según la rúbrica: {$a}%';
$string['unavailablegrade'] = 'No hay una nota numérica comparable para este mismo intento.';
$string['uncertain'] = 'Evidencia insuficiente o incierta';
$string['unsupportedassignment'] = 'Esta versión admite tareas individuales con identidades visibles.';
$string['working'] = 'Procesando';
