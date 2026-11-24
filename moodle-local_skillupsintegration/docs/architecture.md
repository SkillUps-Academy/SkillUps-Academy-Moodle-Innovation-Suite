# Arquitectura de SkillUps Integration Hub

Moodle 5.3 es la referencia primaria. Se inspeccionó XAMPP Moodle 5.0.7, PHP 8.2.12 y las funciones nativas de usuarios, cursos, HTTP y tareas. El componente local usa exclusivamente APIs nativas para escribir usuarios y cursos.

Cada origen tiene su configuración, tipos de datos habilitados, destino de cursos, propietario y referencia de un secreto del entorno. Google Workspace consulta Directory y Classroom con OAuth delegado. Microsoft 365 consulta Microsoft Graph y Education mediante una aplicación Entra ID. Los conectores SIS y HR consumen un contrato REST documentado de la institución. Las entradas REST y webhook comparten un contrato de recepción y generan simulaciones. Los proveedores se desacoplan de la cola y solo producen datos fuente.

La simulación captura datos canónicos y una decisión calculada por fila. La aplicación es una acción separada, autorizada por un operador y vinculada a esa simulación y revisión de perfil. El procesador recalcula cada decisión con el estado de Moodle antes de escribir. Los conflictos conservan el estado actual de Moodle y las filas independientes pueden continuar. Las identidades externas se asocian únicamente a registros creados por ese origen. Se evita que la sincronización cambie nombres de cuenta, correos, autenticación, permisos, categorías o visibilidad.

Las tareas Adhoc trabajan en páginas y bloques acotados, con bloqueo por conector, reintento transitorio y redistribución mediante una tarea programada. Las mutaciones locales de cada fila y su vínculo se confirman juntas. El registro de trabajos contiene estados y códigos de error. El contenido de simulaciones y recepción tiene conservación limitada. Se verifica autenticación, destino, paginación, límites, autorización, concurrencia y aislamiento antes del empaquetado.

Los secretos viven en el entorno del servidor. Las URLs de terceros deben estar autorizadas y pasar por las protecciones HTTP de Moodle. Las credenciales de un conector salen únicamente hacia su origen y servicio de autenticación autorizados. El diseño completo no necesita SkillUps Cloud.

[External Services API](https://moodledev.io/docs/5.3/apis/subsystems/external) · [Moodle Task API](https://moodledev.io/docs/5.3/apis/subsystems/task)
