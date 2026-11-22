# Contribuciones

Instala el plugin en una instancia desechable de Moodle. Usa datos sintéticos y conserva las autorizaciones nativas al ampliar diagnósticos y salidas. Mantén sincronizadas las cadenas en español e inglés.

```shell
vendor/bin/phpunit --testsuite report_skillupsmonitor_testsuite
vendor/bin/behat --config /path/to/behat.yml --tags @report_skillupsmonitor
phpcs --standard=moodle --extensions=php report/skillupsmonitor
```

Después de cambiar el esquema, incrementa `version.php`, actualiza `db/install.xml` y agrega el paso correspondiente en `db/upgrade.php`. Verifica instalación, retención e informes tanto con MariaDB como con PostgreSQL.
