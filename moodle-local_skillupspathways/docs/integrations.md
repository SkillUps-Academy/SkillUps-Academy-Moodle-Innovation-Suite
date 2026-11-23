# Integraciones

## Certificación

Desde un plugin local instalado en Moodle, llama a `\local_skillupspathways\integration\certification::verified_completion($courseid, $pathid, $userid)`. El curso debe ser el de entrada, el gestor debe permitir certificación y el estudiante debe tener una finalización registrada y aún válida. La API devuelve `pathid`, `version` y `completedat`, o `null` si falta evidencia. El proveedor emisor aplica sus capacidades y su política de emisión, renovación y revocación. Pathways aporta la evidencia; la emisión de certificados pertenece al proveedor.

## Gamification Pro

Gamification Pro 0.9.0-beta.2 descubre el proveedor de Pathways cuando ambos están instalados. Habilita Learning Path en el mismo curso de entrada y utiliza el ID numérico del itinerario como ID del hito. Puedes tomarlo del parámetro `pathid` de la URL del diseñador. El participante usa **Comprobar hitos de Learning Path** para conciliar resultados. La evidencia se vuelve a verificar en Pathways y el registro de puntos de Gamification evita duplicados. Esta integración tiene su propio control por curso.
