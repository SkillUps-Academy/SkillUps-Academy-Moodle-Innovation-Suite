# Arquitectura

Helpdesk usa un contexto de sistema para mantener una conversación privada incluso cuando un ticket se clasifica por curso. El acceso de solicitantes se limita a sus propios tickets. Los técnicos acceden a tickets asignados. Los supervisores pueden consultar y asignar en todo el centro. La configuración de catálogos y horarios requiere capacidad administrativa separada. Los listados y exportaciones aplican estos mismos alcances.

- XMLDB contiene catálogos, tickets, mensajes, auditoría y una cola de avisos. La conversación pública y las notas internas comparten almacenamiento con controles distintos de lectura y descarga. Moodle File API mantiene los adjuntos fuera de acceso directo. Las operaciones en un ticket se serializan con Moodle Lock API y guardan también la trazabilidad.
- El reloj de SLA avanza solo en períodos laborales de una zona horaria institucional y excluye feriados configurados. Los compromisos se fijan al abrir el ticket. Se registran primera respuesta pública técnica y resolución. Una tarea programada detecta y registra incumplimientos y despacha los avisos genéricos mediante Message API.
- Catálogos configurables contienen recintos, categorías, prioridades, severidades, estados, artículos y plantillas. El estado inicial y los estados de resolución se identifican por sus propiedades. La prioridad fija los objetivos de respuesta y resolución. Los cambios administrativos se registran.
- La Privacy API exporta datos autorizados y permite retirar contenido personal y adjuntos con un historial mínimo anonimizado. El cron aplica retención a tickets terminales y completados.
- El portal y los informes operan en Community sin servicios externos. La licencia opcional se verifica localmente y habilita un formato de informes adicional.

APIs de Moodle utilizadas. [File API](https://moodledev.io/docs/5.3/apis/subsystems/files), [Message API](https://moodledev.io/docs/5.3/apis/core/message) y [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy).
