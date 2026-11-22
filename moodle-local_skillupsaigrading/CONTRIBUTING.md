# Contribuciones

Usa una instancia de desarrollo con un sitio separado y datos sintéticos. Moodle proporciona las dependencias de PHPUnit y Behat. El ZIP de instalación no requiere Composer.

Ejecuta `vendor/bin/phpunit --testsuite local_skillupsaigrading_testsuite` y Behat con el filtro `--tags @local_skillupsaigrading`. Valida las cadenas en inglés y español, XMLDB y Moodle CodeSniffer. Cualquier modificación al motor de comparación debe demostrar que las tablas de calificación conservan los mismos datos después de revisar una propuesta.

[Incidencias y mejoras](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues)
