
# Contrato de licencias SkillUps

La autorización offline v1 consta de `base64(payload_json).base64(signature_ed25519)`. La firma cubre los bytes originales del JSON. El administrador configura `$CFG->local_skillupscopilot_entitlement` y `$CFG->local_skillupscopilot_licensepublickey` (clave pública Ed25519 Base64). La emisión y la clave privada se mantienen fuera de Moodle.

| Campo | Tipo | Descripción |
| --- | --- | --- |
| `version` | entero | 1 |
| `id` | cadena | Identificador opaco para revocación |
| `tier` | cadena | `professional` o `enterprise` |
| `issued` | entero | Inicio de vigencia Unix UTC |
| `expires` | entero | Vencimiento Unix UTC, máximo 366 días tras el inicio |
| `sites` | lista | Identificadores SHA-256 de los `wwwroot` autorizados, sin barra final |
| `features` | lista | Autorizaciones de funciones, actualmente `aggregate_export` |

`$CFG->local_skillupscopilot_gracedays` acepta de 0 a 30 días y `$CFG->local_skillupscopilot_revoked` contiene IDs rechazados. Moodle verifica la firma localmente y guarda el resultado criptográfico durante la solicitud. La lista de revocación la actualiza el operador. Un servicio SkillUps opcional podría suministrar el mismo artefacto firmado bajo un contrato futuro. Este paquete no incluye una API remota de emisión, revocación o renovación. La edición Community no depende de un servicio de licencias.
