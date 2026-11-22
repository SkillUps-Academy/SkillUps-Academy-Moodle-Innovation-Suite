# Instalación y administración

## Instalar

1. Descarga el ZIP de `releases/skillupsaigrading` y verifica el SHA-256. En **Administración del sitio → Plugins → Instalar plugins**, elige el archivo ZIP. Para instalación por archivos, la carpeta `skillupsaigrading` va en `local/` de la raíz pública de Moodle.
2. Completa **Notificaciones** o ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle.
3. Mantén el cron de Moodle activo para solicitudes de IA y limpieza. Las tareas locales usan los plugins nativos Assignment y Rubric.
4. En **Administración del sitio → Plugins → Plugins locales → SkillUps AI Grading Assistant** revisa retención (por defecto 30 días) y cuotas (50 revisiones locales o IA por tarea y 24 horas).

Las capacidades `local/skillupsaigrading:view`, `:review` y `:useai` se asignan inicialmente a profesor, profesor sin permiso de edición y gestor. Se necesita también `mod/assign:grade`; puedes limitar `:useai` por curso o actividad. Los correctores asignados en el flujo de calificación y los grupos separados mantienen su alcance.

Para desinstalar, elimina el plugin desde Plugins o mediante `php admin/cli/uninstall_plugins.php --plugins=local_skillupsaigrading --run`, retira su carpeta y ejecuta la actualización. Los derivados y decisiones del asistente se eliminan. Haz una copia de seguridad cuando necesitas conservarlos. Las entregas, rúbricas y calificaciones pertenecen a Moodle.

## Procesamiento de PDF

Configura una ruta absoluta a Poppler si admites PDF seleccionable. El proceso admite hasta 8 MB, lee las primeras 100 páginas, limita la ejecución y elimina los temporales. El OCR está fuera del alcance. PHP necesita Zip y DOM para documentos Office.

~~~php
$CFG->local_skillupsaigrading_pdftotext = '/usr/bin/pdftotext';
~~~

## Proveedores opcionales

1. En `config.php`, define los destinos y nombres de variables de entorno. Establece los valores de las variables en el proceso PHP web y en el cron.
2. En el panel, activa la IA y al menos un conector. Indica el ID del modelo y una versión o revisión operativa verificable, por ejemplo un digest de Ollama o un identificador fechado. Temperatura 0 a 2. Salida 64 a 4096 tokens.
3. Activa proveedores externos cuando la política institucional permite transferencias. Registra el tratamiento de datos y el alcance de cada proveedor. El docente autoriza cada envío. Al desactivar IA, las tareas en cola fallan al revalidar, eliminan el extracto y quedan visibles en el historial. Una llamada ya iniciada puede terminar.
4. Programa cron, usa una entrega sintética y verifica que los resultados muestran las citas esperadas, versión y permisos.

~~~php
$CFG->local_skillupsaigrading_providers = [
    'ollama' => [
        'url' => 'http://127.0.0.1:11434/api/chat',
        'allowloopback' => true,
    ],
    'openai' => ['tokenenv' => 'SKILLUPS_GRADING_OPENAI_KEY'],
    'compatible' => [
        'url' => 'https://ai.example.edu/v1/chat/completions',
        'tokenenv' => 'SKILLUPS_GRADING_COMPATIBLE_KEY',
    ],
];
~~~

El proceso web y cron deben alcanzar el destino. El modelo compatible debe aceptar `max_completion_tokens` y devolver el formato Chat Completions. Ollama usa `/api/chat`. El destino de OpenAI es `https://api.openai.com/v1/chat/completions`. Los destinos fuera de loopback requieren HTTPS, siguen las reglas de seguridad cURL de Moodle y se consideran transferencias externas al Moodle incluso dentro de una red institucional. En contenedores, loopback es la interfaz del propio contenedor. Para excluir una clave, retira o rota la variable de entorno y reinicia los procesos PHP correspondientes.

## Licencias opcionales

Community conserva todo el recorrido docente, la IA configurada y el panel. Professional y Enterprise pueden habilitar el CSV agregado con el derecho `aggregate_export`. El adaptador offline verifica Ed25519 con Sodium. La configuración requiere la clave pública y un blob `base64(JSON).base64(firma)`. Los campos firmados son `component: "local_skillupsaigrading"`, `version: 1`, `id`, `tier`, `issued`, `expires`, `sites` (SHA-256 de cada URL de instalación, sin barra final) y `features`. La validez máxima es 366 días y el panel muestra el identificador de instalación y estado.

~~~php
$CFG->local_skillupsaigrading_entitlement = 'BASE64_PAYLOAD.BASE64_SIGNATURE';
$CFG->local_skillupsaigrading_licensepublickey = 'BASE64_PUBLIC_KEY';
$CFG->local_skillupsaigrading_gracedays = 7;
$CFG->local_skillupsaigrading_revoked = ['previous-entitlement-id'];
~~~

Gracia de 0 a 30 días. Para renovar, transferir instalaciones o revocar, actualiza la licencia firmada y la lista de revocación distribuida por el emisor. Se reutiliza la verificación criptográfica dentro de la solicitud. El plugin no contacta un servicio externo de licencias.
