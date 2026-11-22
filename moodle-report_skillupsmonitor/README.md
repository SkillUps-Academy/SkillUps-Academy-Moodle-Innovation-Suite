# SkillUps System Monitor

**Componente:** `report_skillupsmonitor` · **Tipo:** informe de sitio Moodle · **Licencia:** GPL-3.0-or-later

Panel operativo con observaciones programadas, estado de cron, colas de tareas, diagnósticos autorizados, historial de incidentes, alertas y reportes de disponibilidad interna. La edición Community opera sin acceso a servicios externos. Moodle 5.3 LTS es la plataforma de referencia.

- [Instalación y configuración](docs/administration.md)
- [Guía para operadores y definiciones de métricas](docs/operations.md)
- [Prometheus, Grafana y sondas externas](docs/integrations.md)
- [Diseño técnico](docs/architecture.md)
- [Pruebas y capturas](docs/validation.md)
- [Ficha para el directorio de plugins de Moodle](docs/marketplace.md)
- [ZIP instalable](../releases/skillupsmonitor/skillupsmonitor-0.9.0-beta.1.zip) y [SHA-256](../releases/skillupsmonitor/SHA256SUMS)

## Install in English

Install the ZIP under **Site administration → Plugins → Install plugins**, or place `skillupsmonitor` under Moodle’s public `report` directory and run the Core CLI upgrade. Open **Site administration → Reports → SkillUps System Monitor**. Run Moodle cron every minute. The plugin samples every five minutes and applies site-level permissions. [Administration guide](docs/administration.md)
