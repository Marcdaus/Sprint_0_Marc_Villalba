// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: JavaScript
// Curso: 3ºGTI
// =====================================

// ---------------------------------------------------
//
// Traduce las mediciones que devuelve el servidor a objetos planos que la
// web ya puede pintar.
//
// El servidor manda los nombres como los tiene en la tabla (nombre_emisora,
// tipo_medicion, valor_medicion); aqui se acortan para que la web lea mejor:
//   nombre_emisora -> nombre,   tipo_medicion -> tipo,   valor_medicion -> valor
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

			// el nombre de la emisora
			nombre: viene( medicion, "nombre_emisora" ) ? medicion.nombre_emisora : null,

			// el tipo de medicion, ya en texto ("CO2", "temperatura", "prueba")
			tipo: viene( medicion, "tipo_medicion" ) ? medicion.tipo_medicion : null,

			// el valor del proyecto. Llega como numero del servidor (ver el
			// final de recuperarMedicion()), no como texto. Se copia tal cual,
			// sin redondear ni convertir
			valor: viene( medicion, "valor_medicion" ) ? medicion.valor_medicion : null,

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
// porque el valor puede ser justo 0 y con "||" se perdería (0 es falsy en
// JavaScript). Con esto se distingue "viene con 0" de "no viene".
//
// B <-- viene() <-- objeto: Medicion, campo: Text
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
