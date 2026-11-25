<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Cadenas de idioma en español.
 *
 * @package    tool_skillupsmigration
 * @copyright  2026 SkillUps Academy
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['action'] = 'Operación';
$string['activities'] = 'Incluir solo estos ID de actividad';
$string['activitydiff'] = 'Diferencias en actividades';
$string['activitylist'] = 'Actividades y recursos';
$string['actor'] = 'ID del responsable';
$string['allocated'] = 'Destino oculto reservado';
$string['archivebounds'] = 'Se superaron los límites del archivo o del espacio necesario.';
$string['archiveexpired'] = 'El archivo temporal verificado venció. Vuelve a preparar la migración.';
$string['archiveformat'] = 'El paquete no es un respaldo nativo de Moodle admitido.';
$string['archiveio'] = 'No se pudo leer o escribir el respaldo verificado.';
$string['archivemismatch'] = 'La política o los metadatos del respaldo no coinciden con el origen aprobado.';
$string['archivepath'] = 'El paquete contiene una ruta no permitida.';
$string['archivesize'] = 'Tamaño del respaldo';
$string['audit'] = 'Auditoría';
$string['back'] = 'Volver';
$string['backupfailed'] = 'El origen no pudo generar un respaldo nativo utilizable.';
$string['badrequest'] = 'La solicitud de origen o el rango de bytes no es válido.';
$string['blocked'] = 'Bloqueado';
$string['blockedbatch'] = 'Resuelve todos los bloqueos de preparación antes de confirmar la ejecución.';
$string['browseorigin'] = 'Comprobar y explorar {$a}';
$string['busy'] = 'Hay un trabajador de migración ocupado. Reintenta más tarde.';
$string['cancel'] = 'Cancelar trabajo pendiente';
$string['cancelhelp'] = 'Cancela fases pendientes y conserva los destinos ocultos existentes para revisión.';
$string['cancelled'] = 'Cancelado';
$string['categoryid'] = 'Categoría de destino';
$string['checkcount'] = 'Errores ({$a->errors}) y avisos ({$a->warnings})';
$string['complete'] = 'Lote finalizado';
$string['configuration'] = 'Conservar finalización y modo de grupos';
$string['confirmoperation'] = 'Confirmo esta operación';
$string['confirmrequired'] = 'Confirma antes de continuar.';
$string['courses'] = 'ID de curso de origen, nombre corto nuevo por línea';
$string['created'] = 'Migración creada';
$string['credential'] = 'Falta el token de origen en la variable de entorno configurada.';
$string['cronrunning'] = 'El cron de Moodle está procesando este lote. Recarga la página para consultar el avance.';
$string['datedifference'] = 'Diferencias en fechas del curso';
$string['dates'] = 'Conservar fechas del curso';
$string['deferred'] = 'Reintento diferido';
$string['detail'] = 'Detalle';
$string['difference'] = 'Se encontraron diferencias';
$string['done'] = 'Restaurado y verificado';
$string['draft'] = 'Borrador';
$string['duplicate'] = 'Un origen o nombre corto de destino está duplicado o ya se utiliza.';
$string['empty'] = 'No hay registros para esta vista.';
$string['endpoint'] = 'El destino del registro no es válido o incumple la política de red.';
$string['execute'] = 'Confirmar restauración';
$string['executehelp'] = 'Moodle crea cursos nuevos ocultos. Confirma para autorizar la restauración nativa.';
$string['extrasections'] = 'Secciones adicionales de destino';
$string['fileintegrity'] = 'La longitud o la huella no coincide con el respaldo esperado.';
$string['files'] = 'Incluir archivos del curso';
$string['groups'] = 'Grupos y agrupamientos';
$string['history'] = 'Historial de migraciones';
$string['identifier'] = 'ID';
$string['issues'] = 'Incidencia';
$string['job'] = 'Migración';
$string['jobnumber'] = 'Migración {$a}';
$string['missingplugin'] = 'Falta un plugin de destino o carece de soporte de respaldo.';
$string['missingsections'] = 'Secciones faltantes';
$string['name'] = 'Nombre';
$string['nativeprecheck'] = 'Restore de Moodle informó avisos o errores.';
$string['newersource'] = 'La versión Moodle de origen es más reciente que la de destino.';
$string['newjob'] = 'Nueva migración';
$string['next'] = 'Siguiente página';
$string['nothingtoretry'] = 'No hay filas recuperables sin curso de destino.';
$string['olderplugin'] = 'El plugin instalado en destino es anterior al plugin de origen.';
$string['opencourse'] = 'Abrir curso oculto de destino';
$string['operationfailed'] = 'La operación falló. Consulta los registros de la tarea y de la migración.';
$string['origin'] = 'Origen autorizado';
$string['originmismatch'] = 'La identidad del origen cambió o no coincide con el sitio configurado.';
$string['originready'] = 'Origen y servicio de migración verificados. Moodle {$a}.';
$string['planhelp'] = 'Indica hasta diez líneas como ID de curso,nombre corto. La selección de secciones, actividades y usuarios se aplica a planes de un solo curso. Separa los ID con comas. El mapeo usa usuario de origen,usuario de destino,rol de origen,rol de destino.';
$string['planned'] = 'Planificado';
$string['pluginname'] = 'SkillUps Course Migration';
$string['preflight'] = 'Preparando simulación';
$string['privacy:actor'] = 'Cuenta Moodle asociada a la operación.';
$string['privacy:artifact'] = 'Cuenta de origen y metadatos del respaldo privado.';
$string['privacy:audit'] = 'Responsables asociados a operaciones de migración.';
$string['privacy:files'] = 'Respaldos privados de origen para transferencia autorizada.';
$string['privacy:item'] = 'Mapeos explícitos de cuentas para una migración.';
$string['privacy:job'] = 'Responsables y campañas de migración locales.';
$string['privacy:options'] = 'Selección y mapeos de cuenta aportados por el responsable.';
$string['questiontypes'] = 'Tipos de preguntas';
$string['ready'] = 'Verificado para ejecución';
$string['registryempty'] = 'El registro del servidor no tiene orígenes autorizados.';
$string['remoteaccess'] = 'El servicio Moodle rechazó la solicitud. Revisa los permisos y el token.';
$string['remoteconnection'] = 'No se pudo completar la conexión HTTPS autorizada.';
$string['remoteresponse'] = 'El origen devolvió una respuesta inesperada.';
$string['restored'] = 'Restauración nativa finalizada';
$string['restoreerror'] = 'La restauración nativa no terminó correctamente.';
$string['restoreinterrupted'] = 'Una restauración previa pudo haber escrito datos. Revisa el curso oculto antes de continuar.';
$string['restoring'] = 'En restauración nativa';
$string['retry'] = 'Reintentar preparación recuperable';
$string['retryhelp'] = 'Solo las filas bloqueadas sin curso de destino pueden iniciar otra preparación. Los cursos existentes se conservan.';
$string['review'] = 'Simulación completada';
$string['running'] = 'Restaurando cursos autorizados';
$string['sectionlist'] = 'Secciones';
$string['sections'] = 'Incluir solo estos ID de sección';
$string['selection'] = 'Revisa los ID de curso, sección y actividad y los límites del lote.';
$string['servicemissing'] = 'El token no tiene acceso al servicio o protocolo de origen esperado.';
$string['simulate'] = 'Ejecutar simulación';
$string['simulatehelp'] = 'El origen genera un respaldo y el destino verifica su integridad y compatibilidad. Esta fase no crea cursos.';
$string['skillupsmigration:export'] = 'Inventariar y exportar cursos autorizados';
$string['skillupsmigration:manage'] = 'Planificar y ejecutar migraciones';
$string['sourcecat'] = 'Categoría de origen';
$string['sourcechanged'] = 'El inventario de origen cambió. Prepara una migración nueva.';
$string['staff'] = 'Mapeo explícito de usuarios y roles';
$string['stale'] = 'La migración cambió. Recarga y revisa el estado vigente.';
$string['state'] = 'Estado';
$string['supported'] = 'Con respaldo nativo';
$string['targetchanged'] = 'El curso oculto de destino cambió o dejó de estar disponible.';
$string['taskwatch'] = 'Recuperar y limpiar migraciones de cursos';
$string['topology'] = 'La estructura del respaldo difiere del inventario seleccionado.';
$string['transfer'] = 'Transfiriendo paquete';
$string['type'] = 'Tipo';
$string['unavailable'] = 'El curso, respaldo u operación queda fuera de tu ámbito.';
$string['uncertain'] = 'Requiere revisión manual';
$string['unsupported'] = 'Respaldo no disponible';
$string['unsupportedactivity'] = 'Una actividad carece de soporte de respaldo nativo en el origen.';
$string['updated'] = 'Última actualización';
$string['usersmap'] = 'Un mapeo explícito de usuario o rol no es válido o asignable.';
$string['value'] = 'Valor';
$string['verification'] = 'Verificación posterior';
$string['verified'] = 'Coincide el contenido seleccionado';
$string['verify'] = 'Verificando destino';
$string['waiting'] = 'Esperando respaldo de origen';
