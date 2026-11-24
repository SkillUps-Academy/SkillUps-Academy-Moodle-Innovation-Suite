# Administración y operación

Para instalación manual, extrae `skillupssecurity` en `report/` si usas Moodle 5.0 o en `public/report/` cuando Moodle separa la raíz pública. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle. Habilita cron.

## Permisos en el contexto de sistema

| Capacidad | Acceso |
| --- | --- |
| `report/skillupssecurity:view` | Panel, matriz de roles y hallazgos sin identificar personas. |
| `report/skillupssecurity:inventory` | Inventario, eventos, alertas y hallazgos con cuentas identificadas. |
| `report/skillupssecurity:review` | Cambiar la disposición de los casos con motivo obligatorio. |
| `report/skillupssecurity:run` | Encolar revisiones manuales. |
| `report/skillupssecurity:export` | Exportar los resultados que el operador puede consultar. |

Los administradores tienen acceso por Moodle. La asignación a otros operadores se hace mediante un rol del sistema con las capacidades apropiadas.

## Cobertura y seguimiento

La auditoría diaria y la revisión encolada aplican los mismos controles. Se admite una solicitud manual por minuto y el sistema evita solicitudes duplicadas en cola. En cobertura parcial se mantienen los hallazgos anteriores del control. **Ya no se observa** significa que una revisión completa dejó de observar la condición. El estado de revisión es independiente. Al reaparecer una condición, queda con revisión nueva y se preserva su historial.

Los eventos se recogen desde la instalación del plugin. La bitácora registra cambios de roles, capacidades y relaciones de roles, acceso como otro usuario, cambios permitidos de configuración y consultas de Security Center. Las vistas tienen paginación. El CSV exporta hasta 10 000 registros de la selección autorizada.

## Notificaciones y retención

En **Política de Security Center** configura prioridad mínima, destinos Moodle y retención. La mensajería está deshabilitada inicialmente. Se admite una lista de hasta 20 IDs Moodle. Los destinatarios deben conservar permiso de consulta. Se genera una fila de envío por revisión y destinatario, a partir de hallazgos nuevos o recurrentes. El aviso es general. **Aceptado por Moodle** indica recepción por la API de mensajería. Una entrega incierta queda en el historial para evitar duplicados automáticos.

La retención inicial de evidencia histórica y casos resueltos es de 90 días. Se conserva la última instantánea y la evidencia técnica de casos vigentes. Los informes pueden conservar identificadores personales sujetos a las políticas del sitio.

## Planes

Community incluye auditoría, revisiones, alertas y CSV de evidencia. El CSV ampliado añade la huella de correlación y referencia de la revisión con el permiso de exportar y una licencia válida. El contrato es local, Ed25519 y anual. Configuración en `config.php` mediante `report_skillupssecurity_entitlement`, `report_skillupssecurity_licensepublickey`, `report_skillupssecurity_revoked` y `report_skillupssecurity_gracedays`. El formato es `base64(JSON).base64(firma)`. Las declaraciones son `version`, `component`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. La característica opcional se identifica como `extended_evidence`. Cada identificador de sitio es SHA-256 de la URL canónica sin barra final.
