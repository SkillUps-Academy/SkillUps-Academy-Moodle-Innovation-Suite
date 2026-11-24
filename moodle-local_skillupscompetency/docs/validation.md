# Validación de SkillUps Competency Manager

Versión **0.9.0-beta.1**. Moodle aislado en Linux, PHP 8.3.37 y datos de prueba sintéticos.

| Moodle | Base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 7 pruebas, 39 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 7 pruebas, 39 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 7 pruebas, 39 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 7 pruebas, 39 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 7 pruebas, 39 aserciones |

## Cobertura ejecutada

- PHPUnit comprobó vínculos a cursos y actividades, escalas nativas, objetivos por período, indicadores e intercambio JSON, cálculo de brechas, aislamiento por categoría y grupos, revisión autorizada, propuesta obsoleta, archivos privados, Privacy API y mantenimiento.
- La suite construyó una rúbrica real de Moodle en una tarea. El plugin guardó los criterios elegidos y publicó el nivel de competencia tras aprobación. La tarea conservó sus calificaciones intactas.
- Chrome recorrió el catálogo y la matriz, descargó e importó indicadores JSON, adjuntó un PDF mediante el selector Moodle y rechazó su descarga a una cuenta de otro grupo. Después se verificaron la revisión, el nivel vigente y los CSV individual e institucional con sus filtros de grupo.
- La CLI nativa completó la tarea programada. Moodle CodeSniffer y la validación de sintaxis pasaron en 27 archivos PHP.

## Ciclo de instalación

El validador de Moodle aceptó el ZIP. En Moodle 5.3 con PostgreSQL se aprobó un nivel nativo con evidencia, se desinstaló el plugin y se verificó la retirada de su archivo privado. La reinstalación desde el ZIP creó el esquema propio vacío y conservó el nivel de competencia nativo.

## Capturas

[Panel institucional](screenshots/institutional.png) · [Catálogo](screenshots/catalog.png) · [Matriz](screenshots/matrix.png) · [Evaluación](screenshots/assessment.png) · [Panel individual](screenshots/learner.png) · [Brechas](screenshots/gap-report.png).

## Reproducir PHPUnit

En instalaciones con raíz web `public`, antepón `public/` al primer comando.

```sh
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_skillupscompetency_testsuite
```

## Alcance

La matriz cubre los entornos indicados. Quedan fuera MySQL independiente, versiones posteriores, grandes volúmenes productivos y rúbricas de otros módulos. La comparación por períodos utiliza objetivos históricos y niveles vigentes. La revisión en Moodle Marketplace queda pendiente.
