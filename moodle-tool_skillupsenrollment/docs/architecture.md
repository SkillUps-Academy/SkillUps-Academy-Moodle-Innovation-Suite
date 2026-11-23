# Arquitectura de Smart Enrollment Manager

Se inspeccionó Moodle 5.0.7, `enrol_manual`, `enrol_self`, Groups API y Course Factory. Moodle 5.3 LTS es la referencia. La extensión es una herramienta de administración con vistas y tareas nativas. La importación utiliza OpenSpout incluido en Moodle y normaliza el archivo a solicitudes por fila.

La simulación obtiene una instantánea del método, la matrícula, el rol y el grupo. La confirmación registra la autorización y la hora de las tareas. Un despachador programado inicia tareas Adhoc acotadas. Cada fila revalida al operador y compara la situación actual con la simulación. Las mutaciones y su resultado se confirman en una transacción por fila. Un cambio externo previo impide aplicar una operación obsoleta salvo si el estado solicitado ya se alcanzó. Los fallos se aíslan por persona y se pueden reintentar previa validación.

Las matrículas y estados se modifican mediante Enrolment API. La asignación de rol se limita a la instancia manual autorizada y los grupos se gestionan mediante Groups API. Los informes se filtran por lote, curso o usuario. El contrato local de Course Factory devuelve únicamente el ID de un curso ya creado a un operador autorizado. El importador por IDs o nombres cortos funciona de manera independiente.

[Enrolment API](https://moodledev.io/docs/5.3/apis/subsystems/enrol) · [Access API](https://moodledev.io/docs/5.3/apis/subsystems/access) · [Task API](https://moodledev.io/docs/5.3/apis/subsystems/task)
