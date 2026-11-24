# SkillUps Security Center

Centro de auditoría Moodle de solo lectura. Revisa permisos y asignaciones, detecta cuentas inactivas, conserva evidencia de eventos administrativos y permite registrar el seguimiento de hallazgos.

| Dato | Valor |
| --- | --- |
| Componente | `report_skillupssecurity` |
| Versión | `0.9.0-beta.1` |
| Requisito | Moodle 5.0 o posterior y el PHP requerido por Moodle |
| Idiomas | Inglés y español |
| Licencia | GPL v3 o posterior |

[ZIP instalable](../releases/skillupssecurity/skillupssecurity-0.9.0-beta.1.zip) · [SHA-256](../releases/skillupssecurity/SHA256SUMS)

1. Instala el ZIP desde **Administración del sitio → Extensiones → Instalar extensiones**.
2. Abre **Administración del sitio → Informes → SkillUps Security Center**.
3. Consulta la cobertura y encola una revisión, o espera la ejecución programada de Moodle.
4. Examina el inventario, abre un hallazgo y registra la decisión de revisión. Consulta los eventos y exporta evidencia con el permiso correspondiente.

- [Configuración, roles y alertas](docs/administration.md)
- [Catálogo y límites de los controles](docs/controls.md)
- [Arquitectura](docs/architecture.md)
- [Verificación](docs/validation.md)
- [Seguridad y privacidad](SECURITY.md)
