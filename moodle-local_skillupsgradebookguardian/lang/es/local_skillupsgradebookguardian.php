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
 * Spanish strings for Gradebook Guardian.
 *
 * @package    local_skillupsgradebookguardian
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activities'] = 'Actividades vinculadas o compatibles con calificaciones';
$string['actor'] = 'Operador';
$string['advice_aggregation'] = 'Verifica la configuración global y el método guardado antes de hacer cambios.';
$string['advice_categorycycle'] = 'Pide al equipo técnico revisar la jerarquía antes de recalcular.';
$string['advice_dependency'] = 'Revisa con el equipo técnico los elementos y totales de categoría referenciados antes de recalcular.';
$string['advice_droprules'] = 'Confirma la regla de selección en el editor nativo de categorías.';
$string['advice_duplicate'] = 'Compara el módulo y el número de elemento en un entorno de pruebas antes de intervenir.';
$string['advice_empty'] = 'Comprueba si la categoría se reservó para calificaciones futuras.';
$string['advice_formula'] = 'Verifica la expresión en el editor nativo con valores representativos.';
$string['advice_formulalimit'] = 'Revisa la expresión completa en el editor nativo de cálculos.';
$string['advice_hidden'] = 'Revisa la visibilidad y la fecha de publicación programada en Moodle.';
$string['advice_hiddenmismatch'] = 'Comprueba la política de publicación y simula ocultar el elemento si corresponde.';
$string['advice_hiddenreview'] = 'Revisa en Moodle la visibilidad del elemento y la actividad. Este elemento requiere revisión manual.';
$string['advice_itemcategory'] = 'Pide al equipo técnico recuperar la relación con la categoría mediante herramientas compatibles con Moodle.';
$string['advice_missingactivitygrade'] = 'Confirma si la actividad tiene calificaciones habilitadas. Algunas configuraciones no generan notas.';
$string['advice_parent'] = 'Comprueba la jerarquía después de una copia o restauración. Escala la relación inválida.';
$string['advice_pending'] = 'Confirma el estado en Moodle. El indicador por sí solo no identifica un fallo de cálculo.';
$string['advice_range'] = 'Verifica el rango original en la actividad o en el editor nativo antes de corregirlo.';
$string['advice_reference'] = 'Revisa con el equipo técnico los identificadores ausentes o ambiguos antes de editar la fórmula.';
$string['advice_root'] = 'Solicita al equipo técnico verificar la categoría raíz y el total nativo del curso.';
$string['advice_rootactivity'] = 'Verifica si la actividad debe asignarse a una categoría específica.';
$string['advice_scale'] = 'Comprueba la escala en la actividad y pide al equipo técnico revisar la asociación inválida.';
$string['advice_total'] = 'Solicita al equipo técnico reconciliar el total de Moodle con su categoría.';
$string['advice_unlinked'] = 'Revisa la actividad y las tareas de eliminación pendientes. Escala los elementos huérfanos persistentes.';
$string['advice_weight'] = 'Revisa el método de la categoría, los pesos manuales y el crédito extra en el libro de calificaciones.';
$string['advice_weightszero'] = 'Verifica qué elementos deben contribuir al total y ajusta los pesos en Moodle si procede.';
$string['after'] = 'Estado resultante';
$string['all'] = 'Todos';
$string['anonymized'] = 'Anonimizado';
$string['approve'] = 'Aprobar y ocultar el elemento';
$string['approveundo'] = 'Aprobar la recuperación de la visibilidad original';
$string['backhistory'] = 'Volver al historial';
$string['baseline'] = 'Número del diagnóstico de referencia';
$string['before'] = 'Estado original';
$string['busy'] = 'Hay otra operación de Guardian en curso. Inténtalo de nuevo en unos instantes.';
$string['categories'] = 'Categorías';
$string['change_added'] = 'Nuevo';
$string['change_changed'] = 'Evidencia modificada';
$string['change_resolved'] = 'Ausente en este diagnóstico';
$string['change_unchanged'] = 'Sin cambios';
$string['comparison'] = 'Comparación';
$string['cooldown'] = 'Espera diez segundos entre diagnósticos de este curso.';
$string['csv'] = 'Descargar CSV';
$string['date'] = 'Fecha';
$string['dependencies'] = 'Dependencias directas de calificación';
$string['diagnose'] = 'Ejecutar diagnóstico';
$string['emptyfindings'] = 'Ningún hallazgo coincide con los filtros.';
$string['emptyhistory'] = 'Ejecuta un diagnóstico para consultar la configuración del curso y las acciones disponibles.';
$string['emptyrelationships'] = 'No hay relaciones de calificaciones que coincidan con este filtro.';
$string['eventoperation'] = 'Operación de Gradebook Guardian';
$string['evidence'] = 'Evidencia';
$string['expired'] = 'La simulación caducó. Genera otra desde el diagnóstico.';
$string['field_activity'] = 'ID de actividad';
$string['field_aggregation'] = 'Código de agregación';
$string['field_category'] = 'ID de categoría';
$string['field_count'] = 'Cantidad';
$string['field_droplow'] = 'Descartar más bajas';
$string['field_effective'] = 'Código de agregación efectivo';
$string['field_hidden'] = 'Visibilidad';
$string['field_item'] = 'ID del elemento de calificación';
$string['field_keephigh'] = 'Conservar más altas';
$string['field_max'] = 'Máximo';
$string['field_min'] = 'Mínimo';
$string['field_related'] = 'ID de elementos relacionados';
$string['field_weight'] = 'Peso';
$string['filter'] = 'Aplicar filtros';
$string['finding'] = 'Hallazgo';
$string['findings'] = 'Hallazgos';
$string['history'] = 'Historial de auditoría';
$string['impact'] = 'Impacto';
$string['impactdetail'] = 'Se cambia el ajuste de visibilidad de un elemento. Guardian no envía notas de estudiantes ni ejecuta un recálculo. Moodle controla de forma independiente la visibilidad de las notas individuales y los totales.';
$string['intro'] = 'Audita la configuración del libro de calificaciones, revisa las evidencias y aprueba las reparaciones disponibles.';
$string['invalidaction'] = 'Operación de Gradebook Guardian inválida.';
$string['items'] = 'Elementos de calificación';
$string['json'] = 'Descargar JSON';
$string['kind_apply'] = 'Reparación aprobada';
$string['kind_diagnosis'] = 'Diagnóstico';
$string['kind_preview'] = 'Simulación';
$string['kind_revert'] = 'Recuperación aprobada';
$string['licenseexpires'] = 'Vencimiento de la licencia {$a}';
$string['licensefeature'] = 'Este formato de exportación requiere una licencia firmada.';
$string['licensestate_community'] = 'Community';
$string['licensestate_enterprise'] = 'Enterprise';
$string['licensestate_expired'] = 'Vencida';
$string['licensestate_grace'] = 'Período de gracia sin conexión';
$string['licensestate_professional'] = 'Professional';
$string['licensestatus'] = 'Estado de la suscripción — {$a}';
$string['nativegradebook'] = 'Configuración del libro de calificaciones';
$string['noevidence'] = 'Consulta el identificador del registro afectado.';
$string['notrepairable'] = 'Este elemento ya no cumple las condiciones de reparación asistida. Revisa el libro de calificaciones y la actividad en Moodle.';
$string['operation'] = 'Operación';
$string['operatorid'] = 'Usuario #{$a}';
$string['pluginname'] = 'SkillUps Gradebook Guardian';
$string['preview'] = 'Simular reparación';
$string['privacy:metadata:courseid'] = 'Curso en el que ocurrió la operación.';
$string['privacy:metadata:kind'] = 'Tipo de operación.';
$string['privacy:metadata:ledger'] = 'Diagnósticos, simulaciones y cambios aprobados en el curso.';
$string['privacy:metadata:parentid'] = 'Identificador del diagnóstico o la simulación relacionada.';
$string['privacy:metadata:payload'] = 'Evidencia estructural y ajustes antes y después.';
$string['privacy:metadata:timecreated'] = 'Momento de ejecución de la operación.';
$string['privacy:metadata:userid'] = 'Operador responsable de la acción.';
$string['purgetask'] = 'Eliminar historial vencido de Gradebook Guardian';
$string['recommendation'] = 'Recomendación';
$string['recovery'] = 'Recuperación';
$string['recoverydetail'] = 'Tras aprobar, un gestor autorizado puede restaurar el ajuste original desde la simulación mientras se conserve la auditoría y la configuración coincida con el resultado aplicado.';
$string['related'] = 'Abrir registro relacionado';
$string['relationships'] = 'Relaciones';
$string['repairdisabled'] = 'La reparación asistida está deshabilitada o requiere permiso de gestión del libro de calificaciones.';
$string['repairenabled'] = 'Habilitar reparaciones asistidas de visibilidad';
$string['repairenabled_help'] = 'Permite simular, aprobar y recuperar cambios de elementos elegibles según los permisos.';
$string['repairfailed'] = 'Moodle no guardó el estado aprobado. La transacción se revirtió.';
$string['reportviews'] = 'Vistas del diagnóstico';
$string['retention'] = 'Retención de la auditoría en días';
$string['retention_help'] = 'La tarea diaria elimina el historial y las simulaciones vencidas.';
$string['rule'] = 'Regla';
$string['rule_aggregation'] = 'Método de agregación desconocido';
$string['rule_categorycycle'] = 'Ciclo de categorías o ancestro bloqueado';
$string['rule_dependency'] = 'Ciclo de cálculo o dependencia bloqueada por un ciclo';
$string['rule_droprules'] = 'Se configuró descartar bajas y conservar altas';
$string['rule_duplicate'] = 'Identidad duplicada de elemento de actividad';
$string['rule_empty'] = 'Categoría de calificación vacía';
$string['rule_formula'] = 'El cálculo falló la validación estática de Moodle';
$string['rule_formulalimit'] = 'La fórmula supera la longitud máxima del diagnóstico';
$string['rule_hidden'] = 'Elemento de calificación oculto';
$string['rule_hiddenmismatch'] = 'Actividad oculta con un elemento de calificación visible';
$string['rule_hiddenreview'] = 'La diferencia de visibilidad requiere revisión manual';
$string['rule_itemcategory'] = 'Elemento de calificación sin una categoría válida';
$string['rule_missingactivitygrade'] = 'Actividad compatible con calificaciones sin elemento asociado';
$string['rule_parent'] = 'Categoría superior ausente en el curso';
$string['rule_pending'] = 'Moodle marca este total o cálculo para recálculo';
$string['rule_range'] = 'El máximo de calificación no supera el mínimo';
$string['rule_reference'] = 'El cálculo contiene una referencia sin resolver';
$string['rule_root'] = 'Número inesperado de categorías raíz';
$string['rule_rootactivity'] = 'La actividad usa la categoría raíz del curso';
$string['rule_scale'] = 'Escala de calificación ausente o de otro curso';
$string['rule_total'] = 'Total de categoría ausente, duplicado o incorrecto';
$string['rule_unlinked'] = 'Elemento sin vínculo con una actividad activa';
$string['rule_weight'] = 'Peso fuera del multiplicador o la proporción esperada';
$string['rule_weightszero'] = 'La media ponderada carece de peso neto positivo';
$string['saved_apply'] = 'Visibilidad del elemento actualizada y auditada.';
$string['saved_diagnose'] = 'Diagnóstico guardado.';
$string['saved_preview'] = 'Simulación guardada. Revisa el cambio propuesto y la recuperación.';
$string['saved_revert'] = 'Visibilidad original del elemento recuperada y auditada.';
$string['scanlimit'] = 'Máximo de registros estructurales por diagnóstico';
$string['scanlimit_help'] = 'El límite suma elementos de calificación, categorías y actividades compatibles o vinculadas.';
$string['severity'] = 'Severidad';
$string['severity_error'] = 'Error';
$string['severity_info'] = 'Información';
$string['severity_warning'] = 'Advertencia';
$string['skillupsgradebookguardian:approve'] = 'Aprobar y recuperar reparaciones de elementos de calificación';
$string['skillupsgradebookguardian:diagnose'] = 'Ejecutar diagnósticos y simular reparaciones';
$string['skillupsgradebookguardian:export'] = 'Exportar diagnósticos del libro de calificaciones';
$string['skillupsgradebookguardian:view'] = 'Ver diagnósticos del libro de calificaciones';
$string['stale'] = 'La simulación caducó, ya se procesó o el libro cambió. Revisa la configuración actual.';
$string['target'] = 'Registro';
$string['target_activity'] = 'Actividad #{$a}';
$string['target_category'] = 'Categoría #{$a}';
$string['target_course'] = 'Libro de calificaciones del curso';
$string['target_item'] = 'Elemento #{$a}';
$string['toolarge'] = 'Este libro supera el límite configurado de {$a} registros por diagnóstico.';
$string['treatment'] = 'Tratamiento';
$string['treatment_repair'] = 'Reparación asistida para elementos elegibles';
$string['treatment_review'] = 'Revisión en Moodle';
$string['treatment_technical'] = 'Intervención técnica';
$string['visibility_hidden'] = 'Oculto en el elemento';
$string['visibility_until'] = 'Oculto hasta {$a}';
$string['visibility_visible'] = 'Visible en el elemento';
