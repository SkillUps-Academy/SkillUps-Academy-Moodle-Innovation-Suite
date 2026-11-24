# Seguridad

Reporta fallos en privado a los mantenedores de SkillUps Academy mediante el canal de seguridad del repositorio o el contacto institucional autorizado. En un reporte público usa únicamente datos sintéticos.

- Cada pantalla y exportación exige sesión de Moodle, responsable nativo de privacidad o administrador y capacidad de sistema. Los cambios usan `sesskey`. No hay endpoints públicos ni servicios web agregados por el plugin.
- El flujo valida titular, tipo, contexto, responsable, estado y revisión. Un bloqueo Moodle serializa cambios, revisión de versiones, operaciones de privacidad y reclamaciones de alertas.
- El plugin guarda referencias y motivos acotados. Las plantillas escapan declaraciones, nombres y motivos. Los logs y avisos nativos omiten motivos privados. La exportación operativa usa valores controlados y no abre archivos de usuario.
- El inventario depende de declaraciones de plugins instalados. Una integración con un proveedor defectuoso queda sujeta a los mismos límites de fiabilidad del registro de Moodle.
- Los fines de retención cubren el contexto seleccionado. La revisión institucional de otros contextos, fuentes académicas, proveedores externos y solicitudes legales requiere una decisión documentada. Las acciones iniciadas directamente en Moodle se rigen por los permisos y las políticas nativas.
- El plugin no integra servicios de datos externos. Moodle puede enviar los avisos genéricos mediante sus procesadores configurados.
