# Validación de Gamification Pro

Se probaron instalaciones desechables con datos sintéticos y depuración de desarrollador en PHP 8.3.37.

| Moodle | Base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 14 pruebas, 45 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 14 pruebas, 45 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 14 pruebas, 45 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 14 pruebas, 45 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 14 pruebas, 45 aserciones |

- **Reglas y consistencia.** Los eventos de actividad y curso se verificaron mediante Moodle. Se comprobó el rechazo de finalización manual, override, registro ajeno, matrícula suspendida y fuente inválida. La repetición de la tarea y el reemplazo de una regla no duplicaron el premio. Se probaron topes, exclusiones y visibilidad en grupos separados.
- **Carga.** Una prueba reconcilió puntos, XP, saldo y avance de un historial sintético de 1.000 movimientos frente a otro curso. Verificó el fin excluyente de una ventana.
- **Behat en Moodle 5.3.1.** Pasaron un escenario y 11 pasos para acceder a la administración nativa, habilitar la gamificación y revisar el panel.
- **Chromium en Moodle 5.3.1.** Se completó una actividad desde la interfaz. Se ejecutaron las tareas ad hoc nativas y se verificaron XP, misiones, insignias, desafíos por equipos, alias voluntarios, historial y entrega. Dos procesos PHP intentaron canjear a la vez la última unidad de una recompensa. Se registró un único canje.
- **Learning Path.** Se probó que la ausencia del proveedor impide conceder premios de hitos. La suite entrega el contrato opcional v1.
- **Paquete.** Moodle CodeSniffer pasó con el estándar oficial. El ZIP fue validado con el instalador nativo de Moodle 5.0.12 y 5.3.1 y se reinstaló desde el paquete en 5.0.12. La ficha de Marketplace se entrega como borrador.

## Capturas

- [Progreso, nivel y metas](screenshots/progress.png)
- [Clasificación privada](screenshots/ranking.png)
- [Historial de premios](screenshots/history.png)
- [Entrega administrativa de recompensas](screenshots/delivery.png)
