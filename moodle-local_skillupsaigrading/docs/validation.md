# Compatibilidad y validación de 0.9.0-beta.1

Se usaron instancias descartables con PHP 8.3.37 y depuración de desarrollador. La referencia principal es Moodle 5.3 LTS.

| Moodle | Base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Aprobada | 15 pruebas, 59 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Aprobada | 15 pruebas, 59 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Aprobada | 15 pruebas, 59 aserciones |
| 5.3.1 LTS | MariaDB 11.4.10 | Aprobada | 15 pruebas, 59 aserciones |
| 5.3.1 LTS | PostgreSQL 17.11 | Aprobada | 15 pruebas, 59 aserciones |

## Verificado

- PHPUnit comprueba capacidades, módulo, grupos separados, correctores asignados, archivos de otro estudiante, privacidad, rechazo de revisiones desactualizadas, decisiones concurrentes por pestaña, deduplicación y conservación de registros de notas. Se verificó el aislamiento del panel con un conjunto de 510 revisiones de un grupo ajeno.
- La validación de respuestas exige IDs y niveles de la rúbrica, verifica citas, respeta la incertidumbre y descarta contenido marcado como sensible. Se probó la desactivación de IA con un trabajo en cola y se comprobó la eliminación del extracto.
- La asignación de correctores usa el dato nativo anterior a 5.2, la API de Moodle 5.2 y la API que filtra correctores opcionales deshabilitados en 5.3.
- Chromium recorrió el panel, la extracción TXT, la hoja local, la confirmación explícita de envío, la cola, el conector Ollama simulado, la aprobación con retroalimentación editada y el registro de comparación. Moodle mantuvo la nota sintética en 75 %. El proveedor simulado sugirió 90 %.
- Se verificó extracción real de PDF seleccionable con Poppler, DOCX y PPTX con muestras sintéticas en Moodle 5.3. Behat aprobó 2 escenarios y 17 pasos para navegación docente y ausencia del acceso del alumno.
- La licencia firmada se probó con dos identificadores de instalación, validez, gracia y revocación. Moodle CodeSniffer aprobó el estándar Moodle y se comprobó paridad de claves EN/ES.
- La migración de la compilación inicial `2026112100` a `2026112101` pasó en Moodle 5.0.12.
- El validador nativo aceptó el ZIP en Moodle 5.0.12 y 5.3.1. En 5.0.12 también se verificó la desinstalación y reinstalación desde el ZIP. El validador informó la madurez beta y la presencia de una carpeta previa en las instancias de prueba.

## Capturas

- [Panel de la tarea](screenshots/assignment-dashboard.png)
- [Selección de evidencias y autorización](screenshots/evidence-selection.png)
- [Revisión docente con comparación](screenshots/teacher-review.png)

## Límites

- El adaptador admite tareas individuales con identidades visibles y una rúbrica publicada. El análisis procesa una fuente por revisión y conserva el texto extraíble. OCR, escalas y comparación de notas en tareas con más de un corrector configurado quedan fuera de esta versión.
- Las sugerencias y la diferencia con una nota humana requieren interpretación docente. El indicador de diferencia absoluta media se calcula sobre comparaciones registradas y expresa concordancia en esa muestra.
- Las pruebas de IA de esta entrega usaron un servidor simulado. Quedan por validar cuentas reales de Ollama, OpenAI y servidores compatibles, MySQL independiente, otras versiones de PHP y carga sostenida en producción.
- Se incluye un borrador para Moodle Marketplace. La revisión y publicación requieren el flujo del portal.
