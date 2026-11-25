# Verificación técnica

Finalizada el 25 de noviembre de 2026 con datos sintéticos. La migración entre sitios usó MariaDB 11.4.10 y PHP 8.3.37. También se verificó PostgreSQL 17.12 con Moodle 5.3.1.

| Moodle | Base de datos | PHPUnit | Alcance |
|---|---|---|---|
| 5.0.12 | MariaDB 11.4.10 | 7 pruebas y 36 aserciones | Instalación e integración nativa. Origen de la migración entre sitios. |
| 5.3.1 | MariaDB 11.4.10 | 7 pruebas y 36 aserciones | Instalación e integración nativa. Destino de la migración entre sitios. |
| 5.3.1 | PostgreSQL 17.12 | 7 pruebas y 36 aserciones | Instalación en el entorno PHPUnit e integración nativa de respaldo y restauración. |

La suite PHPUnit cubre la política de URL, permisos por curso, aislamiento de archivos entre cuentas, detección de incompatibilidades, selecciones inválidas, preparación idempotente, Backup/Restore de un recurso con archivos y grupo, restauración de un Quiz con una pregunta de verdadero/falso y clasificación de una restauración interrumpida.

## Prueba entre instancias

Chrome y Playwright condujeron la prueba desde el panel de destino. REST transfirió el respaldo por HTTPS con validación de certificado. Las tareas Adhoc de Moodle procesaron ambos lados.

1. Se verificaron la versión y el servicio de origen. La cuenta del servicio solo pudo inventariar el curso autorizado.
2. Se eligió un recurso de un curso con más de una actividad, junto con los grupos y un mapeo explícito a una cuenta local. El archivo de prueba de 734 003 bytes requirió varias respuestas REST.
3. La simulación informó un archivo válido y no reservó un curso.
4. Una ejecución con un rol personalizado ausente del destino se bloqueó en la precomprobación nativa y mostró su motivo.
5. Con el rol equivalente disponible, un nuevo plan restauró el recurso y el grupo en un curso oculto. Se verificaron la misma huella del archivo servido por File API y la matrícula manual de la cuenta local indicada.

[Inventario](screenshots/inventory.png) · [Simulación](screenshots/simulation.png) · [Resultado y registro](screenshots/result.png)

En Moodle 5.3.1 pasaron dos escenarios Behat con siete pasos, que comprueban el acceso administrativo y la exigencia de iniciar sesión. Moodle 5.0.12 también desinstaló y reinstaló el componente con las herramientas CLI de Moodle. Los 28 archivos PHP pasaron la comprobación de sintaxis y Moodle Coding Style. Los archivos de idioma contienen las mismas 124 claves. Los validadores de paquetes de ambas instalaciones aceptaron el ZIP de tipo `tool`.

## Repetir las pruebas

Ejecuta el comando desde la raíz de una instalación Moodle con PHPUnit configurado. En sitios con raíz pública separada, ubica el plugin bajo `public/admin/tool`.

```sh
vendor/bin/phpunit --test-suffix _test.php ruta/al/plugin/skillupsmigration/tests
```

En un sitio Behat inicializado, ejecuta `php public/admin/tool/behat/cli/run.php --tags=@tool_skillupsmigration` para los escenarios de acceso de Moodle 5.3.

El alcance de compatibilidad comprobado comprende las combinaciones de Moodle y base de datos de la tabla y la dirección de migración indicada. Los módulos de terceros, los formatos de curso adicionales y el rendimiento con volúmenes de producción requieren verificación con los datos de cada institución.
