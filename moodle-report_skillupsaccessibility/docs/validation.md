# Validación de SkillUps Accessibility Checker

Versión **0.9.0-beta.1**. Laboratorio Moodle en Linux, PHP 8.3.37 y datos sintéticos.

| Moodle | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 7 pruebas, 51 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 7 pruebas, 51 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 7 pruebas, 51 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 7 pruebas, 51 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 7 pruebas, 51 aserciones |

## Pruebas automatizadas

- HTML con barreras verificadas y advertencias, HTML sin barreras para esas reglas, formatos y colores fuera del alcance.
- DOCX con metadatos incompletos, XML no permitido, PDF válido como señal de revisión y entrada inválida.
- Encolado por curso y categoría, programador con cuenta autorizada, deduplicación de solicitudes, reutilización de datos, correcciones y períodos.
- Filtros de visibilidad en instantáneas, acceso revocado del solicitante, File API y eliminación aprobada por Privacy API.
- Moodle CodeSniffer y comprobación de sintaxis en los 29 archivos PHP.

## Navegador

Chrome recorrió los informes de curso y categoría, ejecutó trabajos Adhoc de Moodle, registró una revisión con evidencia, verificó su descarga autorizada y bloqueada con cuenta de estudiante, y descargó ambos CSV. El segundo escaneo mostró cero análisis nuevos y dos resultados reutilizados. La CLI completó la tarea programada.

[Auditoría del curso](screenshots/course-audit.png) · [Revisión del hallazgo](screenshots/finding.png) · [Informe agregado](screenshots/institutional.png).

## Ciclo de instalación

Moodle aceptó el ZIP como plugin de informes. En Moodle 5.3 con PostgreSQL, la desinstalación retiró las tablas propias y un archivo de evidencia. La reinstalación desde el ZIP recuperó el esquema vacío y conservó el resumen del curso original.

## Reproducción

En instalaciones con raíz web `public`, antepón `public/` al primer comando.

```sh
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite report_skillupsaccessibility_testsuite
```

## Alcance

El laboratorio verifica reglas estáticas acotadas y funciones Moodle. Quedan fuera las pruebas de conformidad con WCAG de un curso completo, los volúmenes productivos y las versiones y motores que no figuran en la matriz. El PDF requiere evaluación manual.
