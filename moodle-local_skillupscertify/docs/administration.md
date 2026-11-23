# Instalación y administración

Para instalar manualmente, extrae `skillupscertify` en `local/` en Moodle 5.0 o en `public/local/` en distribuciones con raíz pública separada. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle. Habilita cron para la emisión por lotes y la limpieza programada.

| Capacidad | Acceso |
| --- | --- |
| `local/skillupscertify:manage` | Editar y habilitar plantillas. |
| `local/skillupscertify:issue` | Emisión individual y por lote dentro del alcance vigente. |
| `local/skillupscertify:viewregistry` | Registro, historial y descargas del curso. |
| `local/skillupscertify:revoke` | Revocar y, junto con emisión, reemplazar. |
| `local/skillupscertify:export` | Exportar CSV con permiso de registro y el mismo alcance del filtro. |

Quien tenga matrícula de estudiante puede recibir un certificado. El titular utiliza su cuenta de Moodle para consultar su archivo, incluso si termina su matrícula. Los operadores respetan la visibilidad de grupos separados salvo que tengan la capacidad nativa de acceder a todos los grupos.

En **Administración del sitio → Extensiones → Plugins locales → SkillUps Certify & Verify** se controla la verificación pública en todo el sitio. La opción de una plantilla autoriza metadatos para documentos nuevos. Desmarcarla retira el título, el emisor y la fecha de la verificación pública de esa plantilla. El estado y el PDF archivado se conservan.

La conservación predeterminada es de 90 días para lotes cerrados y 730 días para la bitácora local. Los certificados, su estado y los PDF permanecen hasta una eliminación aprobada por privacidad, el borrado del curso o la desinstalación. Respalda la base de datos y `moodledata` juntos para preservar archivos, huellas, enlaces y auditoría. Conserva la URL canónica o una redirección segura al migrar el sitio.

Community incluye el diseñador, PDF, verificación QR, registro y exportación CSV. El CSV ampliado con revisión y fuente de evidencia requiere el permiso de exportación y una licencia firmada válida. La configuración opcional por sitio utiliza `local_skillupscertify_entitlement`, `local_skillupscertify_licensepublickey`, `local_skillupscertify_revoked` y `local_skillupscertify_gracedays` en `config.php`. El formato es `base64(JSON).base64(firma Ed25519)`. El documento firmado contiene `version`, `component`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. El identificador de sitio es el SHA-256 de la URL canónica sin barra final. La capacidad de licencia reconocida es `extended_export`. La validación se ejecuta localmente.
