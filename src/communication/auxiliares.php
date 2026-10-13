<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================


// ===========================================================================
// auxiliares.php  --  PROMPT 3 (capa de comunicacion)
//
// Aqui viven las dos funciones de apoyo que comparten los dos endpoints REST:
//
//     parsearJSON()    : cuerpo del POST (texto)  -> Medicion
//     extraerFiltros() : query del GET ($_GET)     -> FiltroMediciones
//
// Estan en un fichero aparte (y no dentro de cada endpoint) para que los
// endpoints las puedan usar los dos y, ademas, se puedan probar sin arrancar
// el servidor (las pruebas hacen require de este fichero y ya esta): asi no
// hace falta incluir un endpoint entero, que al incluirlo ejecutaria la
// atencion de la peticion y terminaria con exit().
//
// Estas funciones son de COMUNICACION, no de negocio: solo traducen de
// texto/query a los tipos de dominio. NO validan reglas de negocio (si el
// tipo es conocido, si el valor falta...): eso es de src/business_logic/.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================


// ---------------------------------------------------------------------------
// parsearJSON() : convierte el cuerpo del POST en una Medicion
//
// Si el JSON es valido, devuelve la Medicion con sus campos. Si esta mal
// formado, devuelve null, que es la senal que usa el endpoint para
// responder con un 400 en vez de un 200.
// OJO: aqui NO se validan los datos (si el tipo es uno de los conocidos, si
// el valor falta...). Eso es de la logica de negocio. Aqui solo se comprueba
// que el texto sea JSON.
//
// cuerpo: Text --> parsearJSON() --> Medicion?
// ---------------------------------------------------------------------------
function parsearJSON( $cuerpo ) {

  // si no llega nada, tampoco hay nada que parsear
  if ( $cuerpo === null || trim( $cuerpo ) === '' ) {
    return null;
  }

  // true = los numeros tambien salen como numeros (no como texto)
  $datos = json_decode( $cuerpo, true );

  // json_last_error() dice si el decode ha fallado. Es mejor mirarlo que
  // comprobar si $datos es null, porque un JSON valido puede ser "null".
  if ( json_last_error() !== JSON_ERROR_NONE ) {
    return null;
  }

  // el JSON tiene que ser un objeto con los campos de una Medicion
  if ( ! is_array( $datos ) ) {
    return null;
  }

  $medicion = new stdClass();

  // el tipo y el nombre llegan como texto
  $medicion->nombre_emisora = isset( $datos['nombre_emisora'] ) ? $datos['nombre_emisora'] : null;
  $medicion->tipo_medicion  = isset( $datos['tipo_medicion'] )  ? $datos['tipo_medicion']  : null;

  // el numero: si llega como texto ("1234") se convierte con (int),
  // para que valor_medicion sea un numero y no un string
  $medicion->valor_medicion = isset( $datos['valor_medicion'] ) ? (int) $datos['valor_medicion'] : null;

  return $medicion;

} // ()


// ---------------------------------------------------------------------------
// extraerFiltros() : lee el query del GET y saca el FiltroMediciones
//
// Solo se aceptan cuatro filtros: desde, hasta, tipo_medicion y
// valor_medicion.
// Los que no vengan se quedan en null, que significa "sin filtro" (no
// "cero" ni "vacio"). Esto es importante: valor_medicion = null es "traeme
// todas las mediciones", mientras que valor_medicion = 1234 es "traeme solo
// las que valen 1234".
//
// OJO: un filtro que venga pero este vacio (?valor_medicion=) tambien se
// queda en null, porque un parametro vacio no es un filtro, es un error de
// quien escribe la URL. Un filtro de valor_medicion que no sea un numero NO
// se descarta aqui: se pasa tal cual a la logica, que es quien decide si
// vale. Aqui no se validan datos.
//
// query: [ Key: Text ] --> extraerFiltros() --> FiltroMediciones
// ---------------------------------------------------------------------------
function extraerFiltros( $query ) {

  $filtro = new stdClass();

  // el punto de partida es $_GET (que es el query del GET)
  $filtro->desde          = null;
  $filtro->hasta          = null;
  $filtro->tipo_medicion  = null;
  $filtro->valor_medicion = null;

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

  // ---- tipo_medicion ----
  if ( isset( $query['tipo_medicion'] ) && trim( (string) $query['tipo_medicion'] ) !== '' ) {
    $filtro->tipo_medicion = trim( (string) $query['tipo_medicion'] );
  }

  // ---- valor_medicion ----
  // llega como texto porque en un query todo es texto: "1234"
  if ( isset( $query['valor_medicion'] ) && trim( (string) $query['valor_medicion'] ) !== '' ) {
    $filtro->valor_medicion = trim( (string) $query['valor_medicion'] );
  }

  return $filtro;

} // ()

?>
