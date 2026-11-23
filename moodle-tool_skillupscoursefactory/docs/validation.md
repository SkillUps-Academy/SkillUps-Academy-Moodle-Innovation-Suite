# Validación de SkillUps Course Factory

La matriz siguiente describe la validación del motor en 0.9.0-beta.1. La versión 0.9.0-beta.2 mantiene el esquema y añade el contrato de consulta v1. Se verificó su actualización en Moodle 5.3.1 conservando un destino previo y se ejercitó la integración de extremo a extremo. [Prueba de integración](../../moodle-tool_skillupsenrollment/docs/validation.md)

Las pruebas se ejecutaron con instalaciones y datos sintéticos. Moodle 5.3 LTS fue la referencia.

| Moodle | PHP | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- | --- |
| 5.0.12 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 44 aserciones |
| 5.1.9 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 44 aserciones |
| 5.2.5 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 44 aserciones |
| 5.3.1 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 44 aserciones |
| 5.3.1 | 8.3.37 | PostgreSQL 17.12 | Correcta | 12 pruebas, 44 aserciones |

- **PHPUnit** verificó CSV UTF-8, XLSX con fechas y rechazo de fórmulas, vista previa de solo lectura, fechas, roles de docente y coordinador, escritura del período en un campo de curso, backup/restore con adjuntos, exclusión de participantes, cancelación programada, privacidad, reintento sobre el destino registrado, recuperación de restauraciones interrumpidas y duplicados entre operaciones.
- **Carga** en Moodle 5.3.1 con MariaDB. Dos pruebas y 27 aserciones. El motor restauró 100 actividades repartidas en 12 secciones y cotejó los hashes de 20 adjuntos de 128 KiB. La segunda prueba confirmó 500 filas y comprobó que el primer trabajo procesó 10 y dejó 490 pendientes.
- **Behat** en Moodle 5.3.1. Un escenario con 16 pasos correctos desde el formulario hasta la ejecución por tareas nativas.
- **Chromium**. Una carga real CSV generó dos clones procedentes del mismo lote. Se verificaron los adjuntos, la categoría, la publicación de un clon, la conservación del otro como oculto y la matrícula solo del docente solicitado. También se verificaron la creación de una plantilla, la descarga CSV de filas inválidas y la exportación XLSX con una licencia sintética local.
- **Paquete**. Comprobación con el validador de ZIP de Moodle 5.0.12 y 5.3.1, y reinstalación desde el ZIP en Moodle 5.0.12. No había una versión pública anterior para probar una migración.
- **Estática**. PHP CodeSniffer con el estándar Moodle, sintaxis PHP y paridad de cadenas en inglés y español. La aprobación de Moodle Marketplace queda pendiente.

La carga sintética verifica dos tamaños concretos. La capacidad real depende del servidor y de los módulos incluidos en cada origen.

## Capturas

- [Panel e historial](screenshots/dashboard.png)
- [Plantillas institucionales](screenshots/templates.png)
- [Vista previa del lote](screenshots/preview.png)
- [Resultados de clonación y errores](screenshots/results.png)
