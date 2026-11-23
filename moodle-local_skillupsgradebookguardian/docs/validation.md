# Validación de Gradebook Guardian

Se usaron instalaciones desechables con depuración de desarrollador y datos sintéticos. Cada versión ejecutó la misma suite de PHPUnit en PHP 8.3.37.

| Moodle | Base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 13 pruebas, 47 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 13 pruebas, 47 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 13 pruebas, 47 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 13 pruebas, 47 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 13 pruebas, 47 aserciones |

- La prueba de regresión compara las filas de notas y su historial, incluidas calificaciones brutas, finales y retroalimentación, y el elemento de total de curso antes y después de simular, aplicar y recuperar el ajuste.
- El motor detectó ciclos, referencias ausentes, fallos de cálculo, pesos y vínculos huérfanos. La prueba de carga cubrió **1.000 elementos** y una cadena circular de dependencias.
- Se verificaron los permisos de docente y alumno, el aislamiento entre cursos, el rechazo de simulaciones desactualizadas y de aprobaciones repetidas, el interruptor de reparaciones, la retención, la privacidad y la firma del derecho opcional.
- **Behat en Moodle 5.3.1** recorrió la navegación del curso, el diagnóstico y el historial en 1 escenario y 11 pasos.
- **Moodle CodeSniffer** pasó con el estándar oficial. El paquete y el proceso de aprobación de Marketplace son independientes. La ficha de Marketplace se entrega como borrador.

- **Chromium** verificó el menú del curso, el diagnóstico, los vínculos y dependencias, la exportación CSV, la simulación sin cambios nativos, la aprobación y recuperación de visibilidad, la invariancia de notas y el historial.
- **ZIP** validado con el instalador nativo de Moodle 5.0.12 y 5.3.1. La prueba de Moodle 5.0.12 incluyó desinstalar y reinstalar el plugin desde el ZIP publicado.

## Capturas

- [Diagnóstico](screenshots/diagnosis.png)
- [Relaciones](screenshots/relationships.png)
- [Simulación](screenshots/simulation.png)
- [Aprobación](screenshots/approval.png)
- [Historial](screenshots/history.png)
