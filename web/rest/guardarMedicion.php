<?php

// ===========================================================================
// guardarMedicion.php
//
//   Ruta REST : rest/guardarMedicion.php
//   Metodo    : POST   (cualquier otro metodo -> 405)
//   Entrada   : el cuerpo del POST es un JSON con una Medicion
//   Delega en : guardarMedicion() de la logica de negocio (PROMPT 4)
//
// Que hace este endpoint, paso a paso (todo aqui mismo, es autocontenido):
//   1. leer el metodo HTTP y el cuerpo del POST
//   2. si el metodo no es POST -> 405 y se termina
//   3. parsear el JSON en una Medicion -> si el JSON esta mal -> 400
//   4. delegar en la logica: guardarMedicion( $medicion )
//   5. escribir el codigo HTTP y el JSON que devuelve la logica
//
// Este endpoint NO implementa las reglas de negocio (si el beacon es
// conocido, si el minor es obligatorio, si la fecha la pone la BD...):
// eso es trabajo de la logica de negocio del PROMPT 4, aqui solo se
// delega.
//
// OJO con el cuerpo del POST: llega en php://input, NO en $_POST.
// Si se mandara por formulario, $_POST ya estaria relleno; con JSON a
// pelo hay que leerlo a mano.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// ====== VARIABLES MODIFICABLES ======
// Rutas del despliegue. El "minor" NO se fija aqui: lo recibe la app
// en el JSON de cada medicion, y puede cambiar entre deployments.
// =====================================

// La logica de negocio. En el PROMPT 4 se pondra el fichero real; hasta
// entonces los tests usan el stub de pruebas/stubLogicaDeNegocio.php.
$rutaLogicaNegocio = __DIR__ . '/../logica/guardarMedicion.php';

// Si este fichero no esta todavia (PROMPT 4 sin hacer), se usa el stub
// temporal. Asi los endpoints se pueden probar ya.
if ( file_exists( $rutaLogicaNegocio ) ) {
  require_once $rutaLogicaNegocio;
} else {
  require_once __DIR__ . '/../pruebas/stubLogicaDeNegocio.php';
}

// ---------------------------------------------------------------------------
// parsearJSON() : convierte el cuerpo del POST en una Medicion
//
// Si el JSON es valido, devuelve la Medicion con sus campos. Si esta mal
// formado, devuelve la Medicion VACIA (todos los campos a null), que es la
// senal que usa el endpoint para responder con un 400 en vez de un 200.
// OJO: aqui NO se validan los datos (si el minor falta, si el uuid es el
// que es...). Eso es de la logica de negocio (PROMPT 4). Aqui solo se
// comprueba que el texto sea JSON.
//
// cuerpo: Text --> parsearJSON() --> Medicion
// ---------------------------------------------------------------------------
function parsearJSON( $cuerpo ) {

  // una Medicion vacia: si el JSON falla, se queda asi
  $medicionVacia = new stdClass();
  $medicionVacia->uuid_beacon    = null;
  $medicionVacia->nombre_emisora = null;
  $medicionVacia->major          = null;
  $medicionVacia->minor          = null;
  $medicionVacia->tx_power       = null;
  $medicionVacia->rssi           = null;

  // si no llega nada, tampoco hay nada que parsear
  if ( $cuerpo === null || trim( $cuerpo ) === '' ) {
    return $medicionVacia;
  }

  // true = los numeros tambien salen como numeros (no como texto)
  $datos = json_decode( $cuerpo, true );

  // json_last_error() dice si el decode ha fallado. Es mejor mirarlo que
  // comprobar si $datos es null, porque un JSON valido puede ser "null".
  if ( json_last_error() !== JSON_ERROR_NONE ) {
    return $medicionVacia;
  }

  // el JSON tiene que ser un objeto con los campos de una Medicion
  if ( ! is_array( $datos ) ) {
    return $medicionVacia;
  }

  $medicion = new stdClass();

  // los campos que llegan tal cual
  $medicion->uuid_beacon    = isset( $datos['uuid_beacon'] )    ? $datos['uuid_beacon']    : null;
  $medicion->nombre_emisora = isset( $datos['nombre_emisora'] ) ? $datos['nombre_emisora'] : null;

  // los numeros: si llegan como texto ("1234") se convierten con (int),
  // para que minor sea un numero y no un string
  $medicion->major    = isset( $datos['major'] )    ? (int) $datos['major']    : null;
  $medicion->minor    = isset( $datos['minor'] )    ? (int) $datos['minor']    : null;
  $medicion->tx_power = isset( $datos['tx_power'] ) ? (int) $datos['tx_power'] : null;
  $medicion->rssi     = isset( $datos['rssi'] )     ? (int) $datos['rssi']     : null;

  return $medicion;

} // ()

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

// la senal de "el JSON no vale" es que el uuid es null: un JSON
// malformado devuelve la Medicion vacia (seccion de parsearJSON)
if ( $medicion->uuid_beacon === null ) {

  http_response_code( 400 );
  header( 'Content-Type: application/json' );
  echo json_encode( [ 'error' => 'JSON malformado' ] );

  exit;

}

// ---------------------------------------------------------------
// 4. delegar en la logica de negocio (PROMPT 4)
// ---------------------------------------------------------------
$respuesta = guardarMedicion( $medicion );

// ---------------------------------------------------------------
// 5. responder con el codigo HTTP y el JSON que devuelve la logica
// ---------------------------------------------------------------
http_response_code( $respuesta->codigo );
header( 'Content-Type: application/json' );
echo $respuesta->cuerpo;

?>
