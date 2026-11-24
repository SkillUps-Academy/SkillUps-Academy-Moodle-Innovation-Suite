# Validación de SkillUps Scheduler

Versión **0.9.0-beta.1**. Pruebas con datos sintéticos, PHP 8.3.37 y servidores Moodle aislados.

| Moodle | Base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 9 pruebas, 98 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 9 pruebas, 98 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 9 pruebas, 98 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 9 pruebas, 98 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 9 pruebas, 98 aserciones |

## Casos ejecutados

- PHPUnit verificó las seis clases de conflictos solicitadas, el bloqueo de aulas virtuales, la recurrencia con cambio estacional, las horas ambiguas, la reversión del lote, la sustitución de docentes, la cancelación, la cola de avisos, la Privacy API, el aislamiento por recinto y grupos y la limpieza al eliminar un curso. Una tarea Moodle conservó su fecha límite al publicar en Scheduler.
- En Chrome se recorrieron las vistas de día, semana y mes, el panel de conflictos y la identificación de un curso sin docente, la creación de una serie, la publicación y la cancelación mediante formularios Moodle, la vista móvil, el enlace del aula virtual, la pertenencia a grupos y el calendario nativo. Se descargó un ICS y se comprobó la ausencia del enlace privado.
- Dos procesos Moodle intentaron publicar la misma reserva simultáneamente. Uno publicó y el otro recibió un conflicto.
- Capturas del laboratorio: [planificador](screenshots/planner.png), [conflictos](screenshots/conflicts.png), [sesión](screenshots/session.png), [administración](screenshots/administration.png) y [móvil](screenshots/mobile.png).

## Empaquetado y migración

El validador nativo de Moodle aceptó el ZIP como plugin local. En Moodle 5.3 con PostgreSQL se desinstaló el plugin, se instaló el esquema inicial de laboratorio y se actualizó desde el ZIP. La migración añadió la relación de período a un ciclo existente y conservó sus fechas. También se ejecutó la tarea programada de avisos desde la CLI. La sintaxis de los 30 archivos PHP y Moodle CodeSniffer pasaron sobre el código de entrega.

## Repetir PHPUnit

Inicializa el PHPUnit de Moodle desde la raíz del código. En instalaciones con raíz web `public`, antecede `public/` al primer comando.

```sh
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_skillupsscheduler_testsuite
```

## Alcance

Quedan fuera de esta matriz MySQL independiente, las versiones posteriores de Moodle, la carga institucional a gran escala y la entrega por SMTP real. La matriz verificó el envío a través de la API de mensajería de Moodle.
