# Operación del Integration Hub

1. Agrega el conector con su administración de credenciales ya configurada. Limita las entidades y, para cursos, elige la categoría de destino.
2. En un proveedor de consulta, pulsa **Ejecutar simulación**. Para REST o webhooks, localiza la ejecución aceptada en el historial. El cron procesa consultas por páginas.
3. Revisa las filas y sus campos. Los filtros muestran cambios planificados, conflictos, filas fallidas, coincidencias y cambios aplicados. Una fila inválida o duplicada queda excluida de la aplicación.
4. Marca la confirmación y pulsa **Aprobar y aplicar**. En ese momento queda registrada la identidad del aprobador. Moodle procesa hasta 50 filas por tarea. Si un usuario, curso, permiso o conector cambia después de la simulación, se vuelve a validar y puede quedar en conflicto o quedar sustituido.
5. Consulta el historial, auditoría y último contacto del conector. Ante fallos transitorios se conserva el punto de progreso y el sistema reintenta con espera creciente. Si falla definitivamente, un operador habilitado puede reintentar la simulación original. Para reintentar una aplicación se requiere el mismo aprobador y una simulación vigente. Una ejecución parcial se corrige con una nueva simulación sobre el origen corregido.

## Conflictos

**Conservar ediciones de Moodle** protege cambios locales en los campos gestionados. **Preferir los valores del origen** habilita una propuesta nueva sobre los campos gestionados en conflicto. Ambas políticas comprueban de nuevo cada fila al aplicar. La sincronización evita apropiarse de usuarios o cursos existentes y exige igualdad de los identificadores protegidos. Si existe un nombre de cuenta, correo o nombre corto ocupado, usa el sistema de origen y los procedimientos de identidad de la institución para resolver la discrepancia. Desactivar un conector o cambiar su versión invalida sus planes pendientes.

La importación crea usuarios con autenticación `nologin` y cursos ocultos. La institución habilita la autenticación y publicación en Moodle después de su revisión. Las actualizaciones gestionan nombre y apellido de las cuentas vinculadas y nombre completo de los cursos vinculados. Contraseñas, roles, matrículas, correos de cuentas existentes, categorías y visibilidad se administran en Moodle. Las cuentas y cursos ausentes de la fuente no desencadenan bajas.

## Capacidad y operación de terceros

El límite es 100 filas por página, 10.000 filas o 150 páginas por simulación y un límite de 20 simulaciones en cola por revisión de conector. La recepción se pausa al alcanzar 20 simulaciones listas para revisión. Usa gateways con un ámbito de datos acotado o divide entradas REST y webhook en lotes. El tiempo de respuesta que muestra el panel corresponde al último intento de lectura externo y depende de OAuth, redes y proveedor. Los conectores entrantes muestran el último evento admitido.

[Ejemplo de panel](screenshots/dashboard.png) · [Ejemplo de simulación](screenshots/preview.png)
