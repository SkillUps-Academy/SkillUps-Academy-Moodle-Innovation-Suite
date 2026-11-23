# Uso y catálogo de reglas

## Diagnosticar, comparar y exportar

1. Abre el plugin en el curso y elige **Ejecutar diagnóstico**. Consulta los conteos, abre **Relaciones** para ver los identificadores de módulos, categorías y dependencias o filtra los **Hallazgos** por severidad y regla.
2. Para comparar dos respuestas, introduce en el informe actual el número de un diagnóstico anterior del mismo curso. La tabla distingue hallazgos nuevos, con evidencia modificada, sin cambios y ausentes en la revisión actual.
3. **Descargar CSV** guarda el informe completo del diagnóstico seleccionado. El historial por tipo conserva también simulaciones, reparaciones y recuperaciones.

## Reparación asistida

Abre **Simular reparación** desde una alerta de actividad oculta con elemento visible. Revisa el identificador del elemento, el estado original, el cambio de visibilidad, su impacto y la recuperación. Un gestor con permisos de Gradebook debe pulsar **Aprobar y ocultar el elemento**. La simulación dura 24 horas. Guardian vuelve a verificar el estado y guarda la decisión con los estados reales.

Desde la simulación aplicada, **Aprobar la recuperación de la visibilidad original** restablece el ajuste si la configuración coincide con la aprobada. Un conflicto exige revisión de la configuración actual. La operación cambia el ajuste del elemento, mantiene los estados de ocultación individuales y deja que Moodle controle los totales y la presentación del curso en cada informe. El editor nativo de Gradebook sigue disponible para las correcciones de configuración. El equipo técnico debe investigar corrupción estructural o relaciones irrecuperables.

| Regla | Señal y evidencia | Tratamiento |
| --- | --- | --- |
| `root`, `parent`, `categorycycle`, `total` | Número de raíces, categoría superior ausente, ciclo o total incorrecto para una categoría | Intervención técnica |
| `empty`, `rootactivity` | Categoría vacía o actividad ubicada en la categoría raíz | Revisar diseño |
| `aggregation` | Método de agregación guardado o efectivo fuera del catálogo nativo | Intervención técnica |
| `droprules` | Descartar bajas y conservar altas se configuraron simultáneamente | Revisar criterio en Moodle |
| `weight`, `weightszero` | Multiplicador negativo en media ponderada, peso manual Natural fuera de 0–1 o suma no positiva en media ponderada | Revisar método y pesos; el crédito extra se interpreta según el método |
| `itemcategory`, `range`, `scale` | Categoría inválida, máximo numérico no superior al mínimo o escala ausente o ajena al curso | Intervención técnica |
| `duplicate`, `unlinked` | Identidad duplicada por curso, módulo, instancia y número, o elemento huérfano o en eliminación | Verificar el ciclo de vida e investigar si persiste |
| `missingactivitygrade` | Una actividad compatible con calificaciones no tiene elemento enlazado | Revisar si la calificación se activó |
| `hidden`, `hiddenmismatch`, `hiddenreview` | Elemento oculto o actividad oculta con elemento explícitamente visible | Revisar la fecha o simular un cambio de visibilidad si el elemento numérico es elegible |
| `reference`, `dependency` | Referencia sin resolver o ambigua, dependencia circular o afectada por un ciclo | Intervención técnica |
| `formula`, `formulalimit` | El evaluador nativo falla con los máximos de las entradas o la fórmula supera 2048 bytes | Revisar en el editor nativo |
| `pending` | Moodle marca un cálculo o total para recálculo | Verificar estado en Moodle |

### Límites del diagnóstico

La raíz es una categoría válida de Moodle. La capacidad de una actividad para producir notas no confirma que esté configurada para hacerlo. La revisión de dependencias incluye relaciones estructurales potenciales sin usar exclusiones, bloqueos o valores de estudiantes. La prueba de cálculo usa los máximos estructurales al igual que la validación estática de Moodle y puede detectar errores de sintaxis o evaluación con esos valores. Puede pasar una fórmula que falle con otras entradas. Los límites configurables son 1.000 a 10.000 registros estructurales por curso. La pausa mínima entre diagnósticos de un mismo curso es de 10 segundos.
