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
 * Language strings for SkillUps Course Quality Inspector.
 *
 * @package    report_skillupsquality
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['alreadyqueued'] = 'Ya hay una auditoría en cola o se solicitó durante el último minuto.';
$string['apply'] = 'Aplicar';
$string['auditdetails'] = 'Resultados de auditoría';
$string['auditid'] = 'ID de auditoría';
$string['backaudit'] = 'Volver a los resultados';
$string['batchhistory'] = 'Resultados del lote';
$string['batchlimit'] = 'La categoría o selección supera los 50 cursos. Elige una subcategoría o envía una lista de hasta 50 IDs.';
$string['batchqueued'] = 'Auditorías en cola {$a->scheduled}. Solicitudes omitidas por actividad reciente {$a->skipped}.';
$string['busy'] = 'La auditoría del curso está ocupada. Inténtalo de nuevo en breve.';
$string['catalog'] = 'Catálogo de reglas';
$string['categoryhelp'] = 'Audita hasta 50 cursos de esta categoría y sus subcategorías.';
$string['categoryid'] = 'ID de la categoría de cursos';
$string['comparison'] = 'Comparar con una auditoría anterior';
$string['comparisonpolicy'] = 'Las políticas de auditoría difieren. Compara los hallazgos individuales y las reglas configuradas.';
$string['compliance'] = 'Evidencia de la última auditoría';
$string['compliancehelp'] = 'Un operador establece el estado de corrección. Una auditoría posterior con la regla activa actualiza la evidencia.';
$string['condition'] = 'Condición';
$string['condition_completion'] = 'La finalización está activa y el curso carece de criterios de finalización.';
$string['condition_dates'] = 'Una fecha de inicio, entrega o cierre del curso, tarea o cuestionario es posterior a la que le sigue.';
$string['condition_emptycontent'] = 'Una Página de Moodle carece de texto visible y medios.';
$string['condition_emptycourse'] = 'No hay actividades ni etiquetas con contenido.';
$string['condition_grade'] = 'El mínimo de un ítem numérico es igual o mayor que su máximo.';
$string['condition_hidden'] = 'Un módulo está oculto explícitamente en la configuración del curso.';
$string['condition_instructions'] = 'Una tarea o cuestionario visible tiene la descripción vacía.';
$string['condition_links'] = 'Un editor compatible hace referencia a un adjunto Moodle ausente o contiene un enlace mal formado o una URI ejecutable.';
$string['condition_quizgrade'] = 'El cuestionario tiene una nota máxima positiva y sus preguntas no suman un total positivo.';
$string['condition_resources'] = 'Un recurso Archivo o Carpeta carece de archivos registrados o tiene un archivo local de cero bytes.';
$string['condition_rubric'] = 'Una tarea usa el método rúbrica y carece de un formulario disponible para calificar.';
$string['condition_sections'] = 'Una sección no general carece de módulos visibles y de un resumen con contenido.';
$string['count'] = 'Cantidad';
$string['course'] = 'Curso';
$string['courseaudits'] = 'Historial de auditorías del curso';
$string['courseid'] = 'ID del curso';
$string['courselimit'] = 'El curso supera el límite actual de 2000 actividades y recursos.';
$string['coursepicker'] = 'Cursos que puedes inspeccionar';
$string['csv'] = 'Descargar CSV';
$string['dashboard'] = 'Panel del sitio';
$string['dashboardhelp'] = 'Abre las auditorías de un curso o introduce el ID de una categoría para solicitar un lote.';
$string['delta_absent'] = 'Ya no detectados';
$string['delta_new'] = 'Hallazgos nuevos';
$string['delta_present'] = 'Todavía detectados';
$string['disabled'] = 'Desactivada';
$string['duedate'] = 'Fecha objetivo';
$string['editplan'] = 'Editar plan de corrección';
$string['eventaudit'] = 'Operación de auditoría de calidad del curso';
$string['evidence'] = 'Evidencia';
$string['evidence_allowsubmissionsfromdate'] = 'Inicio de entregas';
$string['evidence_attachments'] = 'Adjuntos registrados';
$string['evidence_badlinks'] = 'Enlaces inválidos';
$string['evidence_count'] = 'Actividades con contenido';
$string['evidence_cutoffdate'] = 'Fecha límite';
$string['evidence_duedate'] = 'Fecha de entrega';
$string['evidence_emptyfiles'] = 'Archivos locales vacíos';
$string['evidence_enddate'] = 'Fin del curso';
$string['evidence_max'] = 'Calificación máxima';
$string['evidence_min'] = 'Calificación mínima';
$string['evidence_missingfiles'] = 'Adjuntos incrustados ausentes';
$string['evidence_number'] = 'Número de sección';
$string['evidence_questionsum'] = 'Puntuación total de las preguntas';
$string['evidence_startdate'] = 'Inicio del curso';
$string['evidence_timeclose'] = 'Cierre del cuestionario';
$string['evidence_timeopen'] = 'Apertura del cuestionario';
$string['evidence_tracked'] = 'Actividades con finalización activa';
$string['excel'] = 'Descargar Excel';
$string['field'] = 'Campo';
$string['findings'] = 'Hallazgos';
$string['history'] = 'Historial de auditorías';
$string['invalidaction'] = 'La operación solicitada no está disponible.';
$string['invalidbatch'] = 'Introduce IDs numéricos de cursos separados por comas o espacios.';
$string['invalidplan'] = 'Introduce un estado válido y una nota de hasta 4000 caracteres.';
$string['kind'] = 'Tipo de hallazgo';
$string['kind_advisory'] = 'Recomendaciones de diseño';
$string['kind_all'] = 'Todos los tipos';
$string['kind_objective'] = 'Hallazgos comprobables';
$string['latestaudit'] = 'Consultar la última auditoría completada';
$string['license_community'] = 'Edición Community';
$string['license_enterprise'] = 'Edición Enterprise';
$string['license_expired'] = 'Licencia vencida';
$string['license_grace'] = 'Período de gracia de la licencia';
$string['license_professional'] = 'Edición Professional';
$string['licenseexpires'] = 'Vence el {$a}';
$string['licenseinstallation'] = 'Identificador de instalación {$a}';
$string['licensing'] = 'Licencia';
$string['maintain'] = 'Mantener y recuperar auditorías de calidad';
$string['mode_category'] = 'Toda la categoría y sus subcategorías';
$string['mode_selected'] = 'Cursos seleccionados de esta categoría';
$string['noaudits'] = 'Todavía no hay auditorías disponibles. Solicita una para comenzar.';
$string['nocourses'] = 'No hay cursos autorizados para auditar en ese ámbito.';
$string['nofindings'] = 'Ningún hallazgo coincide con el filtro.';
$string['noplans'] = 'Este curso aún no tiene planes de corrección.';
$string['norules'] = 'La política institucional vigente carece de reglas activadas.';
$string['notes'] = 'Notas de acciones';
$string['notesprompt'] = 'Describe la corrección prevista. Evita incluir datos personales del alumnado en estas notas.';
$string['opencategory'] = 'Abrir categoría';
$string['partiallinks'] = 'La comprobación de enlaces quedó incompleta en {$a} actividades porque un campo superó la longitud admitida.';
$string['plans'] = 'Planes de corrección';
$string['plansaved'] = 'Se guardó el plan de corrección.';
$string['pluginname'] = 'SkillUps Course Quality Inspector';
$string['policychanged'] = 'La versión del motor cambió mientras la auditoría estaba pendiente. Solicita una nueva auditoría.';
$string['privacy:metadata:notes'] = 'Notas de acciones proporcionadas por el último autor del plan.';
$string['privacy:metadata:plan'] = 'Guarda el último autor del plan y sus notas de acciones.';
$string['privacy:metadata:run'] = 'Registra quién solicitó la auditoría y la fecha de solicitud.';
$string['privacy:metadata:time'] = 'Fecha de solicitud de auditoría o de edición del plan.';
$string['privacy:metadata:userid'] = 'ID de usuario del operador de Moodle.';
$string['privacy:path'] = 'Auditoría de calidad del curso';
$string['progress_denied'] = 'La persona que solicitó la auditoría ya no tiene permiso para auditar el curso.';
$string['progress_failed'] = 'La auditoría falló. Consulta el registro nativo de tareas o solicita una auditoría nueva.';
$string['progress_queued'] = 'El cron de Moodle procesará esta auditoría.';
$string['progress_running'] = 'Moodle está inspeccionando la configuración del curso.';
$string['queue'] = 'Solicitar auditoría';
$string['queuedcount'] = 'Se puso en cola la auditoría de {$a} curso.';
$string['recommend_completion'] = 'Define criterios si la institución espera que Moodle registre la finalización del curso.';
$string['recommend_dates'] = 'Revisa las fechas afectadas y restablece el orden previsto.';
$string['recommend_emptycontent'] = 'Agrega a la página el texto o recurso multimedia previsto.';
$string['recommend_emptycourse'] = 'Agrega los recursos o actividades que el alumnado necesita para comenzar.';
$string['recommend_grade'] = 'Corrige el mínimo y máximo del ítem en el libro de calificaciones.';
$string['recommend_hidden'] = 'Confirma si el recurso debe permanecer oculto o ser visible.';
$string['recommend_instructions'] = 'Considera explicar qué se espera y cuáles son los criterios de entrega o evaluación.';
$string['recommend_links'] = 'Abre el editor de la actividad y repara los enlaces o adjuntos afectados.';
$string['recommend_quizgrade'] = 'Revisa la lista de preguntas y las puntuaciones asignadas.';
$string['recommend_resources'] = 'Revisa el recurso y sube o reemplaza los archivos afectados.';
$string['recommend_rubric'] = 'Completa la rúbrica y márcala como lista en Calificación avanzada.';
$string['recommend_sections'] = 'Agrega una descripción o materiales, o revisa si la sección es necesaria.';
$string['recommendation'] = 'Recomendación';
$string['refresh'] = 'Actualizar estado';
$string['requested'] = 'Fecha de solicitud';
$string['retention'] = 'Retención del historial de auditorías en días';
$string['retentionhelp'] = 'Conserva los resultados de auditoría entre 30 y 730 días. El valor predeterminado es 180.';
$string['rule'] = 'Regla';
$string['rule_completion'] = 'Criterios de finalización del curso';
$string['rule_dates'] = 'Fechas invertidas';
$string['rule_emptycontent'] = 'Página sin contenido';
$string['rule_emptycourse'] = 'Curso vacío';
$string['rule_grade'] = 'Rango de calificación inválido';
$string['rule_hidden'] = 'Actividad o recurso oculto';
$string['rule_instructions'] = 'Instrucciones de la actividad';
$string['rule_links'] = 'Problema en enlaces verificado localmente';
$string['rule_quizgrade'] = 'Puntuación de cuestionario incompleta';
$string['rule_resources'] = 'Archivos adjuntos ausentes o vacíos';
$string['rule_rubric'] = 'Rúbrica activa sin publicar';
$string['rule_sections'] = 'Sección visible sin materiales';
$string['ruleid'] = 'ID de la regla';
$string['saveplan'] = 'Guardar plan';
$string['scope'] = 'Alcance de la auditoría';
$string['scopefailure'] = 'La auditoría, el curso o la categoría solicitados no están disponibles.';
$string['score'] = 'Puntuación de calidad';
$string['scoredelta'] = 'Puntuación previa {$a->before}. Puntuación actual {$a->after}. Cambio de {$a->change} puntos.';
$string['scorehelp'] = 'La puntuación aplica una penalización por cada regla comprobable con hallazgos. Las recomendaciones de diseño se informan por separado.';
$string['selectedids'] = 'IDs de cursos separados por comas';
$string['selectedbatch'] = 'Auditar una lista de cursos';
$string['settings'] = 'Política de calidad de cursos';
$string['severity'] = 'Gravedad';
$string['severity_critical'] = 'Crítica';
$string['severity_high'] = 'Alta';
$string['severity_low'] = 'Baja';
$string['severity_medium'] = 'Media';
$string['severityfor'] = 'Gravedad de {$a}';
$string['skillupsquality:batch'] = 'Solicitar auditorías por categoría o lote';
$string['skillupsquality:plan'] = 'Editar planes de corrección del curso';
$string['skillupsquality:run'] = 'Solicitar una auditoría de calidad del curso';
$string['skillupsquality:view'] = 'Consultar evidencias de auditoría del curso';
$string['state'] = 'Estado';
$string['state_absent'] = 'No detectado';
$string['state_accepted'] = 'Excepción aceptada';
$string['state_addressed'] = 'Corrección aplicada';
$string['state_denied'] = 'Autorización perdida';
$string['state_done'] = 'Completada';
$string['state_failed'] = 'Fallida';
$string['state_overdue'] = 'Vencido';
$string['state_planned'] = 'Planificado';
$string['state_present'] = 'Detectado';
$string['state_queued'] = 'En cola';
$string['state_running'] = 'En ejecución';
$string['state_unknown'] = 'Verificación pendiente';
$string['state_working'] = 'En curso';
$string['target'] = 'Elemento';
$string['target_course'] = 'Curso ID {$a}';
$string['target_gradeitem'] = 'Ítem de calificación ID {$a}';
$string['target_module'] = 'Actividad ID {$a}';
$string['target_section'] = 'Sección ID {$a}';
$string['unverifiedcount'] = 'Actividades con comprobación de enlaces incompleta';
$string['value'] = 'Valor';
