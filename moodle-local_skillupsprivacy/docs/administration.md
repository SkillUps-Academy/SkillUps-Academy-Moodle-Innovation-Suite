# Instalación y administración

1. Verifica [SHA-256](../../releases/skillupsprivacy/SHA256SUMS), instala el ZIP mediante el instalador de Moodle y completa la actualización. El paquete contiene una única carpeta `skillupsprivacy`. Para despliegue por consola, extrae esa carpeta en el directorio `local` del código web y ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle.
2. Configura un rol de responsable de privacidad en la administración nativa de Moodle y asígnalo en el contexto de sistema. Otorga las capacidades de SkillUps que correspondan. Los administradores del sitio tienen acceso por defecto.
3. En **Administración del sitio → Plugins → Plugins locales → SkillUps Privacy Manager**, revisa el plazo operativo inicial (30 días, rango 1–365), la antelación del aviso (3 días, rango 1–30), la retención de auditoría de consultas (365 días, rango 30–730) y la activación de avisos (desactivados al instalar). Los recordatorios respetan las preferencias y procesadores habilitados en Moodle.
4. Configura la categoría y finalidad predeterminadas del sitio en el registro nativo de Moodle antes de revisar sus políticas efectivas por contexto. Revisa las finalidades protegidas y el calendario institucional de conservación.
5. Comprueba el cron de Moodle. La tarea `\local_skillupsprivacy\task\reconcile` está programada cada 15 minutos. Sin cron, la sincronización programada y los recordatorios quedan pendientes.

| Capacidad en el contexto de sistema | Alcance |
| --- | --- |
| `local/skillupsprivacy:view` | Registro, detalle, referencias nativas e informes agregados |
| `local/skillupsprivacy:manage` | Alta, asignación, cambio de plazo, revisión y actualización manual |
| `local/skillupsprivacy:decide` | Evaluación de retención, aprobación, rechazo, cierre y auditoría de accesos |
| `local/skillupsprivacy:inventory` | Declaraciones de extensiones y acceso a la vista de retención |
| `local/skillupsprivacy:export` | Descarga de evidencias operativas CSV |

Cada acción exige acceso al registro y pertenencia al rol nativo de privacidad del sitio o administración del sitio. La vista **Política de retención** también exige `tool/dataprivacy:managedataregistry`. Las asignaciones solo admiten responsables habilitados, con permiso de gestión. Los permisos de sistema dan acceso a la institución completa. Utiliza roles separados si la institución exige que casework y decisión recaigan en operadores distintos.

## Licencia comercial opcional

Community incluye gestión, alertas, inventario y CSV estándar. Professional y Enterprise pueden ampliar el CSV con la revisión del expediente y las fechas de modificación y conciliación si su licencia contiene `extended_evidence`. La página **Informes** muestra el estado de la edición y su vencimiento cuando aplica.

El adaptador verifica localmente firmas Ed25519 con PHP sodium. `$CFG->local_skillupsprivacy_entitlement` contiene `base64(JSON).base64(firma sobre el JSON)`, `$CFG->local_skillupsprivacy_licensepublickey` la clave pública codificada en base64, `$CFG->local_skillupsprivacy_revoked` una lista opcional de IDs revocados y `$CFG->local_skillupsprivacy_gracedays` un período opcional de gracia de 0 a 30 días. Los campos firmados son `version` (1), `component`, `id`, `tier` (`professional` o `enterprise`), `issued`, `expires` (sellos Unix separados por hasta 366 días), `sites` (SHA-256 de cada URL canónica sin barra final) y `features`. El adaptador almacena en memoria la verificación de firma para la petición actual.

La renovación o transferencia instala una licencia recién firmada con los sitios y vencimiento actualizados. Para revocar, añade el ID a la lista local o retira la licencia. Un cambio de URL canónica exige actualizar la licencia. El flujo Community sigue disponible tras un vencimiento o revocación y funciona sin sodium. No se requieren credenciales de ningún servicio.

## Desinstalación

Tras verificar las obligaciones de conservación, exporta las evidencias necesarias y conserva una copia de seguridad autorizada. Desinstala desde la pantalla de plugins o con `php admin/cli/uninstall_plugins.php --plugins=local_skillupsprivacy --run`. Moodle retira las tablas y ajustes propios del plugin. Las solicitudes, archivos y decisiones de Moodle se administran con sus herramientas nativas.
