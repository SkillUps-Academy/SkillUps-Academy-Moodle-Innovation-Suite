# Arquitectura de SkillUps AI Grading Assistant

La copia de referencia XAMPP contiene Moodle 5.0.7 y PHP 8.2.12. La validación usa el entorno descartable descrito en el informe de compatibilidad, con Moodle 5.3 LTS como destino principal. El tipo local permite un panel de curso y actividad sin modificar Core. AI Feedback Assistant y AI Grader Pro sirvieron de referencias de funcionalidad. La disponibilidad comercial del identificador se confirma en Moodle Marketplace.

## Diseño y orden de implementación

1. Definir una interfaz de actividad que proporcione la entrega actual, rúbrica, evidencias y lectura comparativa de calificación. Adaptar tareas individuales de mod_assign mediante sus objetos y plugins de entrega.
2. En cada petición, comprobar contexto, capacidad de calificar, matrículas activas, grupos separados y asignación de corrector. Las tareas con identidades ocultas o entregas de equipo se quedan fuera de esta versión.
3. Importar una copia estructurada de la rúbrica activa. Vincular las sugerencias a la entrega, su intento, la firma de contenido y la rúbrica vigente. Si el intento o la rúbrica cambian, conservar el historial y exigir una evaluación nueva para aprobar.
4. Extraer texto en Moodle y separar la evidencia de identificación. Sin IA, producir una hoja de evidencias que no asigne puntuaciones. Para IA, el docente elige y desidentifica un extracto antes de autorizar su envío. Pedir JSON con nivel de rúbrica y cita literal. Las recomendaciones sin una cita verificable, con datos sensibles señalados o sin evidencia suficiente pierden la puntuación.
5. Ejecutar tareas ad hoc con límites, control de concurrencia y auditoría de modelo solicitado, versión configurada, modelo reportado, rúbrica y fecha. Eliminar el extracto pendiente al finalizar. Registrar decisiones y comentarios docentes de forma independiente.
6. Usar el libro de calificaciones en modo lectura para comparar únicamente notas numéricas del mismo intento cuando se puedan mapear. El plugin mantiene cero caminos de escritura de notas y enlaza a la interfaz de calificación nativa. La diferencia absoluta media mide concordancia con el criterio humano observado.
7. Probar aislamiento, rúbricas, análisis de archivos, IA simulada, consistencia de notas y revisión humana antes de empaquetar.

El motor recibe un adaptador de actividad para preparar y verificar instantáneas y leer notas. El trabajador y las páginas resuelven el adaptador de tareas individuales. Para admitir otra actividad, incorpora un adaptador con las reglas de acceso propias de esa actividad, selección de fuentes, identidad de intento y lectura comparable de notas.

[Advanced grading API](https://moodledev.io/docs/5.0/apis/core/grading) · [AI Feedback Assistant](https://marketplace.moodle.com/plugins/assignfeedback_aifeedback) · [AI Grader Pro](https://marketplace.moodle.com/plugins/3776)
