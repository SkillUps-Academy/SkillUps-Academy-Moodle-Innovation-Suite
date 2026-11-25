# Instalación y administración

Verifica SHA-256 y selecciona el ZIP en **Administración del sitio → Plugins → Instalar plugins**. Para instalación manual, extrae `skillupscommunication/` en el directorio `local/` público de Moodle. Las distribuciones con raíz web separada usan `public/local/skillupscommunication`. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de código o abre **Notificaciones**.

Las actualizaciones conservan la ruta del componente y usan XMLDB y el actualizador de Moodle. Haz una copia de seguridad antes de actualizar. La desinstalación elimina los datos propios del plugin, incluidos el historial de campaña y la cola de destinatarios. Las notificaciones ya registradas permanecen bajo el control del proveedor de mensajería de Moodle.

## Permisos

| Capacidad `local/skillupscommunication:` | Permiso |
|---|---|
| `view` | Consultar campañas, resultados e historial del ámbito. |
| `compose` | Redactar, previsualizar, enviar borradores a revisión y cancelar pendientes. |
| `approve` | Aprobar, devolver a borrador, cancelar y reintentar fallos recuperables. |
| `configure` | Gestionar plantillas, recintos, programas y conjuntos personalizados. |

El ámbito puede ser el sistema Moodle o una categoría. Asigna `view` junto a las acciones que correspondan. Para un redactor usa `view` y `compose`. Para un coordinador revisor otorga `view` y `approve` en su categoría. El arquetipo *manager* tiene las cuatro capacidades. Una institución que requiera doble control debe distribuir redacción y aprobación entre roles distintos. Una cuenta que recibe ambas capacidades puede efectuar ambas operaciones.

## Cron, canales y límites

Configura el máximo de destinatarios por campaña y el tamaño de lote. La aprobación crea una tarea Adhoc. El cron de Moodle ejecuta los envíos y la tarea periódica recupera campañas cuya programación se interrumpió. Para el correo, revisa los procesadores de mensajería Moodle y las preferencias de notificación de los tres proveedores del componente. Las opciones del sitio y del usuario determinan si Moodle solicita los procesadores configurados.

El centro no configura SMTP propio. Las integraciones externas requieren una clase explícitamente autorizada en ajustes del sitio. Revisa [el contrato de conectores](connectors.md) antes de habilitar una.

## Distribución

El código está en el monorepo de la suite. El ZIP tiene raíz `skillupscommunication/`. La publicación en el repositorio es independiente de la revisión del [Moodle Marketplace](https://marketplace.moodle.com/).
