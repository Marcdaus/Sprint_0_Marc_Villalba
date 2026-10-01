<?php

// ===========================================================================
// testEndpointsRest.php  --  PRUEBAS (PROMPT 3)
//
// QUE PRUEBA: los dos endpoints REST con peticiones DE VERDAD, a traves de
// un servidor http. No se prueban las funciones sueltas (eso lo hace
// testAuxiliaresRest.php), sino lo que ve el cliente: el codigo HTTP y el
// JSON de la respuesta.
//
// Estas pruebas usan el STUB de logica/guardarMedicion.php y
// logica/recuperarMedicion.php, que todavia no existen, asi que los
// endpoints cargan el stub de pruebas/stubLogicaDeNegocio.php. Con el
// PROMPT 4 puesto, cargaran la logica de verdad y estas pruebas habran que
// cambiar: los valores esperados ya no seran los del stub.
//
// COMO EJECUTARLAS:
//
//   1) Arrancar el servidor en una consola, desde Sprint_0_Marc_Villalba
//      (la carpeta que contiene android/, arduino/, servidor/ y web/):
//
//          php -S localhost:8080 -t .
//
//   2) En otra consola, desde la carpeta pruebas/:
//
//          php testEndpointsRest.php
//
//   3) Parar el servidor con Ctrl+C.
//
// ESTAS PRUEBAS NO LAS EJECUTA LA IA: las corre el propietario.
//
// ###########################################################################
// #  ESTAS PRUEBAS AHORA NECESITAN LA BASE DE DATOS.                        #
// #                                                                          #
// # Antes (PROMPT 3) los endpoints usaban el stub de pruebas/, que           #
// # devolvia datos fijos y no necesitaba MySQL. Desde el PROMPT 4 los        #
// # endpoints usan la logica de negocio DE VERDAD, que hace un INSERT y      #
// # un SELECT contra la tabla mediciones: sin la base de datos proyecto_beacon #
// # (PROMPT 5) y sin MySQL encendido, responden 500 y estas pruebas fallan.  #
// #                                                                          #
// # Ademas, los valores que espera este fichero son los del stub (id 42, dos  #
// # mediciones fijas), y la logica de verdad genera ids y filas de verdad,     #
// # asi que hay que reescribir las comparaciones de aqui abajo.               #
// #                                                                          #
// # Para probar los endpoints AHORA MISMO, sin base de datos, hay que        #
// # probar la logica: pruebas/testLogicaDeNegocio.php, que no necesita nada  #
// # de esto. O bien rehacer estas pruebas para la logica de verdad, cuando    #
// # haya base de datos.                                                      #
// ###########################################################################
//
// QUE COMPRUEBA CADA UNA:
//   - POST guardarMedicion con el JSON bueno   -> 200 {"ok":true,...}
//   - POST guardarMedicion con JSON malformado -> 400
//   - POST guardarMedicion sin cuerpo          -> 400
//   - GET  guardarMedicion (metodo malo)      -> 405
//   - GET  recuperarMedicion                   -> 200 {"mediciones":[...]}
//   - GET  recuperarMedicion con filtros       -> 200 (los filtros no rompen)
//   - POST recuperarMedicion (metodo malo)     -> 405
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// ====== VARIABLES MODIFICABLES ======
// Como y donde se llama al servidor durante las pruebas. Si el servidor
// esta en otro puerto o en otra maquina, se cambia aqui.
// =====================================

const URL_SERVIDOR   = 'http://localhost:8080';
const RUTA_GUARDAR   = '/servidor/rest/guardarMedicion.php';
const RUTA_RECUPERAR = '/servidor/rest/recuperarMedicion.php';

// El JSON que manda la app Android de verdad (el del prompt).
const JSON_MEDICION = '{"uuid_beacon":"EPSG-GTI-MARC-3A","nombre_emisora":"GTI-3A",'
                     . '"major":3584,"minor":1234,"tx_power":4,"rssi":-53}';

// ===========================================================================
// PEQUENA BIBLIOTECA DE PRUEBAS
// ===========================================================================

$GLOBALS['pruebas'] = 0;
$GLOBALS['fallos']  = 0;

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

// ------------------------------------------------------------------
// peticion() : llama al servidor y devuelve codigo HTTP y cuerpo
//
// Devuelve un objeto con ->codigo y ->cuerpo. El cuerpo va en crudo (texto)
// y lo_decodea cada prueba, porque lo que hay que comprobar es que lo que
// llega AL CLIENTE es JSON de verdad.
//
// metodo: Text, ruta: Text, cuerpo: Text --> peticion() --> RespuestaPeticion
// ------------------------------------------------------------------
function peticion( $metodo, $ruta, $cuerpo = null ) {

  $url = URL_SERVIDOR . $ruta;

  // curl es lo que hay para hablar con un servidor http desde php
  $curl = curl_init( $url );

  // true = que curl devuelva el cuerpo en vez de imprimirlo
  curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
  curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, $metodo );

  if ( $cuerpo !== null ) {
    curl_setopt( $curl, CURLOPT_POSTFIELDS, $cuerpo );
    // sin esto, algunos servidores no leen php://input si no saben que
    // es un JSON
    curl_setopt( $curl, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ] );
  }

  $respuestaCurl = curl_exec( $curl );

  // el codigo HTTP de verdad, que es lo que hay que comprobar
  $codigo = curl_getinfo( $curl, CURLINFO_HTTP_CODE );

  $error = curl_error( $curl );
  curl_close( $curl );

  $r = new stdClass();
  $r->codigo = $codigo;
  $r->cuerpo = $respuestaCurl;

  // si no se pudo contactar con el servidor, avisar claro: si no, el fallo
  // parece otro y se pierde el tiempo buscando en el codigo
  if ( $error !== '' ) {
    $r->error = $error;
  }

  return $r;

} // ()

// ------------------------------------------------------------------
// avisoServidorCaido() : comprueba que el servidor esta en marcha
// ------------------------------------------------------------------
// URL: Text --> avisoServidorCaido() -->
function avisoServidorCaido() {

  $r = peticion( 'GET', RUTA_RECUPERAR );

  if ( isset( $r->error ) ) {
    printf( "\n  NO SE HA PODIDO CONECTAR CON %s\n", URL_SERVIDOR );
    printf( "  %s\n", $r->error );
    printf( "\n  Arranca el servidor en otra consola, desde la raiz del esqueleto:\n" );
    printf( "      php -S localhost:8080 -t .\n\n" );
    exit( 1 );
  }

} // ()

avisoServidorCaido();

// ===========================================================================
// PRUEBA 1: POST guardarMedicion con el JSON de la app -> 200
// ===========================================================================

$r = peticion( 'POST', RUTA_GUARDAR, JSON_MEDICION );

comprobar( $r->codigo === 200,
           "POST guardarMedicion con JSON bueno -> 200 (llega " . $r->codigo . ")" );

$json = json_decode( $r->cuerpo, true );

comprobar( is_array( $json ),
           "la respuesta del POST es JSON (y no texto suelto)" );

comprobar( isset( $json['ok'] ) && $json['ok'] === true,
           'la respuesta trae {"ok":true}' );

comprobar( isset( $json['id'] ),
           'la respuesta trae el "id" de la medicion guardada' );

// ===========================================================================
// PRUEBA 2: POST guardarMedicion con JSON malformado -> 400
// ===========================================================================

$r = peticion( 'POST', RUTA_GUARDAR, '{ esto no es json ' );

comprobar( $r->codigo === 400,
           "POST guardarMedicion con JSON roto -> 400 (llega " . $r->codigo . ")" );

$json = json_decode( $r->cuerpo, true );

comprobar( isset( $json['error'] ),
           'el 400 trae un campo "error"' );

// sin cuerpo tampoco vale
$r = peticion( 'POST', RUTA_GUARDAR, '' );

comprobar( $r->codigo === 400,
           "POST guardarMedicion sin cuerpo -> 400 (llega " . $r->codigo . ")" );

// ===========================================================================
// PRUEBA 3: GET sobre guardarMedicion -> 405
// ===========================================================================

$r = peticion( 'GET', RUTA_GUARDAR );

comprobar( $r->codigo === 405,
           "GET sobre guardarMedicion -> 405 (llega " . $r->codigo . ")" );

$json = json_decode( $r->cuerpo, true );

comprobar( isset( $json['error'] ) && $json['error'] === 'Metodo no permitido',
           'el 405 dice "Metodo no permitido"' );

// ===========================================================================
// PRUEBA 4: GET recuperarMedicion -> 200 con las mediciones
// ===========================================================================

$r = peticion( 'GET', RUTA_RECUPERAR );

comprobar( $r->codigo === 200,
           "GET recuperarMedicion -> 200 (llega " . $r->codigo . ")" );

$json = json_decode( $r->cuerpo, true );

comprobar( isset( $json['mediciones'] ) && is_array( $json['mediciones'] ),
           'la respuesta del GET trae "mediciones" y es una lista' );

comprobar( count( $json['mediciones'] ?? [] ) > 0,
           'la lista de mediciones no viene vacia (el stub devuelve dos)' );

// la primera medicion del stub, con el uuid del proyecto
$primera = $json['mediciones'][0] ?? null;

comprobar( $primera !== null && $primera['uuid_beacon'] === 'EPSG-GTI-MARC-3A',
           'la medicion trae el uuid del proyecto, EPSG-GTI-MARC-3A' );

comprobar( $primera !== null && $primera['minor'] === 1234,
           'la medicion trae el minor 1234' );

// ===========================================================================
// PRUEBA 5: GET recuperarMedicion con filtros -> 200
//
// El stub no filtra (eso es del PROMPT 4), asi que aqui solo se comprueba
// que mandar filtros NO rompe el endpoint: sigue contestando 200 y con
// mediciones. Cuando la logica de verdad este, estas dos pruebas hay que
// reescribir para comprobar que el filtro se aplica de verdad.
// ===========================================================================

$r = peticion( 'GET', RUTA_RECUPERAR . '?desde=2026-09-01&minor=1234' );

comprobar( $r->codigo === 200,
           "GET recuperarMedicion con filtros -> 200 (llega " . $r->codigo . ")" );

$json = json_decode( $r->cuerpo, true );

comprobar( isset( $json['mediciones'] ),
           'con filtros, la respuesta sigue traer "mediciones"' );

$r = peticion( 'GET', RUTA_RECUPERAR . '?uuid_beacon=EPSG-GTI-MARC-3A&desde=a&hasta=b' );

comprobar( $r->codigo === 200,
           "GET recuperarMedicion con los cuatro filtros -> 200 (llega " . $r->codigo . ")" );

// ===========================================================================
// PRUEBA 6: POST sobre recuperarMedicion -> 405
// ===========================================================================

$r = peticion( 'POST', RUTA_RECUPERAR, JSON_MEDICION );

comprobar( $r->codigo === 405,
           "POST sobre recuperarMedicion -> 405 (llega " . $r->codigo . ")" );

$json = json_decode( $r->cuerpo, true );

comprobar( isset( $json['error'] ) && $json['error'] === 'Metodo no permitido',
           'el 405 de recuperarMedicion tambien dice "Metodo no permitido"' );

// ===========================================================================
// FIN
// ===========================================================================

printf( "\n-----------------------------------------------------\n" );
printf( " %d pruebas, %d fallos\n", $GLOBALS['pruebas'], $GLOBALS['fallos'] );
printf( "-----------------------------------------------------\n" );

// OJO: con el PROMPT 4 puesto, la logica de verdad validara el beacon y el
// minor, y estas pruebas dejaran de servir tal cual: habra que cambiarlas
// por las de la logica de verdad.
printf( "\n AVISO: estas pruebas usan el STUB. Con el PROMPT 4 puesto, la\n" );
printf( "        logica de verdad validara los datos y estas pruebas\n" );
printf( "        tendran que reescribirse.\n" );

exit( $GLOBALS['fallos'] === 0 ? 0 : 1 );

?>
