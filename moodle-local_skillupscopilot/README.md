
# SkillUps AI Copilot

Asistente de SkillUps Academy para Moodle. El chat distingue el contexto del curso y el del sitio, genera borradores educativos y administrativos, y puede incorporar fragmentos de documentos autorizados. La edición Community funciona con Ollama local. OpenAI y los destinos compatibles son opcionales.

- Componente: `local_skillupscopilot`.
- Versión: `0.9.0-beta.1`.
- Idiomas de interfaz: inglés y español.
- Licencia: GPL-3.0-or-later. SkillUps Academy es un proveedor independiente de Moodle.

## Guías

- [Instalación, configuración y administración](docs/administration.md)
- [Guía para estudiantes, docentes y administradores](docs/user-guide.md)
- [Arquitectura](docs/architecture.md)
- [Seguridad y privacidad](SECURITY.md)
- [Compatibilidad y validación](docs/validation.md)
- [Paquete de presentación para Moodle Marketplace](docs/marketplace.md)
- [Historial de cambios](CHANGELOG.md)

El ZIP de instalación contiene un único directorio `skillupscopilot/` con `version.php` en la raíz. Copia ese directorio al área `local/` del directorio web de Moodle o usa el instalador ZIP. Los proveedores requieren configuración explícita y el cron de Moodle debe estar activo.

Para informar de un error general, usa [GitHub Issues](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues). Para seguridad o privacidad, consulta [SECURITY.md](SECURITY.md).
