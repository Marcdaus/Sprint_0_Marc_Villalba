// ---------------------------------------------------
//
// La unica funcion que habla con el servidor.
//
// Pide las mediciones por XHR al PHP de recuperacion y se las pasa a la
// web YA ADAPTADAS, para que quien pinte no sepa nada de XHR ni de JSON.
//
// NO toca el DOM: solo llama a los callbacks que le pasen
//   - si va bien, callbackExito con la lista de mediciones;
//   - si falla, callbackError con un texto para enseñar.
//
// Cada callback se llama UNA sola vez. Importa porque cuando la red se
// cae, el navegador avisa dos veces (una por onreadystatechange y otra por
// onerror), y sin esta caution se llamaria dos veces al mismo callback.
//
// ---------------------------------------------------

// callbackExito: [Medicion] -->, callbackError: Text --> consultarMediciones() -->
function consultarMediciones( callbackExito, callbackError ) {

	// se pone a true en cuanto se conteste, para no repetir
	var yaRespondido = false;

	// ---------------------------------------------------
	// Envoltorio: llama al callback solo si no se ha llamado antes.
	// ---------------------------------------------------
	function responder( callback, valor ) {

		// ya se ha avisado antes (p.ej. el 500 y luego el error de red)
		if ( yaRespondido ) {
			return;
		}

		yaRespondido = true;
		callback( valor );

	} // ()

	var xhr = new XMLHttpRequest();

	// el texto que se enseña si el servidor no contesta. Se avisa de que
	// puede ser que este apagado, porque es el fallo mas tipico
	var mensajeRed = "No se ha podido conectar con el servidor. Esta encendido?";

	xhr.onreadystatechange = function () {

		// solo se mira cuando la peticion ha terminado
		if ( this.readyState !== 4 ) {
			return;
		}

		// ---------------------------------------------------
		// 200: todo bien. SeAdaptan las mediciones y se pasan limpias.
		// ---------------------------------------------------
		if ( this.status === 200 ) {

			var datos;

			try {
				datos = JSON.parse( this.responseText );
			} catch ( error ) {

				// el servidor contesto 200 pero con algo que no es JSON
				responder( callbackError, "El servidor ha contestado con algo que no se entiende." );
				return;
			}

			// el JSON es valido, pero puede que no traiga la lista.
			// "null" es JSON valido, por eso tambien se mira
			if ( datos === null || ! Array.isArray( datos.mediciones ) ) {
				responder( callbackError, "El servidor no ha mandado la lista de mediciones." );
				return;
			}

			responder( callbackExito, adaptarMediciones( datos.mediciones ) );
			return;

		}

		// ---------------------------------------------------
		// Cualquier otro codigo (405, 500...): se intenta sacar el texto
		// "error" que manda el servidor, que es mas util que el codigo.
		// ---------------------------------------------------
		var detalle = "";

		try {
			var fallo = JSON.parse( this.responseText );

			if ( fallo !== null && typeof fallo.error === "string" ) {
				detalle = fallo.error;
			}
		} catch ( error ) {

			// si el cuerpo no es JSON, se queda el detalle vacio y se
			// inventa un texto con el codigo de error
		}

		if ( detalle === "" ) {
			detalle = "El servidor ha contestado con un error (" + this.status + ").";
		}

		responder( callbackError, detalle );

	};

	// si se cae la red (servidor apagado, cable desenchufado...), el
	// navegador llama aqui y no con un codigo de error normal
	xhr.onerror = function () {
		responder( callbackError, mensajeRed );
	};

	// se pide el PHP que devuelve las mediciones. La direccion se monta con
	// construirURL() a partir de los valores de configuracion.js
	xhr.open( "GET", construirURL( HOST, PUERTO, RUTA_RECUPERAR ), true );
	xhr.send();

} // ()