# Contrato de conectores autorizados

La edición independiente utiliza la mensajería de Moodle. La lista de clases externas se instala vacía. Si la institución necesita otro canal, puede instalar por separado un plugin Moodle que implemente `local_skillupscommunication\connector` y autorizar su clase concreta en los ajustes del sitio. La clase debe ser autoloadable, pertenecer a un componente instalado y poder construirse sin argumentos.

El método `send(int $userid, string $subject, string $body, string $key): string` recibe un solo destinatario y una clave estable por sitio, campaña y cuenta. El conector obtiene los datos de canal que necesite bajo sus propios permisos y configuración.

| Resultado | Evidencia exigida |
|---|---|
| `accepted` | El canal recibió la solicitud. |
| `delivered` | El canal confirmó la entrega de forma verificable en la operación. |
| `retry` | El conector sabe que la solicitud no se transfirió. |
| Excepción | Resultado incierto, incluido un timeout ambiguo. |

El adaptador debe usar la clave de idempotencia en el canal cuando esté disponible, limitar tiempos de espera y mantener credenciales en su almacén seguro. La capa central registra resultados sin almacenar respuestas completas ni secretos. No se incluye un conector para un proveedor externo concreto ni se envían mensajes de prueba a servicios externos.
