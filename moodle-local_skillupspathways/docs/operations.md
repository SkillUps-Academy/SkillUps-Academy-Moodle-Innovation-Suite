# Diseño y operación

1. Abre el diseñador del curso de entrada. Añade un paso por destino y copia el valor `id` de la URL de curso o de actividad. Para un curso completo deja el ID del módulo en 0.
2. Selecciona uno o más criterios. La condición de finalización utiliza la configuración nativa. Un umbral usa el porcentaje normalizado de la nota numérica final del alumno, según el mínimo y el máximo del ítem. Una competencia requiere dominio registrado y una vinculación vigente con su curso. Cuando un nodo tiene varios criterios debe satisfacerlos todos.
3. Selecciona los prerrequisitos de cada paso. AND requiere todos. OR desbloquea con cualquiera. Para diseñar una alternativa, marca sus ramas como opcionales y coloca un paso obligatorio común con prerrequisito OR. Se admiten 60 pasos y 150 conexiones por itinerario.
4. Publica. Cada estudiante se vincula a la versión disponible al iniciar. Para cambiarla después, confirma el traslado individual en el informe. El historial previo se conserva y el avance se vuelve a calcular con las condiciones de la nueva versión.
5. El estudiante puede abrir los destinos recomendados y actualizar el avance. Los cursos de destino requieren matrícula activa y las actividades quedan sujetas a disponibilidad, grupos y visibilidad de Moodle.

Los informes identifican a participantes que iniciaron el itinerario y muestran el último estado registrado. El panel individual comprueba la evidencia actual al cargar y guarda transiciones al actualizar, iniciar, migrar o en cron. El cron revisa hasta 50 registros cada cinco minutos, empezando por las verificaciones más antiguas. Una calificación oculta, pendiente de recálculo, de escala o ausente no cumple un umbral. Los totales se omiten cuando un elemento de calificación del curso está oculto.

Los destinos borrados o restringidos se muestran como pasos restringidos. El gestor puede corregir el borrador y publicar otra versión. Deshabilitar un itinerario suspende el acceso de participantes. Sus historiales se conservan y se incluyen en la gestión de privacidad.
