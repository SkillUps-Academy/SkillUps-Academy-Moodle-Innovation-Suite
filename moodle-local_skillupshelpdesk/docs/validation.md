# Validación de SkillUps Helpdesk

Las pruebas se ejecutaron con datos sintéticos y depuración de desarrollador en PHP 8.3.37.

| Moodle | Motor de base de datos | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 18 pruebas, 60 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 18 pruebas, 60 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 18 pruebas, 60 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 18 pruebas, 60 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 18 pruebas, 60 aserciones |

- **Permisos y privacidad.** Detalle y listados aislados, visibilidad de notas internas, retirada al reasignar, restricción de reasignación, acceso al curso al abrir, análisis de adjuntos, exportación limitada, borrado y retención.
- **SLA y operación.** Fin de semana, feriado, cambio de hora, primer comentario técnico, resolución y valoración única. Se verificaron escalamiento idempotente, enfriamiento manual y descarte de destinatarios sin acceso. La prueba de notificaciones usó un sumidero de Moodle.
- **Carga.** Reconciliación de informes y paginación sobre 1.000 tickets sintéticos, con 500 resueltos y 500 activos.
- **Behat.** Un escenario y 11 pasos comprobaron acceso al portal desde Moodle, apertura y retorno al listado en 5.3.1.
- **Chromium.** Se probaron tres cuentas, apertura con curso, asignación, carga de plantilla, nota interna, descarga pública autorizada, respuesta 404 para archivos privados y ajenos, historial de escalamiento, resolución, encuesta, artículo y descarga CSV.
- **Paquete.** PHP CodeSniffer pasó con el estándar oficial. El ZIP pasó el validador nativo de Moodle 5.0.12 y 5.3.1 y se reinstaló desde el paquete en 5.0.12. La ficha de Marketplace se entrega como borrador.

## Capturas

- [Portal del solicitante](screenshots/request.png)
- [Bandeja de técnicos](screenshots/queue.png)
- [Historial y escalamiento](screenshots/history.png)
- [Base de conocimientos](screenshots/knowledge.png)
- [Informes](screenshots/reports.png)
