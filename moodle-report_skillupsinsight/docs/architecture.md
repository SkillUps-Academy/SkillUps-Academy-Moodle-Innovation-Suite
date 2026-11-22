# Diseño técnico de SkillUps Insight Analytics

La instalación local de referencia es Moodle 5.0.7. La compatibilidad primaria se verifica en Moodle 5.3 LTS. El tipo report permite navegación del informe en administración, categoría y curso. Los informes Core Logs, Participation, Completion e Insights sirven de referencia. Se conserva la separación entre informes descriptivos y modelos predictivos.

## Flujo

1. El administrador define períodos académicos y asigna programas mediante un campo corto nativo de curso.
2. Cron calcula una generación por período en lotes pequeños de cursos. Deduplica con una clave por generación y curso. Publica el ID de generación cuando termina el recorrido. El usuario sigue viendo la generación anterior mientras se actualiza.
3. El informe valida los contextos de categoría y curso antes de consultar instantáneas. Para datos de curso completos exige acceso a todos los grupos y permisos nativos de notas y finalización. Agrupar por programa jamás amplía el alcance de la categoría o el curso.
4. Las métricas se obtienen de SQL de matrícula activa de Moodle, registros institucionales de finalización, totales numéricos del libro y el lector del log estándar. Las operaciones Core son de lectura. No persiste filas por persona.
5. Fórmulas y restricciones están en metrics.md. Se guardan numeradores, denominadores y fecha de captura. Las comparaciones agregan numeradores y denominadores antes de calcular proporciones. Los datos de una generación publicada se conservan como históricos.
6. El informe personal permite guardar columnas y filtros. Las exportaciones usan dataformat de Moodle, texto seguro para hojas de cálculo y límites de filas. Las consultas y exportaciones se registran con eventos Moodle sin resultados.
7. Las metas, umbrales y programación se ejecutan como el propietario mediante cron. Se comprueba nuevamente su autorización y se envia una notificación interna al propietario. La notificación enlaza al informe, con sus condiciones de acceso vigentes.
8. Se prueba la reconciliación de indicadores y la separación entre categorías con datos sintéticos. La entrega incorpora un ZIP independiente de la nube.

Referencias [Report plugins](https://moodledev.io/docs/5.3/apis/plugintypes/report), [Data manipulation API](https://moodledev.io/docs/5.3/apis/core/dml), [Report builder API](https://moodledev.io/docs/5.2/apis/core/reportbuilder).
