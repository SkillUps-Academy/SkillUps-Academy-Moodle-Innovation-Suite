# Compatibilidad y validación de 0.9.0-beta.1

Se usaron instancias desechables con PHP 8.3.37 y depuración de desarrollador. Moodle 5.3 LTS es el destino principal.

| Moodle | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Aprobada | 12 pruebas y 43 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Aprobada | 12 pruebas y 43 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Aprobada | 12 pruebas y 43 aserciones |
| 5.3.1 LTS | MariaDB 11.4.10 | Aprobada | 12 pruebas y 43 aserciones |
| 5.3.1 LTS | PostgreSQL 17.11 | Aprobada | 12 pruebas y 43 aserciones |

## Verificaciones

- PHPUnit importó los cinco tipos a categorías de un banco Moodle con `qformat_xml`. Verificó aprobación previa, repetición idempotente, rechazo de una categoría de otro curso y revocación de una variante al reabrir una pregunta.
- La suite cubrió validación de respuestas, pesos XML, conservación de la clave al reordenar, generación local, deduplicación, extracción de TXT, DOCX y PPTX, rechazo de recorridos de directorio en ZIP y eliminación mediante la API de privacidad.
- En Moodle 5.3.1, Poppler extrajo el contenido seleccionable de un PDF sintético y el trabajador eliminó la copia pendiente del File API.
- Moodle CodeSniffer aprobó el estándar Moodle. Se comprobó paridad de claves entre inglés y español.
- Moodle 5.0.12 confirmó actualización sin cambios y desinstalación CLI. Esta es la primera versión y aún carece de una migración desde una versión publicada.
- Behat aprobó 2 escenarios y 16 pasos en Moodle 5.3.1, incluida la ausencia del enlace de Studio para estudiantes.
- En Chromium se verificó la carga TXT, la generación local, la edición de una segunda revisión, la aprobación explícita, la variante, la exportación Moodle XML y la repetición de una publicación sin crear duplicados.
- El validador nativo de Moodle aceptó el ZIP en 5.0.12 y 5.3.1. La reinstalación con el archivo extraído pasó en Moodle 5.0.12.

## Capturas

- [Panel de proyectos](screenshots/project-dashboard.png)
- [Importador y gestor del proyecto](screenshots/question-project.png)
- [Editor y revisión docente](screenshots/question-editor.png)
- [Vista previa de la evaluación](screenshots/assessment-preview.png)

## Límites conocidos

- Quedan pendientes proveedores IA reales, MySQL independiente, otras matrices de PHP y pruebas de carga sostenida.
- La importación de PDF usa Poppler del sistema y requiere texto seleccionable. Los documentos que solo contienen imágenes quedan fuera del alcance del extractor. El procesamiento Office conserva texto y omite imágenes, objetos, comentarios y diagramas. PPTX se lee en el orden numérico de sus partes.
- Los borradores locales son propuestas basadas en glosarios o fragmentos. La calidad, exactitud y clasificación requieren revisión docente. La vista previa ordena preguntas y respuestas. El cuestionario y la administración de sus intentos se gestionan desde Moodle.
- Las competencias se mapean en Studio. Moodle XML conserva una etiqueta informativa con el ID de la competencia. Una institución que importe en otro sitio puede reclasificar esa etiqueta.
- El documento para Moodle Marketplace es un borrador de presentación.
