# Arquitectura y modelo de datos

## Flujo

Moodle Forms abre el selector nativo de archivos y deposita el borrador en el contexto del usuario. El servicio verifica propiedad del borrador, número de archivos, tipo, tamaño y estructura básica antes de guardar una versión en File API. Cada archivo pertenece al contexto de categoría, componente `local_skillupsrepository`, área `payload` y al ID de versión. El callback `pluginfile` comprueba la ruta exacta, la categoría, el estado, la versión y los permisos.

La vista del catálogo usa DML paginado. El filtro de autorización forma parte de la consulta de resultados y conteos. La búsqueda de metadatos utiliza comparaciones parametrizadas y escapa caracteres comodín. El contenido binario queda fuera del índice. El estado de revisión y la publicación se actualizan mediante transacciones DML y Moodle Lock API por categoría. La generación del recurso evita que un formulario antiguo reemplace una decisión más reciente.

| Tabla con prefijo `local_skillupsrepository_` | Función |
|---|---|
| `asset` | Propietario, audiencia, categoría, archivo lógico, punteros de versión y contador de copias. |
| `revision` | Nombre, descripción, clasificación, licencia, contenido, fechas y estado de revisión. |
| `usage` | Registro de versión copiada, curso, sección, actividad, actor y marca de baja. La clave de solicitud única evita copias duplicadas por reenvío. |
| `audit` | Fecha, actor, recurso, versión y transición. |

Para insertar un archivo, el servicio crea una copia en el borrador temporal de Moodle y llama a `add_moduleinfo()`. Las actividades nativas Archivo y URL administran sus propios datos. El registro de uso identifica copias creadas por esta integración. Las duplicaciones posteriores a través de backup, restauración o duplicado nativo no añaden entradas automáticamente.

## Formatos y seguridad

- PDF, DOCX, PPTX y XLSX.
- PNG, JPEG, GIF y WebP.
- MP3, Ogg y WAV. MP4 y WebM.
- URL HTTP/HTTPS sin credenciales incorporadas, abierta por el navegador.
- ZIP/IMSCC con `imsmanifest.xml` en la raíz y H5P con `h5p.json` en la raíz. Se comprueban entradas del contenedor sin extraer ni ejecutar su contenido.

El límite del plugin complementa los límites de Moodle y PHP. Las descargas de documentos y paquetes se envían como adjuntos. La separación de visualización y descarga aplica a los controles y solicitudes del plugin. Los medios entregados para vista previa ya están disponibles en el navegador del lector.

## Privacidad y retención

El proveedor de privacidad identifica aportes, revisiones, archivos y operaciones en los contextos de categoría autorizados. Al aprobar un borrado de datos del propietario se eliminan sus recursos en ese ámbito. Para aportes secundarios se limpian archivos y metadatos personales de las versiones afectadas y se conserva la trazabilidad estructural. Cuando se borra una categoría, se elimina su biblioteca. Las copias de curso pertenecen al módulo nativo que las recibió y se administran desde ese curso. La política institucional de retención se aplica mediante solicitudes autorizadas de Privacy API. No hay exportación externa de metadatos ni un servicio de licencias en esta edición.

## Referencias de Moodle

- [File API](https://moodledev.io/docs/5.3/apis/subsystems/files)
- [Files in Forms](https://moodledev.io/docs/5.3/apis/subsystems/form/usage/files)
- [Plugins de repositorio](https://moodledev.io/docs/5.2/apis/plugintypes/repository)
- [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy)
