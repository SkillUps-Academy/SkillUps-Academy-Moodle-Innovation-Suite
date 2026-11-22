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
 * Spanish translations.
 *
 * @package    local_skillupssuccess
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['abstained'] = 'Sin señal y con desenlace conocido';
$string['accuracy'] = 'Exactitud';
$string['action'] = 'Acción';
$string['actor'] = 'Autor';
$string['aggregationhelp'] = 'Los conteos se agrupan en bandas y se ocultan las celdas pequeñas. Compara los errores solo para la misma versión, fechas de cohorte y protocolo de desenlaces. Las diferencias son descriptivas.';
$string['allrisks'] = 'Todas las clasificaciones por reglas';
$string['allstates'] = 'Todos los estados';
$string['assess'] = 'Evaluación independiente de desenlaces';
$string['assessmenthelp'] = 'Revisa la evidencia del curso durante la ventana indicada de 14 días. Determina si hubo necesidad de apoyo mediante evidencia independiente. Evita usar la alerta o el estado del caso para elegir la etiqueta.';
$string['assessmentqueue'] = 'Evaluaciones de desenlace disponibles';
$string['assessmentwindow'] = 'Ventana de revisión del {$a->start} al {$a->end}.';
$string['assessone'] = 'Evaluar desenlace';
$string['assign'] = 'Asignar o retirar tutor';
$string['assigned'] = 'Asignados a tutor';
$string['assignment'] = 'Tarea';
$string['back'] = 'Panel de apoyo estudiantil';
$string['brier'] = 'Puntuación Brier';
$string['busy'] = 'Este curso está procesando datos de apoyo académico. Inténtalo en unos instantes.';
$string['caseheading'] = 'Caso de apoyo de {$a}';
$string['caseoutcomes'] = 'Resultados del proceso de apoyo';
$string['cases'] = 'Casos';
$string['closedcase'] = 'Reabre el caso antes de agregar otra intervención de apoyo.';
$string['cohort'] = 'Cohorte de observación';
$string['cohortrange'] = 'Del {$a->begin} al {$a->end} (UTC)';
$string['contact'] = 'Registrar contacto';
$string['contributions'] = 'Contribuciones a los log-odds del modelo (asociación)';
$string['courses'] = 'Cursos';
$string['currentpending'] = 'Entregas pendientes actuales (primeras 20)';
$string['due'] = 'Fin de la ventana';
$string['effectivedeadline'] = 'Vencimiento efectivo';
$string['enabled'] = 'Habilitar la recopilación programada de señales';
$string['eventcase'] = 'Se registró una operación de Student Success';
$string['evidence'] = 'Factores observados';
$string['export'] = 'Exportar calidad en CSV';
$string['factor'] = 'Factor';
$string['feature_decline'] = 'Cambio respecto a la nota anterior (puntos de descenso)';
$string['feature_grade'] = 'Total numérico actual del curso (%)';
$string['feature_inactive'] = 'Días desde el último acceso registrado al curso';
$string['feature_overdue'] = 'Tareas individuales sin entrega tras el vencimiento efectivo';
$string['feature_participation'] = 'Módulos disponibles con actividad registrada en 14 días';
$string['filter'] = 'Aplicar filtros';
$string['fn'] = 'Falsos negativos';
$string['fnr'] = 'Tasa de falsos negativos';
$string['followup'] = 'Registrar seguimiento';
$string['fp'] = 'Falsos positivos';
$string['fpr'] = 'Tasa de falsos positivos';
$string['gradehistory'] = 'Observaciones recientes de nota de curso';
$string['group'] = 'Grupo';
$string['grouping'] = 'Agrupación';
$string['high'] = 'Atención alta';
$string['history'] = 'Historial reciente de intervenciones';
$string['identifiedcases'] = 'Casos de apoyo estudiantil';
$string['intervention'] = 'Registrar una intervención';
$string['invalidrequest'] = 'La operación o el valor enviado no es válido.';
$string['label'] = 'Registrar evaluación definitiva';
$string['learner'] = 'Estudiante';
$string['licensecommunity'] = 'Community';
$string['licenseenterprise'] = 'Enterprise';
$string['licenseexpired'] = 'Vencida';
$string['licenseexpires'] = 'Vence el {$a}';
$string['licensegrace'] = 'Período de gracia sin conexión';
$string['licenseinstallation'] = 'ID de instalación {$a}';
$string['licenseprofessional'] = 'Professional';
$string['licensestatus'] = 'Estado de la suscripción';
$string['limit_decline'] = 'Descenso de nota de al menos (puntos porcentuales)';
$string['limit_grade'] = 'Nota numérica de curso inferior a (%)';
$string['limit_high'] = 'Reglas activadas necesarias para atención alta';
$string['limit_inactive'] = 'Inactividad en días (al menos)';
$string['limit_overdue'] = 'Entregas vencidas (al menos)';
$string['limit_participation'] = 'Módulos con actividad registrada en 14 días (menos de)';
$string['matched'] = 'Regla activada con umbral {$a}';
$string['mincell'] = 'Tamaño mínimo de celda agregada';
$string['mincelldesc'] = 'De 5 a 100 registros para el umbral de divulgación. Los conteos se muestran en bandas de cinco.';
$string['mode'] = 'Motor evaluado';
$string['modeldesc'] = 'Requiere un modelo congelado validado en config.php y activación por curso. El plugin no entrena modelos.';
$string['modelenabled'] = 'Habilitar inferencia estadística aprobada';
$string['monitor'] = 'En seguimiento';
$string['noaggregates'] = 'Ningún agregado cumple las condiciones de retención y población mínima.';
$string['noassessments'] = 'No hay desenlaces pendientes listos para evaluar en este curso.';
$string['nocases'] = 'Ningún caso autorizado coincide con los filtros.';
$string['nopath'] = '—';
$string['noprediction'] = 'No se produjo una estimación estadística. El modelo puede estar desactivado o requerir señales no disponibles.';
$string['normal'] = 'Sin regla activada';
$string['notes'] = 'Notas de apoyo (texto plano)';
$string['observed'] = 'Observado o registrado';
$string['observedcounts'] = 'Cobertura y clasificaciones observadas';
$string['open'] = 'Abierto';
$string['operation'] = 'Operación';
$string['outcome'] = 'Necesidad observada de apoyo';
$string['outcomeno'] = 'Sin necesidad de apoyo confirmada con evidencia suficiente';
$string['outcomeunknown'] = 'Evidencia insuficiente';
$string['outcomeyes'] = 'Necesidad de apoyo confirmada';
$string['outofscope'] = 'No tienes acceso a este registro o ámbito de apoyo académico.';
$string['pending'] = 'Etiquetas pendientes';
$string['plan'] = 'Registrar plan de apoyo';
$string['pluginname'] = 'SkillUps Student Success Predictor';
$string['policy'] = 'Reglas y activación del curso';
$string['policyhelp'] = 'Activa la recopilación solo en cursos autorizados. Cero desactiva una regla. El umbral de atención alta es la cantidad de reglas activadas.';
$string['precision'] = 'Precisión';
$string['predictedalert'] = 'Supera el umbral del modelo';
$string['predictiondetail'] = 'El modelo {$a->model} estima {$a->probability}% para el desenlace definido. Umbral de alerta de {$a->threshold}%.';
$string['privacy:cases'] = 'Casos de apoyo y asignaciones de tutores.';
$string['privacy:entries'] = 'Acciones y notas de apoyo.';
$string['privacy:factors'] = 'Señales académicas observadas, etiquetas o resultados derivados.';
$string['privacy:note'] = 'Nota de apoyo redactada por el personal.';
$string['privacy:observations'] = 'Observaciones diarias de apoyo académico.';
$string['privacy:path'] = 'SkillUps Student Success Predictor';
$string['privacy:samples'] = 'Predicciones prospectivas congeladas y desenlaces evaluados de forma independiente.';
$string['privacy:state'] = 'Estado del caso de apoyo gestionado por personas.';
$string['privacy:time'] = 'Fecha de observación, actividad o revisión.';
$string['privacy:user'] = 'Referencia al estudiante o al personal responsable.';
$string['programfield'] = 'Campo de programa del curso';
$string['programfielddesc'] = 'Nombre corto de un campo de curso de texto de una línea visible para los usuarios pertinentes.';
$string['programs'] = 'Programas';
$string['qualitymetrics'] = 'Error observado y calidad de predicción';
$string['recall'] = 'Sensibilidad';
$string['record'] = 'Guardar intervención';
$string['reopen'] = 'Reabrir';
$string['reports'] = 'Informes de Student Success';
$string['resolved'] = 'Resuelto';
$string['retention'] = 'Retención de datos en días';
$string['retentiondesc'] = 'De 45 a 730 días. Los casos activos con observaciones recientes siguen disponibles.';
$string['reviewnotice'] = 'Usa estas señales académicas como insumo para una revisión humana. Considera los datos faltantes y el contexto individual antes de decidir cómo apoyar.';
$string['risk'] = 'Clasificación por reglas';
$string['ruleclass'] = 'Reglas determinísticas';
$string['rulesignal'] = 'Señal de regla';
$string['save'] = 'Guardar reglas del curso';
$string['saved'] = 'Cambios guardados.';
$string['scandisabled'] = 'La recopilación programada está en pausa para este curso.';
$string['skillupssuccess:aggregate'] = 'Ver informes desidentificados de cursos y programas';
$string['skillupssuccess:assess'] = 'Evaluar desenlaces de la cohorte de calidad';
$string['skillupssuccess:configure'] = 'Configurar reglas y activar el seguimiento del curso';
$string['skillupssuccess:learner'] = 'Incluir al estudiante en el seguimiento académico';
$string['skillupssuccess:manage'] = 'Gestionar casos del curso y asignar tutores';
$string['skillupssuccess:view'] = 'Ver casos asignados de apoyo estudiantil';
$string['stale'] = 'Este registro cambió o la evaluación aún no está disponible. Recarga la página.';
$string['state'] = 'Estado del caso';
$string['statistical'] = 'Modelo estadístico';
$string['study'] = 'Recoger muestras prospectivas para evaluar desenlaces, incluidos estudiantes sin alerta';
$string['studydisabled'] = 'La evaluación prospectiva de desenlaces está desactivada en este curso.';
$string['supported'] = 'Con intervenciones';
$string['taskretention'] = 'Eliminar registros vencidos de apoyo académico';
$string['taskscan'] = 'Recoger señales de apoyo académico';
$string['tn'] = 'Verdaderos negativos';
$string['total'] = 'Registros con ventana cerrada';
$string['tp'] = 'Verdaderos positivos';
$string['tutor'] = 'Tutor';
$string['unassigned'] = 'Sin asignar';
$string['unknown'] = 'Desconocido';
$string['unmapped'] = 'Sin programa asignado';
$string['usemodel'] = 'Aplicar en este curso el modelo estadístico aprobado por el sitio';
$string['value'] = 'Valor';
$string['version'] = 'Versión o huella';
$string['watch'] = 'Señal de revisión';
