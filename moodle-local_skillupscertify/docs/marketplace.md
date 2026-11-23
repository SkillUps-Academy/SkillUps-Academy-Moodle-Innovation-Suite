# Ficha para Moodle Marketplace

| Dato | Valor |
| --- | --- |
| Nombre | SkillUps Certify & Verify |
| Componente | `local_skillupscertify` |
| Licencia | GPL v3 o posterior |
| Versión | Beta |
| Dependencias de ejecución | Moodle 5.0 o posterior. Learning Path es opcional. |
| Bibliotecas de terceros empaquetadas | Ninguna. Se utiliza TCPDF distribuido por Moodle. |
| Idioma de origen | Inglés |
| Traducción distribuida por GitHub | Español |

## Short description

Design, issue and verify Moodle certificates with archived PDFs, public QR verification and controlled access to issuance records.

## Full description

Create course-scoped templates with dynamic fields, controlled disclosure and visual previews. Issue certificates manually or in background batches based on Moodle course completion or optional Learning Path evidence. Certify preserves the original PDF and its fingerprint, supports explicit revocation and replacement, and gives learners a private wallet. The public verifier shows issuer-authorized fields and compares a locally selected file with the archived fingerprint.

El ZIP tiene un único directorio `skillupscertify` con `version.php`. El código se distribuye dentro de la suite en GitHub. La presentación requiere confirmar el tratamiento de `lang/es` en el flujo oficial y completar la revisión del Marketplace. [Procedimiento de presentación](https://docs.moodle.org/501/en/Contributed_code_FAQ#How_do_I_submit_my_own_plugin_to_Moodle_Marketplace?)

- [Repositorio e incidencias](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues)
- [Instalación y configuración](administration.md)
- [Seguridad y privacidad](../SECURITY.md)
- [Compatibilidad y capturas](validation.md)
