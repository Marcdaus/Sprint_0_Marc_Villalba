<?php

// ===========================================================================
// stubLogicaDeNegocio.php  --  STUB TEMPORAL, es de las PRUEBAS
//
// QUE ES ESTO: una RÁPIDA sustituta de la logica de negocio. Los endpoints de
// rest/ la necesitan para poder funcionar, pero la logica de verdad es del
// PROMPT 4 y todavia no existe. Este fichero hace de sustituto para que
// los endpoints se puedan probar YA.
//
// EL PROMPT 4 TIENE QUE BORRAR ESTE FICHERO: cuando escriba la logica de
// verdad, los endpoints la cargaran desde logica/ y este stub dejara de
// usarse. Por eso esta aqui, en pruebas/, y no en logica/: el PROMPT 4 no
// tiene que pelearse con el.
//
// QUE HACE, Y QUE NO HACE:
//   - guardarMedicion()   : NO guarda nada. Solo responde 200 con un id.
//   - recuperarMedicion() : NO consulta nada. Devuelve mediciones fijas.
//
// NO VALIDA NADA: no comprueba si el beacon es conocido, ni si el minor
// esta, ni nada de eso. Todo eso es de la logica de verdad (PROMPT 4).
// Este stub solo sirve para comprobar que los endpoints leen, parsean y
// delegan bien.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// ====== VARIABLES MODIFICABLES ======
// El id que devuelve el stub al guardar, y las mediciones de ejemplo que
// devuelve al recuperar. Son de mentira: cambian aqui para que las pruebas
// tengan algo fijo que comparar.
// =====================================

const STUB_ID_GUARDADO = 42;

const STUB_MEDICIONES = [
  [
    'id'             => 1,
    'uuid_beacon'    => 'EPSG-GTI-MARC-3A',
    'nombre_emisora' => 'GTI-3A',
    'major'          => 3584,
    'minor'          => 1234,
    'tx_power'       => 4,
    'rssi'           => -53,
    'fecha'          => '2026-09-29 19:00:00'
  ],
  [
    'id'             => 2,
    'uuid_beacon'    => 'EPSG-GTI-MARC-3A',
    'nombre_emisora' => 'GTI-3A',
    'major'          => 3585,
    'minor'          => 1234,
    'tx_power'       => 4,
    'rssi'           => -60,
    'fecha'          => '2026-09-29 19:00:01'
  ]
];

// ---------------------------------------------------------------------------
// guardarMedicion() : el stub de la logica de negocio
//
// Recibe la Medicion que le ha pasado el endpoint y devuelve un
// RespuestaServidor con 200 y {"ok":true,"id":...}. Como no hay base de
// datos todavia (PROMPT 5), el id es siempre el de STUB_ID_GUARDADO.
//
// m: Medicion --> guardarMedicion() --> RespuestaServidor
// ---------------------------------------------------------------------------
function guardarMedicion( $medicion ) {

  $respuesta = new stdClass();
  $respuesta->codigo = 200;
  $respuesta->cuerpo = json_encode( [ 'ok' => true, 'id' => STUB_ID_GUARDADO ] );

  return $respuesta;

} // ()

// ---------------------------------------------------------------------------
// recuperarMedicion() : el stub de la logica de negocio
//
// Recibe el FiltroMediciones y devuelve un RespuestaServidor con 200 y
// {"mediciones":[...]}. Se devuelven SIEMPRE las mismas dos mediciones de
// ejemplo, se mire el filtro que se mire: este stub no filtra, solo quiere
// comprobar que el endpoint llega hasta aqui y monta bien la respuesta.
//
// f: FiltroMediciones --> recuperarMedicion() --> RespuestaServidor
// ---------------------------------------------------------------------------
function recuperarMedicion( $filtro ) {

  $respuesta = new stdClass();
  $respuesta->codigo = 200;
  $respuesta->cuerpo = json_encode( [ 'mediciones' => STUB_MEDICIONES ] );

  return $respuesta;

} // ()

?>
