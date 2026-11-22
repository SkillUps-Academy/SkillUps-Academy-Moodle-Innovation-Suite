# Integración externa

## Activar rutas HTTPS

1. Configura un token aleatorio de alta entropía con 32 a 256 caracteres de `A–Z a–z 0–9 . _ ~ -`. Inyecta el resumen SHA-256 hexadecimal en minúsculas en el entorno del proceso web PHP como `SKILLUPS_MONITOR_TOKEN_SHA256`. Guarda el token en un archivo de credenciales del cliente con permisos restringidos. El proxy debe evitar registrar el encabezado `Authorization`.
2. Usa HTTPS para el `wwwroot` canónico y activa **Permitir monitoreo externo autenticado**. Para terminación TLS en un proxy, configura el servidor web o PHP-FPM de modo que `HTTPS=on` llegue a PHP únicamente por el virtual host HTTPS de confianza. El plugin evalúa esa variable del servidor y no utiliza encabezados de redirección de TLS suministrados por clientes. Cierra el acceso directo al origen y conserva correctamente `Authorization` en el proxy.
3. Restringe el acceso a estas rutas por red y aplica límites de frecuencia. Envía solo `Authorization: Bearer <token>`. El plugin admite GET y HEAD sin sesión y con respuestas no almacenables.
4. Para revocar acceso desactiva las rutas o cambia el resumen del token y recarga los procesos web. Actualiza los archivos de credenciales autorizados.

## Prometheus

`/report/skillupsmonitor/metrics.php` entrega gauges sin etiquetas de datos institucionales. Ajusta la ruta si Moodle utiliza un subdirectorio. Incorpora el siguiente trabajo a `scrape_configs`.

```yaml
- job_name: skillups-moodle
  scheme: https
  metrics_path: /report/skillupsmonitor/metrics.php
  scrape_interval: 60s
  scrape_timeout: 5s
  authorization:
    type: Bearer
    credentials_file: /run/secrets/skillups_monitor_token
  static_configs:
    - targets: [moodle.example.org]
```

Las series llevan el prefijo `skillups_moodle_`. `sample_fresh` indica frescura, `cron_healthy` señala cron reciente, `readiness` representa el último resultado interno y `maintenance` distingue mantenimiento desde la última muestra. Los datos sin observación aparecen como `NaN`. Combina lecturas internas con `sample_fresh == 1` para evitar interpretar observaciones antiguas como recientes. Un fallo de `up{job="skillups-moodle"}` indica que Prometheus no pudo recoger el endpoint. Se recomienda alertar también por `skillups_moodle_sample_fresh == 0` y por cron no saludable, con un retardo operativo apropiado. [Configuración oficial de Prometheus](https://prometheus.io/docs/prometheus/latest/configuration/configuration/)

## Sonda desde otra red

Configura Blackbox Exporter con un destino fijo y limita su API de sondas a Prometheus. Utiliza `/report/skillupsmonitor/probe.php` como prueba de preparación funcional desde la red de monitoreo. Devuelve 200 cuando puede verificar Moodle y la portada en la base de datos, y 503 si la comprobación falla dentro del plugin. Fallos anteriores al inicio de Moodle pueden manifestarse como otros códigos HTTP, desconexión o timeout. Usa otra sonda de la página pública o del acceso corporativo para medir el recorrido real de los usuarios.

```yaml
modules:
  skillups_moodle_https:
    prober: http
    timeout: 5s
    http:
      valid_status_codes: [200]
      follow_redirects: false
      fail_if_not_ssl: true
      authorization:
        type: Bearer
        credentials_file: /run/secrets/skillups_monitor_token
```

Agrega el siguiente trabajo a Prometheus y cambia el destino de Moodle y la dirección privada del exporter.

```yaml
- job_name: skillups-moodle-blackbox
  metrics_path: /probe
  scrape_interval: 60s
  scrape_timeout: 10s
  params:
    module: [skillups_moodle_https]
  static_configs:
    - targets: [https://moodle.example.org/report/skillupsmonitor/probe.php]
  relabel_configs:
    - source_labels: [__address__]
      target_label: __param_target
    - source_labels: [__param_target]
      target_label: instance
    - target_label: __address__
      replacement: blackbox.internal.example.org:9115
```

`probe_success` mide el resultado de esa sonda, `probe_duration_seconds` mide su duración total y `up` mide si Prometheus pudo consultar el exporter. Usa `fail_if_body_not_matches_regexp: ['^ready$']` dentro de `http` si necesitas verificar también el contenido esperado. [Configuración oficial de Blackbox Exporter](https://github.com/prometheus/blackbox_exporter/blob/master/CONFIGURATION.md)

## Grafana y métricas del servidor

Importa [el dashboard JSON](grafana-dashboard.json), selecciona la fuente Prometheus y ajusta los filtros por trabajo si cambiaste los nombres. Un panel de sonda externa queda sin datos hasta que configures Blackbox Exporter. Puedes agregar paneles separados para CPU, memoria y disco a partir de un agente de infraestructura autorizado, como Node Exporter. Opera los agentes y su retención bajo la política de infraestructura de la institución. [Importación de dashboards](https://grafana.com/docs/grafana/latest/visualizations/dashboards/build-dashboards/import-dashboards/) · [Fuente de datos Prometheus](https://grafana.com/docs/grafana/latest/datasources/prometheus/)
