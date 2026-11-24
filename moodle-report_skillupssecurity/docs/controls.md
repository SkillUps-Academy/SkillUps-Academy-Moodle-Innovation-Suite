# Controles y evidencia

Los hallazgos confirmados describen un hecho registrado en Moodle. Las configuraciones de riesgo requieren revisión institucional. Las recomendaciones orientan la revisión de políticas. La prioridad es una clasificación de atención, sin cálculo de probabilidad ni declaración de seguridad.

| ID | Clase / prioridad | Condición y fuente | Acción |
| --- | --- | --- | --- |
| `https` | Riesgo, alta | Esquema de la URL canónica de Moodle distinto de HTTPS. | Revisar terminación TLS y URL con infraestructura. |
| `debug` | Riesgo, alta | Nivel de depuración mayor que cero y visualización activa. | Usar diagnóstico privado y revisar producción. |
| `guest` | Riesgo, media | Botón de invitado o ingreso automático habilitado. | Confirmar política y revisar acceso a cursos. |
| `registration` | Riesgo, media | Método de autorregistro configurado. | Confirmar aprobación y supervisión. |
| `passwordpolicy` | Riesgo, media | Política de contraseña local deshabilitada. | Revisar política y autoridad de autenticación. |
| `login` | Recomendación, baja | La política general del sitio permite páginas sin autenticación. | Revisar el contenido que debe ser público. |
| `inactive` | Confirmado, baja | Cuenta activa con último acceso anterior al umbral, o creada antes del umbral sin acceso registrado. | Confirmar necesidades del titular. |
| `defaultrole` | Riesgo, alta | Un rol predeterminado admite una capacidad administrativa de la lista en sistema o portada. | Revisar mínimo privilegio. |
| `unusualscope` | Riesgo, media | Arquetipo docente, estudiante o invitado asignado explícitamente en sistema. | Confirmar justificación institucional y ámbito. |
| `privilegedinactive` | Riesgo, alta | Cuenta privilegiada vigente por encima del umbral de inactividad. | Revisar o retirar privilegios obsoletos desde Moodle. |
| `suspendedprivilege` | Confirmado, media | Cuenta suspendida con designación administrativa o permisos definidos por el rol que conserva. | Revisar asignaciones antes de restablecer la cuenta. |

## Alcance

Los controles se evalúan a diario y bajo demanda. Se consideran administradores de sitio y asignaciones explícitas en sistema, categoría y curso. Los roles candidatos provienen de Access API. Los permisos de cuenta se contrastan con `has_capability` en ese contexto. Para cuentas suspendidas, el inventario describe los permisos definidos por el rol. La matriz de roles muestra las definiciones en contexto de sistema. Los contextos de actividad o usuario y las integraciones de identidad externa quedan fuera del inventario.

La lista de capacidades incluye configuración del sitio, gestión, asignación y override de roles, acceso como otro usuario, eliminación de usuario o curso y creación de tokens de servicios web. Se aplican límites de 100–5000 cuentas inactivas y 100–10 000 asignaciones candidatas. El motor usa orden por ID y marca el muestreo parcial cuando encuentra más registros. Un límite bajo puede omitir cuentas o asignaciones más recientes.

La inactividad usa `lastaccess` de Moodle. Este dato refleja accesos registrados y puede no representar actividad por API o integraciones externas. La URL HTTPS se revisa como configuración declarada. La auditoría carece de sondas de red, acceso al sistema operativo y pruebas intrusivas.
