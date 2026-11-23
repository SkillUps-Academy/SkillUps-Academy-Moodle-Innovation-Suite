# Integración opcional con Learning Path

Gamification Pro funciona con eventos de Moodle en ausencia del proveedor. El contrato v1 busca la clase local `\local_skillupslearningpath\integration\gamification_provider`, con constructor sin argumentos, constante pública `CONTRACT_VERSION = 1` e implementación de `\local_skillupsgamification\integration\milestone_provider`.

`completed_at(int $courseid, int $userid, int $milestoneid): ?int` consulta una finalización vigente de ese hito, curso y usuario en el registro autorizado del proveedor. Devuelve una fecha Unix persistida, o `null` si no existe evidencia. El ID de hito debe ser estable. La integración no valida hitos a partir de texto, atributos del navegador o identificadores sin una consulta de evidencia. El proveedor instalado se ejecuta como PHP de Moodle y forma parte del entorno de confianza.

Habilita Learning Path por curso, crea una regla por ID de hito y usa **Comprobar hitos de Learning Path** para conciliar los del participante autenticado. El reconciliador aplica el mismo control de matrícula, exclusiones, límites y duplicados que los eventos nativos. El repositorio de la suite no incluye una implementación compatible de Learning Path en esta entrega.
