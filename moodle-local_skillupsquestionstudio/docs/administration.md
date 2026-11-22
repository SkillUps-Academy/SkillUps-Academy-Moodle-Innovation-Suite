# Instalar y configurar Question Studio

1. Prueba la instalación en una versión de Moodle incluida en el [informe de compatibilidad](validation.md).
2. Sube el ZIP desde **Administración del sitio → Extensiones → Instalar complementos**. Para instalación manual, extrae `skillupsquestionstudio/` en `local/` dentro de la raíz web de Moodle. Si la raíz web es `public/`, utiliza `public/local/`.
3. Completa **Notificaciones** o ejecuta `php admin/cli/upgrade.php --non-interactive` desde la raíz del código Moodle. El ZIP incluye lo necesario para operar sin Composer.
4. Ejecuta el cron habitual de Moodle. El procesamiento documental y la generación funcionan mediante tareas ad hoc.
5. Busca **SkillUps Question Studio** en la navegación del curso.

## Procesamiento PDF

TXT, DOCX y PPTX usan extensiones PHP de Moodle. Para PDF, instala y mantén actualizado Poppler en el servidor de cron y configura la ruta absoluta del ejecutable de confianza antes de cargar el bootstrap de Moodle.

```php
$CFG->local_skillupsquestionstudio_pdftotext = '/usr/bin/pdftotext';
```

En Windows, usa la ruta a `pdftotext.exe`. El binario debe ser accesible al usuario del proceso y PHP debe permitir `proc_open`. La extracción PDF admite texto seleccionable en las primeras 100 páginas. Los documentos escaneados que solo contienen imágenes requieren OCR previo. El límite de subida es 8 MiB. Se retienen hasta 80 000 caracteres extraídos y se eliminan los archivos de trabajo tras el procesamiento.

## Proveedores y roles

La generación local y la edición manual están disponibles en Community. Para activar IA, configura un proveedor de generación de texto en el subsistema IA de Moodle y habilita el ajuste **Habilitar generación con IA de Moodle** dentro de Question Studio. Revisa los contratos, registros y ciclos de retención del proveedor. El docente debe aceptar la política IA de Moodle y autorizar cada transferencia. La integración admite cualquier proveedor válido que Moodle configure para la acción de generación de texto y delega sus credenciales a Moodle.

| Capacidad | Uso |
| --- | --- |
| `local/skillupsquestionstudio:manage` | Abrir proyectos del curso, cargar materiales y editar borradores |
| `local/skillupsquestionstudio:review` | Aprobar o reabrir la revisión vigente |
| `local/skillupsquestionstudio:publish` | Exportar XML e importar a un banco autorizado |
| `local/skillupsquestionstudio:useai` | Solicitar borradores del proveedor IA configurado |

Por defecto se conceden a docentes editores y gestores. Para separar labores, asigna a los revisores también el acceso al proyecto y quita `review` al rol autor. La misma cuenta docente puede aprobar cuando su rol tiene ambas capacidades. La publicación exige también `moodle/question:add` en el módulo de banco o cuestionario. Las categorías del banco deben existir en el mismo curso.

La conservación predeterminada del texto es de 90 días. El borrador de carga se elimina al encolar el documento. La copia interna se elimina tras la extracción o al recuperar un trabajo vencido. Los proyectos, revisiones, variantes y preguntas del banco tienen ciclos de conservación separados.

## Licencias y desinstalación

Community opera sin SkillUps Cloud. Los niveles Professional y Enterprise admiten exportación de métricas agregadas bajo licencia Ed25519 offline. Configura `$CFG->local_skillupsquestionstudio_entitlement` con `base64(JSON).base64(firma)` y `$CFG->local_skillupsquestionstudio_licensepublickey` con la clave pública Base64. El JSON firmado contiene `version: 1`, `id`, `tier`, `issued`, `expires`, `sites` y `features`. El único derecho adicional actual es `aggregate_export`. Los campos temporales son segundos Unix y el período máximo es 366 días. Opcionalmente configura `$CFG->local_skillupsquestionstudio_gracedays` (0 a 30) y `$CFG->local_skillupsquestionstudio_revoked` (IDs revocados). Para renovar o transferir, solicita una emisión actualizada con el identificador que muestra el panel. La validez se verifica localmente y el resultado firmado se reutiliza durante la solicitud.

Usa **Vista general de extensiones → Desinstalar** y elimina el directorio del plugin. Las preguntas ya publicadas permanecen bajo la administración de Moodle.
