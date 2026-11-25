# Seguridad

Usa los [avisos privados del repositorio](https://github.com/SkillUps-Academy/SkillUps-Academy-Moodle-Innovation-Suite/security/advisories/new) cuando estén habilitados. Incluye versión y datos sintéticos.

El registro de orígenes se administra en la configuración del servidor. Cada URL usa HTTPS y una IPv4 autorizada, con verificación TLS, sin seguir redirecciones y sin proxies de entorno. El cliente transmite el token en el cuerpo de un POST a la ruta REST fija. Los tokens se leen del entorno de ejecución de PHP y quedan fuera de los registros del plugin. El servicio de origen comprueba la cuenta y sus capacidades en cada curso.

El destino exige confirmación explícita, usa cursos ocultos nuevos y verifica hashes y límites antes de abrir el MBZ. El autor puede cancelar trabajo pendiente. Una interrupción dentro de Restore requiere revisión manual del destino asignado.
