# Administración de la actividad

## Instalación y actualización

Verifica el [SHA-256](../../releases/skillupsattendance/SHA256SUMS) e instala el ZIP como actividad. El directorio físico es `mod/skillupsattendance` en Moodle 5.0 y `public/mod/skillupsattendance` en instalaciones con directorio web `public`. Completa la actualización desde la página de notificaciones de administración o la CLI. Verifica que las capacidades esperadas estén asignadas en cada curso y que el cron se ejecute regularmente.

| Capacidad `mod/skillupsattendance:` | Función |
| --- | --- |
| `addinstance` | Agregar la actividad en el curso |
| `view` | Abrir la actividad y sus sesiones visibles |
| `recordown` | Autoregistro y justificaciones del estudiante matriculado |
| `sessions` | Crear, revisar, reprogramar y cancelar sesiones. También presentar el QR. |
| `mark` | Registrar, corregir y cerrar sesiones autorizadas |
| `review` | Revisar justificaciones y evidencias en el ámbito permitido |
| `report` | Consultar informes dentro de permisos y grupos |
| `export` | Descargar informes con alcance autorizado |
| `audit` | Consultar historial autorizado |
| `publish` | Autorizar un corte de calificaciones de la actividad |

Los estudiantes heredan consulta y registro propio. Los docentes y gestores del sitio tienen funciones operativas por arquetipo. El administrador puede asignar un rol de coordinador con una combinación de capacidades en el curso o la actividad. En grupos separados el personal sin `moodle/site:accessallgroups` gestiona las sesiones de sus grupos autorizados. Un estudiante solo se registra para un grupo al que pertenece.

## Cierre y mensajería

La tarea `\mod_skillupsattendance\task\maintain` ejecuta cada cinco minutos el cierre de hasta 25 sesiones y la revisión de alertas de hasta 15 actividades pendientes. Usa la programación nativa de Moodle y ajusta su frecuencia a la capacidad del servidor. La mensajería nativa permite definir canales y preferencias. Los avisos al estudiante contienen texto genérico y un vínculo al módulo. Si cambia la asistencia y se resuelve un indicador, una nueva infracción puede iniciar otro aviso.

## Calificaciones

Habilita **Publicación opcional en calificaciones** al editar el módulo, si corresponde. La publicación exige capacidades de informe y publicación, `moodle/grade:edit` y alcance sobre toda la actividad. Desde el informe, marca la autorización y ejecuta **Publicar calificaciones autorizadas**. Moodle recibe porcentajes de estudiantes activos con un denominador válido. El proceso omite calificaciones bloqueadas o anuladas por Gradebook. Cada ejecución añade una entrada de auditoría. Los filtros visuales del informe afectan las consultas y archivos de exportación. La autorización de calificaciones abarca el corte completo de la actividad.

## Ediciones

Community incluye registro, QR, justificaciones, informes CSV y Excel, avisos y publicación autorizada de calificaciones. Professional y Enterprise pueden habilitar un CSV del historial de auditoría con `extended_evidence`. La descarga requiere capacidades de auditoría y exportación y usa los mismos filtros de grupo que el historial. La edición se muestra en esa página.

La licencia opcional se verifica localmente con PHP sodium. `$CFG->mod_skillupsattendance_entitlement` contiene `base64(JSON).base64(firma Ed25519)` y `$CFG->mod_skillupsattendance_licensepublickey` la clave pública base64. Los campos firmados son `version` (1), `component` (`mod_skillupsattendance`), `id`, `tier` (`professional` o `enterprise`), `issued` y `expires` como sellos Unix separados por hasta 366 días, `sites` como SHA-256 de cada URL canónica sin barra final y `features`. `$CFG->mod_skillupsattendance_revoked` contiene IDs revocados y `$CFG->mod_skillupsattendance_gracedays` define de 0 a 30 días de gracia. La verificación de firma se almacena en memoria durante la petición. Renueva o transfiere instalando una licencia firmada para los sitios autorizados. Community funciona tras caducidad o revocación.

## Copia y eliminación

Moodle Backup y Restore incluyen sesiones y configuración. Cuando se autoriza incluir información de usuarios, incluyen registros, decisiones, auditoría y evidencias. Los códigos de acceso se reconfiguran en el destino y los secretos QR de sesiones vigentes se regeneran. Si falta la correspondencia de un grupo, la sesión se cancela para conservar el aislamiento. En restauraciones sin usuarios, las sesiones ya vencidas se cancelan para evitar crear ausencias sobre fechas históricas.

La desinstalación nativa retira las tablas y configuración del componente. La eliminación de una instancia gestiona sus archivos, sesiones y asociaciones mediante Moodle. Las copias oficiales de datos académicos se rigen por la política de la institución.
