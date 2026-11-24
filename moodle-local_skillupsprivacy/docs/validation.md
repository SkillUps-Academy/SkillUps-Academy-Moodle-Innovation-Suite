# Validación de SkillUps Privacy Manager

Versión **0.9.0-beta.1**, probada con PHP 8.3.37, Moodle en Linux y depuración de desarrollador. Los datos de prueba son sintéticos. Moodle y sus pruebas incorporan dependencias de desarrollo en el laboratorio. El ZIP de distribución se instala sin Composer.

## Matriz

| Moodle | Base de datos | Instalación del plugin por CLI | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 11 aprobadas, 52 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 11 aprobadas, 52 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 11 aprobadas, 52 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 11 aprobadas, 52 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 11 aprobadas, 52 aserciones |

La suite cubre permisos de responsable delegado, exportación y eliminación autorizadas por contexto, estados locales y nativos separados, referencia duplicada o de otro titular, retención protegida, reapertura, revisión desactualizada, deduplicación de avisos, informes sobre 2.000 casos y licencias.

En Moodle 5.3.1 con MariaDB, Behat ejecutó 2 escenarios y 13 pasos. Chrome verificó alta, revisión, rechazo de una aprobación prematura, reserva de retención, alta de una solicitud vinculada, metadatos del inventario, consulta de política, descarga CSV y acceso denegado a una cuenta ordinaria. La lógica de notificaciones se probó en PHPUnit y la ejecución de la tarea en la consola de Moodle. Se comprobó la paridad de 180 claves entre inglés y español.

Moodle Coding Style y el análisis de sintaxis PHP pasaron. El validador de ZIP de Moodle aceptó el paquete. En Moodle 5.0.12 con MariaDB se verificó que desinstalar el plugin retira sus tres tablas y preserva una solicitud nativa. La reinstalación desde el ZIP recreó el esquema y conservó la misma solicitud.


## Reproducción

Desde la raíz de Moodle, inicializa PHPUnit con `php admin/tool/phpunit/cli/init.php` en 5.0 o `php public/admin/tool/phpunit/cli/init.php` en instalaciones con directorio `public`. Ejecuta:

```sh
vendor/bin/phpunit --testsuite local_skillupsprivacy_testsuite
```

Inicializa Behat con el CLI nativo de Moodle con su base de datos y `dataroot` de pruebas. Usa la configuración que imprime el inicializador.

```sh
vendor/bin/behat --config /path/to/behat.yml --tags=@local_skillupsprivacy
```

## Límites de la validación

La matriz se limita a las versiones y motores observados. MySQL independiente, distintas versiones de PHP y pruebas de entrega de correo real quedan fuera de esta matriz. El paquete es la primera versión del componente. Las migraciones desde versiones anteriores del plugin todavía no aplican. El informe valida comportamiento técnico con datos sintéticos. La revisión de Moodle Marketplace y la validación de procesos de producción por la institución quedan pendientes.
