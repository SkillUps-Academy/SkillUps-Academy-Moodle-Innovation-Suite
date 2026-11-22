# Operación y métricas

## Panel

El panel muestra la última muestra interna, una lectura actual de la marca nativa de cron y los incidentes pendientes. Diagnóstico indica configuración de HTTPS, cron y mantenimiento, disponibilidad de lectores de registros y estado de instalación de un conjunto fijo de componentes. El detalle de tareas presenta hasta 50 clases de tareas programadas atrasadas o en reintento y hasta 50 intentos fallidos de las últimas 24 horas. La vista de eventos presenta fecha y categoría de errores de correo y antivirus cuando el registro estándar de Moodle está disponible. La vista omite datos de usuario, detalles de ejecución y cargas útiles.

| Indicador | Fuente y cálculo | Alcance |
| --- | --- | --- |
| Disponibilidad interna | Una lectura indexada del registro de portada `SITEID` cada cinco minutos. Presencia equivale a accesible, ausencia a no disponible. Una excepción de base de datos produce estado desconocido si el colector logra registrar la muestra. | Observación desde Moodle. Una caída que impide ejecutar el colector o guardar la muestra se registra como falta de observación. |
| Latencia interna | Duración monotónica de esa lectura. Se almacena en microsegundos. | Excluye red del usuario, PHP anterior al inicio de la consulta y renderizado de página. |
| Señal de cron | Antigüedad de `tool_task/lastcronstart`. El indicador también distingue cron deshabilitado y ausencia de una marca nativa. | Registra inicio de cron. Los reintentos y atrasos se observan aparte. |
| Tareas programadas | Task API. Cuenta tareas activas, desactivadas, en ejecución, atrasadas tras el margen y con retraso de reintento. | Las tareas de componentes desactivados se excluyen salvo si Core autoriza que se ejecuten. Un atraso basta para avisar. |
| Tareas ad hoc | Cola nativa. Cuenta tareas con siguiente ejecución vencida sin ejecución registrada y tareas con retraso de reintento. | Los recuentos se limitan a 1000. |
| Intentos fallidos | Conteo de ejecuciones fallidas en los últimos 15 minutos con el logger nativo de tareas en BD. | Desconocido con otro logger. El recuento se limita a 1000. |
| Componentes | Plugin Manager de Moodle. Lee presencia, versión relativa a la BD y activación. | Estado de instalación. El catálogo observado es `tool_task`, `report_status`, `logstore_standard`, `auth_manual`, `enrol_manual` y `cachestore_file`. |
| Indicadores de uso | Cursos excluyendo la portada y cuentas confirmadas, activas y no invitadas con último acceso registrado en las 24 horas anteriores al cálculo. | Recuento de sitio actualizado aproximadamente a diario. El acceso registrado no mide permanencia ni concurrencia. |

## Incidentes y alertas

Cada comprobación tiene su propio estado. El historial distingue la primera observación problemática, el momento de declarar el incidente y el de confirmar recuperación. Una muestra desconocida, un intervalo sin observaciones o el mantenimiento interrumpen la racha. La revisión manual registra una fecha y conserva el estado operativo. Al reanudarse las observaciones tras superar el umbral de antigüedad, el monitor registra el intervalo de pérdida de cobertura como un incidente recuperado. Los logs estándar de Moodle contienen las operaciones del monitor cuando el sitio los tiene habilitados.

La columna de entrega informa si Moodle aceptó el aviso, si el despacho falló, si se omitió o si quedó sin confirmar. El plugin evita repetir una entrega cuyo resultado es ambiguo. Las notificaciones dependen de cron. Configura un canal externo independiente para avisar durante interrupciones que detengan Moodle o cron.

## Informe de disponibilidad

Los períodos terminan al inicio del intervalo UTC vigente. La pantalla presenta fechas en la zona del usuario. CSV exporta los límites en UTC y en unidades explícitas.

- Disponibilidad observada = 100 × accesibles ÷ (accesibles + no disponibles). Un denominador cero se presenta como dato ausente.
- Cobertura = 100 × (accesibles + no disponibles + mantenimiento observado) ÷ intervalos esperados.
- Intervalos desconocidos = intervalos esperados menos intervalos con resultado de disponibilidad o mantenimiento.
- Percentil 95 = el valor en el rango ascendente `ceil(0.95 × n)` de las latencias internas de observaciones accesibles.

El informe admite 1 hora, 24 horas, 7 días y 30 días. Las muestras reflejan intervalos de observación y pueden omitir interrupciones entre comprobaciones. Usa una [sonda externa](integrations.md) para medir HTTP, TLS, DNS y latencia desde otra red.
