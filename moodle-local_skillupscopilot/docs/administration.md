
# Instalación y administración

## Instalación

1. Elige una instancia conforme a la [matriz de validación](validation.md). Haz una copia de seguridad de Moodle y prueba la instalación en un entorno de ensayo.
2. Carga el ZIP en **Administración del sitio → Extensiones → Instalar complementos**, o extrae `skillupscopilot/` en `local/` del directorio web. En instalaciones con raíz web `public/`, instala en `public/local/`.
3. Completa **Notificaciones** o ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de código. El ZIP no requiere Composer.
4. Mantén activo el cron de Moodle. La generación usa tareas ad hoc. La tarea de retención se ejecuta a diario.

## Proveedores y modelos

En `config.php`, antes de cargar `lib/setup.php`, agrega solo los proveedores que la institución permite. Usa variables de entorno inyectadas desde el gestor de secretos de la organización. Inyéctalas tanto en el proceso web como en cron. Los nombres de variables y dominios siguientes son ilustrativos.

```php
$CFG->local_skillupscopilot_providers = [
    'ollama' => ['url' => 'http://127.0.0.1:11434/api/chat', 'allowloopback' => true],
    'openai' => ['tokenenv' => 'SKILLUPS_OPENAI_API_KEY'],
    'compatible' => [
        'url' => 'https://ai.example.edu/v1/chat/completions',
        'tokenenv' => 'SKILLUPS_COMPATIBLE_API_KEY',
    ],
];
```

Ollama usa su API nativa. Solo las direcciones explícitas `127.0.0.1` y `[::1]` de ese conector se consideran locales. Desactiva la funcionalidad cloud de Ollama en el servidor y selecciona un modelo instalado localmente si necesitas un despliegue aislado. Si Ollama corre en otra máquina, usa su URL HTTPS y autoriza el envío externo. OpenAI usa `api.openai.com` y `store: false`. Una API compatible debe admitir `messages`, `temperature`, `max_completion_tokens` y una respuesta de Chat Completions. El token de la API compatible es opcional si el destino institucional no lo requiere. Modelos que no aceptan temperatura configurable pueden devolver un error.

Abre **Administración del sitio → Extensiones → Extensiones locales → SkillUps AI Copilot → Configuración de proveedores y cuotas**. Habilita cada conector y escribe el identificador exacto del modelo. Configura temperatura y máximo de tokens de salida. Habilita **Permitir proveedores externos** únicamente después de revisar la política, residencia, retención y condiciones de tratamiento del proveedor. El tráfico respeta las restricciones HTTP de Moodle. Si activas explícitamente la opción de despliegue `allowloopback`, Copilot permite solo la URL literal de Ollama local configurada para `/api/chat`, sin usar proxy ni redirecciones y sin modificar la configuración HTTP del resto de Moodle.

El panel indica si existe una configuración utilizable y muestra el último resultado registrado. El intento de generación es la comprobación real de disponibilidad. Un HTTP 429 admite un reintento automático tras un minuto. Otros errores informan al usuario y permiten un nuevo envío. Un fallo ambiguo del transporte no se reenvía automáticamente.

## Cuotas, retención y roles

Las cuotas diarias cuentan solicitudes lógicas aceptadas según el día UTC. Los límites predeterminados son 30 por usuario, 500 por curso y 2000 por instalación. Un usuario está sujeto a todos los límites aplicables. Usa valores positivos para imponer un límite o cero para dejarlo ilimitado. El panel permite establecer un límite particular por ID de curso y usar −1 para restaurar el valor predeterminado. Los reintentos automáticos se imputan a la misma solicitud. Los totales de tokens usan los datos informados por el proveedor.

Los turnos se conservan 30 días y los registros de consumo 90 días de forma predeterminada, con un intervalo configurable de 1 a 365 días. Al borrar una conversación desde el chat se conservan los registros de cuota hasta su vencimiento o eliminación vía la API de privacidad.

| Capacidad | Propósito |
| --- | --- |
| `local/skillupscopilot:use` | Abrir el chat de un curso elegible |
| `local/skillupscopilot:author` | Pedir borradores de actividades y preguntas |
| `local/skillupscopilot:managesources` | Curar documentos de un curso |
| `local/skillupscopilot:sitechat` | Usar el asistente del sitio |
| `local/skillupscopilot:manage` | Configurar conectores, ver consumo y administrar cuotas |

## Licencias

Community incluye todos los flujos de chat, RAG, administración básica y cuotas. Professional y Enterprise pueden habilitar la exportación CSV agregada mediante la autorización `aggregate_export`. El panel muestra el identificador de instalación, el nivel y la fecha de vencimiento. La renovación se realiza sustituyendo el documento firmado con la nueva emisión. La revocación se aplica configurando el ID revocado en cada instalación y la transferencia requiere emitir una licencia con los identificadores autorizados actualizados. Consulta el [contrato de licencia](licensing.md).

## Desinstalación

Usa **Vista general de extensiones → Desinstalar** y retira el directorio. Esto borra las tablas de Copilot, sus fuentes y sus trabajos pendientes gestionados por Moodle. Los registros de auditoría conservados en el sistema de logs y las copias ya transferidas al proveedor se rigen por sus políticas respectivas.
