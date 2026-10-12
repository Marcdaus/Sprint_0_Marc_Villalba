<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// =====================================


// ===========================================================================
// testLogicaDeNegocio.php  --  PRUEBAS (PROMPT 4)
//
// QUE PRUEBA: la logica de negocio de verdad, la de logica/LogicaDeNegocio.php,
// complying las reglas de negocio RN1..RN3.
//
// COMO ESTA HECHO, Y POR QUE: estas pruebas NO usan una base de datos de
// verdad, sino un PDO FALSO (la clase PDOFalso de mas abajo). El PDO falso
// se parece lo justo a un PDO real: tiene prepare(), execute(),
// fetchAll() y lastInsertId(), pero en vez de hablar con MySQL se apunta
// todo en memoria. Gracias a eso se pueden comprobar las RN sin tener
// XAMPP encendido, y sin instalar ni composer ni PHPUnit.
//
// COMO SE CONECHA EL PDO FALSO: conexionBD() de LogicaDeNegocio.php usa la
// variable global $pdo si ya hay una puesta. Estas pruebas la ponen antes
// de cada caso, y por eso el codigo real de la base de datos no se ejecuta
// ni se intenta.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

require_once __DIR__ . '/../logica/LogicaDeNegocio.php';


// ===========================================================================
// LOS DOBLES: EL PDO FALSO Y SU SENTENCIA
// ===========================================================================

// ---------------------------------------------------------------------------
// SentenciaFalsa : la sentencia que devuelve PDOFalso::prepare()
//
// Guarda el SQL que se le ha pedido y los valores con los que se ejecuta,
// para que las pruebas puedan mirar despues que SQL se construyo y con que
// parametros (que es como se comprueba que el INSERT no lleva la fecha y que
// los valores van parametrizados, y no pegados dentro del SQL).
// ---------------------------------------------------------------------------
class SentenciaFalsa {

  public $sql        = '';
  public $parametros = null;
  public $esInsert   = false;
  public $pdo        = null;

  function __construct( $pdo, $sql ) {

    $this->pdo      = $pdo;
    $this->sql      = $sql;
    $this->esInsert = ( stripos( ltrim( $sql ), 'INSERT' ) === 0 );

  } // ()

  function execute( $parametros ) {

    $this->parametros = $parametros;
    $this->pdo->ejecuciones++;

    // el fallo se produce aqui, para probar el 500
    if ( $this->pdo->explotarEn === 'execute' ) {
      throw new PDOException( 'MySQL ha muerto' );
    }

    if ( $this->esInsert ) {
      $this->pdo->inserts++;
    }

    return true;

  } // ()

  function fetchAll() {

    if ( $this->pdo->explotarEn === 'fetch' ) {
      throw new PDOException( 'MySQL ha muerto' );
    }

    return $this->pdo->filas;

  } // ()

} // ()

// ---------------------------------------------------------------------------
// PDOFalso : un PDO que no es un PDO, pero se porta como si lo fuera
//
// explodingEn es para probar el camino del 500:
//   null      -> no falla nunca
//   'prepare' -> falla al preparar
//   'execute' -> falla al ejecutar
//   'fetch'   -> falla al leer las filas
// ---------------------------------------------------------------------------
class PDOFalso {

  public $sentencias         = array();
  public $filas              = array();
  public $ultimoIdInsertado  = 7;
  public $explotarEn         = null;
  public $ejecuciones        = 0;
  public $inserts            = 0;

  function prepare( $sql ) {

    if ( $this->explotarEn === 'prepare' ) {
      throw new PDOException( 'MySQL ha muerto' );
    }

    $sentencia = new SentenciaFalsa( $this, $sql );
    $this->sentencias[] = $sentencia;

    return $sentencia;

  } // ()

  function lastInsertId() {
    return $this->ultimoIdInsertado;
  }

  function numeroDeSentencias() {
    return count( $this->sentencias );
  }

  function ultimaSentencia() {
    return end( $this->sentencias );
  }

} // ()


// ===========================================================================
// PEQUENA BIBLIOTECA DE PRUEBAS
// ===========================================================================

$GLOBALS['pruebas'] = 0;
$GLOBALS['fallos']  = 0;

// ---------------------------------------------------------------------------
// comprobar() : dice si la condicion es verdad y lleva la cuenta
// ---------------------------------------------------------------------------
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

function titulo( $texto ) {
  printf( "\n%s\n", $texto );
}

// ---------------------------------------------------------------------------
// ponerPDOFalso() : deja un PDO falso limpio antes de cada caso
// ---------------------------------------------------------------------------
// PDOFalso --> ponerPDOFalso() --> PDOFalso
function ponerPDOFalso() {

  $falso = new PDOFalso();
  $GLOBALS['pdo'] = $falso;   // global, que es donde lo mira conexionBD()

  return $falso;

} // ()

// ---------------------------------------------------------------------------
// medicionDePrueba() : una Medicion valida, para no repetirla en cada caso
// ---------------------------------------------------------------------------
// sobreescribe: Text?, valor: Z? --> medicionDePrueba() --> Medicion
function medicionDePrueba( $campo = null, $valor = null ) {

  $m = new stdClass();
  $m->nombre_emisora = 'GTI-3A';
  $m->tipo_medicion  = 'prueba';
  $m->valor_medicion = 1234;

  if ( $campo !== null ) {
    $m->$campo = $valor;
  }

  return $m;

} // ()

// ---------------------------------------------------------------------------
// filtroDePrueba() : un FiltroMediciones vacio (todos los filtros opcionales)
// ---------------------------------------------------------------------------
// desde: Text?, hasta: Text?, tipo_medicion: Text?, valor_medicion: Z? --> filtroDePrueba() --> FiltroMediciones
function filtroDePrueba( $desde = null, $hasta = null, $tipo = null, $valor = null ) {

  $f = new stdClass();
  $f->desde          = $desde;
  $f->hasta          = $hasta;
  $f->tipo_medicion  = $tipo;
  $f->valor_medicion = $valor;

  return $f;

} // ()

// ---------------------------------------------------------------------------
// cuerpoDecodificado() : pasa el cuerpo de la respuesta a array
// ---------------------------------------------------------------------------
// RespuestaServidor --> cuerpoDecodificado() --> Text
function cuerpoDecodificado( $respuesta ) {
  return json_decode( $respuesta->cuerpo, true );
}


// ===========================================================================
// RN1: el tipo tiene que ser uno de los conocidos
// ===========================================================================

titulo( 'RN1: el tipo_medicion tiene que ser uno de los conocidos' );

$falso = ponerPDOFalso();

$r = guardarMedicion( medicionDePrueba( 'tipo_medicion', 'humedad' ) );

comprobar( $r->codigo === 400, 'un tipo desconocido -> 400' );

$json = cuerpoDecodificado( $r );
comprobar( isset( $json['error'] ), 'el 400 trae un campo "error"' );

comprobar( $falso->numeroDeSentencias() === 0,
           'NO se prepara ninguna sentencia: no se inserta nada' );

comprobar( $falso->ejecuciones === 0,
           'NO se ejecuta nada contra la base de datos' );

// el nombre del error tiene que decir que el tipo no lo reconoce
comprobar( strpos( (string) ( $json['error'] ?? '' ), 'tipo' ) !== false,
           'el motivo del error habla del tipo' );


titulo( 'RN1: con un tipo conocido si se guarda' );

foreach ( [ 'CO2', 'temperatura', 'prueba' ] as $tipoValido ) {

  $falso = ponerPDOFalso();

  $r = guardarMedicion( medicionDePrueba( 'tipo_medicion', $tipoValido ) );

  comprobar( $r->codigo === 200, 'el tipo "' . $tipoValido . '" es valido -> 200' );

  comprobar( $falso->inserts === 1, 'se inserta exactamente una fila' );

}


// ===========================================================================
// RN2: el valor_medicion es obligatorio y entero
// ===========================================================================

titulo( 'RN2: el valor_medicion es obligatorio' );

$falso = ponerPDOFalso();

$r = guardarMedicion( medicionDePrueba( 'valor_medicion', null ) );

comprobar( $r->codigo === 400, 'sin valor_medicion -> 400' );

comprobar( $falso->numeroDeSentencias() === 0,
           'sin valor_medicion NO se inserta nada' );

$json = cuerpoDecodificado( $r );
comprobar( strpos( (string) ( $json['error'] ?? '' ), 'valor' ) !== false,
           'el motivo del error habla del valor' );


titulo( 'RN2: el valor_medicion tiene que ser un entero, no texto' );

$falso = ponerPDOFalso();

$r = guardarMedicion( medicionDePrueba( 'valor_medicion', '1234' ) );

comprobar( $r->codigo === 400, 'un valor_medicion de texto -> 400' );

comprobar( $falso->numeroDeSentencias() === 0,
           'un valor_medicion de texto NO se inserta' );


titulo( 'RN2: el valor_medicion NO se valida contra un valor fijo' );

// aqui esta el fondo de la regla: hoy vale 1234, pero el valor es del
// proyecto y se puede cambiar para la demo, asi que CUALQUIER entero vale

$falso = ponerPDOFalso();
$r = guardarMedicion( medicionDePrueba( 'valor_medicion', 999 ) );
comprobar( $r->codigo === 200, 'un valor_medicion 999 (distinto de 1234) -> 200' );

$falso = ponerPDOFalso();
$r = guardarMedicion( medicionDePrueba( 'valor_medicion', 0 ) );
comprobar( $r->codigo === 200, 'un valor_medicion 0 -> 200' );

$falso = ponerPDOFalso();
$r = guardarMedicion( medicionDePrueba( 'valor_medicion', -7 ) );
comprobar( $r->codigo === 200, 'un valor_medicion negativo -> 200' );


// ===========================================================================
// RN3: la fecha la pone la BD, no el codigo
// ===========================================================================

titulo( 'RN3: el INSERT no puede llevar la columna fecha' );

$falso = ponerPDOFalso();
guardarMedicion( medicionDePrueba() );

$sql = $falso->ultimaSentencia()->sql;

comprobar( stripos( $sql, 'fecha' ) === false,
           'el SQL del INSERT no menciona la palabra fecha en ninguna parte' );

comprobar( substr_count( $sql, '?' ) === 3,
           'el INSERT lleva 3 interrogantes, uno por cada columna' );

comprobar( stripos( $sql, 'INSERT INTO mediciones' ) === 0,
           'el INSERT es sobre la tabla mediciones' );

comprobar( stripos( $sql, 'VALUES' ) !== false,
           'el INSERT usa VALUES' );

// los tres valores van en el mismo orden que las tres columnas
$esperados = [ 'GTI-3A', 'prueba', 1234 ];
$recibidos = $falso->ultimaSentencia()->parametros;

comprobar( $recibidos === $esperados,
           'los 3 parametros van en el orden de las columnas' );

// los valores NO van pegados dentro del SQL: van aparte (parametrizado)
comprobar( strpos( $sql, 'GTI-3A' ) === false
           && strpos( $sql, '1234' ) === false,
           'ningun valor esta pegado dentro del SQL (consulta parametrizada)' );


titulo( 'RN3: la respuesta lleva el id que ha generado la BD' );

$falso = ponerPDOFalso();
$falso->ultimoIdInsertado = 42;

$r = guardarMedicion( medicionDePrueba() );
$json = cuerpoDecodificado( $r );

comprobar( $json['ok'] === true, 'la respuesta trae {"ok":true}' );
comprobar( $json['id'] === 42, 'la respuesta trae el id que devolvio la BD' );
comprobar( is_int( $json['id'] ), 'el id sale como numero' );
comprobar( is_string( $r->cuerpo ),
           'el cuerpo es texto (un JSON), porque el endpoint hace echo de el' );


// ===========================================================================
// recuperarMedicion: los filtros son opcionales
// ===========================================================================

titulo( 'recuperarMedicion: sin filtros, se devuelven todas' );

$falso = ponerPDOFalso();
$falso->filas = [
  [ 'id' => '1', 'nombre_emisora' => 'GTI-3A', 'tipo_medicion' => 'prueba',
    'valor_medicion' => '1234', 'fecha' => '2026-09-29 10:15:00' ]
];

$r = recuperarMedicion( filtroDePrueba() );
$sql = $falso->ultimaSentencia()->sql;
$json = cuerpoDecodificado( $r );

comprobar( $r->codigo === 200, 'sin filtros -> 200' );

comprobar( stripos( $sql, 'WHERE' ) === false,
           'sin filtros, el SELECT no lleva WHERE' );

comprobar( $falso->ultimaSentencia()->parametros === array(),
           'sin filtros, no se manda ningun parametro' );

comprobar( stripos( $sql, 'ORDER BY fecha DESC' ) !== false,
           'el SELECT ordena por fecha DESC' );

comprobar( preg_match( '/LIMIT\s+50/', $sql ) === 1,
           'el SELECT trae LIMIT 50' );

comprobar( isset( $json['mediciones'] ) && is_array( $json['mediciones'] ),
           'la respuesta trae "mediciones" y es una lista' );

comprobar( count( $json['mediciones'] ) === 1, 'viene la fila que hay en la BD' );

// los numeros del SELECT salen como numeros en el JSON, no como texto
$medicion = $json['mediciones'][0];

comprobar( $medicion['valor_medicion'] === 1234 && is_int( $medicion['valor_medicion'] ),
           'el valor_medicion sale como numero, no como texto' );

comprobar( $medicion['tipo_medicion'] === 'prueba',
           'el tipo_medicion sale como el texto que hay en la BD' );

comprobar( $medicion['fecha'] === '2026-09-29 10:15:00',
           'la fecha viene de la BD, tal cual' );

comprobar( isset( $medicion['nombre_emisora'] ) && isset( $medicion['tipo_medicion'] )
           && isset( $medicion['valor_medicion'] ),
           'la medicion trae los tres campos de la medicion mas el id y la fecha' );

comprobar( $medicion['id'] === 1 && is_int( $medicion['id'] ),
           'la medicion trae el id, que es lo que pinta la web en la primera columna' );


titulo( 'recuperarMedicion: con un solo filtro, solo ese filtro' );

$falso = ponerPDOFalso();
$r = recuperarMedicion( filtroDePrueba( null, null, null, 1234 ) );
$sentencia = $falso->ultimaSentencia();

comprobar( stripos( $sentencia->sql, 'WHERE' ) !== false,
           'con filtro de valor_medicion, el SELECT lleva WHERE' );

comprobar( strpos( $sentencia->sql, 'valor_medicion = ?' ) !== false,
           'la condicion del WHERE es "valor_medicion = ?"' );

comprobar( stripos( $sentencia->sql, 'tipo_medicion = ?' ) === false,
           'NO aparece un filtro de tipo que no se ha pedido' );

comprobar( stripos( $sentencia->sql, 'fecha >=' ) === false
           && stripos( $sentencia->sql, 'fecha <=' ) === false,
           'NO aparecen filtros de fecha si no se han pedido' );

comprobar( $sentencia->parametros === array( 1234 ),
           'solo se manda el valor del valor_medicion, y como numero' );


titulo( 'recuperarMedicion: con los cuatro filtros, los cuatro' );

$falso = ponerPDOFalso();
$r = recuperarMedicion( filtroDePrueba( '2026-09-01', '2026-09-30',
                                        'prueba', 1234 ) );
$sentencia = $falso->ultimaSentencia();

comprobar( substr_count( $sentencia->sql, 'AND' ) === 3,
           'cuatro condiciones unidas por tres AND' );

comprobar( substr_count( $sentencia->sql, '?' ) === 4,
           'las cuatro condiciones van parametrizadas' );

$esperados = [ '2026-09-01 00:00:00', '2026-09-30 23:59:59',
               'prueba', 1234 ];

comprobar( $sentencia->parametros === $esperados,
           'los cuatro parametros van en orden, con el dia acotado' );


titulo( 'recuperarMedicion: un filtro de fecha con hora se deja igual' );

$falso = ponerPDOFalso();
$r = recuperarMedicion( filtroDePrueba( '2026-09-01 08:30:00' ) );

comprobar( $falso->ultimaSentencia()->parametros === array( '2026-09-01 08:30:00' ),
           'si el desde ya trae hora, no se le pega nada' );

$falso = ponerPDOFalso();
$r = recuperarMedicion( filtroDePrueba( null, '2026-09-30 18:00:00' ) );

comprobar( $falso->ultimaSentencia()->parametros === array( '2026-09-30 18:00:00' ),
           'si el hasta ya trae hora, no se le pega nada' );


titulo( 'recuperarMedicion: el limite sale de la variable modificable' );

// la variable de arriba del fichero se lee con global, y si vale 50 el SQL
// tiene que traer LIMIT 50. Si alguien cambia $limiteConsulta, cambia aqui
// tambien, sin tocar esta prueba.
comprobar( $GLOBALS['limiteConsulta'] === 50,
           'la variable $limiteConsulta vale 50' );

$falso = ponerPDOFalso();
$r = recuperarMedicion( filtroDePrueba() );
comprobar( preg_match( '/LIMIT\s+50$/', trim( $falso->ultimaSentencia()->sql ) ) === 1,
           'el LIMIT del SQL es el de la variable, y va al final' );


// ===========================================================================
// RN5: los errores de MySQL se responden con 500, no con una pantalla en blanco
// ===========================================================================

titulo( 'Errores: un PDO que revienta -> 500' );

$falso = ponerPDOFalso();
$falso->explotarEn = 'prepare';
$r = guardarMedicion( medicionDePrueba() );
$json = cuerpoDecodificado( $r );

comprobar( $r->codigo === 500, 'si falla al guardar -> 500' );
comprobar( isset( $json['error'] ), 'el 500 trae un campo "error"' );
comprobar( is_string( $r->cuerpo ), 'el 500 tambien es JSON en texto' );

// el error de MySQL NO se le enseña al cliente, por si lleva datos de la BD
comprobar( strpos( $r->cuerpo, 'MySQL ha muerto' ) === false,
           'al cliente no se le enseña el texto real del error de MySQL' );

$falso = ponerPDOFalso();
$falso->explotarEn = 'fetch';
$r = recuperarMedicion( filtroDePrueba() );
$json = cuerpoDecodificado( $r );

comprobar( $r->codigo === 500, 'si falla al leer -> 500' );
comprobar( isset( $json['error'] ), 'el 500 trae un campo "error"' );


titulo( 'La conexion se reutiliza' );

$falso = ponerPDOFalso();
conexionBD();
conexionBD();
conexionBD();

comprobar( $falso->ejecuciones === 0 && $GLOBALS['pdo'] === $falso,
           'conexionBD() usa la misma conexion, no abre una nueva' );


// ===== PRUEBAS =====
//
// COMANDO, desde la carpeta pruebas/ :
//
//     php testLogicaDeNegocio.php
//
// ESTAS PRUEBAS NO NECESITAN SERVIDOR NI BASE DE DATOS: llevan su propio PDO
// falso, asi que se pueden correr con MySQL apagado.
//
// CODIGO DE SALIDA: 0 si no hay ningun fallo, 1 si hay alguno. Lo mismo que
// en testEndpointsRest.php, para poder encadenar los dos con && .
//
// RESULTADO ESPERADO:
//
//      60 pruebas, 0 fallos
//
// Y una linea mas al final con las pruebas que han pasado. Si sale
// "1 fallos" o mas, el numero del fallo es el del principio de la seccion.
//
// QUE NO SE COMPRUEBA AQUI, Y POR QUE:
//
//   - Que el INSERT funcione de verdad contra MySQL. Aqui solo se mira el
//     SQL que se ha construido. Lo comprueba el PROMPT 5, con la base de
//     datos ya creada.
//   - Que la tabla mediciones tenga esas columnas. Tambien es del PROMPT 5.
//   - Los endpoints REST de verdad. Eso lo hace testEndpointsRest.php, pero
//     OJO: desde que la logica de negocio es la real (PROMPT 4), ese
//     fichero ya no sirve tal cual, porque la logica real necesita MySQL.
//     Requiere que la BD proyecto_beacon exista (PROMPT 5).
//
// ===========================================================================


printf( "\n-----------------------------------------------------\n" );
printf( " %d pruebas, %d fallos\n", $GLOBALS['pruebas'], $GLOBALS['fallos'] );
printf( "-----------------------------------------------------\n" );

exit( $GLOBALS['fallos'] === 0 ? 0 : 1 );

?>
