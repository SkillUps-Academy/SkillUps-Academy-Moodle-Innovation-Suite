# Validación de la beta

| Moodle | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- |
| 5.0.12 | MariaDB 11.4.10 | Correcta | 5 pruebas, 43 aserciones |
| 5.1.9 | MariaDB 11.4.10 | Correcta | 5 pruebas, 43 aserciones |
| 5.2.5 | MariaDB 11.4.10 | Correcta | 5 pruebas, 43 aserciones |
| 5.3.1 | MariaDB 11.4.10 | Correcta | 5 pruebas, 43 aserciones |
| 5.3.1 | PostgreSQL 17.12 | Correcta | 5 pruebas, 43 aserciones |

## Cobertura comprobada

- PHPUnit con PHP 8.3.37 verificó la conservación del PDF, la revocación, la reemisión, la reducción de metadatos públicos, la detección de alteraciones, la eliminación por privacidad, los grupos y el alcance entre cursos. El test de lotes comprobó finalización nativa y revisión fijada.
- Behat en Moodle 5.3.1 completó 3 escenarios y 13 pasos. Se verificaron el acceso administrativo al catálogo, la cartera privada y el rechazo público de un código desconocido.
- Chrome comprobó diseñador, vista previa PDF, emisión, descarga privada, aislamiento de otro usuario, verificación anónima, huella local, revocación, reemplazo y exportación CSV. Se decodificó el QR del PDF renderizado y se comparó su destino con la URL del registro. El código impreso también permitió verificar la revocación manualmente.
- Se ejecutó desde la CLI oficial de Moodle el lote encolado en el navegador. Emitió un PDF al alumno que completó el curso y omitió los otros dos registros.
- En un lote sintético con 200 destinatarios, 100 elegibles y 100 sin evidencia, el procesamiento generó 100 PDF. Una repetición mantuvo esos resultados sin duplicarlos. La ejecución local de ese lote tardó aproximadamente 11 s.
- La integración opcional emitió con la prueba de finalización de Learning Path y bloqueó la reemisión una vez revocado el permiso de certificación del itinerario.

## Reproducir

Desde la raíz de Moodle ejecuta:

```sh
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --test-suffix _test.php public/local/skillupscertify/tests
```

En Moodle 5.0 usa las rutas equivalentes sin `public/`. Para Behat, inicializa el entorno de pruebas de Moodle y ejecuta `vendor/bin/behat` con el perfil generado y `--tags @local_skillupscertify`. Usa datos sintéticos para reproducir el flujo de verificación y procesamiento por lote.

Los controles se probaron en el entorno local descrito. Open Badges, credenciales verificables y firmas PDF embebidas quedan fuera de la beta. La validación bajo carga institucional sostenida y la revisión oficial de Moodle Marketplace quedan pendientes.

[Diseñador](screenshots/designer.png) · [PDF generado con datos sintéticos](screenshots/certificate.png) · [Verificador público](screenshots/verification.png) · [Registro](screenshots/registry.png)
