# Compatibilidad y validación de 0.9.0-beta.1

Las pruebas usaron Moodle con PHP 8.3.37 y depuración de desarrollador en instancias descartables. Moodle 5.3 LTS es el destino principal.

| Moodle | Base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Aprobada | 7 pruebas, 51 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Aprobada | 7 pruebas, 51 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Aprobada | 7 pruebas, 51 aserciones |
| 5.3.1 LTS | MariaDB 11.4.10 | Aprobada | 7 pruebas, 51 aserciones |
| 5.3.1 LTS | PostgreSQL 17.12 | Aprobada | 7 pruebas, 51 aserciones |

## Comprobaciones

- Tendencias del libro de calificaciones, plazos efectivos de Assignment, prórrogas y ausencia de cambios en las notas o entregas tras registrar intervenciones.
- Captura idempotente, casos aislados por curso y tutor, revisión concurrente obsoleta, ventana de 14 días, cohorte con negativos y borrado selectivo de datos.
- Estimación reproducible con coeficientes sintéticos, parámetros autorizados, abstención con datos faltantes y contribuciones por factor. Se comprobaron falsos positivos, falsos negativos, Brier, supresión y contraste de tasas de 75 % y 25 % en cohortes simuladas.
- Behat aprobó 2 escenarios y 16 pasos en Moodle 5.3.1. Chromium verificó el panel, el modelo sintético, el detalle de vencimientos, la asignación de tutor, el seguimiento, la revisión independiente y las estadísticas sin nombres.
- Moodle CodeSniffer aprobó el estándar Moodle y se verificó la paridad de cadenas EN/ES.
- El validador nativo aceptó el ZIP en Moodle 5.0.12 y 5.3.1. Moodle 5.0.12 completó desinstalación y reinstalación desde el archivo. El validador informó madurez beta y destino previo existente en las instancias de prueba.

## Capturas

- [Panel de casos](screenshots/support-dashboard.png)
- [Caso, factores e intervención](screenshots/identified-case.png)
- [Reporte agregado de calidad](screenshots/quality-report.png)

## Límites

- Las pruebas de predicción, discrepancias y seguimiento utilizan datos sintéticos. Quedan pendientes validación y calibración con cohortes institucionales autorizadas, carga sostenida y MySQL independiente. La entrega no incluye un modelo entrenado ni evidencia de validación demográfica.
- El seguimiento de entregas cubre Assignment individual compatible. Se utiliza el lector estándar para participación y el último acceso de Moodle para inactividad. Se omiten datos ausentes según el diccionario de señales.
- El histórico de evolución comienza al activar el curso. Las alertas se revisan en el panel y las medidas académicas pertenecen al equipo autorizado.
- Se incluye un borrador de Moodle Marketplace para revisión posterior.
