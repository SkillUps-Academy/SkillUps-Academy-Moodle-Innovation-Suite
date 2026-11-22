# Compatibilidad y validación de 0.9.0-beta.1

La prueba se realizó en instancias desechables con PHP 8.3.37 y depuración de desarrollador. Moodle 5.3 LTS es el destino principal.

| Moodle | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Aprobada | 5 pruebas, 29 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Aprobada | 5 pruebas, 29 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Aprobada | 5 pruebas, 29 aserciones |
| 5.3.1 LTS | MariaDB 11.4.10 | Aprobada | 5 pruebas, 29 aserciones |
| 5.3.1 LTS | PostgreSQL 17.12 | Aprobada | 5 pruebas, 29 aserciones |

## Verificado

- PHPUnit reconcilió matrículas, eventos distintos de estudiantes y docentes, participación, una nota de curso de 80 %, y una finalización entre dos estudiantes elegibles. Excluyó registros anónimos, cuentas suspendidas y sesiones de suplantación. Sin lector estándar activo, las métricas de eventos quedaron ausentes.
- Se verificaron publicación completa por lotes, acceso por categoría, rechazo de la exportación fuera del ámbito y bloqueo a un docente sin acceso a todos los grupos. La prueba de datos aditivos confirmó la proporción ponderada, supresión y neutralización de fórmulas.
- Se probó reautorización en cron y eliminación de configuraciones por propietario. Behat completó 2 escenarios y 12 pasos en Moodle 5.3.1.
- Chromium verificó períodos comparados, agrupación por campo de programa, creación de un informe personalizado, exportaciones CSV, XLSX y PDF, y una meta alcanzada después de ejecutar cron. En la captura sintética, la participación pasó de 62,5 % a 87,5 %.
- Moodle CodeSniffer aprobó el estándar Moodle. Se verificó paridad de cadenas EN/ES. La migración de la compilación de desarrollo `2026112100` a `2026112101` se ejecutó en MariaDB y PostgreSQL.
- El validador nativo aceptó el ZIP en Moodle 5.0.12 y 5.3.1. La desinstalación y reinstalación desde el ZIP pasó en Moodle 5.0.12. El validador informó el estado beta y la carpeta de prueba existente.

## Capturas

- [Panel ejecutivo y comparación](screenshots/executive-dashboard.png)
- [Constructor de informes](screenshots/report-builder.png)
- [Programación y meta alcanzada](screenshots/indicator-target.png)

## Límites y trabajo pendiente

- El lector de analítica de eventos usa el almacén estándar. La fuente puede tener retención incompleta. Los eventos reflejan acciones registradas y no duración o presencia en tiempo real.
- El histórico guarda capturas calculadas desde la instalación. La primera recomputación de un período antiguo usa las matrículas y notas aún observables. No recupera registros borrados ni notas sobrescritas.
- Los reportes y metas guardados pertenecen a su propietario. El ámbito puede ser institucional, de categoría o de curso. Los avisos programados enlazan el informe bajo los permisos vigentes.
- Quedan pruebas de carga sostenida, MySQL independiente y otras versiones de PHP. Marketplace se incluye como borrador.
