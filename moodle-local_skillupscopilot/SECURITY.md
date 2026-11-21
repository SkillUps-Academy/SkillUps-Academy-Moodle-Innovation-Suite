
# Seguridad y privacidad de SkillUps AI Copilot

Para comunicar una posible exposición, usa el aviso privado de seguridad del repositorio si está habilitado o contacta al administrador responsable de SkillUps Academy por el canal institucional. Incluye versión, contexto y pasos de diagnóstico sin datos estudiantiles, identificadores innecesarios ni credenciales.

## Controles implementados

- Inicio de sesión, usuario activo y capacidades del contexto. Las conversaciones se consultan por ID, propietario y curso. Los documentos se autorizan antes de leer el archivo y sus permisos se comprueban al mostrar la respuesta.
- Formularios Moodle con sesskey y confirmación para borrar. Las respuestas y mensajes se escapan en Mustache. Los valores acceden a DML con parámetros. La decisión de recuperar materiales, los permisos y el destino HTTP se controlan en el servidor.
- Archivos de texto UTF-8 limitados y guardados mediante la API de archivos de Moodle. El grupo y la visibilidad de Moodle restringen el acceso si el documento se vinculó a ellos.
- HTTPS para destinos externos, certificados verificados, credenciales desde el entorno, redirecciones deshabilitadas, cuotas y límites de entrada, salida y tiempo. El modelo no dispone de herramientas para ejecutar operaciones en Moodle.
- Eventos de Moodle para envíos, valoraciones, eliminación, exportación y gestión. Los logs y errores de proveedor no incluyen prompts, resultados ni claves.

## Datos y transferencia

El sitio guarda prompts, respuestas, valoraciones, IDs de fuentes y estado. Un registro independiente contiene usuario, curso, proveedor, horas y consumo. Las fuentes son materiales del curso curados institucionalmente. No deben incluir entregas, notas, datos de matrícula u otros datos personales. La API de privacidad registra las categorías, permite exportar y borrar los datos de usuario aprobados y evita exportar respuestas fundamentadas en una fuente actualmente restringida. Los logs del núcleo siguen la política de Moodle.

La solicitud de generación puede contener el mensaje, contenido reciente elegible del mismo proveedor y hasta cuatro fragmentos de fuentes autorizadas. El plugin no entrena modelos ni envía un objeto de perfil o credenciales Moodle al proveedor. El tratamiento y la retención posteriores dependen del proveedor y del contrato institucional. La retirada de permisos protege lecturas futuras en Moodle, pero no elimina contenido de solicitudes que ya hayan salido de la institución. La aplicación tampoco puede impedir que un usuario copie manualmente información personal en su mensaje.

La recuperación es léxica, no usa embeddings ni un índice externo. Los textos seleccionados pueden contener instrucciones engañosas. Se tratan como datos, la respuesta se presenta como texto escapado y el modelo carece de herramientas o conexiones de escritura a Moodle. La exactitud de cualquier cita requiere revisión humana.
