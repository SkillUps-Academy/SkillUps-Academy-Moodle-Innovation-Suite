
# Compatibilidad y validación de 0.9.0-beta.1

## Matriz ejecutada

La matriz se ejecutó en instancias Docker desechables, con PHP 8.3.37 y depuración de desarrollador. El destino principal es Moodle 5.3 LTS.

| Moodle | Motor | Instalación CLI | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Aprobada | 9 pruebas, 49 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Aprobada | 9 pruebas, 49 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Aprobada | 9 pruebas, 49 aserciones |
| 5.3.1 LTS | MariaDB 11.4.10 | Aprobada | 9 pruebas, 49 aserciones |
| 5.3.1 LTS | PostgreSQL 17.11 | Aprobada | 9 pruebas, 49 aserciones |

Las versiones 5.0–5.2 de la matriz demuestran compatibilidad funcional de los escenarios probados. Moodle 5.0 ya está fuera de soporte de seguridad. [Versiones oficiales](https://moodledev.io/general/releases)

## Verificaciones aprobadas

- PHPUnit cubrió aislamiento entre propietarios, cursos, grupos y actividades, invalidación de fuentes, máximo de 40 documentos, límites de fragmentos, idempotencia, cuotas, persistencia de tokens, autorización externa, verificación de firma, revocación y borrado por la API de privacidad.
- Behat en Moodle 5.3.1 con MariaDB aprobó 2 escenarios y 18 pasos de navegación y permisos visibles.
- Chromium en Moodle 5.3.1 con MariaDB verificó navegación, chat, transporte HTTP, metadatos de uso, valoración, carga de documentos, retirada de una fuente, ocultación de su respuesta, eliminación de conversación y persistencia de una cuota específica. Se utilizó un servidor local que simulaba el protocolo de Ollama.
- PHP_CodeSniffer aprobó el estándar Moodle. PHP 8.2.12 se utilizó además para revisar la sintaxis del código.
- El CLI de Moodle 5.0.12 comprobó que una segunda ejecución de actualización no aplicaba cambios y desinstaló el plugin correctamente. Esta primera versión aún carece de migraciones entre esquemas publicados.
- El validador ZIP nativo de Moodle aceptó el paquete en 5.0.12 y 5.3.1. Indicó la madurez beta y la existencia del directorio de destino de ensayo. Tras desinstalar y extraer el ZIP, el CLI reinstaló el plugin en Moodle 5.0.12.

## Límites y pendientes

- OpenAI y las APIs compatibles requieren credenciales y destinos institucionales para las pruebas en vivo. La verificación funcional de HTTP utilizó un proveedor simulado. Tampoco se evaluó la calidad de inferencia de un modelo Ollama real.
- MySQL independiente, instalaciones con PHP 8.4, proveedores reales, cargas concurrentes a escala de producción y migraciones futuras requieren pruebas adicionales.
- RAG usa coincidencia léxica sobre documentos TXT y Markdown curados. No indexa directamente PDFs, DOCX, entregas, foros, notas ni archivos de otros componentes. Los borradores de actividades y preguntas son textuales y pasan por revisión humana.
- La publicación en GitHub requiere acceso de escritura al repositorio de la suite. La publicación en Moodle Marketplace requiere una cuenta autorizada y la revisión de la ficha. El borrador de la presentación y los materiales de revisión están incluidos.

## Capturas de la instancia de prueba

- [Chat de curso](screenshots/course-chat.png)
- [Chat en móvil](screenshots/course-chat-mobile.png)
- [Administración de proveedores y cuotas](screenshots/provider-dashboard.png)
- [Fuentes del curso](screenshots/course-sources.png)
