// ===========================================================================
// PRUEBAS DE LA LOGICA FAKE DE LA WEB   (PROMPT 6)
//
// Comprueban las cuatro funciones de ux/logicaFake/ SIN necesitar el
// servidor, sin base de datos y sin navegador: el XHR se sustituye por una
// clase falsa que contesta lo que le pidan.
//
// No hay Jest ni ningun otro paquete: se ejecuta con Node y ya.
//
//     cd web\pruebas
//     node pruebasLogicaFake.js
//
// Si todo va bien sale "20 pruebas, 0 fallos" y el codigo de salida es 0.
// Si algo falla, el codigo de salida es 1.
//
// ===========================================================================

const fs = require("fs");
const path = require("path");

// ---------------------------------------------------------------------------
// CARGAR LA LOGICA DE VERDAD
//
// No se copia el codigo de los ficheros: se leen del disco y se meten todos
// juntos en el mismo ambito, que es justo como funcionan los <script> del
// navegador (uno define construirURL y otro la usa). Asi se prueban los
// ficheros de verdad, no una copia que se pueda quedar vieja.
// ---------------------------------------------------------------------------
const CARPETA = path.join(__dirname, "..", "ux", "logicaFake");

const FICHEROS = [
	"configuracion.js",
	"construirURL.js",
	"adaptarMediciones.js",
	"formatearFecha.js",
	"consultarMediciones.js"
];

const fuente = FICHEROS.map( function ( nombre ) {
	return fs.readFileSync( path.join( CARPETA, nombre ), "utf8" );
}).join( "\n" );

// al final se devuelve lo que se quiere poder llamar desde aqui
const logica = new Function( fuente + [
	"",
	"return {",
	"	construirURL: construirURL,",
	"	adaptarMediciones: adaptarMediciones,",
	"	formatearFecha: formatearFecha,",
	"	consultarMediciones: consultarMediciones",
	"};"
].join( "\n" ) )();

// ---------------------------------------------------------------------------
// EL XHR FALSO
//
// Se pone en su sitio antes de llamar a consultarMediciones(), y a partir de
// ahi "new XMLHttpRequest()" dentro de la logica crea este invento. Guardamos
// la ultima peticion en "ultimaPeticion" para poder mirar a quien se le ha
// llamado, y "responderFalso" decide como contesta cada llamada a send().
// ---------------------------------------------------------------------------
let ultimaPeticion = null;
let responderFalso = null;

globalThis.XMLHttpRequest = class {

	constructor() {
		this.readyState = 0;
		this.status = 0;
		this.responseText = "";
		ultimaPeticion = this;
	}

	open( metodo, url ) {
		this.metodo = metodo;
		this.url = url;
	}

	send() {
		if ( responderFalso !== null ) {
			responderFalso( this );
		}
	}

};

// el servidor contesta con un codigo y un cuerpo
function servidorContesta( codigo, cuerpo ) {
	responderFalso = function ( xhr ) {
		xhr.status = codigo;
		xhr.responseText = cuerpo;
		xhr.readyState = 4;
		xhr.onreadystatechange();
	};
}

// el servidor no esta: se dispara el evento de error de red
function redCaida() {
	responderFalso = function ( xhr ) {
		xhr.status = 0;
		xhr.readyState = 4;
		xhr.onerror();
	};
}

// no se contesta nada (se queda esperando)
function sinRespuesta() {
	responderFalso = null;
}

// ---------------------------------------------------------------------------
// LAS COMPROBACIONES
// ---------------------------------------------------------------------------
let pruebas = 0;
let fallos = 0;

function comprobar( condicion, descripcion ) {
	pruebas++;
	if ( condicion ) {
		console.log( "  OK    " + descripcion );
	} else {
		fallos++;
		console.log( "  FALLO " + descripcion );
	}
}

// compara dos cosas y, si no son iguales, enseña las dos
function comprobarIgual( obtenido, esperado, descripcion ) {
	const bien = JSON.stringify( obtenido ) === JSON.stringify( esperado );
	if ( bien ) {
		comprobar( true, descripcion );
	} else {
		comprobar( false, descripcion );
		console.log( "          esperado: " + JSON.stringify( esperado ) );
		console.log( "          obtenido: " + JSON.stringify( obtenido ) );
	}
}

// llama a consultarMediciones() guardando lo que llega a cada callback
function consultar( preparar ) {
	const recibido = { exito: null, error: null, llamadas: 0 };
	preparar();
	logica.consultarMediciones(
		function ( lista ) { recibido.llamadas++; recibido.exito = lista; },
		function ( texto ) { recibido.llamadas++; recibido.error = texto; }
	);
	return recibido;
}

// una medicion tal cual la manda el servidor
function medicionDelServidor() {
	return {
		id: 1,
		uuid_beacon: "EPSG-GTI-MARC-3A",
		nombre_emisora: "GTI-3A",
		major: 3584,
		minor: 1234,
		tx_power: 4,
		rssi: -53,
		fecha: "2026-09-26 12:34:05"
	};
}

console.log( "" );
console.log( "construirURL" );

comprobarIgual(
	logica.construirURL( "localhost", 8080, "servidor/rest/recuperarMedicion.php" ),
	"http://localhost:8080/servidor/rest/recuperarMedicion.php",
	"monta la direccion del servidor"
);

comprobarIgual(
	logica.construirURL( "192.168.1.5", 8080, "rest/recuperarMedicion.php" ),
	"http://192.168.1.5:8080/rest/recuperarMedicion.php",
	"cambia host, puerto y ruta y la direccion cambia entera"
);

console.log( "" );
console.log( "adaptarMediciones" );

const adaptada = logica.adaptarMediciones( [ medicionDelServidor() ] );

comprobarIgual( Object.keys( adaptada[0] ).sort(),
	[ "fecha", "id", "major", "minor", "nombre", "rssi", "txPower", "uuid" ],
	"los campos del servidor se renombran (uuid_beacon -> uuid, tx_power -> txPower)"
);

comprobar( adaptada[0].uuid === "EPSG-GTI-MARC-3A", "el uuid se copia" );
comprobar( adaptada[0].nombre === "GTI-3A", "el nombre de la emisora se copia" );
comprobar( adaptada[0].id === 1, "el id se copia" );

comprobarIgual(
	[ adaptada[0].minor, adaptada[0].major, adaptada[0].txPower, adaptada[0].rssi ],
	[ 1234, 3584, 4, -53 ],
	"minor, major, txPower y rssi conservan su valor (rssi sigue siendo -53)"
);

comprobar( adaptada[0].fecha === "2026-09-26 12:34:05",
	"la fecha se deja en el texto del servidor, sin formatear" );

comprobarIgual( logica.adaptarMediciones( [] ), [], "una lista vacia devuelve una lista vacia" );

comprobarIgual( logica.adaptarMediciones( null ), [], "si no llega una lista, devuelve una vacia" );

comprobar( logica.adaptarMediciones( [ { minor: 0, rssi: 0 } ] )[0].rssi === 0,
	"un rssi de 0 no se pierde (por eso no se usa \"||\")" );

console.log( "" );
console.log( "formatearFecha" );

comprobarIgual(
	logica.formatearFecha( "2026-09-26 12:34:05" ),
	"26/09/2026 12:34",
	"pasa la fecha del servidor a algo legible"
);

comprobarIgual(
	logica.formatearFecha( "2026-09-26T12:34:05" ),
	"26/09/2026 12:34",
	"tambien acepta una T entre la fecha y la hora"
);

comprobarIgual(
	logica.formatearFecha( "no es una fecha" ),
	"no es una fecha",
	"si el formato no es el del servidor, devuelve el texto sin tocar"
);

comprobarIgual(
	logica.formatearFecha( null ),
	"",
	"si no hay fecha, devuelve texto vacio y no la palabra null"
);

console.log( "" );
console.log( "consultarMediciones" );

const cuerpoBueno = JSON.stringify( { mediciones: [ medicionDelServidor() ] } );

sinRespuesta();
logica.consultarMediciones( function () {}, function () {} );
comprobar( ultimaPeticion.metodo === "GET", "la peticion se hace por GET" );
comprobar( ultimaPeticion.url === "http://localhost:8080/servidor/rest/recuperarMedicion.php",
	"la peticion va a la URL que dicen HOST, PUERTO y RUTA_RECUPERAR" );

const r200 = consultar( function () { servidorContesta( 200, cuerpoBueno ); } );
comprobar( r200.llamadas === 1, "con 200 se llama a un solo callback" );
comprobar( r200.error === null, "con 200 no se llama al callback de error" );
comprobar( r200.exito !== null && r200.exito.length === 1, "con 200 llegan las mediciones" );
comprobar( r200.exito !== null && r200.exito[0].nombre === "GTI-3A",
	"con 200 las mediciones llegan YA ADAPTADAS (nombre, no nombre_emisora)" );
comprobar( r200.exito !== null && r200.exito[0].id === 1,
	"con 200 llega tambien el id, que la web pinta en la primera columna" );

const r500 = consultar( function () {
	servidorContesta( 500, JSON.stringify( { error: "MySQL esta apagado" } ) );
} );
comprobar( r500.llamadas === 1, "con 500 se llama a un solo callback" );
comprobar( r500.exito === null, "con 500 no se llama al callback de exito" );
comprobarIgual( r500.error, "MySQL esta apagado", "con 500 se enseña el mensaje del servidor" );

const r405 = consultar( function () {
	servidorContesta( 405, JSON.stringify( { error: "Metodo no permitido" } ) );
} );
comprobarIgual( r405.error, "Metodo no permitido", "con 405 tambien se pasa el mensaje del servidor" );

const r500Tonto = consultar( function () { servidorContesta( 500, "" ); } );
comprobar( r500Tonto.error !== null && r500Tonto.error.indexOf( "500" ) !== -1,
	"con 500 y sin cuerpo JSON, el aviso incluye el codigo de error" );

const rRed = consultar( function () { redCaida(); } );
comprobar( rRed.llamadas === 1, "con la red caida se llama a un solo callback" );
comprobar( rRed.exito === null, "con la red caida no se llama al callback de exito" );
comprobar( rRed.error !== null && rRed.error.indexOf( "conectar" ) !== -1,
	"con la red caida se avisa de que no se ha podido conectar" );

const rRoto = consultar( function () { servidorContesta( 200, "esto no es JSON" ); } );
comprobar( rRoto.exito === null && rRoto.error !== null,
	"con 200 pero el cuerpo no es JSON, se avisa de error" );

const rSinLista = consultar( function () { servidorContesta( 200, JSON.stringify( { otra_cosa: 1 } ) ); } );
comprobar( rSinLista.exito === null && rSinLista.error !== null,
	"con 200 pero sin la lista, se avisa de error" );

const rDosVeces = consultar( function () {
	responderFalso = function ( xhr ) {
		xhr.status = 500;
		xhr.responseText = "";
		xhr.readyState = 4;
		xhr.onreadystatechange();
		xhr.onerror(); // el navegador avisa dos veces al caerse la red
	};
} );
comprobar( rDosVeces.llamadas === 1,
	"si saltan a la vez el error y la red caida, el callback se llama solo una vez" );

// ---------------------------------------------------------------------------
// RESULTADO
// ---------------------------------------------------------------------------
console.log( "" );
console.log( "----------------------------------------" );

if ( fallos === 0 ) {
	console.log( pruebas + " pruebas, " + fallos + " fallos. TODO CORRECTO." );
} else {
	console.log( pruebas + " pruebas, " + fallos + " fallos. HAY ALGO QUE ARREGLAR." );
}

console.log( "" );
process.exitCode = ( fallos === 0 ) ? 0 : 1;

// ===========================================================================
// PRUEBAS
//
// Para pasarlas:
//
//     cd "C:\Users\MarcV\Desktop\otra vez\Sprint_0_Marc_Villalba\web\pruebas"
//     node pruebasLogicaFake.js
//
// Resultado esperado: 20 pruebas, 0 fallos. TODO CORRECTO.
//
// Que se prueba y como:
//
//   construirURL       junta host + puerto + ruta. Sin red.
//   adaptarMediciones  renombra los campos y conserva los valores
//                      (minor 1234, rssi -53, la fecha en texto).
//   formatearFecha     "2026-09-26 12:34:05" -> "26/09/2026 12:34".
//   consultarMediciones XHR simulado, con 4 respuestas distintas:
//                      200 con lista, 500 con mensaje, 405, y la red
//                      caida. Tambien que el callback se llame una vez.
//
// Que NO se prueba (es del PROMPT 7, aqui no hay nada que mirar):
//
//   que se pinte la tabla, la cabecera "Ultima medida registrada", el
//   separador "medidas anteriores" ni el sufijo " ppm" de la columna
//   Medicion. Esta logica no sabe nada del DOM.
//
// ===========================================================================