# Validación de SkillUps Communication Center

Los laboratorios usaron cuentas sintéticas y depuración de desarrollo. La mensajería por correo se comprobó mediante la API de Moodle en PHPUnit. Los ensayos no usaron un buzón ni un SMTP de producción.

| Moodle | PHP | Base de datos | PHPUnit |
|---|---|---|---|
| 5.0.12 | 8.3.37 | MariaDB 11.4.10 | 11 pruebas, 38 aserciones |
| 5.3.1 | 8.3.37 | MariaDB 11.4.10 | 11 pruebas, 38 aserciones |
| 5.3.1 | 8.3.37 | PostgreSQL 17.12 | 11 pruebas, 38 aserciones |

Las pruebas cubren aislamiento de categoría, roles y grupos, restricciones de subcategoría, cohortes y segmentos, padrón congelado, aprobación, estados de notificación, programación, cancelación, lotes, reintentos, interrupciones, contrato de conector y Privacy API.

## Verificaciones adicionales

- Chrome con Boost en Moodle 5.3.1. Se comprobó el panel, carga de plantilla, redacción, lista exacta de destinatarios, confirmación de revisión, aprobación y ejecución de la tarea Adhoc nativa. La tarea registró dos notificaciones para cuentas sintéticas. El informe funcionó en una ventana de 390 píxeles sin desplazamiento horizontal.
- Se iniciaron dos trabajadores simultáneos sobre un mismo lote. Moodle registró exactamente dos notificaciones para los dos destinatarios autorizados.
- Behat aprobó **1 escenario y 8 pasos** sobre navegación y redacción en Moodle 5.3.1.
- Moodle 5.0.12 desinstaló y reinstaló el componente por CLI. PHP_CodeSniffer terminó sin errores ni avisos con el estándar Moodle. Los 32 archivos PHP pasaron la comprobación de sintaxis. Inglés y español comparten 138 claves.

Para reproducir la suite desde la raíz de código de Moodle con su sitio de pruebas configurado:

```bash
vendor/bin/phpunit --test-suffix _test.php public/local/skillupscommunication/tests
```

Si la instalación carece de raíz web separada, usa `local/skillupscommunication/tests`. La etiqueta Behat es `@local_skillupscommunication`.

## Capturas

- [Panel de comunicaciones](screenshots/dashboard.png)
- [Vista previa de destinatarios](screenshots/preview.png)
- [Resultados de procesamiento](screenshots/outcomes.png)
