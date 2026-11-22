# Seguridad y privacidad

Comunica fallos de seguridad o posibles exposiciones mediante un aviso privado del repositorio si está habilitado, o contacta por el canal institucional de SkillUps Academy. Usa datos sintéticos en el informe.

## Separación de acceso

Cada vista exige login de Moodle, contexto de curso y una capacidad de Studio. Los IDs de proyectos, fuentes, borradores y revisiones se resuelven dentro del ámbito actual. Publicar requiere una categoría de módulo del mismo curso y permisos nativos. Los trabajadores vuelven a comprobar los permisos del autor. Los cambios en interfaz usan POST y sesskey. Los datos se escapan en Mustache y XML.

## Documentos y generación

La API de archivos almacena temporalmente cargas internas con tamaño limitado. Los archivos Office se analizan leyendo únicamente partes ZIP seleccionadas. Se comprueban tamaños, rutas, duplicados, cifrado declarado y tipos de partes. El parser XML evita DTD y red, y Poppler opera con argumentos fijos, proceso independiente, límite de tiempo y archivos privados de trabajo. La eliminación usa las APIs de Moodle y el ciclo de limpieza de su almacén.

El proveedor local trabaja sobre texto. La IA está deshabilitada inicialmente, requiere permisos, política Moodle y consentimiento. Moodle puede registrar el prompt en su subsistema IA. Cada proveedor administra sus propias políticas de procesamiento y conservación. Studio limita el fragmento enviado a 10 000 caracteres, no añade un perfil del usuario al prompt y carece de código para entrenar modelos. La IA devuelve propuestas de texto que deben pasar validación y aprobación docente.

## Eliminación y exportación

La API de privacidad exporta las contribuciones aprobadas del interesado, elimina sus fuentes y trabajos, y anonimiza la atribución de material institucional que se conserva en el proyecto. La eliminación completa desde la interfaz y el borrado de curso retiran el material del plugin. Revisa textos libres si una persona ha escrito datos de terceros en nombres, enunciados o fuentes. Las preguntas publicadas y los registros de IA de Moodle se gestionan mediante sus subsistemas respectivos.
