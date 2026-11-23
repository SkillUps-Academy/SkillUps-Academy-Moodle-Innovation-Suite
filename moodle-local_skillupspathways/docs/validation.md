# Validación

## Matriz ejecutada

| Moodle | Base de datos | Instalación de Pathways | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 5 pruebas, 25 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 5 pruebas, 25 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 5 pruebas, 25 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 5 pruebas, 25 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 5 pruebas, 25 aserciones |

La matriz utilizó PHP 8.3.37 con datos sintéticos. PHPUnit verificó rutas entre categorías, AND/OR, calificaciones visibles y competencias, versiones, revocación de evidencia, privacidad y grupos separados.

Desde una instalación de desarrollo con el componente instalado, prepara PHPUnit y ejecuta la suite:

```sh
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --test-suffix _test.php public/local/skillupspathways/tests
```

En Moodle 5.0 usa las mismas rutas sin `public/`. Para Behat usa el perfil generado por Moodle y el filtro `--tags @local_skillupspathways`.

## Verificaciones adicionales

- Behat en Moodle 5.3.1 completó 2 escenarios y 13 pasos. El alumno ve el portal y el administrador puede abrir el diseñador.

- Se validó el ZIP con el validador nativo de Moodle y se completó una desinstalación y reinstalación desde el ZIP en Moodle 5.0.12.
- En Chrome y Moodle 5.3.1 se probó la navegación desde el curso, la publicación desde el editor, el bloqueo y desbloqueo de un paso en otra categoría mediante finalización real, el historial y el informe por grupo.
- En Moodle 5.3.1 se actualizó Gamification Pro de beta.1 a beta.2 y se comprobó que el hito verificado otorga puntos una sola vez.
- Una prueba sintética de carga con 200 participantes y 60 pasos por itinerario confirmó que cron revisa un lote de 50 participantes sin duplicar transiciones. El lote tardó aproximadamente 0,55 s en el entorno local de pruebas.
- La integración de certificación se probó mediante su contrato de lectura. La emisión con un proveedor externo y el rendimiento bajo carga institucional sostenida quedan pendientes de verificación.

[Captura del diseñador](screenshots/designer.png) · [Captura del progreso individual](screenshots/progress.png) · [Captura del informe por grupo](screenshots/report.png)
