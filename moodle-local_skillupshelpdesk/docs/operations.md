# Portal, operación de soporte e informes

## Abrir y atender un ticket

1. En la navegación principal de Moodle, abre **SkillUps Helpdesk → Abrir ticket**. Completa el asunto, la descripción, el recinto, la categoría, la prioridad y la severidad. Vincula un curso accesible cuando corresponda.
2. Desde **Mis tickets**, sigue la conversación y el estado. Cada mensaje admite hasta tres archivos locales PDF, PNG, JPEG o TXT, con un máximo de 5 MiB cada uno, 12 MiB por mensaje y el límite inferior del sitio.
3. En la **Bandeja de técnicos**, consulta tus asignaciones. Un supervisor puede ver los tickets pendientes de asignación en **Sin asignar** y seleccionar un técnico al guardar la clasificación. El estado inicial se configura en el catálogo. Los estados terminales cierran el ticket y permiten al solicitante valorarlo una vez de 1 a 5. Los tickets terminales quedan en modo lectura.
4. Usa **Cargar plantilla en la respuesta** para preparar un borrador editable. Selecciona **Nota exclusiva para técnicos** cuando el comentario y los archivos deban mantenerse dentro del equipo. El historial visible del solicitante omite las notas y los eventos internos.
5. Usa **Escalar ticket** para avisar al supervisor habilitado y al técnico asignado. La repetición manual se limita a una vez cada 15 minutos por ticket. Los incumplimientos automáticos se registran una vez por cada objetivo.
6. Los artículos activos se pueden consultar desde **Base de conocimientos**. Los artículos y plantillas se administran como contenido de texto y los artículos pueden quedar desactivados como borradores.

## Cálculo de SLA

Cada prioridad indica la meta de primera respuesta y la de resolución en minutos laborales. La prioridad elegida al abrir el ticket fija los plazos. Reclasificar un ticket o modificar el calendario afecta la clasificación o los tickets nuevos, según corresponda. Los compromisos de tickets existentes conservan los plazos guardados.

La configuración inicial usa UTC con turnos de lunes a viernes de 09:00 a 17:00. Puedes elegir una zona como `America/Santo_Domingo`, definir varios turnos por día y declarar feriados locales con formato `AAAA-MM-DD`. El calendario usa minutos transcurridos dentro de cada turno, incluidas las transiciones horarias. Por ejemplo, `1=09:00-12:00,13:00-17:00` define un descanso el lunes.

La primera respuesta cuenta al publicar un mensaje técnico al solicitante. Una nota interna o la respuesta del propio solicitante carecen de ese efecto. La resolución cuenta al entrar en un estado terminal. Un ticket cerrado antes del plazo de primera respuesta sin mensaje técnico no se cuenta como incumplimiento de respuesta. Si ese plazo ya pasó, el incumplimiento sí se registra. El estado de espera no interrumpe el reloj. Los avisos se encolan por destinatario y ticket y se reintentan hasta cinco veces, siempre que el destinatario conserve acceso al ticket.

## Informes y filtros

El panel muestra totales, abiertos, resueltos e incumplimientos con los filtros activos. **Informes** agrupa por técnico, recinto, curso o categoría y muestra volumen, resolución, incumplimientos de ambos SLA, cantidad de valoraciones y valoración media. La agrupación usa los valores actuales del ticket, incluida la asignación vigente. La fecha de inicio de un filtro se mide desde la creación del ticket. Los informes de técnicos se limitan a las asignaciones propias.

El CSV contiene los agregados del filtro activo. Los asuntos, mensajes y adjuntos quedan excluidos del archivo. Los recuentos representan tickets, y las valoraciones medias usan solo respuestas registradas.
