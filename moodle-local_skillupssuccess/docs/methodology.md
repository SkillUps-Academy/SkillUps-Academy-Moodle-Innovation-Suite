# Metodología

## Población y señales

La unidad es un estudiante matriculado activamente con `local/skillupssuccess:learner` en un curso visible y en curso. Por defecto esa capacidad corresponde al arquetipo estudiante. Se excluyen cuentas suspendidas, eliminadas o sin confirmar. Cron captura un registro por estudiante y día UTC y trabaja en lotes de hasta 25. La ventana comienza después de la matrícula activa y del inicio del curso, según proceda.

| Señal | Fuente y cálculo | Cuándo falta o requiere cautela |
| --- | --- | --- |
| Inactividad | Días completos desde el mayor valor entre el último acceso registrado en el curso y el inicio elegible | Se omite durante los primeros tres días. Un registro ausente mide días desde ese inicio. El acceso acredita una visita registrada, con cobertura variable. |
| Tareas vencidas | Cuenta tareas Assignment visibles para el estudiante con vencimiento efectivo anterior a la captura y ningún intento actual enviado | Respeta fechas relativas, anulaciones de usuario y grupo y prórrogas. Omite tareas ocultas, grupales y anónimas mientras no se revelen identidades. Se excluyen vencimientos anteriores a la matrícula y actividades aún cerradas. Sin tareas elegibles el valor está ausente. |
| Nota actual | 100 × (nota final del total de curso − mínimo) ÷ (máximo − mínimo) | Usa únicamente un total numérico existente, visible y con el libro actualizado. Una escala, libro pendiente o nota ausente no produce un cero. |
| Descenso de nota | Porcentaje de la observación previa válida menos porcentaje actual. Usa la captura válida más reciente de hace entre 7 y 30 días | Se requieren ambas notas. Los cambios pueden reflejar nuevas actividades, ponderaciones o correcciones. |
| Participación | Número de módulos disponibles con al menos un evento web o servicio web registrado en los últimos 14 días | Requiere matrícula con una ventana completa y log estándar activo con retención configurada suficiente. Excluye claves anónimas y suplantación. Omite cursos sin actividades disponibles. Los eventos no miden tiempo, esfuerzo ni aprendizaje. |

Un guion indica ausencia o supresión según la pantalla. La retención y la actividad fuera de Moodle pueden reducir la cobertura del registro. Las señales reflejan observaciones y pueden variar por diseño del curso y modalidad.

## Dos motores

Las **reglas** comparan observaciones con sus umbrales. Inactividad, pendientes y descensos usan «al menos». Participación y nota usan «por debajo». Un umbral cero desactiva esa regla. Con cero observaciones comparables se informa «desconocido». Con evidencia y ningún umbral activado se indica «sin regla activada». Una o más reglas generan revisión y el umbral acumulado produce «atención alta». La huella registra versión del motor y configuración normalizada.

El **modelo estadístico** es una inferencia logística congelada para `independent_support_need_14d`. Se identifica versión y SHA-256. Recorta cada señal al rango del cuadro y la escala a 0–1. Suma intercepto y productos coeficiente × señal escalada al logit. La probabilidad es `1/(1+exp(-logit))`. Si falta una señal con peso distinto de cero, el modelo se abstiene. En el caso se muestran las contribuciones de cada señal al logit y el umbral aplicado. El plugin carece de canal de entrenamiento y no exporta los insumos a un proveedor externo.

| Señal | Rango de escala congelado |
| --- | --- |
| Inactividad | 0 a 30 días |
| Entregas vencidas | 0 a 5 |
| Nota | 0 a 100 % |
| Descenso | −100 a 100 puntos porcentuales |
| Participación | 0 a 10 módulos |

Una regla activada o una estimación sobre el umbral puede abrir un caso de apoyo. El equipo determina el seguimiento, las intervenciones y su resolución. La interpretación de un coeficiente, alerta o comparación es asociativa. La evolución de la nota observada o un cierre posterior no identifica el efecto de la intervención.

## Evaluación prospectiva y sesgos

Activa el estudio con autorización institucional. El sistema toma una muestra inicial por estudiante y curso en cada cohorte fija de 14 días UTC, incluidos estudiantes sin alertas. Congela ambos resultados y sus huellas antes de conocer el desenlace. La revisión se habilita después de 14 días desde la observación. La cola no muestra puntuaciones, alertas ni estado del caso.

1. Asigna un equipo evaluador independiente, con una rúbrica institucional aprobada antes del estudio. Usa evidencia académica de la ventana completa. Confirma «necesidad de apoyo» cuando una revisión del trabajo académico o del seguimiento institucional documenta una dificultad concreta que amerita apoyo. Usa «sin necesidad» cuando la evidencia suficiente sustenta esa decisión. Usa «evidencia insuficiente» para ausencia de observación o discrepancia. La mera existencia de una alerta o de una intervención registrada no forma la etiqueta.
2. Aplica el mismo protocolo a positivos y negativos. Revisa cobertura, casos no evaluados, insuficientes y abstenciones. Una matrícula que deja de cumplir el acceso identificado puede dejar una muestra pendiente. La evaluación puede subestimar errores si los desenlaces disponibles se concentran en casos con alertas. Las intervenciones realizadas durante el período también pueden cambiar el desenlace observado.
3. Compara dentro de la misma cohorte, huella y unidad. Positivo observado y señalado = VP. Negativo observado y señalado = FP. Negativo y sin señal = VN. Positivo y sin señal = FN. Etiquetas ausentes, insuficientes y abstenciones se contabilizan aparte.
4. Precisión = VP/(VP+FP). Sensibilidad = VP/(VP+FN). FPR = FP/(FP+VN). FNR = FN/(FN+VP). Exactitud = (VP+VN)/(VP+FP+VN+FN). Brier, solo para el modelo, es la media de (probabilidad−etiqueta)² entre evaluados y puntuados. Valores más bajos indican menor error cuadrático.
5. Revisa diferencias de FPR, FNR, cobertura y abstención entre programas y cursos con soporte comparable. Esto evalúa posibles disparidades operativas. No se recopilan atributos protegidos. El producto no certifica equidad demográfica. El doble conteo entre cursos se interpreta en pares persona–curso, no como observaciones independientes.
6. Los reportes aplican un mínimo por celda de 10 por defecto, bandas de cinco para conteos y redondeo de tasas a cinco puntos. Se ocultan tasas cuyo evento o complemento no cumple el mínimo. Brier requiere soporte mínimo en ambas clases y se redondea a pasos de 0,05. Mantén los resultados por versión y nunca reetiquetes para mejorar una métrica.

Las etiquetas independientes miden concordancia con el protocolo adoptado. El panel operativo informa casos, asignaciones, intervenciones y resoluciones. Cualquier afirmación de eficacia requiere un diseño de evaluación independiente.
