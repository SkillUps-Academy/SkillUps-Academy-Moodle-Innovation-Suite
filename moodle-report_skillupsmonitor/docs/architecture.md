# Diseño de SkillUps System Monitor

La instalación de referencia local es Moodle 5.0.7 con PHP 8.2.12. Moodle 5.3 LTS es el destino primario de validación. Se revisaron Report Status, Scheduled Tasks y Task Logs. El plugin usa el tipo report para el panel operativo y la Check API para que el estado del colector aparezca en el informe nativo.

1. Tareas a intervalos fijos recogen un mínimo de mediciones de Moodle. Una comprobación local de lectura del registro de portada mide tiempo monotónico interno y la tasa de respuesta del componente Moodle/BD en el proceso. La medición distingue modo de mantenimiento, errores confirmados y muestras sin información.
2. Usar la marca nativa de último inicio de cron, Task API para configuración de tareas programadas, consulta acotada de cola y metadatos del logger nativo. Las comprobaciones de configuración son locales y la lista de componentes tiene un conjunto fijo.
3. Una muestra por intervalo UTC, bloqueo de clúster y publicación transaccional. Incidentes con umbral de fallo y recuperación; estados desconocidos cortan una racha y no cierran incidentes. Los huecos se registran como huecos de observación.
4. Las notificaciones son internas a Moodle y usan destinatarios autorizados. Se registra el resultado y se evita el reenvío cuando el estado de la entrega es ambiguo.
5. El dashboard separa muestras históricas, indicaciones actuales, estado de tareas, fallos de tareas y eventos accesibles. El acceso al panel es de sitio; se requiere el permiso administrativo nativo antes de leer detalles de ejecución.
6. Las rutas opcionales HTTPS usan un token fuera de la base de datos. Prometheus obtiene indicadores precomputados. Blackbox Exporter puede medir el servicio desde otra red. Las métricas del sistema operativo corresponden a agentes autorizados independientes.

[Check API](https://moodledev.io/docs/5.0/apis/subsystems/check) · [Task API](https://moodledev.io/docs/5.3/apis/subsystems/task) · [Prometheus exposition formats](https://prometheus.io/docs/instrumenting/exposition_formats/)
