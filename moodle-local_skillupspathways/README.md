# SkillUps Learning Path Designer

Diseñador de itinerarios versionados para Moodle. Enlaza cursos y actividades de distintas categorías y aplica prerrequisitos, calificaciones y competencias antes de recomendar cómo avanzar.

| Dato | Valor |
| --- | --- |
| Componente | `local_skillupspathways` |
| Versión | `0.9.0-beta.1` |
| Tipo | Plugin local |
| Requisito | Moodle 5.0 o posterior y PHP 8.2 o posterior según la versión de Moodle |
| Idiomas | Español e inglés |
| Licencia | GPL v3 o posterior |

[ZIP instalable](../releases/skillupspathways/skillupspathways-0.9.0-beta.1.zip) · [SHA-256](../releases/skillupspathways/SHA256SUMS)

1. Instala el ZIP desde **Administración del sitio → Extensiones → Instalar extensiones** y completa la actualización.
2. Abre el curso desde el que se ofrecerá el itinerario. En el menú **Más** selecciona **SkillUps Learning Path Designer → Diseñador de itinerarios**.
3. Crea el grafo, configura los prerrequisitos y publica una versión. Los alumnos con matrícula activa pueden iniciarla desde ese curso.
4. Abre **Informes de progreso** desde el itinerario para consultar participantes por grupo y, si corresponde, trasladar individualmente a la versión vigente.

- [Instalación, roles y configuración](docs/administration.md)
- [Diseño y operación](docs/operations.md)
- [Arquitectura](docs/architecture.md)
- [Certificación y Gamification Pro](docs/integrations.md)
- [Verificación](docs/validation.md)
- [Seguridad](SECURITY.md)
