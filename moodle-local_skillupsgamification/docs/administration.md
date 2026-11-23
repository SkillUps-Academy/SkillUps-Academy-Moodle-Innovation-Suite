# Instalación y configuración

1. Instala el [ZIP](../../releases/skillupsgamification/skillupsgamification-0.9.0-beta.1.zip) desde **Administración del sitio → Plugins → Instalar plugins**. En una instalación manual, copia `skillupsgamification` a `local/` bajo la raíz pública. Desde Moodle 5.1 la distribución estándar usa `public/local/`. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz.
2. Habilita la finalización en Moodle y en el curso. Configura actividades con finalización automática y, si se usará la regla de curso, configura la finalización general.
3. Abre **Curso → Más → SkillUps Gamification Pro → Administrar gamificación**. Habilita el curso. Configura XP por nivel, el tope global de puntos por día UTC y los permisos de ranking.
4. Si quieres usar equipos, crea un agrupamiento Moodle y asigna grupos y participantes. Selecciona el agrupamiento en la configuración. La pertenencia ambigua a varios grupos del mismo agrupamiento no produce un equipo.
5. Agrega reglas de puntos y configura el catálogo. Ejecuta el cron de Moodle con regularidad y usa un proveedor de bloqueos compartido en instalaciones con múltiples servidores.

| Capacidad | Uso predeterminado |
| --- | --- |
| `local/skillupsgamification:view` | Ver el panel del curso |
| `local/skillupsgamification:earn` | Con matrícula activa, acumular puntos, consentir el ranking y reclamar logros |
| `local/skillupsgamification:manage` | Configurar reglas, fuentes y metas, gestionar exclusiones y confirmar entregas |

Excluye un participante por su ID de Moodle. La exclusión impide nuevos premios y retira su consentimiento de clasificación. Conserva su historial personal. El plugin añade registros propios y conserva las calificaciones de Moodle.

## Privacidad, retención y licencias

Se guardan preferencias y alias por curso, puntos y XP, evidencia identificada por ID, equipo al conceder el premio, solicitudes, entregas y auditoría. No se copian notas, actividades ni respuestas. Los datos se conservan mientras exista el curso para impedir repeticiones. La Privacy API exporta o retira los datos en el contexto aprobado. Las entregas de recompensas ya realizadas conservan un asiento anonimizado para mantener el inventario. Al eliminar el curso se elimina el historial propio del plugin. Los eventos del registro Moodle siguen la política del almacén de logs.

Community incluye todo el flujo. Las licencias Professional o Enterprise con derecho `aggregate_export` habilitan un CSV de conteos por meta, sin personas. Configura `$CFG->local_skillupsgamification_entitlement` y `$CFG->local_skillupsgamification_licensepublickey`. El formato es `base64(JSON).base64(firma)` con clave pública Ed25519. El documento contiene `version` igual a 1, `component` igual a `local_skillupsgamification`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. Vigencia máxima de 366 días. Cada sitio es el SHA-256 de su URL canónica sin barra final.

El estado se muestra en la administración del sitio. `$CFG->local_skillupsgamification_revoked` admite IDs revocados y `$CFG->local_skillupsgamification_gracedays` admite hasta 30 días. La verificación se realiza en el servidor Moodle con Sodium. Renueva o transfiere solicitando un nuevo documento firmado al emisor, con la lista de instalaciones autorizadas.
