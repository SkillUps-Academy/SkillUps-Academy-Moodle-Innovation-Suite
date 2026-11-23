# Seguridad y privacidad

Reporta vulnerabilidades de forma privada a los administradores del repositorio. Incluye la versión, el alcance y pasos reproducibles con datos sintéticos.

| Riesgo | Control |
| --- | --- |
| Enumeración de certificados | Identificador aleatorio de 192 bits, búsqueda exacta mediante SHA-256 y respuesta uniforme para códigos inexistentes o malformados. |
| Acceso a documentos ajenos | Sesión Moodle y autorización por titular, curso y grupos. Los documentos se almacenan en un área privada de File API. |
| Acciones en nombre de otro usuario | Capacidades de Moodle y sesskey para acciones con estado. El lote vuelve a comprobar el alcance del operador y la elegibilidad de cada destinatario. |
| Falsificación o copia del QR | Registro bajo el dominio del emisor y comparación SHA-256 de los bytes completos. Cada PDF se comprueba antes de servirse. |
| Inyección en PDF, HTML o CSV | Campos de diseño de texto plano y permitidos por lista. Mustache escapa la salida y el CSV neutraliza fórmulas. |
| Fuga por registros o enlaces | Sin indexación, caché pública ni referrer en el verificador. Se guarda el hash del código en la tabla de emisión. |

Usa HTTPS en producción. El QR y la URL actúan como un enlace compartible. El código aparece en la URL y en el PDF original. Configura la redacción de la consulta `code` en registros del aprovisionador, del proxy y de analítica. Cuando autorices metadatos públicos, usa un título e institución aptos para divulgación. El verificador descarta los datos identificativos del destinatario. La comparación de archivos ocurre en un navegador con Web Crypto y el archivo permanece en el dispositivo.

El acceso a la base de datos y a la configuración de la institución forma parte del perímetro de confianza. La verificación demuestra el estado del registro y permite comparar los bytes del archivo. Las firmas PDF embebidas y la validación sin conexión quedan fuera del perfil implementado.

La API de privacidad de Moodle permite exportar o eliminar los registros y PDF de la persona en el curso aprobado. La eliminación aprobada invalida el enlace público. La bitácora local conserva el tipo de acción con actor anonimizado. El log central de Moodle aplica su propia política.
