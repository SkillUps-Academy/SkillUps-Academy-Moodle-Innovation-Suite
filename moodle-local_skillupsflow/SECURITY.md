# Seguridad y privacidad

Comunica posibles problemas mediante un aviso privado en GitHub si está disponible o por el canal institucional de SkillUps Academy. Describe versión e impacto con datos de prueba.

## Controles revisados

Las páginas requieren login y las capacidades del contexto de sistema. Los cambios usan POST y sesskey. El servidor comprueba un formato JSON versionado y acotado. Rechaza tipos desconocidos, ciclos y nodos desconectados. Los nodos corresponden a operaciones Moodle registradas. La definición no puede proporcionar PHP, SQL, URLs ni clases ejecutables.

El autor de la revisión es la cuenta ejecutora. Antes de aplicar efectos se comprueban el acceso de esa cuenta y la capacidad nativa específica. Una cuenta debe ser distinta del autor para aprobar. Las decisiones, lanzamientos y publicación se auditan. Los datos se escapan en Mustache y el editor los inserta mediante nodos de texto.

El bloqueo por flujo y una clave única evitan admisiones duplicadas. Las transiciones y las escrituras transaccionales se confirman juntas. Tras una interrupción, el reconciliador reanuda el trabajo desde la base. Las notificaciones usan una marca previa de intento. Si el resultado queda incierto, la ejecución lo registra y sigue la ruta de error o termina. El administrador puede decidir si corresponde un envío nuevo.

## Datos y conservación

Las tablas guardan revisiones, IDs de autores, objetivos y revisores, estados y fechas. El borrado por privacidad elimina las asociaciones del interesado, revoca las revisiones afectadas y cancela sus trabajos pendientes. Los mensajes siguen la retención del canal y del subsistema de Moodle. El plugin usa los canales de notificación administrados por la institución y carece de servicios SaaS obligatorios o conectores externos propios.

Configura mensajes con datos que la institución esté autorizada a distribuir por sus canales. Los campos de etiqueta y mensaje se guardan tal como se escriben. Las exportaciones conservan esos textos para que el administrador pueda reutilizar la plantilla.
