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
 * SkillUps accessibility language strings.
 *
 * @package    report_skillupsaccessibility
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['all'] = 'Todos';
$string['apply'] = 'Aplicar filtros';
$string['assignee'] = 'Responsable asignado';
$string['back'] = 'Volver al informe del curso';
$string['badperiod'] = 'Introduce fechas válidas en orden cronológico.';
$string['busy'] = 'Ya se está procesando una auditoría o revisión de este curso. Inténtalo en unos segundos.';
$string['cachecounts'] = 'Análisis nuevos {$a->changed}. Resultados reutilizados {$a->reused}.';
$string['checked'] = 'Fuentes evaluadas';
$string['classification'] = 'Tipo de hallazgo';
$string['comparefrom'] = 'Comparación desde';
$string['compareto'] = 'Comparación hasta';
$string['complete'] = 'Completada';
$string['confirmed'] = 'Errores estáticos comprobados';
$string['corrected'] = 'Corregido';
$string['course'] = 'Curso';
$string['criterion'] = 'Referencia WCAG';
$string['date'] = 'Fecha de auditoría';
$string['dismissed'] = 'Descartado';
$string['download'] = 'Descargar CSV';
$string['externalfile'] = 'Referencia a repositorio externo';
$string['formatunsupported'] = 'Formato de contenido Moodle fuera del alcance';
$string['unreadable'] = 'Fuente estática ilegible';
$string['oversize'] = 'Fuente supera el límite de procesamiento';
$string['pdfmanual'] = 'PDF requiere revisión manual de estructura';
$string['unsupported'] = 'Actividad o documento fuera del alcance';
$string['empty'] = 'No hay registros en esta selección.';
$string['eventaudit'] = 'Auditoría de accesibilidad modificada';
$string['evidence'] = 'Evidencia privada de revisión';
$string['failed'] = 'Fallida';
$string['findings'] = 'Hallazgos';
$string['fix_audiotranscript'] = 'Verifica que exista una transcripción equivalente accesible y que su enlace sea claro.';
$string['fix_contrast'] = 'Mide el primer plano y fondo renderizados y ajusta los colores según tamaño y grosor.';
$string['fix_docxalt'] = 'Revisa el dibujo en Word y añade una alternativa o márcalo como decorativo cuando proceda.';
$string['fix_docxheading'] = 'Comprueba la navegación y verifica que los estilos de encabezado integrados reflejen la jerarquía.';
$string['fix_emptylink'] = 'Añade texto o un nombre accesible que identifique el destino o propósito.';
$string['fix_formlabel'] = 'Añade una etiqueta visible y vincúlala con el control cuando sea posible.';
$string['fix_frametitle'] = 'Identifica el marco con un título pertinente o nombre accesible y revisa su contenido.';
$string['fix_genericlink'] = 'Revisa el contexto y usa texto que identifique el destino cuando sea posible.';
$string['fix_headskip'] = 'Revisa la jerarquía del contenido renderizado y usa niveles que reflejen la estructura.';
$string['fix_imgalt'] = 'Añade una alternativa breve y pertinente. Usa alt vacío para imágenes puramente decorativas.';
$string['fix_movingmarkup'] = 'Revisa la duración y los controles del movimiento o usa contenido estático accesible.';
$string['fix_pdfstructure'] = 'Comprueba etiquetas, encabezados, alternativas y orden de lectura con herramientas para PDF y pruebas manuales.';
$string['fix_placeholder'] = 'Proporciona una etiqueta persistente y reserva el marcador para orientación complementaria.';
$string['fix_positivetab'] = 'Comprueba el orden de foco con teclado y usa el orden natural del DOM.';
$string['fix_tableheaders'] = 'En tablas de datos, añade celdas th y comprueba sus asociaciones. Revisa por separado las tablas de maquetación.';
$string['fix_videocaptions'] = 'Comprueba el reproductor y proporciona subtítulos sincronizados cuando correspondan.';
$string['from'] = 'Período desde';
$string['high'] = 'Alta';
$string['index'] = 'Índice estático de accesibilidad';
$string['institution'] = 'Informe institucional de accesibilidad';
$string['interpretation'] = 'El índice indica la proporción de fuentes evaluadas sin errores estáticos comprobados. La cobertura y la revisión humana se muestran por separado. El índice no acredita conformidad con WCAG.';
$string['interrupted'] = 'Se sustituyó una solicitud interrumpida.';
$string['intervaldays'] = 'Días entre auditorías programadas';
$string['intervalhelp'] = 'Define un valor entre 1 y 90. La tarea programada encola los cursos pendientes cada hora.';
$string['invalidreview'] = 'Introduce notas y elige un estado válido y un responsable autorizado.';
$string['inventoried'] = 'Fuentes inventariadas';
$string['jobstatus'] = 'Estado de la última solicitud';
$string['location'] = 'Ubicación';
$string['markedfalse'] = 'Indicador explícito de etiquetado PDF en falso';
$string['medium'] = 'Media';
$string['nodata'] = 'Sin evaluación';
$string['notavailable'] = 'Este informe o hallazgo no está disponible en tu ámbito.';
$string['notes'] = 'Notas de revisión';
$string['opensource'] = 'Abrir fuente en Moodle';
$string['outside'] = 'Criterios fuera de la evaluación automática';
$string['outsidedetails'] = 'Evalúa manualmente la navegación con teclado y productos de apoyo, orden y visibilidad del foco, ampliación y reajuste, calidad de alternativas y subtítulos, multimedia en directo, orden de lectura PDF, contraste del tema e interfaces externas.';
$string['partial'] = 'La auditoría alcanzó el máximo de 400 fuentes. El resultado abarca las fuentes recopiladas.';
$string['pending'] = 'Pendiente';
$string['pluginname'] = 'SkillUps Accessibility Checker';
$string['previous'] = 'Índice del período comparado';
$string['privacy:actorid'] = 'Cuenta que autorizó la auditoría.';
$string['privacy:assignee'] = 'Cuenta asignada al hallazgo.';
$string['privacy:authorid'] = 'Autor de la revisión.';
$string['privacy:files'] = 'Evidencia privada de revisión mediante File API.';
$string['privacy:finding'] = 'Una cuenta responsable opcional para un hallazgo.';
$string['privacy:job'] = 'La cuenta asociada a una auditoría.';
$string['privacy:notes'] = 'Notas aportadas por el autor.';
$string['privacy:review'] = 'Identidad, asignaciones y notas de revisión.';
$string['queuebulk'] = 'Encolar auditorías del ámbito';
$string['queued'] = 'En cola';
$string['queuednotice'] = 'La solicitud se añadió a la cola de tareas Moodle.';
$string['queuescan'] = 'Encolar auditoría del curso';
$string['recommendation'] = 'Orientación de corrección';
$string['recurring'] = 'Reglas recurrentes en esta selección';
$string['review'] = 'Requiere revisión humana';
$string['reviewhistory'] = 'Historial de revisión';
$string['revoked'] = 'La cuenta o el acceso al curso ya no están autorizados.';
$string['rule'] = 'Regla';
$string['rule_audiotranscript'] = 'Audio sin indicio cercano de transcripción';
$string['rule_contrast'] = 'Contraste bajo en colores literales en línea';
$string['rule_docxalt'] = 'Dibujo en Word sin metadatos alternativos detectables';
$string['rule_docxheading'] = 'Salto de estilo de encabezado en Word';
$string['rule_emptylink'] = 'Enlace sin nombre detectable';
$string['rule_formlabel'] = 'Control sin etiqueta detectable';
$string['rule_frametitle'] = 'Marco integrado sin nombre detectable';
$string['rule_genericlink'] = 'Texto de enlace genérico';
$string['rule_headskip'] = 'Salto de nivel de encabezado';
$string['rule_imgalt'] = 'Imagen sin atributo de texto alternativo';
$string['rule_movingmarkup'] = 'Elemento HTML móvil o parpadeante';
$string['rule_pdfstructure'] = 'PDF pendiente de revisión estructural';
$string['rule_placeholder'] = 'Texto de marcador sin etiqueta persistente';
$string['rule_positivetab'] = 'Tabindex positivo';
$string['rule_tableheaders'] = 'Tabla sin celdas de encabezado detectables';
$string['rule_videocaptions'] = 'Vídeo sin pista de subtítulos en el fragmento';
$string['running'] = 'En ejecución';
$string['saved'] = 'Revisión guardada.';
$string['savereview'] = 'Guardar revisión';
$string['scanfailed'] = 'El escaneo no terminó. Consulta el registro de tareas Moodle.';
$string['scheduleuserhelp'] = 'Usa una cuenta activa con permiso institucional y acceso de edición a los cursos. Cero desactiva el escaneo programado.';
$string['scheduleuserid'] = 'ID de la cuenta para auditorías programadas';
$string['settings'] = 'Configuración de auditoría de accesibilidad';
$string['severity'] = 'Gravedad';
$string['shortpartial'] = 'Auditoría parcial';
$string['skillupsaccessibility:bulk'] = 'Encolar y consultar auditorías de categoría o institución';
$string['skillupsaccessibility:export'] = 'Exportar informes de accesibilidad del ámbito';
$string['skillupsaccessibility:review'] = 'Revisar hallazgos y evidencias';
$string['skillupsaccessibility:scan'] = 'Encolar auditoría de accesibilidad del curso';
$string['skillupsaccessibility:view'] = 'Consultar la auditoría de accesibilidad del curso';
$string['source'] = 'Fuente';
$string['state'] = 'Estado de revisión';
$string['structureseen'] = 'Se detectó un marcador de árbol estructural';
$string['structureunknown'] = 'Marcador de árbol estructural no identificado en los bytes inspeccionados';
$string['taskschedule'] = 'Encolar auditorías programadas de accesibilidad';
$string['timeline'] = 'Historial de auditorías';
$string['to'] = 'Período hasta';
$string['unassigned'] = 'Sin asignar o no disponible';
