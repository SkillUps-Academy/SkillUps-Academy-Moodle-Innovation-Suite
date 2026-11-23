# SkillUps Course Factory

**`tool_skillupscoursefactory`** · Herramienta de administración de Moodle · GPL-3.0-or-later

Crea aulas y clona contenidos mediante las APIs nativas de Moodle. La interfaz bilingüe admite una solicitud individual o un lote CSV/XLSX, con vista previa, plantillas institucionales, destinos por categoría, programación, matriculación y seguimiento por fila.

- [Instalación y configuración](docs/administration.md)
- [Operación, importación y recuperación](docs/operations.md)
- [Arquitectura](docs/architecture.md)
- [Pruebas y capturas](docs/validation.md)
- [Borrador para Moodle Marketplace](docs/marketplace.md)
- [ZIP instalable](../releases/skillupscoursefactory/skillupscoursefactory-0.9.0-beta.1.zip)

## English

Install the ZIP from **Site administration → Plugins → Install plugins**. Open **Site administration → Courses → SkillUps Course Factory**. Choose a source course or institutional template to copy content, or leave the source empty to create a course. Review the preview before confirming. Moodle cron handles due work in bounded slices. [Operator guide](docs/operations.md)
