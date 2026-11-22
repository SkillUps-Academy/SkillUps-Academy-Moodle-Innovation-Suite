# Diseñar y supervisar un flujo

1. En **Panel de flujos**, crea un flujo o importa una plantilla.
2. Elige el disparador. Puedes lanzar un flujo manualmente, programarlo una vez, usar un intervalo fijo u observar la creación de usuarios o cursos, finalización de cursos y creación de matrículas.
3. Añade tarjetas desde la lista del lienzo. Arrástralas o usa los botones de movimiento del inspector. Elige el nodo inicial y conecta cada salida con una tarjeta de destino. Los controles del inspector también funcionan con teclado.
4. Configura condiciones, acciones y tiempos. Puedes combinar criterios con AND/OR. Las condiciones y aprobaciones tienen rutas alternativas. Un nodo de acción puede tener una salida de error. La ejecución sigue una ruta por decisión.
5. Guarda una revisión. Actívala desde el historial de revisiones o el panel. Un borrador guardado puede avanzar mientras una revisión anterior sigue activa.
6. Usa **Ejecutar ahora** para una ejecución manual de la revisión activa. En el historial, abre el ID para revisar las transiciones y tiempos.

Para un primer flujo, importa la plantilla de aprobación incluida. Actívala, ejecútala y pide a otra cuenta autorizada que abra **Aprobaciones**. La decisión se registra una vez. Si la aprobación vence, se sigue la salida de rechazo o se marca fallida cuando esa salida no está conectada.

Los campos de usuario y curso se expresan como IDs. En un nodo, 0 hereda el objetivo de la ejecución. En un disparador de evento, un ID positivo filtra los eventos. En un disparador de horario o un lanzamiento manual, suministra IDs de objetivo adecuados. Los intervalos son minutos transcurridos y la hora del editor se interpreta en la zona del navegador.

Las plantillas exportan JSON sin IDs fijos de usuario ni de curso. Antes de activar una importación, revisa el horario, el contenido de notificaciones y los IDs de rol. Las plantillas importadas empiezan como borradores.
