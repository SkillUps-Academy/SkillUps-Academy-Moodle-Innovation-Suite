# Instalación y administración

El ZIP contiene el directorio `skillupspathways`. Para la instalación manual, colócalo en `local/skillupspathways` en Moodle 5.0 o en `public/local/skillupspathways` en las distribuciones con directorio público separado. Ejecuta la actualización desde Notificaciones o con `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle. Mantén habilitado cron.

| Capacidad | Alcance |
| --- | --- |
| `local/skillupspathways:view` | Entrada al portal. Iniciar o actualizar un itinerario requiere matrícula activa en el curso de entrada. |
| `local/skillupspathways:manage` | Edición, publicación y traslado confirmado. La publicación requiere además `moodle/course:update` en cada curso de destino. |
| `local/skillupspathways:report` | Informes del curso de entrada, sujetos a su modalidad de grupos. |

Los grupos separados acotan el informe a los grupos del operador salvo que disponga de `moodle/site:accessallgroups`. Revisa también las capacidades nativas del estudiante en cursos de destino. Habilita finalización, calificaciones o competencias según los criterios utilizados.

Consulta el plan y su caducidad en **Administración del sitio → Extensiones → Plugins locales → SkillUps Learning Path Designer**. El plugin se ejecuta localmente. Las funciones principales están disponibles en Community. El CSV agregado es una función opcional con licencia firmada validada localmente. Configuración en `config.php` mediante `local_skillupspathways_entitlement`, `local_skillupspathways_licensepublickey`, lista `local_skillupspathways_revoked` y período `local_skillupspathways_gracedays` de 0 a 30 días. El documento de licencia usa `base64(JSON).base64(firma Ed25519)`. El emisor firma propiedades `version`, `component`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. El identificador de sitio es SHA-256 de la URL canónica sin barra final. Solo se reconoce `aggregate_export` como función licenciable.

Respalda la base de datos antes de una actualización o desinstalación. Las tablas del plugin y sus historiales pertenecen al sitio y se eliminan al desinstalar el componente. Un respaldo de curso de Moodle conserva el contenido estándar del curso y los itinerarios entre cursos se conservan mediante el respaldo institucional de base de datos.
