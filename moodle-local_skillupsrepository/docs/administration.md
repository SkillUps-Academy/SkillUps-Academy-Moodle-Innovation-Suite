# Instalación y administración

Verifica el archivo con `SHA256SUMS`. En Moodle, abre **Administración del sitio → Plugins → Instalar plugins** y selecciona el ZIP. Para instalación manual, extrae `skillupsrepository/` en el directorio `local/` público de la instalación. Las distribuciones con raíz web separada usan `public/local/skillupsrepository`. Ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz de Moodle o abre **Notificaciones**.

Las actualizaciones conservan la carpeta y se aplican con el actualizador de Moodle después de respaldar la base de datos y `moodledata`. Esta versión instala el esquema inicial con XMLDB. La desinstalación elimina los registros y los archivos del componente. Las actividades Archivo y URL que ya existen en cursos se administran como actividades nativas.

## Capacidades

| Capacidad `local/skillupsrepository:` | Aplicación |
|---|---|
| `view` | Consultar versiones institucionales aprobadas de la categoría. |
| `download` | Descargar o abrir enlaces cuando la versión institucional autoriza la entrega. |
| `contribute` | Añadir recursos, crear versiones propias y enviarlas a revisión. |
| `moderate` | Consultar, editar, aprobar, rechazar, cambiar visibilidad y archivar dentro de la categoría. |
| `reuse` | Copiar versiones aprobadas con permiso de redistribución. Moodle exige además edición de actividades y alta del módulo en el curso de destino. |

Asigna los roles a nivel de categoría o superior según el alcance previsto. Un rol de profesor asignado solo dentro de un curso carece de alcance de categoría. Un gestor de Moodle recibe las capacidades de biblioteca mediante su arquetipo. El autor mantiene acceso a sus propios recursos y el moderador al material de su categoría.

## Publicación pública

La configuración **Habilitar publicación pública** permite abrir versiones públicas aprobadas en categorías Moodle visibles a visitantes anónimos. Se respeta la opción del sitio que obliga a iniciar sesión. El límite de archivo efectivo es el menor de los límites aplicables. La biblioteca usa el almacenamiento y los repositorios File API del sitio.

## Distribución

El repositorio institucional de código es el monorepo de SkillUps Academy. El paquete tiene raíz `skillupsrepository/`. El envío y la aprobación en Moodle Marketplace son procesos independientes. [Moodle Marketplace](https://marketplace.moodle.com/)
