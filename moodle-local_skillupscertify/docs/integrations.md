# Integraciones de credenciales

## Evidence providers

`local_skillupscertify\evidence\source` recibe curso, revisión, destinatario y momento de emisión. Devuelve evidencia acotada o `null`. Moodle local provee evidencia para autorización manual, finalización oficial del curso y, cuando está instalado, Learning Path Designer. El operador debe iniciar o encolar la emisión. La evidencia se vuelve a comprobar antes de guardar cada certificado.

Para Learning Path, activa la verificación para certificaciones en el itinerario, usa el mismo curso de entrada y configura el ID numérico del itinerario. Certify utiliza la API `verified_completion` del contrato v1 y conserva la fecha y la versión en la evidencia de la emisión.

## Formatos de credencial

`local_skillupscertify\credential\renderer` consume una instantánea de datos de emisión y la URL de verificación. PDF es el formato distribuido. Un adaptador futuro para Open Badges o credenciales verificables deberá implementar identificación del emisor, claves y rotación, perfil de pruebas criptográficas, estado y pruebas de interoperabilidad antes de declarar conformidad.
