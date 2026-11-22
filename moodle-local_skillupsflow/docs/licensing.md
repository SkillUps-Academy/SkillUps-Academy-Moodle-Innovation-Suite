# Licencias offline

Configura, antes del bootstrap de Moodle, `$CFG->local_skillupsflow_entitlement` y `$CFG->local_skillupsflow_licensepublickey`. El contrato de entrega es `base64(payload JSON).base64(firma Ed25519)`. La clave pública está en Base64 y la firma cubre los bytes originales del JSON.

Usa las declaraciones `version: 1`, `id`, `tier` (professional o enterprise), `issued` y `expires` como segundos Unix, `sites` como lista de identificadores de instalación y `features` como lista de derechos, actualmente `aggregate_export`. Cada período firmado dura hasta 366 días. El panel muestra el identificador SHA-256 de la URL base y el estado de vigencia.

`$CFG->local_skillupsflow_gracedays` establece una gracia de 0 a 30 días. `$CFG->local_skillupsflow_revoked` contiene IDs revocados. La renovación o transferencia se hace sustituyendo la autorización por una nueva emisión firmada que incluye las instalaciones acordadas. Las verificaciones correctas se reutilizan durante la solicitud actual. La infraestructura de emisión se mantiene fuera del plugin y podría entregar el mismo artefacto con un adaptador futuro. Community y los flujos existentes conservan sus funciones ante un vencimiento.
