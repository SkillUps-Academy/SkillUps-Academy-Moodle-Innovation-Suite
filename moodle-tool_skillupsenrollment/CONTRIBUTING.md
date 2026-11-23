# Contribuciones

Prepara un sitio desechable y usa usuarios, grupos y cursos sintéticos. Conserva las pruebas de permisos, idempotencia, aislamiento transaccional y conflictos de simulación al añadir operaciones.

```shell
vendor/bin/phpunit admin/tool/skillupsenrollment/tests/enrollment_test.php
vendor/bin/phpunit admin/tool/skillupsenrollment/tests/scale_test.php
vendor/bin/phpunit admin/tool/skillupsenrollment/tests/factory_integration_test.php
vendor/bin/behat --config /path/to/behat.yml --tags @tool_skillupsenrollment
phpcs --standard=moodle --extensions=php admin/tool/skillupsenrollment
```

Antepon `public/` a las rutas del plugin en la distribución estándar de Moodle 5.1 o posterior. Instala Course Factory con contrato v1 para ejecutar la prueba opcional. Usa el [gestor de incidencias](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues) para errores reproducibles.
