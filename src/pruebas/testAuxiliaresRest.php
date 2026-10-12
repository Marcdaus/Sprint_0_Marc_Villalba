<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// =====================================


// ===========================================================================
// testAuxiliaresRest.php  --  PRUEBAS (PROMPT 3)
//
// ###########################################################################
// #  ESTE FICHERO NO FUNCIONA. NO LO EJECUTES.                               #
// #                                                                          #
// # Para usar parsearJSON() y extraerFiltros() hace falta incluir (require)  #
// # los endpoints de rest/, pero al incluirlos se ejecuta tambien su       #
// # codigo, que comprueba el metodo HTTP. En consola no hay metodo HTTP,     #
// # asi que guardarMedicion.php responde un 405 y llama a exit(), que      #
// # corta este script al instante: solo imprime                               #
// # {"error":"Metodo no permitido"} y no llega a hacer ninguna prueba.       #
// #                                                                          #
// # El exit() no se puede tapar con output buffering, de ahi el ob_start()   #
// # de abajo, que no sirve para nada.                                        #
// #                                                                          #
// # Lo que SI funciona es testEndpointsRest.php, que prueba lo mismo por    #
// # http de verdad. Ver la seccion 4 del 00-Leeme.txt.                       #
// #                                                                          #
// # Para arreglar este fichero habria que meter una guardia en los           #
// # endpoints, del tipo:                                                     #
// #                                                                          #
// #     if (realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) { ... }     #
// #                                                                          #
// # alrededor del bloque que atiende la peticion, para que ese bloque solo   #
// # se ejecute cuando al endpoint lo pide el navegador y no otro script.    #
// # De momento se ha decidido no tocar los endpoints.                        #
// ###########################################################################
//
// QUE PRUEBA (si algun dia se arregla): los dos metodos auxiliares de los
// endpoints REST, sin necesidad de arrancar el servidor:
//   - parsearJSON()      : JSON -> Medicion
//   - extraerFiltros()   : query -> FiltroMediciones
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// Las pruebas necesitan las funciones, que estan dentro de los endpoints
// de rest/. Se incluyen con output buffering para que los endpoints no
// escriban su HTML/JSON aqui: solo queremos las funciones.
ob_start();
require_once __DIR__ . '/../communication/guardarMedicion.php';
require_once __DIR__ . '/../communication/recuperarMedicion.php';
ob_end_clean();

// ===========================================================================
// PEQUENA BIBLIOTECA DE PRUEBAS
// ===========================================================================

$GLOBALS['pruebas']  = 0;
$GLOBALS['fallos']   = 0;

// ------------------------------------------------------------------
// comprobar() : dice si la condicion es verdad y lleva la cuenta
// ------------------------------------------------------------------
// condicion: B, texto: Text --> comprobar() -->
function comprobar( $condicion, $texto ) {

  $GLOBALS['pruebas']++;

  if ( $condicion ) {
    printf( "  OK    %s\n", $texto );
  } else {
    $GLOBALS['fallos']++;
    printf( "  FALLO %s\n", $texto );
  }

} // ()

// ===========================================================================
// PRUEBA 1: parsearJSON() con el JSON de verdad de la app
// ===========================================================================

$jsonBueno = '{"nombre_emisora":"GTI-3A","tipo_medicion":"prueba","valor_medicion":1234}';

$m = parsearJSON( $jsonBueno );

comprobar( $m->nombre_emisora === 'GTI-3A',
           "parsearJSON(): el nombre_emisora es GTI-3A" );

comprobar( $m->tipo_medicion === 'prueba',
           "parsearJSON(): el tipo_medicion es prueba" );

comprobar( $m->valor_medicion === 1234,
           "parsearJSON(): el valor_medicion es 1234" );

// el numero tiene que salir como numero, no como texto
comprobar( is_int( $m->valor_medicion ),
           "parsearJSON(): el valor_medicion sale como numero, no como texto" );

// ===========================================================================
// PRUEBA 2: parsearJSON() con JSON malformado -> null (-> 400)
// ===========================================================================

// el caso que de verdad tiene que dar 400
$vacia = parsearJSON( '{ esto no es json ' );

comprobar( $vacia === null,
           "parsearJSON(): con JSON malformado devuelve null (-> 400)" );

// JSON vacio
$vacia = parsearJSON( '' );

comprobar( $vacia === null,
           "parsearJSON(): con el cuerpo vacio devuelve null" );

// un JSON que vale pero no es un objeto de Medicion
$vacia = parsearJSON( '"solo soy un texto"' );

comprobar( $vacia === null,
           "parsearJSON(): con un JSON que no es objeto devuelve null" );

// un JSON con solo algunos campos: los que falten quedan en null
$m = parsearJSON( '{"nombre_emisora":"GTI-3A","valor_medicion":1234}' );

comprobar( $m->nombre_emisora === 'GTI-3A' && $m->valor_medicion === 1234,
           "parsearJSON(): con campos sueltos lee los que hay" );

comprobar( $m->tipo_medicion === null,
           "parsearJSON(): los campos que no llegan se quedan en null (no en 0)" );

// los numeros que llegan como texto se convierten a numero
$m = parsearJSON( '{"tipo_medicion":"prueba","valor_medicion":"1234"}' );

comprobar( $m->valor_medicion === 1234,
           "parsearJSON(): si el numero llega como texto, tambien lo convierte" );

// ===========================================================================
// PRUEBA 3: extraerFiltros() con query y sin query
// ===========================================================================

// ---- con filtros ----
$query = [ 'desde' => '2026-09-29', 'tipo_medicion' => 'prueba' ];

$f = extraerFiltros( $query );

comprobar( $f->desde === '2026-09-29',
           "extraerFiltros(): el filtro 'desde' se lee" );

comprobar( $f->tipo_medicion === 'prueba',
           "extraerFiltros(): el filtro 'tipo_medicion' se lee" );

// los que no vienen se quedan en null = sin filtro
comprobar( $f->hasta === null,
           "extraerFiltros(): 'hasta' no venia, queda en null (sin filtro)" );

comprobar( $f->valor_medicion === null,
           "extraerFiltros(): 'valor_medicion' no venia, queda en null" );

// ---- con los cuatro filtros ----
$query = [ 'desde' => '2026-09-01', 'hasta' => '2026-09-30',
           'tipo_medicion' => 'prueba', 'valor_medicion' => '1234' ];

$f = extraerFiltros( $query );

comprobar( $f->desde === '2026-09-01' && $f->hasta === '2026-09-30',
           "extraerFiltros(): con los cuatro filtros, todos se leen" );

comprobar( $f->tipo_medicion === 'prueba' && $f->valor_medicion === '1234',
           "extraerFiltros(): con los cuatro filtros, tipo y valor tambien" );

// ---- sin query: todo en null ----
$f = extraerFiltros( [] );

comprobar( $f->desde === null && $f->hasta === null
           && $f->tipo_medicion === null && $f->valor_medicion === null,
           "extraerFiltros(): sin query, los cuatro filtros quedan en null" );

// ---- un filtro que viene pero vacio no cuenta como filtro ----
$f = extraerFiltros( [ 'valor_medicion' => '' ] );

comprobar( $f->valor_medicion === null,
           "extraerFiltros(): un filtro vacio (?valor_medicion=) es null, no un filtro" );

// ---- un filtro que no existe se ignora, no peta ----
$f = extraerFiltros( [ 'noExiste' => 'lo que sea' ] );

comprobar( $f->valor_medicion === null,
           "extraerFiltros(): un parametro desconocido se ignora" );

// ===========================================================================
// FIN
// ===========================================================================

printf( "\n-----------------------------------------------------\n" );
printf( " %d pruebas, %d fallos\n", $GLOBALS['pruebas'], $GLOBALS['fallos'] );
printf( "-----------------------------------------------------\n" );

exit( $GLOBALS['fallos'] === 0 ? 0 : 1 );

?>
