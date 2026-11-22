# Instalación y administración

1. Verifica el SHA-256 y sube el ZIP de `releases/skillupsinsight` en **Administración del sitio → Plugins → Instalar plugins**. Por archivos el destino relativo a la raíz pública es `report/skillupsinsight`. Moodle proporciona los complementos de formato CSV, Excel y PDF. La instalación no requiere Composer.
2. Completa la actualización en Notificaciones o ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle.
3. Desde **Administración del sitio → Informes → SkillUps Insight Analytics**, abre **Períodos académicos**. Crea el período actual y el de referencia. Los períodos se definen en UTC. Mantén cron operativo para generar capturas y evaluar alertas.
4. En **SkillUps Insight configuration**, elige el denominador mínimo (predeterminado 5), la retención de generaciones anteriores (predeterminada 90 días) y el nombre corto del campo que representa el programa.
5. Para programas, crea un campo personalizado nativo de curso de texto de una línea, visible para los perfiles que consultarán informes, con nombre corto `insightprogram` o el nombre configurado. Usa un código o nombre de programa coherente por curso. Los cursos sin campo visible quedan sin programa.
6. Habilita el almacén estándar de registros para medir actividad y participación. Revisa la política de retención de Moodle, especialmente durante el cierre de períodos. Configura y prueba finalización nativa y notas del curso antes de reconciliar las métricas.

## Roles y tareas

Concede `report/skillupsinsight:view` y, cuando proceda, `:export`, `:personalise` y `:schedule` en el contexto autorizado. La vista general exige alcance del sitio. En categoría se incluyen descendientes autorizados. La vista de curso requiere `moodle/site:accessallgroups`, `moodle/grade:viewall`, `report/completion:view` y acceso vigente al curso. `:manage` a nivel de sitio administra períodos. `:learner` y `:educator` determinan quién integra las poblaciones y se asignan a roles de personas matriculadas.

Cron ejecuta `report_skillupsinsight\task\aggregate` y `report_skillupsinsight\task\deliver`. Un período nuevo tarda tantos ciclos como lotes necesite procesar. Congela las capturas aprobadas de períodos cerrados antes de que los cambios de matrícula o retención del log alteren futuras recomputaciones. La última captura publicada de cada período se conserva al vencer la retención.

Los reportes programados envían enlaces privados mediante Moodle Messaging. Las alertas notifican al entrar en estado de meta alcanzada. La programación por «cada 30 días» usa un intervalo fijo. Si Moodle falla después de confirmar una ejecución, se conserva la siguiente fecha sin reintentar una entrega ambigua. No se incluyen métricas en el correo o notificación. Ajusta los canales de mensajería desde Moodle.

## Licencias opcionales

Community incluye el panel, constructor, exportaciones, metas y programación. Professional y Enterprise pueden ampliar de 1.000 a 5.000 filas el límite de CSV/XLSX con `aggregate_export`. PDF admite hasta 1.000 filas verticales de indicador.

La configuración de licencia reside en `config.php`. Define `$CFG->report_skillupsinsight_entitlement` como `base64(JSON).base64(firma)` y `$CFG->report_skillupsinsight_licensepublickey` con la clave pública Ed25519 en Base64. El JSON firmado incluye `component: "report_skillupsinsight"`, `version: 1`, `id`, `tier`, `issued`, `expires`, `sites` (identificadores SHA-256 de las URLs sin barra final) y `features`. Validez máxima de 366 días. Sodium verifica y reutiliza la firma durante la solicitud. El panel de configuración muestra el estado y el ID de esta instalación.

Usa `$CFG->report_skillupsinsight_gracedays` (0 a 30) y `$CFG->report_skillupsinsight_revoked` (lista de IDs revocados), cuando corresponda. Renovación y transferencia requieren una licencia firmada actualizada del emisor. La ampliación solo aplica mientras la autorización esté vigente o en gracia.

## Retirar el producto

Exporta las capturas que necesitas conservar. Desinstala desde la vista de plugins o con `php admin/cli/uninstall_plugins.php --plugins=report_skillupsinsight --run`. Retira el directorio y completa la actualización. La desinstalación elimina las tablas del plugin. Las fuentes Moodle de matrícula, calificación, finalización y eventos siguen bajo administración de Moodle.
