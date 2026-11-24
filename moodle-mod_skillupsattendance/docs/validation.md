# Validación de SkillUps Attendance Pro

Versión **0.9.0-beta.1**. Laboratorio aislado con PHP 8.3.37, Linux, depuración de desarrollador y datos sintéticos.

| Moodle | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 8 pruebas, 59 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 8 pruebas, 59 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 8 pruebas, 59 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 8 pruebas, 59 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 8 pruebas, 59 aserciones |

La suite verificó series y cambio de horario de verano, calendario nativo y cancelación, vigencia de QR, límites de intentos en código, entrada y salida idempotentes, cierre de la nómina activa, justificaciones y archivos, eliminación mediante Privacy API, informes de curso y separación por grupos, publicación explícita de calificaciones, duplicación nativa y avisos sin duplicados.

## Interfaz y flujos

- Behat en Moodle 5.3 ejecutó dos escenarios y veinte pasos. Incluyeron creación de sesiones y consulta del informe con permisos de estudiante.
- Chrome decodificó el PNG del QR, registró entrada y salida y confirmó que otro grupo carecía de acceso a la sesión.
- Chrome subió evidencia con el selector de Moodle. La cuenta de otro grupo recibió la respuesta nativa de archivo no encontrado y quedó fuera de la descarga. El revisor descargó el archivo y aprobó la justificación.
- Se descargaron CSV y XLSX con los formatos nativos y se comprobó la publicación solo tras marcar la autorización. Los identificadores de las capturas son sintéticos.

## Empaquetado y ciclo de vida

- El validador de ZIP de Moodle aceptó el componente de actividad. En Moodle 5.3 con PostgreSQL se comprobó la desinstalación, la preservación del curso y la reinstalación desde el ZIP mediante la CLI nativa.
- En Moodle 5.3 con MariaDB una copia MBZ con datos de usuario se restauró conservando el estudiante, la decisión aprobada y la huella de los bytes de la evidencia. La eliminación nativa de la actividad restaurada retiró marcas, justificaciones y archivos.
- Se ejecutó la tarea programada desde la CLI y terminó correctamente. Los 39 archivos PHP superaron la verificación de sintaxis y Moodle CodeSniffer.

## Reproducción

Inicializa PHPUnit desde la raíz de Moodle mediante `php admin/tool/phpunit/cli/init.php` en Moodle 5.0 o `php public/admin/tool/phpunit/cli/init.php` cuando el directorio web es `public`. Ejecuta:

```sh
vendor/bin/phpunit --testsuite mod_skillupsattendance_testsuite
```

Inicializa Behat con su CLI y usa la ruta de configuración que imprime Moodle:

```sh
vendor/bin/behat --config /path/to/behat.yml --tags=@mod_skillupsattendance
```

## Alcance

La cobertura se limita a las versiones, motores y PHP identificados. Quedan fuera de la matriz MySQL independiente, versiones posteriores sin verificar, correo saliente real y cargas productivas. Esta primera versión carece de una versión anterior publicada para probar una migración de esquema. El envío a Moodle Marketplace está pendiente.
