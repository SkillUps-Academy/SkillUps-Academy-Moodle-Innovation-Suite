# SkillUps Course Migration

- **Componente** `tool_skillupsmigration`
- **Edición** Community · SkillUps Moodle Innovation Suite
- **Versión** 0.9.0-beta.1
- **Idiomas** Español e inglés

Herramienta administrativa para inventariar, simular, restaurar y auditar cursos entre instalaciones Moodle. El origen genera un MBZ mediante Backup API y el destino valida su contenido y lo restaura con Moodle Restore en un curso oculto nuevo.

1. Instala el [ZIP](../releases/skillupsmigration/skillupsmigration-0.9.0-beta.1.zip) en ambas instancias.
2. Habilita el servicio REST de origen para una cuenta autorizada y configura el registro seguro del destino.
3. Selecciona los cursos y la categoría de destino. Ejecuta y revisa la simulación.
4. Confirma la restauración. Revisa las diferencias antes de publicar cursos desde Moodle.

- [Administración, instalación y conectividad](docs/administration.md)
- [Manual de operación](docs/usage.md)
- [Compatibilidad y límites](docs/compatibility.md)
- [Arquitectura, persistencia y privacidad](docs/architecture.md)
- [Pruebas](docs/validation.md)
- [Cambios](CHANGELOG.md) · [Seguridad](SECURITY.md)

## English listing

### Short description

Plan and validate native Moodle course migrations between manually authorized Moodle instances.

### Full description

SkillUps Course Migration inventories origin courses using a dedicated Moodle REST service, transfers bounded verified native backups, and restores approved migrations to new hidden courses. It supports course, section and activity selection, explicit category and account mappings, recoverable transfer, compatibility reporting, and an audited restore boundary.

GPL v3 o posterior. SkillUps Academy mantiene este componente de forma independiente. El ZIP utiliza las APIs y extensiones PHP de la instalación Moodle y no incluye dependencias de terceros ni requiere Composer para instalarse.

[Incidencias de la suite](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues) · [Requisitos de publicación de Moodle Marketplace](https://moodle.atlassian.net/wiki/external/ODRhYjAyNTY4NDVmNGJlNjljN2ViMzkwYzdmYmIwMGI)
