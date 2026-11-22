# Seguridad y privacidad

Comunica incidentes de seguridad mediante un canal privado con el equipo administrador del repositorio. Facilita la versión y una reproducción con datos sintéticos.

El informe requiere inicio de sesión y la capacidad de sitio `report/skillupsmonitor:view`. Confirmar la revisión de un incidente exige además `report/skillupsmonitor:manage` y una petición POST con clave de sesión. Los detalles de tareas y eventos se someten a las capacidades nativas `moodle/site:config` y `report/log:view`. El monitor conserva muestras agregadas, categorías y fechas. Los identificadores de operadores se usan para atribuir revisiones y entregar alertas. El proveedor de privacidad de Moodle declara, exporta y elimina esas referencias. Los mensajes entregados y el registro de auditoría de Moodle siguen las políticas de sus subsistemas.

El acceso automatizado es opcional, utiliza HTTPS y un token dedicado. Guarda el resumen SHA-256 en el entorno privado del proceso PHP y el token en el gestor de secretos del cliente. Limita las dos rutas a la red de monitoreo mediante el proxy o cortafuegos. [Configuración de la integración](docs/integrations.md)
