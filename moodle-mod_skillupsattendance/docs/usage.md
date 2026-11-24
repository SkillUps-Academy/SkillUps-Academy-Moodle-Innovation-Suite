# Manual de uso

## Docentes y coordinadores

1. Agrega una actividad de asistencia y configura sus reglas y motivos.
2. En **Agregar sesión**, elige inicio y fin, modalidad, grupo, docente y método de registro. La duración se deriva de las fechas. Usa la recurrencia diaria o semanal para crear hasta 26 sesiones. Las repeticiones conservan la hora local en la zona de Moodle del operador.
3. Marca una sesión extraordinaria cuando corresponda. Ajusta apertura anticipada, cierre adicional y tolerancia de tardanza entre 0 y 120 minutos. Una sesión programada admite cambios de fecha y grupo hasta que existan marcas. Cancelarla retira su evento del calendario y la excluye de los informes.
4. El registro manual permite elegir el estado del estudiante y guardar los cambios de la página. En métodos de autoregistro, la ventana va desde el inicio menos la antelación hasta el fin más la gracia. Una entrada posterior al inicio más la tolerancia se clasifica como tardanza.
5. En sesiones QR, presenta la imagen desde Moodle y actualízala entre registros. Cada código dura hasta dos minutos dentro de la ventana. Moodle verifica la matrícula, el grupo y el código vigente. El docente valida la presencia física cuando la política del curso lo requiere.
6. Revisa los justificantes en la sesión. Abre las evidencias desde Moodle, escribe una explicación breve y aprueba o rechaza. La aprobación cambia el estado a justificado.

## Estudiantes

Abre la sesión en el calendario o desde el módulo. Pulsa **Registrar entrada** y confirma. Si el docente usa QR, escanea la imagen vigente e inicia sesión en Moodle. Si usa código, introdúcelo en el formulario. Al habilitarse la salida, se ofrece tras una entrada válida en la misma ventana. Cada sesión conserva una entrada y, cuando aplica, una salida.

Una vez terminado el horario previsto, abre la sesión y elige **Enviar justificación** si tu marca es tardía, ausente o pendiente. Adjunta hasta tres archivos PDF, JPG o PNG que cumplan el límite de Moodle y del curso. Consulta las decisiones en **Justificaciones**. Tu informe muestra tus propios registros en el contexto de la actividad.

## Cálculo e informes

El cierre toma la nómina elegible en ese momento y asigna ausencia cuando falta un registro. En el porcentaje, presencia y tardanza cuentan como asistencia. El denominador incluye presencias, tardanzas y ausencias. Una fila justificada queda fuera del denominador. Si el denominador es cero, se muestra un porcentaje no disponible. Las sesiones programadas o canceladas se excluyen.

Una racha de ausencias se calcula en orden cronológico con las sesiones finalizadas incluidas. Presencia, tardanza y justificación terminan la racha. El aviso de mínimo compara el porcentaje mostrado con el mínimo configurado. El aviso de racha usa el umbral alcanzado. El aviso de total se activa al superar las inasistencias permitidas. Cero deshabilita las reglas de racha y límite de ausencias. El filtro de riesgo ayuda al seguimiento docente.

Desde el informe se puede filtrar por estudiante, período, grupo, docente y modalidad. La opción de curso incluye únicamente actividades visibles y reportables para el operador y aplica las reglas de la actividad desde la que se abrió. CSV y Excel usan los formatos habilitados por el administrador de Moodle. Consulta el [alcance del libro de calificaciones](administration.md#calificaciones) para la publicación autorizada.

[Ejemplo de sesión](screenshots/session.png) · [Ejemplo de informe](screenshots/report.png)
