# API de integración

## Entrada común

La entrada acepta un objeto JSON UTF-8 de hasta 256 KiB con un identificador `event` de 1 a 128 caracteres y entre 1 y 100 registros. Cada registro contiene `entity` con valor `user` o `course` y un objeto `fields`. Los campos se interpretan según el mapeo del perfil. Los registros inválidos se conservan como errores de la simulación. Cada evento admitido genera una simulación que requiere aprobación humana.

```json
{
  "event": "registry-2026-11-24-001",
  "records": [
    {
      "entity": "user",
      "fields": {
        "id": "student-401",
        "username": "student401",
        "firstname": "Ana",
        "lastname": "Pérez",
        "email": "student401@example.invalid"
      }
    },
    {
      "entity": "course",
      "fields": {
        "id": "course-22",
        "shortname": "BIO-22",
        "fullname": "Biología general"
      }
    }
  ]
}
```

La respuesta incluye `runid`, `state` y `replayed`. Reintenta con el mismo ID y exactamente los mismos bytes. El mismo evento con un cuerpo distinto devuelve un conflicto de idempotencia. El período de deduplicación sigue la conservación de auditoría del sitio. Una vez vencido, se mantienen las asociaciones con objetos Moodle que siguen en gestión.

## Moodle REST

1. Habilita servicios web y REST por HTTPS. Habilita **SkillUps Integration Hub ingestion**, restringido a cuentas autorizadas.
2. Crea un token para la cuenta indicada en el perfil. Concede `webservice/rest:use` y la capacidad `local/skillupsintegration:ingest`. Define límites de red y vencimiento según la política institucional.
3. Usa `POST /webservice/rest/server.php` con los parámetros de formulario `wstoken`, `moodlewsrestformat=json`, `wsfunction=local_skillupsintegration_submit`, `sourceid` y `payload` con el JSON exacto. Mantén el token fuera de la URL, las capturas y el historial de comandos.
4. Usa `local_skillupsintegration_status` con `runid` para obtener `runid`, `state` y `records`. Solo la cuenta REST que envió la operación y sigue autorizada para ese perfil puede consultarla. Los errores usan el formato de servicios web de Moodle.

La API documentada de la función también aparece en **Administración del sitio → Servidor → Servicios web → Documentación de API**.

[External Services API de Moodle](https://moodledev.io/docs/5.3/apis/subsystems/external)

## Webhook firmado

Usa `POST /local/skillupsintegration/webhook.php?sourceid=ID` sobre HTTPS y `Content-Type: application/json`. En `X-SkillUps-Timestamp` envía el Unix timestamp actual como diez dígitos decimales. En `X-SkillUps-Signature` envía `sha256=` seguido de 64 caracteres hexadecimales en minúsculas. Calcula HMAC-SHA256 con la clave `webhook` que comparte el emisor y el receptor, sobre la concatenación exacta de:

```text
timestamp + LF + sourceid_decimal + LF + bytes_del_JSON
```

Usa una clave de al menos 32 bytes. El receptor admite hasta cinco minutos de desviación y admite una clave anterior durante la rotación configurada. Usa HTTPS con validación de certificado en el emisor. Conserva el mismo `event` y cuerpo al reintentar, aunque se genere una firma nueva. Mantén fuera del payload credenciales, calificaciones, archivos y atributos innecesarios.

| Estado HTTP | Significado |
| --- | --- |
| 202 | Evento aceptado para revisión |
| 200 | Evento ya registrado con los mismos bytes |
| 400 | Contrato JSON inválido |
| 403 | Autenticación o conector no autorizado |
| 405 | Método no admitido |
| 409 | Event ID con un cuerpo distinto |
| 413 | Cuerpo demasiado grande |
| 415 | Tipo de contenido distinto de JSON |
| 429 | Conector ocupado o cuota de simulaciones pendientes |
| 500 | Error operativo genérico |

## Contrato de consulta SIS y RR. HH.

El gateway recibe un bearer y responde a `GET /users?limit=100` y `GET /courses?limit=100`. Para continuar, Moodle añade el parámetro URL-codificado `cursor`. La respuesta es un objeto con `items` (0 a 100 objetos) y `next_cursor` (string opaco, vacío cuando termina). Usa páginas estables para evitar duplicaciones y revisa orden e IDs antes de activar. Devuelve HTTP 429 o 5xx para fallos transitorios y `Retry-After` en segundos cuando aplique.

```json
{"items":[{"id":"person-7","firstname":"Ana","lastname":"Pérez","email":"ana@example.invalid"}],"next_cursor":""}
```
