# Instalación y administración

La plataforma de referencia es Moodle 5.3 LTS y la versión mínima es Moodle 5.0. El plugin utiliza OpenSpout incluido en Moodle y los plugins nativos `enrol_manual`, `dataformat_csv` y `dataformat_excel`. La automatriculación es opcional y requiere que Moodle tenga habilitado `enrol_self`. Course Factory es opcional.

1. Instala el ZIP desde **Administración del sitio → Plugins → Instalar plugins**. Para instalación manual, copia `skillupsenrollment` a `admin/tool` bajo la raíz pública. Desde Moodle 5.1 la distribución estándar usa `public/admin/tool`. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz.
2. Abre **Administración del sitio → Cursos → SkillUps Smart Enrollment Manager**. Busca **Configuración de Smart Enrollment Manager** para definir el máximo de 100, 500 o 1000 filas. El valor inicial es 500.
3. Ejecuta cron cada minuto y verifica la tarea de despacho. El ejecutor serializa el trabajo del plugin mediante Lock API y atiende hasta 50 filas o hasta alcanzar su presupuesto de ejecución por tarea. En un clúster utiliza un backend de bloqueos compartido y cron activo.
4. Define los roles institucionales de coordinador en Moodle con alcance de curso y revisa qué roles puede asignar cada operador. Si una acción indica un grupo, el operador también necesita permiso para gestionar grupos y acceso al ámbito correspondiente.

`tool/skillupsenrollment:operate` permite simular y ejecutar los lotes propios. `tool/skillupsenrollment:manage` permite consultar lotes de otros operadores y cancelar trabajo pendiente. Los administradores tienen acceso inicialmente. Los permisos nativos de participantes, matriculación, asignación de roles y grupos se aplican además en cada curso.

El historial se conserva 180 días por defecto, con rango de 30 a 730 días. Los borradores vencen a los siete días. Desinstalar elimina los datos de operación del plugin. Las matrículas, roles y grupos de Moodle mantienen su ciclo de vida nativo.

## Licencia opcional

Community incluye la importación CSV/XLSX, simulación, ejecución, informes CSV e integración local. La autorización firmada `aggregate_export` de Professional o Enterprise habilita la exportación de informes XLSX.

Configura `$CFG->tool_skillupsenrollment_entitlement` y `$CFG->tool_skillupsenrollment_licensepublickey`. El formato es `base64(JSON).base64(firma)` con la clave pública Ed25519 en base64. El JSON lleva `version` igual a 1, `component` igual a `tool_skillupsenrollment`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. La vigencia máxima es de 366 días y las fechas se expresan en segundos Unix. `sites` contiene hashes SHA-256 de URLs canónicas. `$CFG->tool_skillupsenrollment_revoked` admite una lista de IDs y `$CFG->tool_skillupsenrollment_gracedays` hasta 30 días de gracia. La verificación con Sodium es local.
