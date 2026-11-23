# Instalación y administración

1. Sube el ZIP desde **Administración del sitio → Plugins → Instalar plugins**. Si haces la instalación manual, extrae la carpeta `skillupsgradebookguardian` en `local/` bajo la raíz pública de Moodle. En las distribuciones estándar desde Moodle 5.1 corresponde a `public/local/`. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle.
2. En **Administración del sitio → Plugins → Plugins locales → SkillUps Gradebook Guardian**, configura la retención, el límite por diagnóstico y el interruptor de reparación.
3. Abre el menú **Más** del curso y selecciona **SkillUps Gradebook Guardian**. De forma predeterminada los docentes editores y gestores pueden consultar y ejecutar diagnósticos. Los gestores y administradores pueden aprobar, recuperar y exportar según sus capacidades efectivas.
4. Mantén el cron de Moodle activo para la eliminación periódica de registros vencidos. Para instalaciones en clúster configura un backend de Moodle Lock API compartido.

| Capacidad | Uso |
| --- | --- |
| `local/skillupsgradebookguardian:view` | Consultar el historial, los hallazgos y la estructura guardada del curso |
| `local/skillupsgradebookguardian:diagnose` | Ejecutar diagnósticos y guardar simulaciones |
| `local/skillupsgradebookguardian:approve` | Aprobar el cambio o recuperar la visibilidad con `moodle/grade:manage` |
| `local/skillupsgradebookguardian:export` | Descargar los informes completos del curso |

El acceso también exige `moodle/grade:viewall`, `moodle/grade:viewhidden` y `moodle/course:viewhiddenactivities`. Asigna las capacidades en el contexto adecuado. El informe analiza la estructura de todo el curso y no contiene notas de grupos o estudiantes.

## Retención y privacidad

La operación conserva su tipo, curso, fecha, identificador de operador, evidencia estructural, registros relacionados y, si procede, ajustes de visibilidad antes y después. No se guardan valores, nombres ni retroalimentación de estudiantes. La Privacy API exporta los registros del operador y anonimiza su atribución en los contextos aprobados. La retención es de 180 días de forma predeterminada. Eliminar el curso elimina el historial del plugin. Los eventos adicionales siguen la retención del almacén de logs de Moodle. Desinstalar elimina la tabla del plugin. Si necesitas deshacer cambios de visibilidad, usa la recuperación antes de desinstalar.

## Licencia opcional

Community incluye diagnóstico, relaciones, comparación, simulación, aprobación, recuperación y CSV. Consulta el estado de la suscripción en los ajustes del plugin. El derecho `json_export` para Professional o Enterprise añade un JSON con estructura, dependencias y hallazgos.

Configura en Moodle `$CFG->local_skillupsgradebookguardian_entitlement` y `$CFG->local_skillupsgradebookguardian_licensepublickey`. El formato es `base64(JSON).base64(firma)` con la clave pública Ed25519 en base64. El JSON lleva `version` igual a 1, `component` igual a `local_skillupsgradebookguardian`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. La vigencia máxima es de 366 días. `sites` contiene hashes SHA-256 de las URLs canónicas sin barra final, uno por instalación autorizada. Configura `$CFG->local_skillupsgradebookguardian_revoked` para revocar IDs y `$CFG->local_skillupsgradebookguardian_gracedays` para habilitar hasta 30 días de gracia. Renueva o transfiere mediante un documento firmado nuevo para los sitios autorizados. La verificación usa Sodium, se reutiliza durante la solicitud y funciona sin conexión.
