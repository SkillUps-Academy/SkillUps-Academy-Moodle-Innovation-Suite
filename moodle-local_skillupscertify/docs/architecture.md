# Arquitectura

Certify es un plugin local por curso. Cada plantilla tiene revisiones inmutables. La emisión valida las capacidades del operador, su alcance de grupos, la matrícula y la evidencia elegida. El trabajo masivo guarda los destinatarios, utiliza una revisión fija y vuelve a evaluar las autorizaciones al procesar cada fila.

La emisión guarda una instantánea, sella sus fechas, almacena un único PDF por certificación con File API y registra su SHA-256. El registro público recibe un identificador aleatorio de 192 bits por QR y consulta únicamente su hash. Devuelve campos autorizados del momento de emisión y el estado actual. El verificador puede comparar localmente la huella del PDF con la registrada.

La reemisión crea otro identificador y archiva o revoca la edición anterior mediante una transacción. El PDF previo conserva sus bytes. Los cambios se registran con Moodle Events API y en la bitácora del plugin. Docentes y gestores actúan en el contexto del curso y según grupos.

Los proveedores de evidencia y los renderizadores utilizan contratos PHP locales. Una futura implementación de Open Badges o credenciales verificables deberá implementar un perfil propio de firma, estado, pruebas de conformidad e interoperabilidad.

Referencias [File API](https://moodledev.io/docs/5.2/apis/subsystems/files), [Adhoc Tasks](https://moodledev.io/docs/5.2/apis/subsystems/task/adhoc), [Access API](https://moodledev.io/docs/5.3/apis/subsystems/access).
