<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================


// ===========================================================================
// recuperarMedicion.php  --  PROMPT 3 (capa de comunicacion)
//
//   Ruta REST : src/communication/recuperarMedicion.php
//   Metodo    : GET   (cualquier otro metodo -> 405)
//   Entrada   : filtros opcionales en el query (?desde=...&hasta=...
//               &tipo_medicion=...&valor_medicion=...)
//   Delega en : recuperarMedicion() de la logica de negocio (PROMPT 4)
//
// Que hace este endpoint, paso a paso:
//   1. leer el metodo HTTP y el query del GET
//   2. si el metodo no es GET -> 405 y se termina
//   3. sacar los filtros del query
//   4. delegar en la logica: recuperarMedicion( $filtro )
//   5. TRADUCIR el resultado de dominio a la respuesta de red
//      (codigo HTTP + JSON) y escribirla
//
// ESTE ES EL SITIO DONDE VIVE EL HTTP. La logica de negocio devuelve un
// ResultadoLista de dominio (ok/mediciones/error/clase) y NO sabe nada de
// codigos de estado: es aqui donde el resultado se convierte en respuesta:
//   - ok                     -> 200  { "mediciones": [ ... ] }
//   - clase 'persistencia'   -> 500  { "error": "<motivo>" }
//
// OJO con un detalle: este endpoint NO tiene paso de "parsear y si esta
// mal -> 400", y no por descuido. Aqui no hay JSON que parsear: los
// filtros vienen del query, y extraerFiltros() no puede fallar (si un
// filtro no viene, se queda en null = sin filtro). Por eso el unico error
// posible en esta ruta es el de persistencia (500).
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// La logica de negocio (solo define funciones; su codigo no se ejecuta al
// cargarla) y los auxiliares de comunicacion (extraerFiltros).
require_once __DIR__ . '/../business_logic/recuperarMedicion.php';
require_once __DIR__ . '/auxiliares.php';

/*
   ---------------------------------------------------------------------------
   Escribir la respuesta: codigo HTTP + JSON.

   OJO: esto NO es una funcion. Leer la peticion y escribir la respuesta son
   pasos INLINE de cada endpoint, sin funciones tipo atenderPeticion(),
   enrutar(), construirRespuesta() o enviarRespuesta(). Por eso se escriben
   las tres lineas aqui, en el punto donde hacen falta.
   ---------------------------------------------------------------------------
*/

// ---------------------------------------------------------------
// 1. el metodo HTTP y el query del GET
// ---------------------------------------------------------------
$metodo = $_SERVER['REQUEST_METHOD'] ?? '';

// en un GET no hay cuerpo: los filtros van en el query, o sea, en $_GET
$query = $_GET;

// ---------------------------------------------------------------
// 2. metodo != GET ? -> 405 y se termina
// ---------------------------------------------------------------
if ( $metodo !== 'GET' ) {

  http_response_code( 405 );
  header( 'Content-Type: application/json' );
  echo json_encode( [ 'error' => 'Metodo no permitido' ] );

  exit;

}

// ---------------------------------------------------------------
// 3. sacar los filtros del query
// ---------------------------------------------------------------
$filtro = extraerFiltros( $query );

// ---------------------------------------------------------------
// 4. delegar en la logica de negocio (PROMPT 4). Devuelve dominio.
// ---------------------------------------------------------------
$resultado = recuperarMedicion( $filtro );

// ---------------------------------------------------------------
// 5. TRADUCIR el resultado de dominio a HTTP y responder.
//    La logica de negocio no sabe que es un 200/500: lo decide aqui.
// ---------------------------------------------------------------
header( 'Content-Type: application/json' );

if ( $resultado->ok === true ) {

  // lectura correcta -> 200 con la lista de mediciones
  http_response_code( 200 );
  echo json_encode( [ 'mediciones' => $resultado->mediciones ], JSON_UNESCAPED_UNICODE );

} else {

  // fallo al leer de la base de datos -> 500
  http_response_code( 500 );
  echo json_encode( [ 'error' => $resultado->error ], JSON_UNESCAPED_UNICODE );

}

?>
