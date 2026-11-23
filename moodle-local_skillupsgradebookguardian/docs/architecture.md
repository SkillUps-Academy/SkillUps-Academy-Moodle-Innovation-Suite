# Arquitectura

El plugin local agrega un enlace en la navegación del curso. El acceso requiere las capacidades del componente y de Gradebook y de actividades ocultas. El curso se selecciona antes de abrir el panel y todos los registros se consultan por curso. Moodle gestiona la sesión y cada operación usa POST con sesskey.

1. Construir una instantánea de categorías, elementos y actividades mediante Gradebook y get_fast_modinfo. No leer notas individuales, no llamar funciones de regrade y evitar los getters que pueden persistir normalizaciones de fórmulas.
2. Evaluar un catálogo de reglas con evidencias estructurales y dependencias directas e indirectas. Guardar hallazgos, totales y autoría en un registro propio. Ofrecer historial paginado, filtros y descarga.
3. Limitar las reparaciones aplicables a la visibilidad de un elemento de actividad identificado de manera inequívoca. Obtener la configuración actual, validar alcance y elegibilidad y guardar una simulación reproducible.
4. Exigir aprobación administrativa y comprobación de huella vigente. Ejecutar grade_update con solo hidden y sin notas, bajo cerrojo por curso y transacción. Verificar el estado resultante y guardar auditoría. Exigir otra confirmación y un estado vigente para recuperar la visibilidad anterior.
5. Derivar las inconsistencias de pesos, agregaciones, fórmulas y relaciones al editor nativo o al equipo técnico según el caso. El simulador compara configuración y alcance; no proyecta resultados académicos.
6. Aplicar retención, anonimización y exportación de identidades de operadores mediante las tareas y Privacy API de Moodle.

Fuentes oficiales: [Gradebook API](https://docs.moodle.org/dev/Gradebook_API), [grade_item PHP API](https://phpdoc.moodledev.io/5.0/d0/d8b/classgrade__item.html), [Navigation API](https://moodledev.io/docs/5.3/apis/core/navigation) y [Privacy API](https://moodledev.io/docs/5.3/apis/subsystems/privacy).
