# Arquitectura de SkillUps Student Success Predictor

La referencia local es Moodle 5.0.7 con PHP 8.2.12. Se revisaron el modelo nativo «Students at risk of dropping out», las APIs de matrícula, disponibilidad, Assignment, calificaciones y lector del log estándar. El componente local separa la gestión de casos de los modelos de Moodle Analytics y reutiliza las fuentes de Moodle mediante lecturas. Moodle 5.3 LTS es la primera referencia de validación.

1. Capacidades de curso, verificación de acceso a todos los grupos y de permisos para los datos académicos, separación de tutores y autorización de cada caso.
2. Capturas programadas por estudiante activo. Se usan último acceso por curso, entregas individuales vencidas con fechas efectivas, calificación total numérica y actividad registrada. Se distinguen evidencias faltantes de ceros observados.
3. Motor determinístico versionado con umbrales por curso. Motor estadístico opcional con coeficientes aprobados y congelados, sin código ejecutable de usuario y sin entrenamiento.
4. Registro de observaciones, alertas, casos, tutores e intervenciones. Los casos tienen ciclo de vida humano. Las operaciones se limitan a datos del plugin y auditoría.
5. Registro prospectivo opcional de positivos y negativos antes del desenlace. Revisión humana diferida y métricas separadas por motor y por versión de modelo. Informes desidentificados por curso y programa, con bandas y supresión.
6. Verificación con datos sintéticos, privacidad Moodle, retención y empaquetado independiente de la nube.

[Analytics API](https://moodledev.io/docs/5.3/apis/subsystems/analytics) · [Modelo nativo de abandono](https://docs.moodle.org/501/en/Students_at_risk_of_dropping_out) · [Access API](https://moodledev.io/docs/5.3/apis/subsystems/access) · [Enrolment API](https://moodledev.io/docs/5.3/apis/subsystems/enrol)
