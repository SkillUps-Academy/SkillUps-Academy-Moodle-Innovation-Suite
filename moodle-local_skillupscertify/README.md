# SkillUps Certify & Verify

Plugin local de Moodle para diseñar, emitir y administrar certificados PDF. Cada documento incluye un QR vinculado al registro del emisor y se conserva con su huella SHA-256, incluso después de una revisión de plantilla, revocación o reemisión.

| Dato | Valor |
| --- | --- |
| Componente | `local_skillupscertify` |
| Versión | `0.9.0-beta.1` |
| Requisito | Moodle 5.0 o posterior y la versión de PHP requerida por Moodle |
| Idiomas | Español e inglés |
| Licencia | GPL v3 o posterior |

[ZIP instalable](../releases/skillupscertify/skillupscertify-0.9.0-beta.1.zip) · [SHA-256](../releases/skillupscertify/SHA256SUMS)

1. Instala el ZIP en **Administración del sitio → Extensiones → Instalar extensiones**.
2. Abre el curso y selecciona **Más → SkillUps Certify & Verify → Plantillas de certificados**. Diseña el certificado y elige la evidencia.
3. Emite a un estudiante por su nombre de usuario o encola un grupo desde **Emitir certificados**. Los estudiantes acceden a sus PDF desde **Mis certificados**.
4. Usa el **Registro de certificados** para filtrar, exportar, revocar o reemitir. Escanea el QR para comprobar el estado y comparar el PDF con el original.

- [Administración, permisos y retención](docs/administration.md)
- [Diseño, emisión y verificación](docs/operations.md)
- [Arquitectura](docs/architecture.md)
- [Learning Path y extensibilidad](docs/integrations.md)
- [Pruebas y capturas](docs/validation.md)
- [Seguridad y privacidad](SECURITY.md)
