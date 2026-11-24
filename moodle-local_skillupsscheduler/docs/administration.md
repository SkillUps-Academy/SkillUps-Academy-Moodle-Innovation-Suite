# Administración de SkillUps Scheduler

## Instalación y actualización

1. Instala el [ZIP](../../releases/skillupsscheduler/skillupsscheduler-0.9.0-beta.1.zip) o despliega la carpeta del plugin en `local/skillupsscheduler`. En distribuciones con raíz web `public`, el destino es `public/local/skillupsscheduler`.
2. Desde la raíz del código ejecuta `php admin/cli/upgrade.php --non-interactive`. Haz copia de la base y mantén la instancia en modo mantenimiento al actualizar.
3. Abre **Administración del sitio → Plugins → Plugins locales → SkillUps Scheduler**.
4. Crea un recinto que apunte a una categoría Moodle. Sus categorías descendientes quedan incluidas.
5. Configura períodos y ciclos, recursos, turnos y bloques. Concede acceso a cada operador en el recinto correspondiente.

La actualización mediante XMLDB incorpora `parentid` a los ciclos del esquema inicial. Los ciclos preexistentes pasan a ser períodos sin padre. Antes de desinstalar, exporta los calendarios que quieras conservar y guarda las auditorías de acuerdo con tu política institucional.

## Capacidades

| Rol operativo | Capacidad | Contexto y alcance |
| --- | --- | --- |
| Administrador institucional | `local/skillupsscheduler:admin` | Sistema. Administra todos los recintos y puede programar, publicar y auditar |
| Coordinador | `local/skillupsscheduler:plan` | Categoría del recinto y habilitación explícita en el recinto |
| Publicador | `local/skillupsscheduler:publish` | Requiere además permiso de planificación |
| Auditor | `local/skillupsscheduler:audit` | Categoría y recinto. Combínalo con planificación o consulta para acceder a la sesión |
| Consultor | `local/skillupsscheduler:consult` | Categoría y recinto. Consulta calendarios publicados |
| Docente | `local/skillupsscheduler:teach`, `local/skillupsscheduler:view` | Matrícula activa y capacidades de curso |
| Participante | `local/skillupsscheduler:view` | Matrícula activa y pertenencia al grupo cuando corresponda |

Crea los roles de coordinador y consultor desde Moodle. Asigna las capacidades requeridas en su categoría y registra el ID de usuario en la sección **Acceso al recinto**. Revocar el acceso al recinto retira el acceso institucional. Las capacidades de curso siguen la configuración nativa de Moodle.

## Avisos y cron

Conserva cron activo y configura la dirección de no respuesta de Moodle. La tarea **Enviar avisos de cambios de horario** se programa cada cinco minutos y procesa lotes limitados. Los avisos llegan a los usuarios todavía autorizados en Moodle y a los docentes afectados que conserven acceso al curso. Los errores transitorios se reintentan con espera creciente. El panel **Cola de notificaciones** muestra los intentos agotados. Las preferencias de Moodle y sus procesadores determinan el canal final. Si un proceso se interrumpe justo después de entregar un mensaje y antes de registrar el avance, ese aviso puede repetirse.

## Capacidad y zonas horarias

El aforo usa matrículas activas con la capacidad nativa `moodle/course:isincompletionreports` y filtra por grupo cuando corresponde. Capacidad cero significa sin límite en un aula virtual. El aforo se evalúa al guardar y al consultar los conflictos. Una matrícula posterior puede originar un nuevo hallazgo.

Los formularios de planificación usan la zona del recinto. El calendario usa la zona del usuario configurada en Moodle y el ICS registra horas UTC. Las horas locales ambiguas o inexistentes por cambio estacional se rechazan.
