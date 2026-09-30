<?php

// ===========================================================================
// testBaseDeDatos.php  --  PRUEBAS (PROMPT 5)
//
// QUE PRUEBA: que el esquema de la base de datos sea el correcto, sobre todo
// la RN3 (que la fecha la pone la base de datos y no el movil).
//
// QUE NECESITA ESTAR EN MARCHA:
//   - MySQL encendido (el de XAMPP, el que pone "Start" en el panel)
//   - el usuario root sin contrasena (los valores de logica/configuracion.php)
//
// COMO ESTA HECHO, Y POR QUE: estas pruebas NO tocan la base de datos de
// verdad del proyecto (proyecto_beacon). Trabajan sobre una base de datos
// desechable que se llama proyecto_beacon_test, que se crea al principio,
// se llena con el DDL REAL de baseDeDatos/crearBaseDeDatos.sql, se prueba,
// y se borra al final.
//
// Importante: las pruebas aplican el fichero .sql de verdad, no una copia
// del esquema escrita aqui dentro. Asi, si el DDL esta mal, estas pruebas
// fallan. Si el esquema se copiara en el test, el DDL podria estar roto y
// las pruebas no se enterarian.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// ===========================================================================
// VARIABLES
// ===========================================================================

// La base de datos desechable donde se prueba todo
$bdDePruebas = 'proyecto_beacon_test';

// El DDL real, que es el que hay que probar
$rutaDDL = __DIR__ . '/../baseDeDatos/crearBaseDeDatos.sql';

// Ponerlo a false deja la base de datos de pruebas ahi, para poder mirarla
// con phpMyAdmin cuando algo falle
$borrarAlFinalizar = true;

// La medicion real del proyecto (PROMPT 2)
$uuidPrueba    = 'EPSG-GTI-MARC-3A';
$nombrePrueba  = 'GTI-3A';
$majorPrueba   = 3584;
$minorPrueba   = 1234;
$txPowerPrueba = 4;
$rssiPrueba    = -53;

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

function aviso( $texto ) {
  printf( "  ----  %s\n", $texto );
}

// ---------------------------------------------------------------------------
// conectar() : se conecta a MySQL (sin elegir base de datos)
// ---------------------------------------------------------------------------
// Lee los datos de logica/configuracion.php, que es la unica parte donde
// estan escritos. Si MySQL esta apagado, lo dice claro y para.
// ---------------------------------------------------------------------------
function conectar() {

  $config = require __DIR__ . '/../logica/configuracion.php';

  $dsn = sprintf( 'mysql:host=%s;port=%d;charset=%s',
                  $config->host, $config->puerto, $config->charset );

  try {

    return new PDO( $dsn, $config->usuario, $config->contrasena, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ] );

  } catch ( PDOException $excepcion ) {

    printf( "\nNO SE HA PODIDO CONECTAR CON MySQL.\n\n" );
    printf( "  %s\n\n", $excepcion->getMessage() );
    printf( "  O lo mas probable: MySQL no esta encendido.\n" );
    printf( "  Abre el panel de control de XAMPP y dale a Start en MySQL.\n\n" );
    printf( "  Tambien puede ser que el usuario o la contrasena de\n" );
    printf( "  logica/configuracion.php no sean los de tu MySQL.\n\n" );
    exit( 1 );

  } // ()

} // ()

// ---------------------------------------------------------------------------
// partirSentencias() : saca las sentencias del .sql, listas para MySQL
//
// El problema: para mandar el DDL sentencia a sentencia hay que partirlo por
// ';', pero los comentarios hay que quitarlos antes, porque si no al partir
// aparecen trozos sueltos como "  -- valor del proyecto", que no son una
// sentencia y MySQL los rechaza.
//
// Y hay que quitar los comentarios TENIENDO CUIDADO con las comillas, por
// dos motivos:
//   - un comentario puede ser de linea (--) o de bloque (/*...* /)
//   - si se quita a lo bruto, un texto entre comillas que contenga -- se
//     rompe, y el valor se pierde
// Por eso esto va letra a letra, recuerda si esta dentro de comillas, y no
// toca nada de lo que este entrecomillado.
// ---------------------------------------------------------------------------
// ruta: Text --> partirSentencias() --> [ Text ]
function partirSentencias( $ruta ) {

  $sql = file_get_contents( $ruta );

  $limpio = '';
  $n     = strlen( $sql );
  $i     = 0;

  $comillaSimple = false;   // estamos dentro de '...'
  $comillaDoble  = false;   // estamos dentro de "..."

  while ( $i < $n ) {

    $c        = $sql[$i];
    $siguiente = ( $i + 1 < $n ) ? $sql[ $i + 1 ] : '';
    $tercero   = ( $i + 2 < $n ) ? $sql[ $i + 2 ] : '';

    $dentroDeComillas = $comillaSimple || $comillaDoble;

    // comentario de linea. OJO: MySQL solo considera que "--" abre un
    // comentario si detras hay un espacio, un tabulador o un salto de
    // linea. Una linea que es solo "--" TAMBIEN es un comentario, y si no
    // se quita se cuela como principio de la sentencia siguiente. Este
    // parser tiene que ser igual de generoso que MySQL.
    if ( ! $dentroDeComillas && $c === '-' && $siguiente === '-'
         && ( $tercero === '' || $tercero === ' ' || $tercero === "\t"
              || $tercero === "\r" || $tercero === "\n" ) ) {

      while ( $i < $n && $sql[$i] !== "\n" ) {
        $i++;
      }
      continue;

    }

    // comillas: se anotan para no confundir un -- de dentro con un comentario
    if ( $c === "'" && ! $comillaDoble ) {

      // '' dentro de un texto es un apostrofe escapado, no una comilla nueva
      if ( $comillaSimple && $siguiente === "'" ) {
        $limpio .= "''";
        $i += 2;
        continue;
      }

      $comillaSimple = ! $comillaSimple;

    } elseif ( $c === '"' && ! $comillaSimple ) {
      $comillaDoble = ! $comillaDoble;
    }

    $limpio .= $c;
    $i++;

  } // ()

  // partir por ';', pero solo por los que no estan dentro de un texto
  $sentencias  = [];
  $actual      = '';
  $comillaSimple = false;
  $comillaDoble  = false;

  $n = strlen( $limpio );

  for ( $i = 0; $i < $n; $i++ ) {

    $c        = $limpio[$i];
    $siguiente = ( $i + 1 < $n ) ? $limpio[ $i + 1 ] : '';

    if ( $c === "'" && ! $comillaDoble ) {

      if ( $comillaSimple && $siguiente === "'" ) {
        $actual .= "''";
        $i++;
        continue;
      }

      $comillaSimple = ! $comillaSimple;

    } elseif ( $c === '"' && ! $comillaSimple ) {
      $comillaDoble = ! $comillaDoble;
    }

    // el ';' solo corta si no estamos dentro de un texto
    if ( $c === ';' && ! $comillaSimple && ! $comillaDoble ) {

      $actual = trim( $actual );
      if ( $actual !== '' ) {
        $sentencias[] = $actual;
      }
      $actual = '';
      continue;

    }

    $actual .= $c;

  } // ()

  // la ultima sentencia puede no llevar ';'
  $actual = trim( $actual );
  if ( $actual !== '' ) {
    $sentencias[] = $actual;
  }

  return $sentencias;

} // ()

// ---------------------------------------------------------------------------
// aplicarDDL() : ejecuta el .sql de verdad sobre la base de datos de pruebas
//
// El fichero va con comentarios y cada sentencia acaba en ';', asi que se
// parte con partirSentencias() y se manda cada parte por separado. Ademas se
// cambia el nombre de la base de datos por el de pruebas, para no escribir
// nunca en la de verdad.
// ---------------------------------------------------------------------------
// ruta: Text, bd: Text --> aplicarDDL() --> PDO
function aplicarDDL( $ruta, $bd ) {

  if ( ! is_readable( $ruta ) ) {
    printf( "\nNO SE ENCUENTRA EL FICHERO DDL: %s\n\n", $ruta );
    exit( 1 );
  }

  $sentencias = partirSentencias( $ruta );

  $pdo = conectar();
  $pdo->exec( 'DROP DATABASE IF EXISTS ' . $bd );

  foreach ( $sentencias as $sentencia ) {

    // el nombre de la base de datos del proyecto se cambia por el de
    // pruebas, para no escribir en la de verdad
    $sentencia = str_replace( 'proyecto_beacon', $bd, $sentencia );

    try {
      $pdo->exec( $sentencia );
    } catch ( PDOException $excepcion ) {
      printf( "\nEL DDL HA FALLADO. Sentencia:\n%s\n\n  %s\n\n",
              $sentencia, $excepcion->getMessage() );
      exit( 1 );
    }

  } // ()

  return $pdo;

} // ()

// ---------------------------------------------------------------------------
// ahoraMySQL() : la hora del SERVIDOR de MySQL, no la de PHP
//
// Importante para la RN3: la fecha la genera MySQL, asi que hay que
// compararla con la hora de MySQL y no con la de este ordenador.
// ---------------------------------------------------------------------------
// PDO --> ahoraMySQL() --> Text
function ahoraMySQL( $pdo ) {

  $sentencia = $pdo->query( 'SELECT NOW() AS ahora' );

  return $sentencia->fetchColumn();

} // ()

// ---------------------------------------------------------------------------
// insertarMedicion() : mete una medicion SIN la columna fecha (RN3)
// ---------------------------------------------------------------------------
// PDO --> insertarMedicion() --> id: Z
function insertarMedicion( $pdo, $rssi = -53 ) {

  // OJO: la columna fecha NO aparece. La pone la base de datos (RN3)
  $sql = 'INSERT INTO mediciones
              (uuid_beacon, nombre_emisora, major, minor, tx_power, rssi)
            VALUES (?, ?, ?, ?, ?, ?)';

  $sentencia = $pdo->prepare( $sql );
  $sentencia->execute( [ $GLOBALS['uuidPrueba'], $GLOBALS['nombrePrueba'],
                         $GLOBALS['majorPrueba'], $GLOBALS['minorPrueba'],
                         $GLOBALS['txPowerPrueba'], $rssi ] );

  return (int) $pdo->lastInsertId();

} // ()


// ===========================================================================
// ARRANQUE
// ===========================================================================

printf( "PRUEBAS DEL ESQUEMA DE LA BASE DE DATOS\n" );
printf( "Base de datos de pruebas: %s\n", $bdDePruebas );

$pdo = aplicarDDL( $rutaDDL, $bdDePruebas );

aviso( 'el DDL se ha aplicado bien sobre ' . $bdDePruebas );

// el DDL deja tres filas de ejemplo. Se borran para empezar de cero, que si
// no luego las cuentas no salen
$pdo->exec( 'DELETE FROM mediciones' );


titulo( '1. Existencia de la base de datos y de la tabla' );

$sentencia = $pdo->query( "SHOW TABLES LIKE 'mediciones'" );

comprobar( $sentencia->fetch() !== false,
           'la tabla mediciones existe despues de aplicar el DDL' );

$sentencia = $pdo->query( 'SELECT COUNT(*) AS n FROM mediciones' );

comprobar( is_numeric( $sentencia->fetchColumn() ),
           'se puede consultar la tabla sin error' );

// La base de datos de VERDAD es otra cosa: ahi es donde ira el movil, y a
// veces todavia no se ha importado el DDL en phpMyAdmin. Esto no es una
// prueba que pueda fallar aqui, asi que solo se avisa.
$enBaseDeDatos = $pdo->query( 'SELECT schema_name FROM information_schema.SCHEMATA'
                              . " WHERE schema_name = 'proyecto_beacon'" );

if ( $enBaseDeDatos->fetch() === false ) {
  aviso( 'OJO: la base de datos proyecto_beacon NO existe todavia.' );
  aviso( 'Estas pruebas no lo necesitan, pero el movil no podra guardar' );
  aviso( 'nada hasta que importes el DDL en phpMyAdmin.' );
} else {
  aviso( 'la base de datos proyecto_beacon tambien existe' );
}


titulo( '2. Estructura de la tabla' );

// los nombres y los tipos de cada columna, tal cual los declara MySQL
$sentencia = $pdo->query( 'SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
                            FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                           ORDER BY ORDINAL_POSITION' );
$sentencia->execute( [ $bdDePruebas, 'mediciones' ] );

$columnas = [];
foreach ( $sentencia->fetchAll() as $columna ) {
  $columnas[ $columna['COLUMN_NAME'] ] = $columna;
}

// las ocho columnas, en el orden del DDL
$esperadas = [ 'id', 'uuid_beacon', 'nombre_emisora', 'major',
               'minor', 'tx_power', 'rssi', 'fecha' ];

comprobar( array_keys( $columnas ) === $esperadas,
           'las 8 columnas estan, y en el orden del DDL' );

// los tipos, que es donde mas fallos se cuelan
$tipos = [
  'id'             => 'int unsigned',
  'uuid_beacon'    => 'varchar(32)',
  'nombre_emisora' => 'varchar(20)',
  'major'          => 'smallint',
  'minor'          => 'smallint',
  'tx_power'       => 'tinyint',
  'rssi'           => 'tinyint',
  'fecha'          => 'datetime',
];

foreach ( $tipos as $nombre => $tipo ) {

  $tieneTipo = isset( $columnas[$nombre] )
                && strtolower( $columnas[$nombre]['COLUMN_TYPE'] ) === $tipo;

  comprobar( $tieneTipo, sprintf( 'la columna %-15s es %s', $nombre, $tipo ) );

}

// id: autoincremental y sin permitir nulos
comprobar( isset( $columnas['id']['EXTRA'] )
           && stripos( $columnas['id']['EXTRA'], 'auto_increment' ) !== false,
           'la columna id es AUTO_INCREMENT' );

// RN3: la fecha por defecto la tiene que poner MySQL
comprobar( isset( $columnas['fecha']['COLUMN_DEFAULT'] )
           && stripos( $columnas['fecha']['COLUMN_DEFAULT'], 'CURRENT_TIMESTAMP' ) !== false,
           'la columna fecha tiene por defecto CURRENT_TIMESTAMP (RN3)' );

// ninguna columna puede quedar a null
$nulos = array_filter( $columnas, function ( $c ) {
  return $c['IS_NULLABLE'] === 'YES';
} );

comprobar( count( $nulos ) === 0,
           'ninguna columna admite NULL' );

// la clave primaria
$sentencia = $pdo->query( 'SELECT COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
                              AND CONSTRAINT_NAME = "PRIMARY"' );
$sentencia->execute( [ $bdDePruebas, 'mediciones' ] );

comprobar( $sentencia->fetchColumn() === 'id',
           'la PRIMARY KEY es la columna id' );

// el indice por fecha, que es el que usa el ORDER BY fecha DESC LIMIT 50
$sentencia = $pdo->query( 'SELECT INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = "idx_mediciones_fecha"' );
$sentencia->execute( [ $bdDePruebas, 'mediciones' ] );

$indiceFecha = $sentencia->fetch();

comprobar( $indiceFecha !== false && $indiceFecha['COLUMN_NAME'] === 'fecha',
           'el indice idx_mediciones_fecha existe y es sobre la columna fecha' );


titulo( '3. INSERT valido, sin la columna fecha' );

$antes = ahoraMySQL( $pdo );
$id = insertarMedicion( $pdo, $rssiPrueba );
$despues = ahoraMySQL( $pdo );

comprobar( $id > 0, 'el INSERT devuelve un id (el ' . $id . ')' );

$sentencia = $pdo->query( 'SELECT COUNT(*) FROM mediciones' );

comprobar( (int) $sentencia->fetchColumn() === 1,
           'el INSERT ha metido exactamente 1 fila' );

// el id tiene que crecer solo: mete otra y tiene que ser mayor
$id2 = insertarMedicion( $pdo, -61 );

comprobar( $id2 > $id, 'el id se incrementa solo (de ' . $id . ' a ' . $id2 . ')' );

comprobar( $id2 === $id + 1, 'y de uno en uno' );

// guardar de mas, para las pruebas siguientes
$sentencia = $pdo->query( 'DELETE FROM mediciones WHERE id > ?' );
$sentencia->execute( [ $id ] );


titulo( '4. RN3: la fecha la pone la base de datos' );

$sentencia = $pdo->query( 'SELECT fecha FROM mediciones WHERE id = ?' );
$sentencia->execute( [ $id ] );
$fechaGuardada = $sentencia->fetchColumn();

comprobar( $fechaGuardada !== null, 'la fecha NO es NULL: la relleno la base de datos' );

// la fila tiene que tener una fecha de dentro del intervalo en el que se
// hizo el INSERT. Ojo: el intervalo son las horas de MYSQL, no las de PHP,
// porque quien pone la fecha es MySQL
comprobar( $fechaGuardada >= $antes && $fechaGuardada <= $despues,
           'la fecha cae entre el antes y el despues del INSERT' );

// CURRENT_TIMESTAMP guarda segundos enteros, sin decimales
comprobar( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $fechaGuardada ) === 1,
           'la fecha esta redondeada al segundo, como CURRENT_TIMESTAMP' );

// y tiene que coincidir con la hora que da MySQL ahora mismo
$ahora = ahoraMySQL( $pdo );
$diferencia = abs( strtotime( $ahora ) - strtotime( $fechaGuardada ) );

comprobar( $diferencia <= 10,
           'la fecha coincide con el NOW() de MySQL (diferencia de ' . $diferencia . ' s)' );

// prueba contraria: si el movil hubiera mandado la fecha, esta seria una
// fecha de hace años. Se comprueba que la guardada es de HOY
comprobar( substr( $fechaGuardada, 0, 10 ) === date( 'Y-m-d', strtotime( $ahora ) ),
           'la fecha es la de hoy: nadie la ha mandado desde fuera' );


titulo( '5. La columna fecha usa su valor por defecto' );

comprobar( stripos( $columnas['fecha']['COLUMN_DEFAULT'], 'CURRENT_TIMESTAMP' ) !== false,
           'el valor por defecto de fecha sigue siendo CURRENT_TIMESTAMP' );

// un INSERT sin la columna fecha tiene que salir con fecha puesta
$pdo->exec( 'DELETE FROM mediciones' );
$nuevoId = insertarMedicion( $pdo, -48 );

$sentencia = $pdo->query( 'SELECT fecha FROM mediciones WHERE id = ?' );
$sentencia->execute( [ $nuevoId ] );

comprobar( $sentencia->fetchColumn() !== null,
           'una medicion insertada sin fecha sale con fecha igualmente' );


titulo( '6. La consulta tipica de la web' );

// tres filas con fechas a proposito, para poder comprobar el orden. En una
// base de datos de verdad esto no se hace, pero aqui si: esta tabla es
// desechable y es la unica forma de comprobar el ORDER BY de verdad
$pdo->exec( 'DELETE FROM mediciones' );

$fechas = [ '2026-01-15 08:00:00', '2026-06-15 08:00:00', '2026-09-15 08:00:00' ];

foreach ( $fechas as $unaFecha ) {

  $idFila = insertarMedicion( $pdo );
  $sentencia = $pdo->prepare( 'UPDATE mediciones SET fecha = ? WHERE id = ?' );
  $sentencia->execute( [ $unaFecha, $idFila ] );

} // ()

$sentencia = $pdo->query( 'SELECT fecha FROM mediciones ORDER BY fecha DESC' );
$orden = array_column( $sentencia->fetchAll(), 'fecha' );

comprobar( $orden === array_reverse( $fechas ),
           'el ORDER BY fecha DESC devuelve las mas recientes primero' );

// el indice tiene que ser el que usa MySQL para esta consulta. Si no lo usa,
// con mucha tabla esto se vuelve lentisimo
$sentencia = $pdo->query( 'EXPLAIN SELECT * FROM mediciones ORDER BY fecha DESC LIMIT 50' );
$plan = $sentencia->fetchAll();

$usaIndice = false;
foreach ( $plan as $linea ) {
  if ( isset( $linea['key'] ) && $linea['key'] === 'idx_mediciones_fecha' ) {
    $usaIndice = true;
  }
}

comprobar( $usaIndice,
           'MySQL usa el indice idx_mediciones_fecha para el ORDER BY fecha DESC' );

// el LIMIT 50: se meten 55 filas y tienen que salir 50
$pdo->exec( 'DELETE FROM mediciones' );

for ( $i = 0; $i < 55; $i++ ) {
  insertarMedicion( $pdo, -40 - ( $i % 30 ) );
}

$sentencia = $pdo->query( 'SELECT COUNT(*) FROM mediciones' );

comprobar( (int) $sentencia->fetchColumn() === 55,
           'se han metido 55 filas' );

$sentencia = $pdo->query( 'SELECT id, fecha FROM mediciones ORDER BY fecha DESC LIMIT 50' );
$lasUltimas = $sentencia->fetchAll();

comprobar( count( $lasUltimas ) === 50,
           'el LIMIT 50 devuelve 50 filas de las 55 que hay' );

// y tienen que ser las 50 mas nuevas, no 50 cualesquiera
$sentencia = $pdo->query( 'SELECT MAX(fecha) FROM mediciones' );
$masReciente = $sentencia->fetchColumn();

comprobar( $lasUltimas[0]['fecha'] === $masReciente,
           'la primera fila devuelta es la mas reciente de todas' );


// ===========================================================================
// FIN: limpiar
// ===========================================================================

if ( $borrarAlFinalizar ) {

  $pdo->exec( 'DROP DATABASE IF EXISTS ' . $bdDePruebas );
  aviso( 'se ha borrado la base de datos de pruebas ' . $bdDePruebas );

} else {

  aviso( 'se ha dejado la base de datos ' . $bdDePruebas . ' para mirarla' );

} // ()

printf( "\n-----------------------------------------------------\n" );
printf( " %d pruebas, %d fallos\n", $GLOBALS['pruebas'], $GLOBALS['fallos'] );
printf( "-----------------------------------------------------\n" );

exit( $GLOBALS['fallos'] === 0 ? 0 : 1 );


// ===== PRUEBAS =====
//
// QUE HACE ESTE FICHERO, RESUMIDO:
//   - borra proyecto_beacon_test
//   - aplica baseDeDatos/crearBaseDeDatos.sql sobre ella
//   - comprueba que el esquema es el correcto
//   - comprueba que un INSERT sin fecha sale con fecha (RN3)
//   - comprueba el ORDER BY fecha DESC y el LIMIT 50
//   - borra proyecto_beacon_test
//
// LO QUE HACE ES DESTRUCTIVO Y LO QUE NO:
//   - BORRA proyecto_beacon_test, que es una base de datos CREADA POR ESTAS
//     PRUEBAS. No es la del proyecto.
//   - NO toca proyecto_beacon, la de verdad. Las pruebas unicamente leen
//     de information_schema si existe.
//
// UN SOLO COMANDO, desde la carpeta pruebas/ :
//
//     php testBaseDeDatos.php
//
// CODIGO DE SALIDA: 0 si no hay fallos, 1 si hay alguno.igual que en los
// otros ficheros de pruebas, para poder encadenarlos con && :
//
//     php testLogicaDeNegocio.php && php testBaseDeDatos.php
//
// REQUISITOS:
//   - MySQL encendido. Si no, el script lo dice y para, con el mensaje
//     "NO SE HA PODIDO CONECTAR CON MySQL".
//   - el usuario root sin contrasena, que es lo que dice
//     logica/configuracion.php.
//
// RESULTADO ESPERADO:
//
//     33 pruebas, 0 fallos
//
// Y despues, el aviso de que se ha borrado la base de datos de pruebas.
//
// SI ALGO FALLA:
//   - si falla la parte 1, casi siempre es que el DDL tiene un error de
//     sintaxis: el script imprime la sentencia exacta que ha fallado.
//   - si fallan las partes 2 o 3, es que el DDL se ha alejado del prompt.
//   - para mirar la base de datos de pruebas, poner $borrarAlFinalizar a
//     false y abrirla en phpMyAdmin.
// ===========================================================================