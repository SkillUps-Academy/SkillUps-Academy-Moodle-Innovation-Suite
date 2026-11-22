# Compatibilidad y validación de 0.9.0-beta.1

Las instancias desechables usaron PHP 8.3.37 y depuración de desarrollador. Moodle 5.3 LTS es el destino principal.

| Moodle | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Aprobada | 10 pruebas, 35 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Aprobada | 10 pruebas, 35 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Aprobada | 10 pruebas, 35 aserciones |
| 5.3.1 LTS | MariaDB 11.4.10 | Aprobada | 10 pruebas, 35 aserciones |
| 5.3.1 LTS | PostgreSQL 17.11 | Aprobada | 10 pruebas, 35 aserciones |

## Pruebas ejecutadas

- PHPUnit verificó validación de grafos, rutas, cambios nativos de visibilidad y matrícula, notificación con el interceptador de mensajes de Moodle, aprobación de otra cuenta, esperas, eventos, intervalos, reintentos, fallo parcial, recuperación de tareas y revocación por privacidad.
- Una prueba con dos procesos de admisión y dos trabajadores reales en PostgreSQL completó una sola ejecución para una misma clave.
- Moodle CodeSniffer aprobó el estándar Moodle. Se comprobó la paridad de 174 cadenas entre inglés y español.
- Moodle 5.0.12 confirmó actualización idempotente y desinstalación por CLI. Esta es la primera versión y carece de migraciones entre esquemas publicados.
- Behat en Moodle 5.3.1 aprobó 2 escenarios y 7 pasos.
- Chromium verificó creación visual, movimiento y conexión de nodos, activación, lanzamiento, aprobación con una cuenta delegada, historial e importación de una plantilla JSON.
- El validador de Moodle aceptó el ZIP en 5.0.12 y 5.3.1. La reinstalación desde el ZIP se completó en Moodle 5.0.12.

## Capturas

- [Constructor visual](screenshots/workflow-builder.png)
- [Panel de flujos](screenshots/workflow-dashboard.png)
- [Bandeja de aprobaciones](screenshots/approvals.png)
- [Detalle de una ejecución](screenshots/execution-detail.png)

## Límites conocidos

El motor ejecuta una ruta por decisión, hasta 40 nodos, sin ciclos. Los horarios recurrentes usan intervalos transcurridos. No se incluyen reglas de calendario complejas ni ejecución paralela dentro de una misma instancia. Los eventos observados se limitan a los cuatro enumerados en la guía. Las claves de deduplicación se conservan mientras existen los registros de ejecución. La prueba de notificación utilizó el interceptor de Moodle y no probó un servidor de correo real.

MySQL independiente, otras versiones de PHP y cargas institucionales prolongadas quedan pendientes de validación. La ficha de Marketplace es un borrador de presentación.
