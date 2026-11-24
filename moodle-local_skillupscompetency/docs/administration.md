# Administración e instalación

1. Habilita competencias desde la administración de Moodle y verifica que están disponibles las herramientas nativas de planes de aprendizaje y CSV.
2. Instala el ZIP desde Moodle o coloca el directorio `skillupscompetency` en `local/skillupscompetency` dentro de la raíz web. En instalaciones con raíz web `public`, el destino es `public/local/skillupscompetency`.
3. Desde la raíz de Moodle, ejecuta `php admin/cli/upgrade.php --non-interactive`.
4. Abre **Administración del sitio → Plugins → Plugins locales → SkillUps Competency Manager**. Define los roles de programa y coordinación. Matricula participantes mediante los métodos nativos.
5. Mantén cron activo. La tarea de conciliación elimina referencias propias a cursos o competencias eliminados, en lotes limitados. Los grupos y las matrículas conservan su administración nativa.

Al actualizar, respalda la base de datos y Moodledata, activa mantenimiento y vuelve a ejecutar la CLI. Esta primera versión incluye el punto de extensión de XMLDB para futuras migraciones. La desinstalación Moodle retira tablas propias del plugin. Los niveles nativos que se aprobaron pertenecen a Moodle Core.

| Rol | Capacidades del plugin | Permisos complementarios |
| --- | --- | --- |
| Administrador de programas | `manage` y, si corresponde, `reports` | Ámbito de la categoría y acceso nativo a los cursos |
| Gestor del catálogo | `catalog` | `moodle/competency:competencymanage` en el contexto del marco |
| Coordinador | `review`, y `reports` si consulta brechas | Acceso al curso, lectura de competencias del estudiante y `moodle/competency:competencygrade` para publicar |
| Docente | `view`, `assess` | Matrícula y capacidades nativas de competencias. Gestión de vínculos requiere `moodle/competency:coursecompetencymanage` |
| Estudiante | `view`, `self` | Matrícula activa con `moodle/competency:coursecompetencygradable` |

Las capacidades del plugin usan el prefijo `local/skillupscompetency:`. Moodle concede las predeterminadas de docente y estudiante según su rol arquetípico. Un administrador del sitio puede configurar los demás roles y asignaciones. La revisión exige un actor diferente del autor y del estudiante evaluado.

Los CSV requieren el formato de datos CSV de Moodle habilitado. La aprobación usa la preferencia de propagación de niveles a planes de aprendizaje configurada en el curso Moodle.
