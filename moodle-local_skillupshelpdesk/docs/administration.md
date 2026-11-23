# Instalación y administración

1. Sube el ZIP desde **Administración del sitio → Plugins → Instalar plugins**. Para instalar manualmente, coloca la carpeta `skillupshelpdesk` en `local/` bajo la raíz pública. Desde Moodle 5.1 la distribución estándar usa `public/local/`. Después ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz.
2. Crea roles de soporte que se puedan asignar en el contexto del sistema. Usa la tabla de permisos para evitar dar supervisión global al rol de técnico.
3. Abre **SkillUps Helpdesk → Administración** y configura categorías, recintos, prioridades, severidades, estados, artículos y plantillas. Usa **Configuración del sitio** para el horario laboral, los feriados, el supervisor de escalamiento, avisos y retención.
4. Ejecuta el cron de Moodle con regularidad. Helpdesk revisa incumplimientos y procesa avisos cada cinco minutos. Configura Lock API de forma compartida si Moodle se ejecuta en varios servidores.

| Rol | Capacidad | Acceso |
| --- | --- | --- |
| Solicitante | `local/skillupshelpdesk:request` | Crear y ver tickets propios, responder públicamente y valorar un ticket resuelto |
| Técnico | `local/skillupshelpdesk:work` | Ver y atender tickets asignados, escribir notas internas, escalar y acceder a informes de su asignación actual |
| Supervisor | `local/skillupshelpdesk:supervise` | Ver tickets de todo el centro, asignar o reasignar a técnicos habilitados, resolver y consultar informes globales |
| Administrador de catálogos | `local/skillupshelpdesk:configure` | Configurar catálogos, estados, artículos y plantillas y consultar las operaciones administrativas recientes |

El rol autenticado tiene la capacidad de solicitar por defecto. Los administradores de sitio acceden a todas las áreas. Un recinto o curso de un ticket es una dimensión de clasificación, no una delegación de supervisión. La supervisión institucional es de sistema completo.

## Datos y retención

Helpdesk conserva datos de solicitantes y operadores, asuntos, comentarios y adjuntos, clasificación, historial, plazos, valoraciones y avisos pendientes. Cada ticket terminal puede conservarse entre 30 y 730 días, con valor inicial de 365. Se exige que tanto la resolución como la última actividad sean anteriores al corte. La retención de tickets activos se gestiona según el ciclo de soporte. El borrado autorizado del solicitante elimina su ticket y los adjuntos relacionados. Para otros usuarios se eliminan sus mensajes, adjuntos y referencias personales conservando el registro mínimo de las operaciones. Los almacenes de registros y avisos de Moodle tienen sus propias políticas de retención.

Los avisos de Helpdesk no incluyen asuntos, textos, archivos, valoraciones ni identificadores de tickets. El correo está deshabilitado de forma predeterminada para el proveedor. Si se habilita mediante las preferencias de Moodle, se mantiene el mismo texto genérico y el enlace al portal.

## Licenciamiento opcional

Community incluye todo el flujo, calendario, base de conocimientos, encuestas e informes CSV. La autorización `json_export` añade exportación de informes JSON para Professional o Enterprise. El estado y la fecha de vigencia aparecen en los ajustes.

Configura `$CFG->local_skillupshelpdesk_entitlement` y `$CFG->local_skillupshelpdesk_licensepublickey`. El formato es `base64(JSON).base64(firma)`. El documento contiene `version` igual a 1, `component` igual a `local_skillupshelpdesk`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. Usa una clave pública Ed25519 en base64 y un máximo de 366 días de vigencia. Cada elemento de `sites` es el SHA-256 de una URL Moodle canónica sin barra al final. Las instalaciones autorizadas se enumeran en la misma licencia.

`$CFG->local_skillupshelpdesk_revoked` admite IDs revocados y `$CFG->local_skillupshelpdesk_gracedays` admite hasta 30 días. La verificación con Sodium es local y cachea firmas en la solicitud. Renueva o transfiere solicitando al emisor un documento firmado con las instalaciones autorizadas actualizadas.
