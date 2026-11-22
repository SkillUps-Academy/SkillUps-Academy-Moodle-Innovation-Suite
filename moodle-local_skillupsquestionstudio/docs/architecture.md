# Arquitectura de SkillUps Question Studio

## Investigación y plan

La copia local XAMPP contiene Moodle 5.0.7 y PHP 8.2.12. Carece de config.php y no hay motor Moodle configurado en esa copia. El destino principal es Moodle 5.3 LTS. Se usan instancias desechables para comprobar el tipo local, el esquema y la compatibilidad. El nombre del componente estaba libre en el árbol local. La unicidad definitiva corresponde al registro de Moodle Marketplace.

El tipo local permite integrar proyectos de curso, ingestión asíncrona, edición y revisión antes del banco. AI Quiz Generator y Generative AI Question Bank son referencias de funcionalidad. Question Studio usa la API de preguntas de Moodle y su importador `qformat_xml` en categorías de módulos autorizados y pertenecientes al mismo curso. No escribe tablas Core directamente. Las competencias se seleccionan de las ya vinculadas al curso. La IA usa el gestor de proveedores de Moodle bajo una interfaz de generación y comparte el mismo validador que los borradores manuales y locales.

1. Crear el ámbito de curso, las capacidades y el esquema de proyectos, fuentes, revisiones, variantes y publicaciones.
2. Aplicar validación de tamaños y formatos antes de extraer texto. Leer partes Office seleccionadas sin desplegar el ZIP y ejecutar PDF con Poppler confiable en un proceso acotado.
3. Generar propuestas con un motor local y, opcionalmente, con el subsistema IA. Revalidar el actor al ejecutar tareas ad hoc, confinar las fuentes al proyecto y mantener trazabilidad.
4. Validar estructura, respuestas, Bloom, dificultad, competencia y duplicados. Guardar revisiones y requerir aprobación explícita antes de exportar o publicar.
5. Generar variantes de evaluación con revisiones fijadas y opciones ordenadas de manera reproducible. Volver a verificar aprobación y contexto al publicar.
6. Probar formatos, importación Moodle XML, permisos, gobernanza de archivos, privacidad, reintentos transaccionales, compatibilidad e instalación.

Referencias: [Questions API](https://moodledev.io/docs/5.3/apis/subsystems/question), [Moodle XML](https://docs.moodle.org/501/en/Moodle_XML), [AI Quiz Generator](https://marketplace.moodle.com/plugins/local_hlai_quizgen).
