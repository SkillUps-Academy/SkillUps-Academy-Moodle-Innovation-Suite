# Validación de SkillUps Content Repository

Los ensayos usaron instalaciones Moodle aisladas, datos sintéticos y depuración de desarrollo.

| Moodle | PHP | Base de datos | PHPUnit |
|---|---|---|---|
| 5.0.12 | 8.3.37 | MariaDB 11.4.10 | 8 pruebas, 39 aserciones |
| 5.3.1 | 8.3.37 | MariaDB 11.4.10 | 8 pruebas, 39 aserciones |
| 5.3.1 | 8.3.37 | PostgreSQL 17.12 | 8 pruebas, 39 aserciones |

La suite ejercita aislamiento de categorías y recursos privados, autorización de descarga, publicación y archivo, conservación del archivo entre revisiones, persistencia de licencias de Moodle, rechazo de un archivo renombrado, reconocimiento de un paquete con manifiesto, creación de actividades Archivo y URL, idempotencia de la copia, seguimiento al eliminar la actividad y borrado mediante Privacy API.

Behat ejecutó **2 escenarios y 13 pasos** en Moodle 5.3.1 y verificó los controles de aportación y revisión para un administrador y un usuario sin permisos de biblioteca.

Para reproducir PHPUnit desde el directorio de código de Moodle con su sitio de pruebas configurado:

```bash
vendor/bin/phpunit --test-suffix _test.php public/local/skillupsrepository/tests
```

En instalaciones sin raíz pública separada, usa `local/skillupsrepository/tests`. La etiqueta Behat del componente es `@local_skillupsrepository`.

## Pruebas adicionales

- Chrome con el tema Boost en Moodle 5.3.1. Se verificaron la búsqueda, cuadrícula, lista, carga PNG mediante el selector nativo, vista previa, descarga pública, denegación del archivo privado, envío a revisión, aprobación y creación de una URL nativa desde la interfaz. La página funcionó en una ventana de 390 píxeles sin desplazamiento horizontal.
- Moodle 5.0.12 desinstaló y reinstaló el componente por CLI. Moodle 5.3.1 instaló su esquema en MariaDB y PostgreSQL.
- El validador nativo de paquetes aceptó el ZIP en las tres instalaciones de la matriz.
- PHP_CodeSniffer con el estándar Moodle terminó sin errores ni avisos. Los 24 archivos PHP pasaron la comprobación de sintaxis. Español e inglés comparten las 131 claves de traducción.

## Capturas

- [Catálogo con filtros](screenshots/library.png)
- [Previsualización de una imagen](screenshots/preview.png)
- [Biblioteca móvil](screenshots/mobile.png)
