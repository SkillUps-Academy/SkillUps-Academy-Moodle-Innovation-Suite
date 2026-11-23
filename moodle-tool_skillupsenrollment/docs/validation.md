# Validación de Smart Enrollment Manager

Las pruebas utilizaron datos sintéticos e instalaciones desechables. Moodle 5.3 LTS fue la referencia.

| Moodle | PHP | Motor | Instalación | PHPUnit |
| --- | --- | --- | --- | --- |
| 5.0.12 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 50 aserciones |
| 5.1.9 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 50 aserciones |
| 5.2.5 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 50 aserciones |
| 5.3.1 | 8.3.37 | MariaDB 11.4.10 | Correcta | 10 pruebas, 50 aserciones |
| 5.3.1 | 8.3.37 | PostgreSQL 17.12 | Correcta | 10 pruebas, 50 aserciones |

- **PHPUnit** cubrió CSV y XLSX, fórmulas rechazadas, simulación sin cambios, duplicados, roles de estudiante, docente y coordinador, aislamiento de grupos, conservación de fechas y roles, suspensión por método, reactivación, reintento selectivo, bloqueo ante conflictos, revocación de permisos y privacidad.
- **Carga** en Moodle 5.3.1. Se simularon y aplicaron 1000 matrículas distribuidas entre 25 usuarios y 40 cursos. Se verificó el límite de 50 filas por tarea y que repetir el procesamiento conservara exactamente las mismas 1000 matrículas.
- **Course Factory** en Moodle 5.3.1. Una prueba con seis aserciones cubrió destino pendiente, autorización del contrato v1, destino completado y matrícula efectiva.
- **Behat** en Moodle 5.3.1. Un escenario y 16 pasos correctos desde la simulación hasta la aplicación con tareas nativas.
- **Chromium**. Carga real CSV, simulación sin escrituras, matrícula con grupo, suspensión, protección ante un grupo de otro curso, informe combinado por curso y usuario y exportación CSV de errores. Se verificó un libro XLSX válido con licencia firmada sintética.
- **Paquete**. Validación nativa del ZIP en Moodle 5.0.12 y 5.3.1 y reinstalación desde el ZIP en 5.0.12. PHP CodeSniffer con el estándar Moodle y revisión de sintaxis. La aprobación de Moodle Marketplace queda pendiente.

## Capturas

- [Panel](screenshots/dashboard.png)
- [Simulación](screenshots/simulation.png)
- [Resultados](screenshots/results.png)
- [Informe por curso y usuario](screenshots/report.png)
