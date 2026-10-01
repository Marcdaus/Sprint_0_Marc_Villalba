// ---------------------------------------------------
//
// Monta la direccion completa del PHP que hay que llamar.
//
// No hace ninguna peticion: solo junta las piezas. La usa
// consultarMediciones(), que es quien de verdad habla con el servidor.
//
// Se separa del resto para poder probarla sola, sin red y sin servidor.
//
// ---------------------------------------------------

// host: Text, puerto: N, ruta: Text --> construirURL() --> Text
function construirURL( host, puerto, ruta ) {

	// se monta tal cual el orden que espera la peticion: protocolo, host,
	// puerto y luego la ruta
	return "http://" + host + ":" + puerto + "/" + ruta;

} // ()