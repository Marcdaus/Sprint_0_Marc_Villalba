<?php

// ===========================================================================
// LogicaDeNegocio.php  --  PROMPT 4 (logica de negocio, la verdadera)
//
// ESTE FICHERO ES EL CORAZON DEL SERVIDOR. Aqui viven las dos unicas
// funciones publicas que usan los endpoints REST (PROMPT 3):
//
//     guardarMedicion( Medicion m )       -> RespuestaServidor
//     recuperarMedicion( FiltroMediciones f ) -> RespuestaServidor
//
// Que hace cada una, paso a paso:
//
//   guardarMedicion(m)
//     1. VALIDAR las reglas de negocio RN1 y RN2. Si alguna falla, se
//        responde 400 y NO se inserta NADA.
//     2. INSERTAR la medicion. El INSERT NO lleva la columna fecha: la
//        pone la base de datos sola (RN3).
//     3. Responder 200 con el id que ha generado la BD.
//
//   recuperarMedicion(f)
//     1. Montar el SELECT.
//     2. Añadir el WHERE SOLO con los filtros que venir informedos
//        (todos son opcionales).
//     3. ORDER BY fecha DESC LIMIT <limiteConsulta>.
//     4. Responder 200 con la lista de mediciones.
//
// REGLAS DE NEGOCIO (RN):
//   RN1  la uuid_beacon tiene que ser la conocida (EPSG-GTI-MARC-3A).
//   RN2  el minor es obligatorio y entero. NO se comprueba contra un valor
//        fijo: hoy es 1234, pero es un dato del proyecto que se puede
//        cambiar para la demo, asi que llega desde la app.
//   RN3  la fecha NO la manda ni la app ni el servidor: la pone la BD con
//        DEFAULT CURRENT_TIMESTAMP, asi que el INSERT no la lleva.
//   RN4  acceso publico: no hay sesion ni login.
//   RN5  siempre se responde con un codigo HTTP y un JSON correctos.
//
// QUE HAY DENTRO, ADEMAS DE LAS DOS PUBLICAS: unas cuantas funciones
// internas (conexionBD, validaMedicion, respuestaJSON, respuestaError,
// valorDeFiltro, limitesDeFecha) que son detalles de implementacion. En PHP
// plano no existen las funciones privadas, asi que estan marcadas como
// internas en el comentario y no forman parte del contrato: lo publico es
// solo guardarMedicion() y recuperarMedicion().
//
// ACCESO A LA BD: con PDO y driver MySQL, y SIEMPRE con consultas
// parametrizadas (con ? y execute(array), nunca con interpolacion de
// valores dentro del SQL), para que no haya inyeccion SQL.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// ====== VARIABLES MODIFICABLES ======
// La uuid que se da por buena (RN1) y cuantas mediciones devuelve como
// mucho la consulta. El valor del minor NO se configura aqui: es el que
// manda la app en cada medicion (RN2 no lo fija a un numero).
$uuidConocido   = 'EPSG-GTI-MARC-3A';
$limiteConsulta = 50;   // ultimas mediciones
// =====================================


// ---------------------------------------------------------------------------
// conexionBD() : devuelve el PDO para trabajar con la base de datos
//
// Crea la conexion la primera vez y la guarda, para no abrirla en cada
// llamada. Lee los datos de configuracion.php.
//
// Devuelve: PDO --> conexionBD() --> PDO
//
// OJO, esto es lo que permite hacer las pruebas sin base de datos: si ya
// hay una variable global $pdo puesta, se USA ESA (las pruebas ponen
// una falsa) en vez de intentar conectarse de verdad. Asi las RN se pueden
// comprobar sin tener MySQL encendido.
// ---------------------------------------------------------------------------
function conexionBD() {

  // sin esto, dentro de la funcion $pdo seria una variable nueva y no se
  // veria la de fuera
  global $pdo;

  // ¿ya hay una conexion? (la real, de una llamada anterior, o la falsa
  // que hayan puesto las pruebas)
  if ( isset( $pdo ) && is_object( $pdo ) ) {
    return $pdo;
  }

  // require y NO require_once: con require_once la segunda vez se devuelve
  // true en vez de lo que devuelve el fichero, y perderiamos la config
  $config = require __DIR__ . '/configuracion.php';

  $dsn = sprintf( 'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                  $config->host, $config->puerto, $config->nombre, $config->charset );

  $pdo = new PDO( $dsn, $config->usuario, $config->contrasena, [

    // ERRMODE_EXCEPTION: hace que los errores de MySQL salten como
    // PDOException. Sin esto, PDO callaria en vez deavisar y el try/catch
    // de abajo no serviria de nada.
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

    // las filas como array asociativo (uuid_beacon => '...')
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

    // prepares de verdad, no emulados: los ? se tratan en el servidor de
    // MySQL y no se puede colar codigo por ahi
    PDO::ATTR_EMULATE_PREPARES => false

  ] );

  return $pdo;

} // ()


// ---------------------------------------------------------------------------
// validaMedicion() : comprueba las reglas de negocio antes de insertar
//
// Es la barrera RN1 y RN2. Solo la usa guardarMedicion(), justo antes del
// INSERT, para que una medicion invalida NUNCA llegue a la base de datos.
//
// m: Medicion --> validaMedicion() --> ( ok: B, motivo: Text? )
// ---------------------------------------------------------------------------
function validaMedicion( $m ) {

  global $uuidConocido;

  $validacion = new stdClass();
  $validacion->ok     = true;
  $validacion->motivo = null;

  // ---- RN1: la uuid tiene que ser la que conocemos ----
  if ( $m->uuid_beacon !== $uuidConocido ) {
    $validacion->ok     = false;
    $validacion->motivo = 'uuid_beacon no reconocida';
    return $validacion;
  }

  // ---- RN2: el minor es obligatorio ----
  if ( $m->minor === null ) {
    $validacion->ok     = false;
    $validacion->motivo = 'falta el minor';
    return $validacion;
  }

  // ---- RN2: y ademas tiene que ser un entero de verdad ----
  // si llega "1234" como texto no vale: minor es un numero (SmallInt)
  if ( ! is_int( $m->minor ) ) {
    $validacion->ok     = false;
    $validacion->motivo = 'el minor tiene que ser un numero entero';
    return $validacion;
  }

  return $validacion;

} // ()


// ---------------------------------------------------------------------------
// respuestaJSON() : monta la RespuestaServidor que esperan los endpoints
//
// Los endpoints del PROMPT 3 hacen echo de $respuesta->cuerpo, asi que el
// cuerpo tiene que ser el JSON YA en texto, no un array de PHP.
//
// datos: Text --> respuestaJSON( codigo: N, datos: Text ) --> RespuestaServidor
// ---------------------------------------------------------------------------
function respuestaJSON( $codigo, $datos ) {

  $respuesta = new stdClass();
  $respuesta->codigo = $codigo;
  // JSON_UNESCAPED_UNICODE: deja los acentos como acentos, para que el
  // error "falta el minor" o "medicion" se lea bien en el log del movil
  $respuesta->cuerpo = json_encode( $datos, JSON_UNESCAPED_UNICODE );

  return $respuesta;

} // ()


// ---------------------------------------------------------------------------
// respuestaError() : atajo para responder solo con un {"error": "..."}
//
// codigo: N, motivo: Text --> respuestaError() --> RespuestaServidor
// ---------------------------------------------------------------------------
function respuestaError( $codigo, $motivo ) {

  return respuestaJSON( $codigo, [ 'error' => $motivo ] );

} // ()


// ---------------------------------------------------------------------------
// limitesDeFecha() : prepara los filtros desde y hasta para el WHERE
//
// La fecha de la BD es un DATETIME, pero los filtros llegan como texto. Si
// el filtro es solo un dia ("2026-09-29"), hay que decidir hasta donde
// llega ese dia: con desde se empieza a las 00:00:00 y con hasta se
// acaba a las 23:59:59. Si el filtro ya trae hora, se deja tal cual.
//
// Devuelve: desde: Text?, hasta: Text? --> limitesDeFecha() --> ( desde: Text?, hasta: Text? )
// ---------------------------------------------------------------------------
function limitesDeFecha( $desde, $hasta ) {

  // ^\d{4}-\d{2}-\d{2}$ = son solo 10 digitos con dos guiones: AAAA-MM-DD
  $esSoloDia = '/^\d{4}-\d{2}-\d{2}$/';

  $desdeAmpliado = $desde;
  $hastaAmpliado = $hasta;

  if ( $desde !== null && preg_match( $esSoloDia, $desde ) ) {
    $desdeAmpliado = $desde . ' 00:00:00';
  }

  if ( $hasta !== null && preg_match( $esSoloDia, $hasta ) ) {
    $hastaAmpliado = $hasta . ' 23:59:59';
  }

  $limites = new stdClass();
  $limites->desde = $desdeAmpliado;
  $limites->hasta = $hastaAmpliado;

  return $limites;

} // ()


// ---------------------------------------------------------------------------
// guardarMedicion() : valida y guarda una medicion en la base de datos
//
// Recibe la Medicion que le pasa el endpoint REST y devuelve lo que el
// endpoint tiene que responderle al cliente: codigo HTTP y JSON.
//
// La fecha NO la pone este codigo: la pone la BD (RN3).
//
// m: Medicion --> guardarMedicion() --> RespuestaServidor
// ---------------------------------------------------------------------------
function guardarMedicion( $medicion ) {

  // ---------------------------------------------------------------
  // 1. VALIDAR las RN. Si falla, 400 y NO se inserta nada
  // ---------------------------------------------------------------
  $validacion = validaMedicion( $medicion );

  if ( $validacion->ok !== true ) {
    return respuestaError( 400, $validacion->motivo );
  }

  try {

    $pdo = conexionBD();

    // ---------------------------------------------------------------
    // 2. INSERTAR. Ojo: la lista de columnas NO lleva fecha (RN3), y los
    //    seis valores van con ? : nada se pega dentro del SQL.
    // ---------------------------------------------------------------
    $sql = 'INSERT INTO mediciones
              (uuid_beacon, nombre_emisora, major, minor, tx_power, rssi)
            VALUES (?, ?, ?, ?, ?, ?)';

    $sentencia = $pdo->prepare( $sql );
    $sentencia->execute( [ $medicion->uuid_beacon,
                           $medicion->nombre_emisora,
                           $medicion->major,
                           $medicion->minor,
                           $medicion->tx_power,
                           $medicion->rssi ] );

    // el id lo genera la BD (AUTO_INCREMENT)
    $id = (int) $pdo->lastInsertId();

    // ---------------------------------------------------------------
    // 3. RESPONDER 200 con el id guardado
    // ---------------------------------------------------------------
    return respuestaJSON( 200, [ 'ok' => true, 'id' => $id ] );

  } catch ( PDOException $excepcion ) {

    // el error de verdad se escribe en el log del servidor, pero al
    // cliente no se le enseña: podria estar falando de la estructura de
    // la base de datos, y el movil no necesita saber eso
    error_log( 'guardarMedicion: ' . $excepcion->getMessage() );

    return respuestaError( 500, 'Error al guardar la medicion' );

  }

} // ()


// ---------------------------------------------------------------------------
// recuperarMedicion() : devuelve las mediciones, filtradas y ordenadas
//
// Recibe el FiltroMediciones que le pasa el endpoint REST (todos sus
// campos son opcionales) y devuelve las mediciones de la BD.
//
// Los filtros que no vienen informedos NO se ponen en el WHERE: sin minor
// no se filtra por minor, y asi.
// La fecha es de la BD, no la manda la app.
//
// f: FiltroMediciones --> recuperarMedicion() --> RespuestaServidor
// ---------------------------------------------------------------------------
function recuperarMedicion( $filtro ) {

  global $limiteConsulta;

  try {

    $pdo = conexionBD();

    // ---------------------------------------------------------------
    // 1. SELECT base
    // ---------------------------------------------------------------
    $columnas = 'uuid_beacon, nombre_emisora, major, minor, tx_power, rssi, fecha';
    $sql = 'SELECT ' . $columnas . ' FROM mediciones';

    $condiciones = [];
    $parametros  = [];

    // ---------------------------------------------------------------
    // 2. WHERE, SOLO con los filtros que vienen informedos
    // ---------------------------------------------------------------
    $limites = limitesDeFecha( $filtro->desde, $filtro->hasta );

    if ( $limites->desde !== null ) {
      $condiciones[] = 'fecha >= ?';
      $parametros[]  = $limites->desde;
    }

    if ( $limites->hasta !== null ) {
      $condiciones[] = 'fecha <= ?';
      $parametros[]  = $limites->hasta;
    }

    if ( $filtro->uuid_beacon !== null ) {
      $condiciones[] = 'uuid_beacon = ?';
      $parametros[]  = $filtro->uuid_beacon;
    }

    if ( $filtro->minor !== null ) {
      $condiciones[] = 'minor = ?';
      $parametros[]  = (int) $filtro->minor;
    }

    if ( count( $condiciones ) > 0 ) {
      $sql .= ' WHERE ' . implode( ' AND ', $condiciones );
    }

    // ---------------------------------------------------------------
    // 3. las ultimas primero, y con tope. El limite se pasa como numero
    //    ya convertido con (int), asi que no cabe SQL injection aqui
    // ---------------------------------------------------------------
    $sql .= ' ORDER BY fecha DESC LIMIT ' . (int) $limiteConsulta;

    $sentencia = $pdo->prepare( $sql );
    $sentencia->execute( $parametros );

    $filas = $sentencia->fetchAll();

    // se montan una a una para dejar los numeros como numeros en el JSON
    // (major, minor, tx_power y rssi), y no como texto
    $mediciones = [];

    foreach ( $filas as $fila ) {

      $medicion = new stdClass();
      $medicion->uuid_beacon    = $fila['uuid_beacon'];
      $medicion->nombre_emisora = $fila['nombre_emisora'];
      $medicion->major          = (int) $fila['major'];
      $medicion->minor          = (int) $fila['minor'];
      $medicion->tx_power       = (int) $fila['tx_power'];
      $medicion->rssi           = (int) $fila['rssi'];
      $medicion->fecha          = $fila['fecha'];

      $mediciones[] = $medicion;

    }

    // ---------------------------------------------------------------
    // 4. RESPONDER 200 con la lista
    // ---------------------------------------------------------------
    return respuestaJSON( 200, [ 'mediciones' => $mediciones ] );

  } catch ( PDOException $excepcion ) {

    error_log( 'recuperarMedicion: ' . $excepcion->getMessage() );

    return respuestaError( 500, 'Error al recuperar las mediciones' );

  }

} // ()

?>
