# Manual de uso

## Redactar y segmentar

Abre el centro desde la navegación de un curso autorizado o en `/local/skillupscommunication/index.php`. Selecciona el ámbito institucional en el panel. Crea una comunicación o inicia un borrador a partir de una plantilla. Escribe asunto y cuerpo, selecciona el tipo, el canal y, si procede, la fecha y hora en el selector de Moodle.

Los criterios seleccionados se combinan por intersección. Un grupo requiere el curso al que pertenece. Al usar un programa o recinto, la configuración debe vincularlo a la categoría Moodle correspondiente. Dos asignaciones organizativas deben pertenecer a la misma rama. Los conjuntos personalizados se preparan en **Segmentos institucionales** con cuentas Moodle válidas y pertenecientes al ámbito.

## Previsualizar, aprobar y cancelar

Abre **Previsualizar destinatarios**, verifica la lista y confirma el envío a revisión. El envío a revisión congela la lista y no distribuye mensajes. Un revisor puede devolver la comunicación a borrador o aprobarla. Si la audiencia definida cambió durante la revisión, devuelve la comunicación a borrador, actualiza la vista previa y envíala nuevamente a revisión.

Con la fecha desactivada, la comunicación aprobada se procesa cuando cron ejecute la tarea. La cancelación afecta a destinatarios todavía pendientes. Los resultados de transferencias ya procesadas se conservan.

## Consultar resultados

Abre **Destinatarios y resultados** o **Historial**. La interfaz muestra el estado por destinatario, los intentos y la referencia de notificación cuando Moodle la devuelve. «Aceptado por el canal» se refiere a la transferencia, no a una lectura o apertura. «Entrega confirmada» requiere evidencia del conector. Los desenlaces inciertos quedan fuera del reintento. Los fallos recuperables pueden ser reintentados por un revisor dentro del límite.
