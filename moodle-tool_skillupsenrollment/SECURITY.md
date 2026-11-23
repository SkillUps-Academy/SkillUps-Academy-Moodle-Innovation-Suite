# Seguridad y privacidad

Comunica problemas de seguridad por un canal privado con los administradores del repositorio. Proporciona versión y pasos reproducibles con datos sintéticos.

Las tareas revalidan la identidad del operador, la visibilidad del curso, los permisos de matriculación del método, los roles asignables y el ámbito de grupos. La interfaz exige sesión y clave de sesión para autorizar, reintentar, cancelar y exportar. El historial de otros operadores requiere la capacidad de gestión y las filas siguen sujetas al acceso al curso, el método y el participante.

El importador aplica límites de tamaño y expansión para XLSX y admite valores literales. Elimina el archivo temporal y su borrador tras el procesamiento. El registro conserva identificadores nativos y evidencia acotada de la operación. Una fila inválida conserva el número de línea y el motivo. El plugin opera localmente.

Privacy API exporta y elimina referencias al operador y al matriculado. La eliminación cancela solicitudes pendientes de estas identidades y anonimiza su registro, preservando las matrículas de Moodle. El subsistema de matriculación y el almacén de eventos del sitio aplican sus propias responsabilidades de privacidad y retención.
