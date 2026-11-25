# Instalación y conectividad

Verifica el ZIP con SHA-256 e instálalo en **Administración del sitio → Plugins → Instalar plugins** de ambas instancias. La instalación manual extrae `skillupsmigration/` en `admin/tool/` de la raíz pública. Ejecuta el actualizador Moodle con `php admin/cli/upgrade.php --non-interactive` o desde **Notificaciones**. Las actualizaciones conservan la carpeta del componente. Respaldar ambas instalaciones permite conservar la configuración y los registros.

## Origen

1. Habilita servicios web y el protocolo REST.
2. Habilita el servicio **SkillUps Course Migration origin**. Su nombre corto es `tool_skillupsmigration_origin`.
3. Autoriza una cuenta de servicio activa. Otorga `webservice/rest:use` en sistema, y `tool/skillupsmigration:export` más `moodle/backup:backupcourse` solo en los cursos que corresponden. El acceso a cursos ocultos requiere la capacidad nativa correspondiente.
4. Crea un token para esa cuenta y ese servicio desde Moodle. Configura vencimiento y restricción a la dirección del destino donde sea posible.
5. Activa cron para ejecutar tareas Adhoc. No hace falta habilitar carga o descarga genérica de archivos en el servicio. La transferencia usa la operación de respaldo acotada del plugin.

## Destino

Entrega el token a PHP web y CLI mediante la variable de entorno o el gestor de secretos institucional. Define la entrada siguiente en `config.php` antes de cargar `lib/setup.php`. Usa la URL canónica informada por el Moodle de origen y reemplaza el marcador por la IPv4 autorizada.

```php
$CFG->tool_skillupsmigration_origins = [
    'central' => [
        'label' => 'Moodle central',
        'url' => 'https://origen.example.edu/moodle',
        'ip' => '<IPV4_AUTORIZADA>',
        'tokenenv' => 'SKILLUPS_MIGRATION_SOURCE_TOKEN',
    ],
];
```

Si el origen usa IPv4 privada RFC 1918 y está en la red institucional autorizada, añade `'allowinternal' => true` a esa entrada y aplica la ACL de salida de esa red. Para una CA interna, añade `'cafile' => '/ruta/controlada/ca.pem'` o integra la CA con el almacén de confianza del servidor. El certificado debe ser válido para el nombre canónico.

El destino exige `tool/skillupsmigration:manage` en sistema y `moodle/course:create` más `moodle/restore:restorecourse` en la categoría correspondiente. El mapeo de usuarios requiere autorización de matrícula manual y roles asignables en el curso nuevo. Configura cron y acceso compartido a `moodledata` entre nodos de ejecución. El tamaño máximo por MBZ se configura con `$CFG->tool_skillupsmigration_maxbytes`, con valor predeterminado de 128 MiB y tope de 1 GiB.

## Verificar antes de abrir un lote

En **SkillUps Course Migration**, abre **Comprobar y explorar**. El plugin valida TLS, identidad canónica, versión y presencia de sus métodos en el servicio obtenido con el token. El inventario confirma los cursos que la cuenta puede exportar.

La desinstalación retira las tablas y archivos File API del componente. Los cursos ya asignados al destino son cursos Moodle normales. El directorio privado `tool_skillupsmigration` bajo `moodledata` puede retirarse por separado cuando la institución haya cerrado las operaciones pendientes.
