# Catálogo de reglas y cobertura

Las referencias sitúan cada hallazgo dentro de WCAG 2.2. Un resultado describe el fragmento de origen inspeccionado. La conformidad exige evaluar el contenido renderizado y los criterios que requieren juicio humano. [WCAG 2.2, requisitos de conformidad](https://www.w3.org/TR/WCAG22/#conformance-reqs)

| Regla | Señal comprobada o motivo de revisión | Clase | WCAG 2.2 |
| --- | --- | --- | --- |
| `imgalt` | Imagen sin atributo alt ni indicio alternativo ARIA o declarativo de decoración | Error estático | 1.1.1 |
| `emptylink` | Enlace navegable sin texto detectable, nombre ARIA, título ni alt en imagen descendiente | Error estático | 2.4.4 |
| `formlabel` | Control sin etiqueta asociada, nombre detectable ni marcador | Error estático | 3.3.2, 4.1.2 |
| `frametitle` | Iframe sin título ni indicio de nombre ARIA | Error estático | 4.1.2 |
| `genericlink` | Texto genérico reconocido en inglés o español | Revisión | 2.4.4 |
| `headskip` | Aumento de más de un nivel entre encabezados del fragmento | Revisión | 1.3.1, 2.4.6 |
| `tableheaders` | Dos o más filas y columnas sin celdas de encabezado ni roles reconocidos | Revisión | 1.3.1 |
| `placeholder` | Control con marcador y sin otra etiqueta detectable | Revisión | 3.3.2 |
| `videocaptions` | Vídeo sin pista captions declarada en el fragmento | Revisión | 1.2.2 |
| `audiotranscript` | Audio sin indicador textual cercano de transcripción | Revisión | 1.2.1 |
| `contrast` | Color y fondo opacos literales dentro del mismo atributo style | Revisión | 1.4.3 |
| `positivetab` | Índice de tabulación positivo | Revisión | 2.4.3 |
| `movingmarkup` | Elementos históricos marquee o blink | Revisión | 2.2.2 |
| `docxalt` | Dibujo Word sin metadatos alternativos detectables | Revisión | 1.1.1 |
| `docxheading` | Salto entre estilos convencionales Heading1–Heading9 del cuerpo Word | Revisión | 1.3.1, 2.4.6 |
| `pdfstructure` | Marcadores estructurales o señal explícita de etiquetado en los bytes inspeccionados | Revisión | 1.3.1, 1.3.2 |

El contraste usa luminancia relativa sRGB. Aplica 4,5:1 en ausencia de tamaño grande explícito y 3:1 desde 24 px o desde 18,667 px en negrita explícita. Se omiten las combinaciones cuya presencia visual depende de variables, opacidad, imágenes de fondo o reglas no resueltas. Toda señal exige cotejar la composición visual real.

El texto plano y los formatos Moodle que requieren renderizado se excluyen del índice. La regla PDF se limita a metadatos detectables. Los PDF siempre quedan fuera del denominador del índice. La regla DOCX lee únicamente `word/document.xml` y analiza sus estilos convencionales. Una alternativa vacía en HTML puede ser correcta para una imagen decorativa. La pertinencia del texto alternativo, el etiquetado PDF, el teclado, el lector de pantalla, el reflujo, el foco, las interacciones y los subtítulos reales se revisan manualmente.
