<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================


// ===========================================================================
// guardarMedicion.php  --  PROMPT 3 (capa de comunicacion)
//
//   Ruta REST : src/communication/guardarMedicion.php
//   Metodo    : POST   (cualquier otro metodo -> 405)
//   Entrada   : el cuerpo del POST es un JSON con una Medicion
//   Delega en : guardarMedicion() de la logica de negocio (PROMPT 4)
//
// Que hace este endpoint, paso a paso:
//   1. leer el metodo HTTP y el cuerpo del POST
//   2. si el metodo no es POST -> 405 y se termina
//   3. parsear el JSON en una Medicion -> si el JSON esta mal -> 400
//   4. delegar en la logica: guardarMedicion( $medicion )
//   5. TRADUCIR el resultado de dominio a la respuesta de red
//      (codigo HTTP + JSON) y escribirla
//
// ESTE ES EL SITIO DONDE VIVE EL HTTP. La logica de negocio devuelve un
// ResultadoGuardado de dominio (ok/error/clase, sin id) y NO sabe nada de
// codigos de estado: es aqui, en la capa de comunicacion, donde esa clase
// de error de negocio se convierte en un codigo HTTP:
//   - ok                     -> 200  { "ok": true }
//   - clase 'validacion'     -> 400  { "error": "<motivo>" }
//   - clase 'persistencia'   -> 500  { "error": "<motivo>" }
//
// OJO con el cuerpo del POST: llega en php://input, NO en $_POST.
// Si se mandara por formulario, $_POST ya estaria relleno; con JSON a
// pelo hay que leerlo a mano.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// La logica de negocio (solo define funciones; su codigo no se ejecuta al
// cargarla) y los auxiliares de comunicacion (parsearJSON).
require_once __DIR__ . '/../business_logic/guardarMedicion.php';
require_once __DIR__ . '/auxiliares.php';

/*
   ---------------------------------------------------------------------------
   Escribir la respuesta: codigo HTTP + JSON.

   OJO: esto NO es una funcion. El prompt dice que leer la peticion y
   escribir la respuesta son pasos INLINE de cada endpoint, sin funciones
   tipo atenderPeticion(), enrutar() o enviarRespuesta(). Por eso aqui se
   escriben las tres lineas directamente y en el punto donde hacen falta,
   en vez de crear una funcion que las oculte.
   ---------------------------------------------------------------------------
*/

// ---------------------------------------------------------------
// 1. el metodo HTTP y el cuerpo del POST
// ---------------------------------------------------------------
$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

// el cuerpo del POST llega aqui, NO en $_POST: es JSON, no un formulario
$cuerpo = file_get_contents( 'php://input' );

// ---------------------------------------------------------------
// 2. metodo != POST ? -> 405 y se termina
// ---------------------------------------------------------------
if ( $metodo !== 'POST' ) {

  http_response_code( 405 );
  header( 'Content-Type: application/json' );
  echo json_encode( [ 'error' => 'Metodo no permitido' ] );

  // exit y no return, porque este fichero es el endpoint: no hay nadie
  // mas que lo llame
  exit;

}

// ---------------------------------------------------------------
// 3. parsear el JSON en una Medicion -> si esta mal -> 400
// ---------------------------------------------------------------
$medicion = parsearJSON( $cuerpo );

// la senal de "el JSON no vale" es que parsearJSON() devuelve null.
// Un JSON valido pero sin campos NO entra aqui: llega a la logica, que
// devuelve el error de validacion.
if ( $medicion === null ) {

  http_response_code( 400 );
  header( 'Content-Type: application/json' );
  echo json_encode( [ 'error' => 'JSON malformado' ] );

  exit;

}

// ---------------------------------------------------------------
// 4. delegar en la logica de negocio (PROMPT 4). Devuelve dominio.
// ---------------------------------------------------------------
$resultado = guardarMedicion( $medicion );

// ---------------------------------------------------------------
// 5. TRADUCIR el resultado de dominio a HTTP y responder.
//    La logica de negocio no sabe que es un 200/400/500: lo decide aqui.
// ---------------------------------------------------------------
header( 'Content-Type: application/json' );

if ( $resultado->ok === true ) {

  // guardado correcto -> 200 { "ok": true }. No hay id que devolver: la
  // clave de la fila es la natural (id_plaquita, fecha), que puso la BD
  http_response_code( 200 );
  echo json_encode( [ 'ok' => true ], JSON_UNESCAPED_UNICODE );

} elseif ( $resultado->clase === 'validacion' ) {

  // la medicion no cumple las RN (dato mal formado) -> 400
  http_response_code( 400 );
  echo json_encode( [ 'error' => $resultado->error ], JSON_UNESCAPED_UNICODE );

} else {

  // fallo al guardar en la base de datos -> 500
  http_response_code( 500 );
  echo json_encode( [ 'error' => $resultado->error ], JSON_UNESCAPED_UNICODE );

}

?>
