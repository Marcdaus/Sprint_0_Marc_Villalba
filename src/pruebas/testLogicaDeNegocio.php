<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================


// ===========================================================================
// testLogicaDeNegocio.php  --  PRUEBAS (PROMPT 4)
//
// QUE PRUEBA: la logica de negocio de verdad, la de
// src/business_logic/LogicaDeNegocio.php, comprobando las reglas de negocio
// RN1..RN3.
//
// IMPORTANTE: la logica de negocio es INDEPENDIENTE DE LA COMUNICACION, o
// sea, devuelve objetos de DOMINIO (con ok / id / mediciones / error /
// clase), NO respuestas de red. Por eso estas pruebas comprueban el
// resultado de dominio, y NO codigos HTTP ni JSON: traducir a HTTP es cosa
// de la capa de comunicacion (src/communication/).
//
// COMO ESTA HECHO, Y POR QUE: estas pruebas NO usan una base de datos de
// verdad, sino un PDO FALSO (la clase PDOFalso de mas abajo). El PDO falso
// se parece lo justo a un PDO real: tiene prepare(), execute(),
// fetchAll() y lastInsertId(), pero en vez de hablar con MySQL se apunta
// todo en memoria. Gracias a eso se pueden comprobar las RN sin tener
// XAMPP encendido, y sin instalar ni composer ni PHPUnit.
//
// COMO SE CONECTA EL PDO FALSO: conexionBD() de LogicaDeNegocio.php usa la
// variable global $pdo si ya hay una puesta. Estas pruebas la ponen antes
// de cada caso, y por eso el codigo real de la base de datos no se ejecuta
// ni se intenta.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

require_once __DIR__ . '/../business_logic/LogicaDeNegocio.php';


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

    // el fallo se produce aqui, para probar el error de persistencia
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
// explotarEn es para probar el camino del error de persistencia:
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


// ===========================================================================
// RN1: el tipo tiene que ser uno de los conocidos
// ===========================================================================

titulo( 'RN1: el tipo_medicion tiene que ser uno de los conocidos' );

$falso = ponerPDOFalso();

$r = guardarMedicion( medicionDePrueba( 'tipo_medicion', 'humedad' ) );

comprobar( $r->ok === false, 'un tipo desconocido devuelve un resultado de error' );
comprobar( $r->clase === 'validacion', 'el fallo es de validacion (no de la base de datos)' );
comprobar( $r->error !== null && $r->error !== '', 'la respuesta trae el motivo del error' );

comprobar( $falso->numeroDeSentencias() === 0,
           'NO se prepara ninguna sentencia: no se inserta nada' );

comprobar( $falso->ejecuciones === 0,
           'NO se ejecuta nada contra la base de datos' );

// el texto del error tiene que decir que el tipo no lo reconoce
comprobar( strpos( (string) $r->error, 'tipo' ) !== false,
           'el motivo del error habla del tipo' );


titulo( 'RN1: con un tipo conocido si se guarda' );

foreach ( [ 'CO2', 'temperatura', 'prueba' ] as $tipoValido ) {

  $falso = ponerPDOFalso();

  $r = guardarMedicion( medicionDePrueba( 'tipo_medicion', $tipoValido ) );

  comprobar( $r->ok === true, 'el tipo "' . $tipoValido . '" es valido -> ok' );

  comprobar( $falso->inserts === 1, 'se inserta exactamente una fila' );

}


// ===========================================================================
// RN2: el valor_medicion es obligatorio y entero
// ===========================================================================

titulo( 'RN2: el valor_medicion es obligatorio' );

$falso = ponerPDOFalso();

$r = guardarMedicion( medicionDePrueba( 'valor_medicion', null ) );

comprobar( $r->ok === false, 'sin valor_medicion -> resultado de error' );
comprobar( $r->clase === 'validacion', 'el fallo (sin valor) tambien es de validacion' );

comprobar( $falso->numeroDeSentencias() === 0,
           'sin valor_medicion NO se inserta nada' );

comprobar( strpos( (string) $r->error, 'valor' ) !== false,
           'el motivo del error habla del valor' );


titulo( 'RN2: el valor_medicion tiene que ser un entero, no texto' );

$falso = ponerPDOFalso();

$r = guardarMedicion( medicionDePrueba( 'valor_medicion', '1234' ) );

comprobar( $r->ok === false, 'un valor_medicion de texto -> error' );

comprobar( $falso->numeroDeSentencias() === 0,
           'un valor_medicion de texto NO se inserta' );


titulo( 'RN2: el valor_medicion NO se valida contra un valor fijo' );

// aqui esta el fondo de la regla: hoy vale 1234, pero el valor es del
// proyecto y se puede cambiar para la demo, asi que CUALQUIER entero vale

$falso = ponerPDOFalso();
$r = guardarMedicion( medicionDePrueba( 'valor_medicion', 999 ) );
comprobar( $r->ok === true, 'un valor_medicion 999 (distinto de 1234) -> ok' );

$falso = ponerPDOFalso();
$r = guardarMedicion( medicionDePrueba( 'valor_medicion', 0 ) );
comprobar( $r->ok === true, 'un valor_medicion 0 -> ok' );

$falso = ponerPDOFalso();
$r = guardarMedicion( medicionDePrueba( 'valor_medicion', -7 ) );
comprobar( $r->ok === true, 'un valor_medicion negativo -> ok' );


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


titulo( 'RN3: la respuesta ya NO lleva id (clave natural compuesta)' );

$falso = ponerPDOFalso();

$r = guardarMedicion( medicionDePrueba() );

comprobar( $r->ok === true, 'la operacion correcta devuelve ok' );
comprobar( ! isset( $r->id ), 'la respuesta NO trae id: la clave de la fila es (nombre_emisora, fecha)' );

// ademas, ya no se le pide al PDO el ultimo id: el objeto ni lo usa
comprobar( $falso->numeroDeSentencias() === 1
           && $falso->inserts === 1,
           'el guardado solo prepara y ejecuta un INSERT, sin lastInsertId' );

// la prueba clave del desacoplamiento: el resultado NO trae nada de transporte
comprobar( ! isset( $r->codigo ) && ! isset( $r->cuerpo ),
           'el resultado de negocio NO trae codigo HTTP ni cuerpo JSON' );


// ===========================================================================
// recuperarMedicion: los filtros son opcionales
// ===========================================================================

titulo( 'recuperarMedicion: sin filtros, se devuelven todas' );

$falso = ponerPDOFalso();
$falso->filas = [
  [ 'nombre_emisora' => 'GTI-3A', 'tipo_medicion' => 'prueba',
    'valor_medicion' => '1234', 'fecha' => '2026-09-29 10:15:00' ]
];

$r = recuperarMedicion( filtroDePrueba() );
$sql = $falso->ultimaSentencia()->sql;

comprobar( $r->ok === true, 'sin filtros -> ok' );

comprobar( stripos( $sql, 'WHERE' ) === false,
           'sin filtros, el SELECT no lleva WHERE' );

comprobar( $falso->ultimaSentencia()->parametros === array(),
           'sin filtros, no se manda ningun parametro' );

comprobar( stripos( $sql, 'ORDER BY fecha DESC' ) !== false,
           'el SELECT ordena por fecha DESC' );

comprobar( preg_match( '/LIMIT\s+50/', $sql ) === 1,
           'el SELECT trae LIMIT 50' );

comprobar( is_array( $r->mediciones ),
           'la respuesta trae la lista de mediciones' );

comprobar( count( $r->mediciones ) === 1, 'viene la fila que hay en la BD' );

// los numeros del SELECT salen como numeros, no como texto
$medicion = $r->mediciones[0];

comprobar( $medicion->valor_medicion === 1234 && is_int( $medicion->valor_medicion ),
           'el valor_medicion sale como numero, no como texto' );

comprobar( $medicion->tipo_medicion === 'prueba',
           'el tipo_medicion sale como el texto que hay en la BD' );

comprobar( $medicion->fecha === '2026-09-29 10:15:00',
           'la fecha viene de la BD, tal cual' );

comprobar( isset( $medicion->nombre_emisora ) && isset( $medicion->tipo_medicion )
           && isset( $medicion->valor_medicion ) && isset( $medicion->fecha ),
           'la medicion trae los cuatro campos de la tabla' );

comprobar( ! isset( $medicion->id ),
           'la medicion NO trae id: su clave es (nombre_emisora, fecha)' );


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
// RN5: los fallos de MySQL se devuelven como error de persistencia
// ===========================================================================

titulo( 'Errores: un PDO que revienta -> error de persistencia' );

$falso = ponerPDOFalso();
$falso->explotarEn = 'prepare';
$r = guardarMedicion( medicionDePrueba() );

comprobar( $r->ok === false, 'si falla al guardar -> resultado de error' );
comprobar( $r->clase === 'persistencia', 'el fallo de la BD es de persistencia' );
comprobar( $r->error !== null, 'la respuesta trae el motivo del error' );

// el error de MySQL NO se le enseña al cliente, por si lleva datos de la BD
comprobar( strpos( (string) $r->error, 'MySQL ha muerto' ) === false,
           'el error de negocio NO filtra el texto real del error de MySQL' );

$falso = ponerPDOFalso();
$falso->explotarEn = 'fetch';
$r = recuperarMedicion( filtroDePrueba() );

comprobar( $r->ok === false, 'si falla al leer -> resultado de error' );
comprobar( $r->clase === 'persistencia', 'el fallo al leer tambien es de persistencia' );


titulo( 'La conexion se reutiliza' );

$falso = ponerPDOFalso();
conexionBD();
conexionBD();
conexionBD();

comprobar( $falso->ejecuciones === 0 && $GLOBALS['pdo'] === $falso,
           'conexionBD() usa la misma conexion, no abre una nueva' );


// ===========================================================================
// DESACOPLAMIENTO: la logica de negocio no conoce la comunicacion
//
// Esta seccion es la que demuestra la "Zero Dependency Rule": se lee el
// propio fichero de la logica de negocio y se comprueba que NO aparece nada
// de transporte (ni codigos HTTP, ni cabeceras, ni JSON, ni rutas).
// ===========================================================================

titulo( 'Desacoplamiento: la logica de negocio no usa HTTP/JSON' );

$fuente = file_get_contents( __DIR__ . '/../business_logic/LogicaDeNegocio.php' );

comprobar( strpos( $fuente, 'http_response_code' ) === false,
           'la logica de negocio no fija codigos HTTP' );

comprobar( strpos( $fuente, 'header(' ) === false,
           'la logica de negocio no escribe cabeceras HTTP' );

comprobar( strpos( $fuente, 'json_encode' ) === false,
           'la logica de negocio no monta JSON' );

comprobar( strpos( $fuente, 'curl_' ) === false
           && strpos( $fuente, '$_SERVER' ) === false
           && strpos( $fuente, '$_GET' ) === false,
           'la logica de negocio no habla de transporte (ni curl ni superglobales de peticion)' );


// ===== PRUEBAS =====
//
// COMANDO, desde la raiz del proyecto:
//
//     php src\pruebas\testLogicaDeNegocio.php
//
// ESTAS PRUEBAS NO NECESITAN SERVIDOR NI BASE DE DATOS: llevan su propio PDO
// falso, asi que se pueden correr con MySQL apagado.
//
// CODIGO DE SALIDA: 0 si no hay ningun fallo, 1 si hay alguno. Lo mismo que
// en testEndpointsRest.php, para poder encadenar los dos con && .
//
// RESULTADO ESPERADO:
//
//      66 pruebas, 0 fallos
//
// ===========================================================================


printf( "\n-----------------------------------------------------\n" );
printf( " %d pruebas, %d fallos\n", $GLOBALS['pruebas'], $GLOBALS['fallos'] );
printf( "-----------------------------------------------------\n" );

exit( $GLOBALS['fallos'] === 0 ? 0 : 1 );

?>
