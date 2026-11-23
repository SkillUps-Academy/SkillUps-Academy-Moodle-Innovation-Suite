# Operación y archivos

Usa **Simular un registro** o **Importar CSV o XLSX**. Revisa la evidencia y confirma las filas válidas. Para ejecutar más tarde, habilita **Inicio programado** antes de simular. Moodle cron inicia las tareas a partir de esa fecha. Los informes registran cada resultado, el número de intentos y el motivo de error. Usa **Mostrar solo errores** y descarga el CSV para corregir y volver a presentar las filas necesarias.

## Columnas

Usa un CSV UTF-8 separado por comas o la primera hoja de un XLSX con valores literales. Tamaño máximo de 2 MiB, hasta 1000 filas según la configuración, y límites de expansión para XLSX. Las fórmulas y celdas de error se rechazan.

| Columna | Uso |
| --- | --- |
| `action` | `enrol`, `role`, `suspend` o `reactivate`. |
| `userid` o `username` | Una referencia a usuario local de Moodle. |
| `courseid` o `shortname` | Una referencia de curso nativa. |
| `instanceid`, `method` | Instancia exacta y método opcionales. El método predeterminado al omitir ambos es `manual`. Si se indica un método con varias instancias activas, indica el ID. |
| `role` | ID o nombre corto. En `enrol` puede omitirse para usar el rol nativo de la instancia. En `role` es obligatorio. |
| `groupid` | Grupo del mismo curso para `enrol` o `role`. Se añade la pertenencia de forma aditiva. |
| `factory_job`, `factory_row` | Par opcional de IDs que sustituye la referencia de curso. La fila es su posición en Course Factory. |

```csv
action,username,shortname,role,groupid
enrol,ana.formacion,FORMACION-27,student,8
enrol,diego.docente,FORMACION-27,editingteacher,
role,carmen.coordinacion,FORMACION-27,coordinador,
suspend,luis.estudiante,FORMACION-27,,
```

## Comportamiento e informes

- **Matricular** usa una instancia Manual. Para una matrícula existente, conserva fechas y estado. Puede añadir el rol autorizado y el grupo requerido. Los roles existentes se conservan.
- **Añadir rol** actúa sobre una matrícula Manual existente. Una acción con grupo requiere un estado activo.
- **Suspender o reactivar** modifica solo el estado de una instancia activa Manual o de Automatriculación que permita gestión. Las fechas originales se conservan. El acceso efectivo depende también de fechas, otras matrículas y permisos del curso.
- **Simulación y reintentos**. Se conserva una sola fila válida por usuario e instancia en cada lote. Si el estado ya coincide, se registra un resultado sin cambios. Si otro proceso modificó la evidencia y la solicitud aún no se cumple, se pide una simulación nueva. Los demás fallos recuperables se pueden reintentar hasta un máximo de tres intentos por fila.
- **Informes**. Filtra por ID de usuario, curso o lote, de forma simple o combinada. Los informes cubren el historial del plugin y aplican los permisos vigentes. Las filas siguen siendo consultables por lote cuando una entrada inválida carece de identidad resuelta. La exportación admite hasta 5000 filas tomadas del filtro.

## Course Factory

Instala opcionalmente **SkillUps Course Factory 0.9.0-beta.2** o una versión compatible con el contrato v1. En la hoja usa `factory_job` y `factory_row` en lugar de `courseid` o `shortname`. El contrato local `tool_skillupscoursefactory\integration\course_lookup::completed_course` devuelve el ID de la fila completada al propietario autorizado o gestor de Course Factory. Smart Enrollment vuelve a comprobar los permisos de matriculación del destino antes de simular y ejecutar. Los trabajos todavía pendientes y las referencias no autorizadas aparecen como filas inválidas.
