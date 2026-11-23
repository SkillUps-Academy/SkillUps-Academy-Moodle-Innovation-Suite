# Contribuciones

Usa Moodle con datos sintéticos para probar cambios. Mantén las transiciones de restauración e idempotencia en las pruebas y conserva los permisos durante confirmación, ejecución y reintento.

```shell
vendor/bin/phpunit admin/tool/skillupscoursefactory/tests/factory_test.php
vendor/bin/behat --config /path/to/behat.yml --tags @tool_skillupscoursefactory
phpcs --standard=moodle --extensions=php admin/tool/skillupscoursefactory
```

Desde Moodle 5.1 usa el prefijo `public/` para las rutas del plugin en distribuciones con raíz pública separada. Reporta errores con datos de prueba en el [gestor de incidencias del repositorio](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues).
