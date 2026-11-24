# Administración

## Instalación y permisos

Verifica [SHA-256](../../releases/skillupsintegration/SHA256SUMS) e instala el ZIP. La carpeta física es `local/skillupsintegration` en Moodle 5.0, y `public/local/skillupsintegration` donde Moodle usa un directorio web `public`. Ejecuta el cron de Moodle cada minuto en producción. El despachador recupera trabajos debidos y los distribuye mediante tareas Adhoc. Los nodos que ejecutan cron deben recibir las mismas referencias y credenciales que el nodo web.

| Capacidad en el contexto de sistema | Función |
| --- | --- |
| `local/skillupsintegration:view` | Acceso al panel, historial y auditoría operativa |
| `local/skillupsintegration:manage` | Alta y configuración de conectores, incluida la activación |
| `local/skillupsintegration:preview` | Iniciar simulación y consultar los campos de cada fila |
| `local/skillupsintegration:apply` | Revisar campos y autorizar escrituras |
| `local/skillupsintegration:ingest` | Enviar y consultar eventos propios mediante el servicio REST |

Otorga las capacidades de Moodle para creación o actualización de usuarios en sistema y para cursos en sus contextos a los operadores con capacidad de aplicación. Moodle comprueba estas capacidades durante la ejecución. Configura una cuenta específica para REST con permiso de uso del protocolo, acceso al servicio restringido y la capacidad de envío. La identifica el ID configurado en cada conector. La cuenta asignada a webhooks es el operador habilitado que guardó el conector y conserva la capacidad de simulación. Los roles de administración, revisión y servicio pueden ser independientes.

## Credenciales

Aprovisiona un secreto JSON en una variable de entorno cuyo nombre comience con `SKILLUPS_HUB_` y continúe con mayúsculas ASCII, números o guiones bajos. Escribe solo el nombre de esa variable en el formulario. El origen de la variable puede ser el gestor de secretos institucional. El nodo web necesita la variable para verificar webhooks. Los nodos cron la necesitan para consultar proveedores. El plugin conserva una referencia y omite su valor en formularios, historial, auditoría y exportaciones. En una edición, deja la referencia vacía para mantener la anterior.

Para rotar un token OAuth, secreto de aplicación o bearer del gateway, cambia el valor aprovisionado bajo la referencia vigente o instala una nueva referencia y guarda el perfil. Reinicia de manera coordinada los procesos que cargan el entorno al arrancar. Las tareas futuras leen el valor vigente. Para webhooks, aprovisiona `webhook`, `previouswebhook` y `previousuntil` con el último segundo Unix de la transición. Actualiza el emisor y retira la clave anterior al completar la rotación. Los tokens del servicio REST se rotan en Moodle y se limitan a la cuenta y el servicio configurados.

## Conservación

En **Administración del sitio → Plugins → Plugins locales → SkillUps Integration Hub**, ajusta la conservación de simulaciones (por defecto 7 días, rango 1–30) y del historial (por defecto 365 días, rango 30–730). Las filas, cursores y conflictos detallados desaparecen al vencer la conservación de simulaciones. El historial conserva las referencias de idempotencia hasta el plazo de auditoría. Las asociaciones por identidad se mantienen mientras sean necesarias para sincronizar el objeto y se eliminan cuando corresponde por la Privacy API.

## Ediciones

Community incluye todos los conectores, simulación, aplicación, historial y auditoría en pantalla. Professional y Enterprise pueden habilitar un CSV de auditoría con `extended_evidence`. El estado se muestra en el panel. El archivo incluye únicamente identificadores de origen, ejecución y operador, tipos de operación y fechas UTC.

La verificación opcional usa PHP sodium y firmas Ed25519. Configura `$CFG->local_skillupsintegration_entitlement` con `base64(JSON).base64(firma del JSON)`, `$CFG->local_skillupsintegration_licensepublickey` con la clave pública base64, `$CFG->local_skillupsintegration_revoked` con IDs revocados y `$CFG->local_skillupsintegration_gracedays` entre 0 y 30 días. El JSON firmado contiene `version` (1), `component` (`local_skillupsintegration`), `id`, `tier` (`professional` o `enterprise`), `issued` y `expires` (Unix, vigencia de hasta 366 días), `sites` (SHA-256 de cada URL canónica de Moodle sin barra final) y `features`. Se verifica la firma una vez por petición. Una renovación o transferencia instala una licencia nueva, firmada con los sitios vigentes. La revocación local invalida el ID en la lista o retira la licencia. La edición Community permanece disponible tras el vencimiento.

## Desinstalación

Conserva las evidencias autorizadas y retira los tokens e integraciones de origen. Usa la administración de plugins de Moodle o `php admin/cli/uninstall_plugins.php --plugins=local_skillupsintegration --run`. La herramienta de Moodle retira la configuración y tablas propias. Los usuarios y cursos ya materializados conservan su estado en Moodle.
