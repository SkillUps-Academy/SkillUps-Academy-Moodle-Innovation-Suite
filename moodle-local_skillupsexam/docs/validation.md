# Validación de SkillUps Exam Manager

Las pruebas se ejecutaron en laboratorios desechables con configuraciones de depuración de desarrollo y datos sintéticos. La versión mínima declarada por el código es Moodle 5.0.

| Moodle | PHP | Base de datos | PHPUnit |
|---|---|---|---|
| 5.0.12 | 8.3.37 | MariaDB 11.4.10 | 11 pruebas, 41 aserciones |
| 5.1.9 | 8.3.37 | MariaDB 11.4.10 | 11 pruebas, 41 aserciones |
| 5.2.5 | 8.3.37 | MariaDB 11.4.10 | 11 pruebas, 41 aserciones |
| 5.3.1 | 8.3.37 | MariaDB 11.4.10 | 11 pruebas, 41 aserciones |
| 5.3.1 | 8.3.37 | PostgreSQL 17.12 | 11 pruebas, 41 aserciones |

La suite cubre aforo, solapamientos y liberación de reservas, control de revisión, grupos y curso, supervisor asignado y separación entre edición y supervisión, asistencia, incidencias, reintentos idempotentes de inscripción, consulta de un intento real de Quiz, anonimato de Assign, estado de la entrega, lectura de Gradebook sin recálculo, destinatarios de cron y borrado de datos aprobado. La prueba de padrón verifica 122 participantes y que el cursor continúa después de la primera página.

Para repetir PHPUnit en un sitio de desarrollo configurado, ejecuta desde la raíz de Moodle:

```bash
vendor/bin/phpunit --test-suffix _test.php public/local/skillupsexam/tests
```

En estructuras anteriores a la separación del directorio público, usa `local/skillupsexam/tests`. Los escenarios Behat se identifican con `@local_skillupsexam`.

## Verificaciones adicionales

- Chrome con el tema Boost en Moodle 5.3.1. Se verificaron filtros y calendario, creación de borrador con Moodle Forms, edición, cancelación, registro de asistencia e incidencias, exportación de ambos CSV de detalle, rechazo de descarga sin sesskey y rechazo de acceso directo como estudiante. En una ventana de 390 píxeles se comprobó que la página no genera desplazamiento horizontal.
- El validador de paquetes de Moodle aceptó la raíz y la versión del ZIP en Moodle 5.0.12 y 5.3.1.
- Behat ejecutó 2 escenarios y 14 pasos en Moodle 5.3.1. Ambos escenarios aprobaron.
- El estándar Moodle de PHP_CodeSniffer, con `moodle-cs` 3.9.2, terminó sin errores ni avisos. Los 30 archivos PHP pasaron la verificación de sintaxis y se comprobó que las 160 claves coinciden en inglés y español.
- Dos procesos de Moodle 5.3.1 intentaron reservar el mismo espacio, docente y horario. Se publicó una convocatoria y se rechazó la otra por solapamiento.
- El actualizador CLI instaló el esquema y el CLI de Moodle 5.0.12 desinstaló y reinstaló el componente. Esta es la primera versión pública del esquema.

## Evidencia visual

- [Calendario institucional](screenshots/calendar.png)
- [Convocatoria, lista de estudiantes e incidencias](screenshots/convocation.png)
- [Vista móvil](screenshots/mobile.png)

## Alcance

Las alertas de fechas comparan la configuración base y la programación institucional. Se comprobó Moodle Messaging con captura de mensajes en el laboratorio. La entrega por SMTP externo depende de la configuración institucional. Los resultados del laboratorio no representan aprobación en [Moodle Marketplace](https://marketplace.moodle.com/).
