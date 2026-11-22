# Validación de SkillUps Course Quality Inspector

Las pruebas se ejecutaron en instalaciones desechables con datos y cuentas sintéticos. Moodle 5.3 LTS es la plataforma primaria.

| Moodle | PHP | Base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- | --- |
| 5.0.12 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 48 aserciones |
| 5.1.9 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 48 aserciones |
| 5.2.5 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 48 aserciones |
| 5.3.1 | 8.3.37 | MariaDB 11.4.10 | Correcta | 12 pruebas, 48 aserciones |
| 5.3.1 | 8.3.37 | PostgreSQL 17.12 | Correcta | 12 pruebas, 48 aserciones |

- **PHPUnit** verificó detección de cursos vacíos, fechas, calificaciones, rúbricas, ocultación, finalización y adjuntos. Cubrió puntuación, revocación de permisos, aislamiento por curso y categoría, duplicados, recuperación, retención, comparación, planes, privacidad y licencias firmadas. También verificó que un enlace fuera del límite de análisis no se marque como corregido.
- **Behat** en Moodle 5.3.1. Un escenario y 22 pasos correctos, desde la solicitud hasta guardar el plan y mostrar la verificación pendiente.
- **Chromium** en Moodle 5.3.1. Verificó lotes de categoría y del sitio, ejecución por el comando Adhoc de Moodle, detalle de evidencia, descarga CSV, edición de un plan y una segunda revisión. El curso de prueba pasó de 92 a 100 después de retirar la referencia a un adjunto ausente. Se verificó también la descarga XLSX con una licencia local sintética y una hoja de cálculo válida.
- **Carga sintética** en Moodle 5.3.1. Una revisión con 300 actividades registró 305 hallazgos de diseño en 78 ms en el entorno de prueba. El tiempo excluye la generación de datos y no representa rendimiento garantizado en producción.
- **Paquete**. Moodle validó el ZIP en 5.0.12 y 5.3.1. Se desinstaló y reinstaló desde el ZIP extraído en 5.0.12. No existía una versión pública anterior que migrar.
- **Estática**. PHP CodeSniffer con el estándar Moodle y comprobación de sintaxis. El proceso de aprobación de Moodle Marketplace queda pendiente.

## Capturas

- [Panel del sitio](screenshots/dashboard.png)
- [Historial del curso](screenshots/history.png)
- [Resultado y puntuación](screenshots/audit.png)
- [Evidencia de un enlace con archivo ausente](screenshots/finding.png)
- [Comparación entre auditorías](screenshots/comparison.png)
- [Seguimiento de correcciones](screenshots/plans.png)
