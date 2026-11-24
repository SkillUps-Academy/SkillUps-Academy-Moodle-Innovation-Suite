# Configuración de conectores

## Google Workspace

Habilita Directory API y Google Classroom API en la aplicación OAuth. Obtén un token de actualización delegado con acceso offline mediante el flujo de autorización de Google, con un principal institucional que tenga acceso administrativo al directorio y acceso a los cursos deseados. Usa `https://www.googleapis.com/auth/admin.directory.user.readonly` para usuarios y `https://www.googleapis.com/auth/classroom.courses.readonly` para cursos. Según el perfil, solicita ambos o únicamente el ámbito habilitado. En la referencia del entorno aprovisiona un JSON con `clientid`, `clientsecret` y `refresh`. El campo **Endpoint o inquilino** recibe un dominio Workspace o `my_customer`.

Directory devuelve usuarios del ámbito seleccionado. Se ignoran registros suspendidos. Classroom devuelve los cursos activos accesibles por el principal delegado. La configuración del dominio se aplica a Directory. La visibilidad en Classroom depende de la cuenta autorizada y de las políticas de Workspace.

[OAuth de Google para servidores](https://developers.google.com/identity/protocols/oauth2/web-server) · [Directory users.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/users/list) · [Classroom courses.list](https://developers.google.com/workspace/classroom/reference/rest/v1/courses/list)

## Microsoft 365

Registra una aplicación de Microsoft Entra ID en el inquilino correspondiente. Concede y aprueba por administrador `User.Read.All` para importar usuarios y `EduRoster.Read.All` para clases, según las entidades habilitadas. Usa el UUID del inquilino como **Endpoint o inquilino** y aprovisiona un JSON con `clientid` y `clientsecret`. El adaptador usa OAuth 2.0 de credenciales de cliente con el ámbito de aplicación de Microsoft Graph. Está configurado para la nube global. La consulta de cursos requiere que el inquilino tenga acceso a Microsoft Graph Education y clases visibles para la aplicación.

Los usuarios deshabilitados de Graph se omiten. Si `mail` está vacío se usa `userPrincipalName` siempre que pueda validarse como correo. La continuación de Graph se admite solo dentro de la ruta y host originales.

[Entra client credentials](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-client-creds-grant-flow) · [Graph users](https://learn.microsoft.com/en-us/graph/api/user-list?view=graph-rest-1.0) · [Graph Education classes](https://learn.microsoft.com/en-us/graph/api/educationclass-list?view=graph-rest-1.0)

## Sistemas académicos y RR. HH.

Selecciona el tipo de origen correspondiente. Cada proveedor institucional conecta mediante un gateway propio que traduce sus recursos al [contrato JSON](api.md). El adaptador consulta `GET /users` y `GET /courses` según las opciones habilitadas. El prefijo del gateway debe usar HTTPS y el host debe figurar en `$CFG->local_skillupsintegration_hosts` como entrada exacta en minúsculas. Aprovisiona `audience` con ese host y `bearer` con su token. Si también recibe eventos firmados, agrega `webhook` al mismo secreto. Se admiten endpoints con DNS público y TLS válido. El gateway debe responder sin redirección.

Cada perfil tiene su propio destino, mapeo, alcance, historial y activación. Para integraciones exclusivamente entrantes usa el tipo **REST y webhooks** y habilita el método autenticado correspondiente. Es posible usar varios perfiles para sistemas diferentes.

## Mapeo

Una ruta lee hasta seis segmentos de campos JSON. Aplica `trim`, `lower`, `upper` o `collapse` en el orden indicado. Las claves permitidas para usuarios son `id`, `username`, `firstname`, `lastname` y `email`. Para cursos, `id`, `shortname` y `fullname`. Omitir `username` o `shortname` genera un identificador determinista. Por defecto, Google usa `name.givenName`, `name.familyName` y `primaryEmail` en usuarios y `name` en cursos. Microsoft usa `givenName`, `surname`, `mail` y `displayName`. Los gateways y eventos esperan nombres canónicos salvo que el perfil los redefina.

```json
{
  "user": {
    "id": {"path": "employee.id"},
    "firstname": {"path": "employee.givenName", "transforms": ["trim", "collapse"]},
    "email": {"path": "employee.contact.work", "transforms": ["lower"]}
  },
  "course": {
    "fullname": {"path": "label", "transforms": ["trim"]}
  }
}
```

Mantén estables los IDs del origen y la identidad de Moodle. La simulación señala colisiones o cambios de correo, usuario, nombre corto o categoría para revisión institucional.
