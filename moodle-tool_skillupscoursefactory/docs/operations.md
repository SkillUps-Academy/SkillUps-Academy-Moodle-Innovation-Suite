# Operación e importación

En **Crear o clonar un curso**, selecciona un origen o una plantilla institucional, o elige crear un curso vacío. Configura categoría, nombre, nombre corto, código, período, fechas y matrículas. Los nuevos cursos permanecen ocultos durante toda la creación o clonación. Activa su publicación si deben mostrarse al completar la operación.

En **Importar CSV o XLSX**, prepara un CSV UTF-8 con coma o la primera hoja de un XLSX con valores literales. Usa una fila inicial de encabezados y hasta 500 registros, de acuerdo con el límite del sitio y el tamaño de lote seleccionado. El archivo admite hasta 2 MiB y el contenido de la hoja tiene límites de expansión.

| Columna | Uso |
| --- | --- |
| `fullname`, `shortname`, `category` | Obligatorias. Nombre, nombre corto e ID numérico de la categoría destino. |
| `idnumber`, `period` | Código institucional único y período académico opcionales. |
| `source`, `template` | ID numérico de un curso o de una plantilla. Si ambos se omiten, se crea un curso vacío. |
| `startdate`, `enddate` | Fechas ISO `AAAA-MM-DD` en la zona del operador. Una fecha inicial omitida usa el momento de la vista previa. Una fecha final omitida queda desactivada. |
| `teachers`, `coordinators` | IDs numéricos de usuarios separados por punto y coma. Hasta 25 valores por columna y fila. |
| `visible` | `1` para publicar tras el éxito. `0` o vacío conserva el curso oculto. |

```csv
fullname,shortname,category,idnumber,period,source,startdate,enddate,teachers,visible
Introducción a proyectos,PROJECT-2027,5,PR-2027,2027-T1,12,2027-01-04,2027-03-31,18;20,0
```

El motor comprueba permisos, nombres, códigos, fechas y matrículas. La vista previa muestra filas válidas e inválidas; confirmarla autoriza solo las válidas. La programación se expresa en la zona del operador y cron ejecuta el trabajo a partir de la hora prevista. Los identificadores se comprueban de nuevo antes de crear cada destino.

## Estados y recuperación

- **Vista previa** permite revisar o cancelar sin crear cursos.
- **En cola** conserva la programación y los puntos de control por fila. Una cancelación detiene los siguientes cambios seguros. La restauración que ya empezó puede terminar.
- **Completada o cancelada** registra el resultado de cada fila. Usa **Mostrar solo errores** antes de exportar para generar el informe filtrado.
- **Fallida** permite hasta tres intentos en total para errores recuperables. El reintento usa el destino ya registrado si se había creado. Revisa el código de error y corrige el origen, el permiso o la configuración antes de volver a intentar.
- **Requiere revisión manual** significa que una restauración pudo quedar incompleta. El curso registrado permanece oculto. Un administrador puede inspeccionar, corregir o retirar ese destino mediante Moodle. El motor evita repetir una restauración en ese estado.

La clonación utiliza la cobertura de Backup/Restore de los módulos instalados en Moodle y excluye los datos de participantes. La herramienta fija las fechas del curso de destino. La configuración y fechas de actividades siguen las reglas nativas de importación de cada módulo. Los cambios entre la vista previa y la ejecución pueden afectar el contenido del curso de origen.
