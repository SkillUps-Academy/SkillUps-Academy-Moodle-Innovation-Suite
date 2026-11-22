# SkillUps Course Quality Inspector

**`report_skillupsquality`** · Informe de curso de Moodle · GPL-3.0-or-later

Audita cursos individuales, categorías y lotes mediante tareas Adhoc. El motor de reglas configura severidades a nivel institucional, guarda evidencias, muestra comparaciones históricas y vincula cada hallazgo con planes de corrección. El auditor lee la configuración de Moodle. Las correcciones se realizan desde las herramientas de edición nativas.

- [ZIP instalable](../releases/skillupsquality/skillupsquality-0.9.0-beta.1.zip) y [SHA-256](../releases/skillupsquality/SHA256SUMS)
- [Instalación y administración](docs/administration.md)
- [Guía del usuario y catálogo de reglas](docs/operations.md)
- [Arquitectura](docs/architecture.md)
- [Validación y capturas](docs/validation.md)
- [Ficha para el directorio de plugins](docs/marketplace.md)

## English

Install the ZIP through **Site administration → Plugins → Install plugins**. For manual installation, copy the `skillupsquality` directory to Moodle's public `report` directory and run the native upgrade. Open the course report to request an audit or use **Site administration → Reports → SkillUps Course Quality Inspector** for batches. The Community edition runs entirely on Moodle and exports CSV locally. [Administration guide](docs/administration.md)
