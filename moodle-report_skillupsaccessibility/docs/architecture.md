# Arquitectura de SkillUps Accessibility Checker

El informe reutiliza contextos, visibilidad de módulos y secciones, Task API, el navegador de archivos, File API y Privacy API. El análisis se limita a contenido didáctico escrito por autores. Las entregas, los foros, los mensajes y los bancos de preguntas quedan fuera del inventario. El motor analiza fragmentos de origen sin ejecutar JavaScript, resolver URLs ni renderizar filtros de terceros.

Una solicitud registra un trabajo por curso con la identidad del solicitante. Un trabajo Adhoc vuelve a verificar permisos y visibilidad antes de recopilar fuentes. Una tarea programada solo opera cuando se configura un usuario autorizado con alcance de sistema. Una clave de bloqueo por curso serializa las auditorías. La huella del contenido y la versión de las reglas permiten reutilizar resultados previos.

La capa de informes revalida el acceso a cada fuente. Las métricas se calculan sobre las fuentes autorizadas y las revisiones permanecen dentro del contexto del curso. El índice expresa la proporción de fuentes evaluadas que no tienen errores comprobados. Una auditoría sin fuentes evaluadas tiene índice indeterminado. El informe presenta también advertencias y criterios fuera del alcance automático.

## Modelo de datos

| Tabla | Función |
| --- | --- |
| `job` | Solicitud por curso, actor autorizado, origen, estado y fechas |
| `asset` | Identidad y ubicación Moodle de la fuente, sin copia del contenido |
| `rev` | Resultado asociado a una huella de fuente y versión de reglas |
| `hit` | Fuentes y revisiones presentes en una auditoría completada |
| `finding` | Hallazgo por fuente, regla y ubicación estructural, con estado y responsable |
| `review` | Historial de decisiones y evidencia privada de File API |

La huella incorpora la versión de reglas y el HTML o el hash de contenido de Moodle. Volver a escanear descubre fuentes nuevas y reutiliza análisis existentes para huellas conocidas. Una auditoría registra las instantáneas nuevas y reutilizadas antes de actualizar el estado. Los cambios de permisos obligan a revalidar las fuentes también al leer una instantánea anterior.

La cola por categoría o institución se amplía en bloques de 60 cursos. El programador busca hasta 12 cursos vencidos por ejecución. Cada auditoría cubre hasta 400 fuentes autorizadas. Una auditoría que supera ese límite queda señalada como parcial. El HTML se limita a 2 MiB y 12.000 elementos, los documentos a 5 MiB y el XML de Word a 2 MiB. Las actividades dinámicas fuera del catálogo de fuentes se contabilizan como no evaluadas cuando el módulo es visible. Las fuentes autorizadas incluyen resúmenes del curso y secciones, páginas, capítulos de libro, introducciones admitidas y archivos locales de Recurso o Carpeta con acceso nativo.

El índice usa `100 × fuentes evaluadas sin errores comprobados / fuentes evaluadas`. Las advertencias, el estado de la revisión humana y las fuentes excluidas se muestran aparte. Los agregados cuentan fuentes, con ponderación por denominador. Cada período usa el último escaneo completado del curso dentro del intervalo. Los títulos y la autorización visible de las fuentes se resuelven al consultar, por lo que el acceso a una instantánea histórica puede cambiar después de mover, restringir o quitar contenido.

- [WCAG 2.2](https://www.w3.org/TR/WCAG22/)
- [Visibilidad y disponibilidad de Moodle](https://moodledev.io/docs/5.0/apis/subsystems/availability)
- [File API](https://moodledev.io/docs/5.3/apis/subsystems/files)
- [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy)

[Resultados de validación](validation.md)
