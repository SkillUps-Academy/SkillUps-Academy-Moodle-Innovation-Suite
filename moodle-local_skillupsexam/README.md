# SkillUps Exam Manager

- **Componente** `local_skillupsexam`
- **Edición** Community · SkillUps Moodle Innovation Suite
- **Idiomas** English, español
- **Versión** 0.9.0-beta.1
- **Moodle verificado** 5.0.12, 5.1.9, 5.2.5 y 5.3.1

Organiza convocatorias institucionales de examen en Moodle. Planifica turnos, asigna personal y espacios, controla el aforo, gestiona inscripciones operativas y registra asistencia e incidencias. Consulta intentos de Quiz, entregas de Assign y resultados visibles del Gradebook con los permisos nativos.

## Inicio rápido

1. Instala el ZIP de [la carpeta de versiones](../releases/skillupsexam/) desde **Administración del sitio → Plugins → Instalar plugins**. La carpeta del plugin es `local/skillupsexam`.
2. Abre **Períodos y espacios académicos** y crea el período y los espacios en las categorías Moodle correspondientes.
3. Configura las capacidades del coordinador y del supervisor en el ámbito que corresponda.
4. Crea una convocatoria, vincula una evaluación del mismo curso y publícala. Los avisos se procesan por cron.

- [Instalación y administración](docs/administration.md)
- [Manual de usuario](docs/usage.md)
- [Arquitectura, APIs y privacidad](docs/architecture.md)
- [Evidencias y compatibilidad](docs/validation.md)
- [Seguridad](SECURITY.md) · [Cambios](CHANGELOG.md) · [Contribuir](CONTRIBUTING.md)

## English listing

**Short description:** Plan Moodle exam convocations, coordinate rooms and supervisors, and review authorized participation and outcomes.

**Full description:** SkillUps Exam Manager links institutional exam convocations to Moodle Quiz and Assignment activities. Coordinators manage academic periods, room capacity, staff, rosters, status, and scheduling conflicts. Authorized staff record attendance and incidents, review native participation metadata and visible Gradebook results, and export scoped reports. Moodle cron sends generic notices. Moodle Privacy API supports course-context export and erasure.

Distribuido bajo GPL v3 o posterior. Sin dependencias de Composer para el instalador ni librerías externas distribuidas. Los flujos de esta edición funcionan sin licencia ni conexión a SkillUps Cloud. SkillUps Academy mantiene este plugin de forma independiente.
