# Validación de SkillUps System Monitor

La referencia principal es Moodle 5.3 LTS. Todas las pruebas usaron instalaciones desechables y observaciones sintéticas.

| Moodle | PHP | Almacenamiento | Instalación | PHPUnit |
| --- | --- | --- | --- | --- |
| 5.0.12 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 48 aserciones |
| 5.1.9 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 48 aserciones |
| 5.2.5 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 48 aserciones |
| 5.3.1 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 48 aserciones |
| 5.3.1 | 8.3.37 | PostgreSQL 17.12 | Correcta | 10 pruebas, 48 aserciones |

PHPUnit cubre muestreo idempotente, señal real de cron, transiciones de incidentes, intervalos perdidos, mantenimiento, reconciliación de disponibilidad y latencia, autorización nativa, revocación de acceso antes de entregar alertas, deduplicación, privacidad, retención y codificación de series sin datos.

- **Behat** en Moodle 5.3.1 con Firefox. Un escenario y 11 pasos correctos. Verifica el primer uso sin muestras y navegación entre panel, disponibilidad, incidentes y diagnóstico.
- **Chromium** en Moodle 5.3.1. Verificó panel e historial, resumen CSV con 72.7273 % de disponibilidad observada y 100 % de cobertura, revisión de un incidente, diagnóstico, ajustes, informe de estado nativo y rutas externas deshabilitadas.
- **HTTPS** en Moodle 5.1.9 con Apache y TLS local. Se verificaron métricas y sonda autenticadas con 200, credencial inválida con 401, POST con 405, HEAD con 200 y sonda durante mantenimiento con 503. Las respuestas de prueba omitieron cookies y usaron `Cache-Control: no-store`.
- **Paquete y estilo**. Comprobación con el validador de ZIP de Moodle 5.0.12 y 5.3.1. Instalación desde el ZIP extraído por Moodle 5.0.12. PHP CodeSniffer con el estándar Moodle.
- La plantilla Grafana y el ejemplo de configuración se incluyen para integración. Queda pendiente una prueba de despliegue con un Prometheus, Blackbox Exporter y Grafana externos operativos.

## Capturas

- [Dashboard operativo](screenshots/dashboard.png)
- [Informe de disponibilidad](screenshots/availability.png)
- [Historial de incidentes](screenshots/incidents.png)
- [Diagnóstico de Moodle](screenshots/diagnostics.png)
