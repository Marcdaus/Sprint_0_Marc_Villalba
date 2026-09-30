<?php

// ===========================================================================
// recuperarMedicion.php
//
//   Ruta REST : rest/recuperarMedicion.php
//   Metodo    : GET   (cualquier otro metodo -> 405)
//   Entrada   : filtros opcionales en el query (?desde=...&hasta=...
//               &uuid_beacon=...&minor=...)
//   Delega en : recuperarMedicion() de la logica de negocio (PROMPT 4)
//
// Que hace este endpoint, paso a paso (todo aqui mismo, es autocontenido):
//   1. leer el metodo HTTP y el query del GET
//   2. si el metodo no es GET -> 405 y se termina
//   3. sacar los filtros del query
//   4. delegar en la logica: recuperarMedicion( $filtro )
//   5. escribir el codigo HTTP y el JSON que devuelve la logica
//
// Este endpoint NO implementa las reglas de negocio (que beacons son los
// conocidos, como se consulta la BD...): eso es trabajo de la logica de
// negocio del PROMPT 4, aqui solo se delega.
//
// OJO con un detalle: este endpoint NO tiene paso de "parsear y si esta
// mal -> 400", y no por descuido. Aqui no hay JSON que parsear: los
// filtros vienen del query, y extraerFiltros() no puede fallar (si un
// filtro no viene, se queda en null = sin filtro). Por eso el unico 400
// posible en esta ruta es el que devuelva la logica de negocio.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// ====== VARIABLES MODIFICABLES ======
// Rutas del despliegue. El "minor" NO se fija aqui: es un filtro
// opcional que llega en el query, y puede ser el que sea.
// =====================================

// La logica de negocio. En el PROMPT 4 se pondra el fichero real; hasta
// entonces los tests usan el stub de pruebas/stubLogicaDeNegocio.php.
$rutaLogicaNegocio = __DIR__ . '/../logica/recuperarMedicion.php';

// Si este fichero no esta todavia (PROMPT 4 sin hacer), se usa el stub
// temporal. Asi los endpoints se pueden probar ya.
if ( file_exists( $rutaLogicaNegocio ) ) {
  require_once $rutaLogicaNegocio;
} else {
  require_once __DIR__ . '/../pruebas/stubLogicaDeNegocio.php';
}

// ---------------------------------------------------------------------------
// extraerFiltros() : lee el query del GET y saca el FiltroMediciones
//
// Solo se aceptan cuatro filtros: desde, hasta, uuid_beacon y minor.
// Los que no vengan se quedan en null, que significa "sin filtro" (no
// "cero" ni "vacio"). Esto es importante: minor = null es "traeme
// todas las mediciones", mientras que minor = 1234 es "traeme solo las
// que valen 1234".
//
// OJO: un filtro que venga pero este vacio (?minor=) tambien se queda en
// null, porque un parametro vacio no es un filtro, es un error de quien
// escribe la URL. Un filtro de minor que no sea un numero NO se descarta
// aqui: se pasa tal cual a la logica, que es quien decide si vale
// (PROMPT 4). Aqui no se validan datos.
//
// query: Text --> extraerFiltros() --> FiltroMediciones
// ---------------------------------------------------------------------------
function extraerFiltros( $query ) {

  $filtro = new stdClass();

  // el punto de partida es $_GET (que es el query del GET)
  $filtro->desde       = null;
  $filtro->hasta       = null;
  $filtro->uuid_beacon = null;
  $filtro->minor       = null;

  if ( ! is_array( $query ) ) {
    return $filtro;
  }

  // ---- desde ----
  if ( isset( $query['desde'] ) && trim( (string) $query['desde'] ) !== '' ) {
    $filtro->desde = trim( (string) $query['desde'] );
  }

  // ---- hasta ----
  if ( isset( $query['hasta'] ) && trim( (string) $query['hasta'] ) !== '' ) {
    $filtro->hasta = trim( (string) $query['hasta'] );
  }

  // ---- uuid_beacon ----
  if ( isset( $query['uuid_beacon'] ) && trim( (string) $query['uuid_beacon'] ) !== '' ) {
    $filtro->uuid_beacon = trim( (string) $query['uuid_beacon'] );
  }

  // ---- minor ----
  // llega como texto porque en un query todo es texto: "1234"
  if ( isset( $query['minor'] ) && trim( (string) $query['minor'] ) !== '' ) {
    $filtro->minor = trim( (string) $query['minor'] );
  }

  return $filtro;

} // ()

/*
   ---------------------------------------------------------------------------
   Escribir la respuesta: codigo HTTP + JSON.

   OJO: esto NO es una funcion. Leer la peticion y escribir la respuesta son
   pasos INLINE de cada endpoint (ver seccion 3.3 del prompt), sin funciones
   tipo atenderPeticion(), enrutar(), construirRespuesta() o enviarRespuesta().
   Por eso se escriben las tres lineas aqui, en el punto donde hacen falta.
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
// 4. delegar en la logica de negocio (PROMPT 4)
// ---------------------------------------------------------------
$respuesta = recuperarMedicion( $filtro );

// ---------------------------------------------------------------
// 5. responder con el codigo HTTP y el JSON que devuelve la logica
// ---------------------------------------------------------------
http_response_code( $respuesta->codigo );
header( 'Content-Type: application/json' );
echo $respuesta->cuerpo;

?>
