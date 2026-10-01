// ---------------------------------------------------
//
// Traduce las mediciones que devuelve el servidor a objetos planos que la
// web ya puede pintar.
//
// El servidor manda los nombres como los tiene en la tabla (uuid_beacon,
// tx_power...); aqui se les quita el "_" para que la web lea mejor:
//   uuid_beacon -> uuid,   tx_power -> txPower
//
// Recibe la lista que llega de rest/recuperarMedicion.php y devuelve una
// lista nueva, en el mismo orden. NO toca el DOM y NO guarda nada: la
// pintura es del PROMPT 7.
//
// OJO: la fecha se deja TAL CUAL llega, en texto. Quien la quiera mas
// bonita (26/09/2026 12:34) la pasa por formatearFecha().
//
// ---------------------------------------------------

// [Medicion] Server --> adaptarMediciones() --> [Medicion] Vista
function adaptarMediciones( mediciones ) {

	// si lo que llega no es una lista, se devuelve una vacia. Es mejor
	// devolver una lista vacia que reventar al que este pintando
	if ( ! Array.isArray( mediciones ) ) {
		return [];
	}

	// se recorre la lista y se copia cada medicion con los nombres nuevos
	return mediciones.map( function ( medicion ) {

		return {

			// el id lo enseña la web en la primera columna de la tabla
			id: viene( medicion, "id" ) ? medicion.id : null,

			// el nombre del beacon, que es lo que va en la cabecera
			uuid: viene( medicion, "uuid_beacon" ) ? medicion.uuid_beacon : null,

			nombre: viene( medicion, "nombre_emisora" ) ? medicion.nombre_emisora : null,

			// los tres numeros llegan como numeros del servidor (ver el
			// final de recuperarMedicion()), no como texto
			major: viene( medicion, "major" ) ? medicion.major : null,

			// el minor es el valor que viaja dentro del iBeacon. Se copia
			// tal cual, sin redondear ni convertir
			minor: viene( medicion, "minor" ) ? medicion.minor : null,

			txPower: viene( medicion, "tx_power" ) ? medicion.tx_power : null,

			rssi: viene( medicion, "rssi" ) ? medicion.rssi : null,

			// la fecha sin tocar, en el texto del servidor
			fecha: viene( medicion, "fecha" ) ? medicion.fecha : null

		};

	});

} // ()

// ---------------------------------------------------
//
// Pequeña ayuda para adaptarMediciones().
//
// No se puede usar "||" para comprobar si un campo viene informado,
// porque el rssi puede ser justo 0 y con "||" se perdería (0 es falsy en
// JavaScript). Con esto se distingue "viene con 0" de "no viene".
//
// ---------------------------------------------------
function viene( objeto, campo ) {

	// si el objeto no existe, el campo no puede venir
	if ( objeto === null || objeto === undefined ) {
		return false;
	}

	// ni undefined ni null: el campo trae un valor de verdad
	return objeto[campo] !== undefined && objeto[campo] !== null;

} // ()