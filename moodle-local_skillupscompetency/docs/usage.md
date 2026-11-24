# Manual de SkillUps Competency Manager

## Catálogo y logro

1. Abre **Catálogo nativo** y administra los marcos en el editor Moodle. Usa un marco para un área, competencias raíz para dominios y competencias descendientes para la jerarquía.
2. Define códigos, descripciones, taxonomías y escalas con las herramientas Moodle. Abre una competencia desde SkillUps y añade indicadores en cada nivel.
3. El CSV nativo transfiere marcos y competencias. El JSON complementario transfiere indicadores SkillUps tras importar el marco. Mantén los mismos códigos de marco, competencia y etiquetas de escala. Cada importación afecta solo los niveles incluidos y los valida antes de guardar. El límite por archivo es de 256 KiB y 1.000 indicadores.
4. Desde el panel de un curso, abre **Vincular competencias**, selecciona una competencia y elige curso completo o una actividad. El vínculo es nativo y se administra también en Moodle.

## Matriz por programa

Crea un programa bajo una categoría Moodle y vincula sus cursos. Añade un período con fechas inclusivas. Escoge un marco y una competencia para asignar nivel esperado. Marca **Competencia transversal** cuando corresponda. La matriz muestra cuántos cursos vinculados usan cada competencia y permite retirar un objetivo o un curso.

## Evaluaciones

El estudiante envía su autoevaluación desde el panel del curso. El docente elige un estudiante autorizado y crea una evaluación directa. Si una tarea vinculada dispone de rúbrica activa y el docente puede calificarla, el formulario ofrece escoger niveles por criterio. Añade observaciones y, cuando sirva de respaldo, archivos privados.

Coordinación abre la propuesta, revisa las evidencias y registra una aprobación o rechazo con retroalimentación. Solo la aprobación actualiza el nivel de competencia en Moodle. La evaluación por rúbrica utiliza el rango completo de puntos de sus criterios para proponer un nivel y conserva una instantánea de lo seleccionado.

## Seguimiento

El panel muestra estado actual, nivel esperado del período activo y brecha ordinal. El historial conserva las propuestas visibles, decisiones y cambios de nivel. El informe institucional permite elegir programa, período, curso y grupo. La descarga CSV incluye los resultados autorizados. El panel individual ofrece su propio CSV.
