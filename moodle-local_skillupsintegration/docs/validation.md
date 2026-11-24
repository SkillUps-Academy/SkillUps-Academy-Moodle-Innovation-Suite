# Validación de SkillUps Integration Hub

Versión **0.9.0-beta.1**. El laboratorio aislado utiliza PHP 8.3.37, Linux y depuración de desarrollador de Moodle. Se probaron la instalación y la migración XMLDB de una revisión preliminar del esquema. Se usan cuentas, cursos y credenciales sintéticas.

| Moodle | Base de datos | Instalación y migración | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correctas | 11 aprobadas, 67 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correctas | 11 aprobadas, 67 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correctas | 11 aprobadas, 67 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correctas | 11 aprobadas, 67 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correctas | 11 aprobadas, 67 aserciones |

La suite cubre OAuth y paginación mediante transportes sintéticos, mapeo y transformación, reintentos con espera, reanudación y 51 escrituras repartidas en dos bloques. Verifica registro sin escritura antes de la aprobación, usuarios y cursos gestionados, conservación de ediciones locales, idempotencia, colisiones, revalidación del conector, REST por usuario designado, separación de permisos y alcance de Privacy API.

Behat ejecutó 2 escenarios y 11 pasos de administración y auditoría. Chrome verificó alta, ocultación de referencias de credenciales guardadas, revisión y aprobación, ejecución del trabajo Adhoc de Moodle, auditoría y denegación a una cuenta ordinaria. Se probaron REST nativo y webhooks por HTTPS local con certificado de pruebas. REST confirmó recepción, consulta de estado y aislamiento entre conectores. Webhooks confirmó firma válida, reintento idéntico y rechazo de firma ausente y cuerpo distinto. El transporte de salida leyó metadatos públicos de OpenID de Microsoft con TLS, sin enviar credenciales institucionales.

Moodle Coding Style y la validación de sintaxis PHP pasaron. Inglés y español tienen 185 claves equivalentes. Moodle aceptó el ZIP e instaló de nuevo el esquema sobre PostgreSQL. La desinstalación eliminó las cinco tablas propias y conservó el usuario y curso nativos creados por la prueba. La reinstalación desde el ZIP conservó ambos registros.

## Reproducción

Desde la raíz de Moodle, inicializa PHPUnit con `php admin/tool/phpunit/cli/init.php` en 5.0 o `php public/admin/tool/phpunit/cli/init.php` en instalaciones con directorio `public`. Ejecuta:

```sh
vendor/bin/phpunit --testsuite local_skillupsintegration_testsuite
```

Inicializa el entorno separado de Behat con su comando nativo y usa la configuración que imprime Moodle:

```sh
vendor/bin/behat --config /path/to/behat.yml --tags=@local_skillupsintegration
```

## Alcance

El laboratorio comprueba los formatos, autenticación esperada y paginación de los conectores con fixtures. La integración con inquilinos reales de Workspace, Entra ID, SIS o RR. HH. requiere las cuentas y gateways autorizados de cada institución. MySQL independiente, otras versiones de PHP y flujos con volúmenes productivos quedan fuera de la matriz. El envío a Moodle Marketplace está pendiente.
