# Validación

## Matriz ejecutada

| Moodle | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 7 pruebas, 26 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 7 pruebas, 26 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 7 pruebas, 26 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 7 pruebas, 26 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 7 pruebas, 26 aserciones |

Las pruebas ejecutaron PHP 8.3.37 y comprobaron persistencia y reaparición de hallazgos, permisos efectivos, redacción de eventos, autorización de alertas y eliminación de evidencia personal.

## Flujos comprobados

- Chrome comprobó el dashboard, los filtros, la descarga CSV, el seguimiento con motivo, el inventario y la redacción de valores de configuración. Un auditor delegado pudo ver el panel general y recibió una denegación al intentar abrir el inventario. Su vista de detalles excluyó la evidencia identificada.
- Behat en Moodle 5.3.1 completó 2 escenarios y 15 pasos. Se comprobó la navegación administrativa y el alcance de un auditor delegado.
- El escenario de carga incluyó 102 cuentas sintéticas. Una revisión con límite de 100 mantuvo un hallazgo previo fuera de la muestra. Una revisión completa actualizó su estado al dejar de observar la condición.
- Moodle ejecutó la auditoría encolada desde el navegador. La bitácora conservó la solicitud, la exportación y la revisión del caso.
- La tarea de mantenimiento registró dos alertas aceptadas por la API de mensajería de Moodle. El sitio sintético tenía deshabilitado el transporte de correo.
- Moodle CodeSniffer y la revisión de sintaxis PHP se ejecutaron sobre el componente. El validador nativo reconoció el ZIP como un plugin de informe. Se completó la desinstalación y reinstalación desde el ZIP en Moodle 5.0.12.

## Reproducir

```sh
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --test-suffix _test.php public/report/skillupssecurity/tests
```

En Moodle 5.0 usa las mismas rutas sin `public/`. Para Behat, utiliza el perfil generado por Moodle y el filtro `--tags @report_skillupssecurity`.

La revisión institucional bajo carga sostenida y la presentación oficial a Moodle Marketplace quedan pendientes. El alcance y los límites de cada regla están en el [catálogo de controles](controls.md).

[Dashboard](screenshots/dashboard.png) · [Revisión de hallazgo](screenshots/finding.png) · [Inventario](screenshots/inventory.png)
