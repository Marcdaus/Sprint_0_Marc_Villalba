// ---------------------------------------------------
//
// Pasa la fecha que da el servidor a algo mas comodo de leer.
//
//   "2026-09-26 12:34:05"  -->  "26/09/2026 12:34"
//
// Los segundos no se enseñan: para una tabla de medidas sobran.
//
// Si la fecha no llega con ese formato, se devuelve TAL CUAL estaba. Es
// mejor enseñar la fecha tal cual que fallar con "NaN" o deixar la celda
// en blanco. Lo mismo si no llega nada.
//
// ---------------------------------------------------

// fecha: Text --> formatearFecha() --> Text
function formatearFecha( fecha ) {

	// si no hay fecha, se devuelve texto vacio y la web pinta la celda
	// vacia en vez de la palabra "null"
	if ( fecha === null || fecha === undefined ) {
		return "";
	}

	// el servidor manda "AAAA-MM-DD HH:MM:SS". Se parte en trozos con una
	// expresion regular:
	//   1 = año, 2 = mes, 3 = día, 4 = hora, 5 = minuto
	// el [ T ] final permite que entre la fecha y la hora haya un espacio
	// o una T, por si algún dia el servidor cambia de formato
	var trozos = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec( fecha );

	// el formato no encaja: se devuelve la fecha sin tocar
	if ( trozos === null ) {
		return fecha;
	}

	// se reconstruen los trozos en el orden que se lee bien:
	// dia/mes/año hora:minuto
	return trozos[3] + "/" + trozos[2] + "/" + trozos[1] + " " + trozos[4] + ":" + trozos[5];

} // ()