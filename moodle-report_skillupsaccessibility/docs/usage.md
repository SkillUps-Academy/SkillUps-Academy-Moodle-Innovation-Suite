# Manual de SkillUps Accessibility Checker

## Escanear y localizar un problema

1. Abre **Informes → SkillUps Accessibility Checker** en un curso autorizado.
2. Pulsa **Encolar auditoría del curso**. Moodle ejecuta el trabajo por cron. Recarga el informe para consultar el estado.
3. Compara fuentes inventariadas, fuentes evaluadas, errores y advertencias. El índice es el porcentaje de fuentes evaluadas sin errores estáticos comprobados.
4. Abre el hallazgo para encontrar su ubicación estructural, fuente Moodle, gravedad y recomendación. La ubicación indica la ocurrencia del elemento en el fragmento, como `img[2]`.
5. Consulta los criterios fuera de evaluación automática y los motivos de las fuentes no analizadas.

## Revisar

En la ficha del hallazgo, cambia el estado a pendiente, corregido o descartado. Indica un responsable autorizado cuando corresponda, registra el motivo y adjunta evidencia opcional. Las decisiones quedan en el historial. El estado de revisión y el resultado del detector se muestran por separado. Un hallazgo todavía detectable cuenta en el informe aunque esté marcado como corregido o descartado. Un escaneo posterior con el hallazgo ausente actualiza el estado pendiente a corregido.

## Comparar períodos y exportar

El informe del curso conserva accesos a las últimas auditorías. En el informe institucional o de categoría, selecciona dos intervalos y aplica los filtros. Cada curso usa su último escaneo completo dentro de cada intervalo. Las reglas recurrentes son las que aparecen más de una vez en la selección. La descarga CSV se limita a resultados autorizados.

[Referencia WCAG 2.2](https://www.w3.org/TR/WCAG22/)
