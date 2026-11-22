# Instalación y configuración

Requiere Moodle 5.0 o posterior y una versión de PHP y del motor de base de datos admitida por la rama de Moodle instalada. Moodle 5.3 LTS es la rama de referencia. Los componentes nativos `tool_task` y `dataformat_csv` deben estar disponibles.

1. Instala el ZIP desde **Administración del sitio → Plugins → Instalar plugins**. La carpeta de instalación es `report/skillupsmonitor` bajo la raíz pública de Moodle. Desde Moodle 5.1, una instalación estándar la ubica en `public/report/skillupsmonitor`. Para instalación manual, coloca ahí la carpeta `skillupsmonitor` y ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz del código Moodle.
2. Abre **Administración del sitio → Informes → SkillUps System Monitor**. Busca **Configuración del monitor** en Administración del sitio para editar la política.
3. Ejecuta el cron de Moodle cada minuto. En **Servidor → Tareas programadas** verifica el colector cada cinco minutos, el despacho de alertas cada cinco minutos y la limpieza diaria. Para una comprobación inicial usa `php admin/cli/scheduled_task.php --execute='\report_skillupsmonitor\task\sample'`. El primer muestreo cubre su intervalo actual. El historial lista intervalos completos y muestra la muestra cuando cierra ese intervalo.
4. Si necesitas delegar acceso, concede `report/skillupsmonitor:view` en el contexto de sistema. Los administradores y, por defecto, el arquetipo Gestor pueden acceder. Para confirmar una revisión, concede además `report/skillupsmonitor:manage`. El acceso a los detalles nativos de tareas y eventos se controla aparte.
5. Para enviar alertas, activa las notificaciones, selecciona comprobaciones y escribe hasta 20 ID numéricos de operadores con acceso al monitor. Verifica que el canal de notificaciones elegido esté habilitado en la configuración de mensajería de Moodle. El proveedor tiene habilitada por defecto la notificación web.

## Valores iniciales

| Ajuste | Valor |
| --- | --- |
| Fallos y recuperaciones para declarar la transición | Dos observaciones consecutivas por transición |
| Aviso de cron | Último inicio hace más de cinco minutos |
| Latencia interna | Desde 500 ms |
| Margen de tareas atrasadas | Cinco minutos |
| Cola ad hoc atrasada | Aviso desde 20 tareas |
| Observación desactualizada | Más de 15 minutos |
| Retención | 90 días para muestras e incidentes cerrados, configurable entre 30 y 365 |
| Monitoreo externo y entrega de alertas | Desactivados |

El cambio de umbrales reinicia las rachas pendientes. La limpieza conserva incidentes abiertos y elimina lotes de registros vencidos. Exporta el resumen antes de desinstalar si necesitas guardar un registro operativo. La desinstalación elimina las tablas y opciones del plugin.

## Licencia opcional

Community incluye panel, diagnóstico, alertas, historial, CSV resumido, sondas y métricas. La exportación CSV por intervalo requiere la autorización firmada `aggregate_export`, disponible con licencias Professional y Enterprise. Configura en `config.php` el documento `$CFG->report_skillupsmonitor_entitlement` y la clave pública Ed25519 en base64 `$CFG->report_skillupsmonitor_licensepublickey`. La configuración muestra el identificador de instalación. El formato firmado es `base64(JSON).base64(firma)` con `version: 1`, componente, identificador, plan, fecha de emisión, vencimiento anual, sitios autorizados y permisos. Las fechas se expresan en segundos Unix. Las opciones `$CFG->report_skillupsmonitor_revoked` y `$CFG->report_skillupsmonitor_gracedays` aplican revocación local y hasta 30 días de gracia. La validación se ejecuta localmente con Sodium.
