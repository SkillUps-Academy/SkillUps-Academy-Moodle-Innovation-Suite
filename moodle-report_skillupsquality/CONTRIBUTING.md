# Contribuciones

Usa una instancia de prueba con datos sintéticos. Registra nuevas reglas en `classes/catalog.php` mediante el contrato `classes/rule.php`. Proporciona identificadores estables, tipo de hallazgo, severidad, condiciones, recomendaciones y evidencia estructurada. Incrementa la revisión de reglas si cambian sus condiciones o el significado de la puntuación. Mantén equivalencia entre los paquetes de idioma.

```shell
vendor/bin/phpunit report/skillupsquality/tests/quality_test.php
vendor/bin/behat --config /path/to/behat.yml --tags @report_skillupsquality
phpcs --standard=moodle --extensions=php report/skillupsquality
```

En Moodle 5.1 y posteriores, antepone `public/` al directorio de pruebas y a la ruta de PHPCS para una instalación con la distribución estándar. Prueba los cambios de esquema en MariaDB y PostgreSQL. Usa el [gestor de incidencias del repositorio](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/issues) para errores reproducibles sin información personal.
