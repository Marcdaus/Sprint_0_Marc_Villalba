// -*- mode: c++ -*-

// --------------------------------------------------------------
// Publicador: publica el valor del proyecto como un iBeacon
// estandar.
//
// La plaquita (nRF52840) anuncia un iBeacon cuyo uuid es
// "EPSG-GTI-MARC-3A" (16 bytes, los que caben en el campo uuid
// del iBeacon) y cuyo VALOR DEL PROYECTO viaja en el campo
// "minor" (int16). La app Android escucha ese beacon, se queda
// solo con el nuestro (companyID 0x004C) y sube el "minor" al
// servidor REST.
//
// La clase no emite por su cuenta: delega en EmisoraBLE. El
// metodo "publicarValor()" es el que usa el "loop()" del programa
// principal; "publicarCO2()" y "publicarTemperatura()" son del
// esqueleto y se dejan intactos.
//
// Notacion: Logical Design & Reverse Engineering v3
// --------------------------------------------------------------
// Jordi Bataller i Mascarell
// --------------------------------------------------------------

#ifndef PUBLICADOR_H_INCLUIDO
#define PUBLICADOR_H_INCLUIDO

// --------------------------------------------------------------
// --------------------------------------------------------------
class Publicador {

  // ............................................................
  // ............................................................
private:

  // Identidad de nuestro beacon: "EPSG-GTI-MARC-3A" (16 bytes)
  uint8_t beaconUUID[16] = { 
	'E', 'P', 'S', 'G', '-', 'G', 'T', 'I', 
	'-', 'M', 'A', 'R', 'C', '-', '3', 'A'
	};

  // ............................................................
  // ............................................................
public:
  EmisoraBLE laEmisora {
	"GTI-3A-Marc", //  nombre emisora
	  0x004c, // fabricanteID (Apple)
	  4 // txPower
	  };
  
  const int RSSI = -53; // por poner algo, de momento no lo uso

  // ............................................................
  // ............................................................
public:

  // ............................................................
  // ............................................................
  enum MedicionesID  {
	CO2 = 11,
	TEMPERATURA = 12,
	RUIDO = 13,
	DATOS = 14  // (new) tipo de medicion del valor del proyecto
  };

  // ............................................................
  // ............................................................
  Publicador( ) {
	// ATENCION: no hacerlo aquí. (*this).laEmisora.encenderEmisora();
	// Pondremos un método para llamarlo desde el setup() más tarde
  } // ()

  // ............................................................
  // ............................................................
  void encenderEmisora() {
	(*this).laEmisora.encenderEmisora();
  } // ()

  // ............................................................
  // ............................................................
  void publicarCO2( int16_t valorCO2, uint8_t contador,
					long tiempoEspera ) {

	//
	// 1. empezamos anuncio
	//
	uint16_t major = (MedicionesID::CO2 << 8) + contador;
	(*this).laEmisora.emitirAnuncioIBeacon( (*this).beaconUUID, 
											major,
											valorCO2, // minor
											(*this).RSSI // rssi
									);

	/*
	Globales::elPuerto.escribir( "   publicarCO2(): valor=" );
	Globales::elPuerto.escribir( valorCO2 );
	Globales::elPuerto.escribir( "   contador=" );
	Globales::elPuerto.escribir( contador );
	Globales::elPuerto.escribir( "   todo="  );
	Globales::elPuerto.escribir( major );
	Globales::elPuerto.escribir( "\n" );
	*/

	//
	// 2. esperamos el tiempo que nos digan
	//
	esperar( tiempoEspera );

	//
	// 3. paramos anuncio
	//
	(*this).laEmisora.detenerAnuncio();
  } // ()

  // ............................................................
  // ............................................................
  void publicarTemperatura( int16_t valorTemperatura,
							uint8_t contador, long tiempoEspera ) {

	uint16_t major = (MedicionesID::TEMPERATURA << 8) + contador;
	(*this).laEmisora.emitirAnuncioIBeacon( (*this).beaconUUID, 
											major,
											valorTemperatura, // minor
											(*this).RSSI // rssi
									);
	esperar( tiempoEspera );

	(*this).laEmisora.detenerAnuncio();
  } // ()
	
  // ............................................................
  // ............................................................
  // Calcula el campo "major" del iBeacon: los 8 bits altos son el
  // tipo de medicion (MedicionesID) y los 8 bajos el contador.
  // Funcion pura (--x, no usa estado): se separa del resto para
  // poder comprobarla con pruebas automaticas.
  // Recibe el tipo y el contador; devuelve el major.
  // ............................................................
  // ............................................................
  // tipo: N, contador: N --> calcularMajor() --x --> N
  // ............................................................
  // ............................................................
  static uint16_t calcularMajor( uint8_t tipo, uint8_t contador ) {

	// (14 << 8) | contador  ->  0x0E00 | contador  para DATOS
	return (uint16_t) ( (tipo << 8) | contador );
  } // ()

  // ............................................................
  // ............................................................
  // valor: Z, contador: N --> publicarValor() -->
  // ............................................................
  // ............................................................
  // Emite el valor del proyecto como un iBeacon y lo deja anunciando.
  //
  // OJO: aqui NO se espera con delay(). Antes se hacia "emitir -> esperar(1000)
  // -> detener", y ese delay() tumbaba el micro durante toda la ventana de anuncio:
  // la placa se quedaba callada mientras delay() corria. Ahora solo se arranca el
  // anuncio y se devuelve enseguida; quien lo para es el loop(), al ver que ha
  // pasado el intervalo. Asi el micro no para y los anuncios salen.
  // ............................................................
  // ............................................................
  void publicarValor( int16_t valor, uint8_t contador, long tiempoEspera ) {

	//
	// 1. calculamos el major: (DATOS << 8) + contador
	//
	uint16_t major = calcularMajor( MedicionesID::DATOS, contador );

	//
	// 2. aviso por el puerto serie de lo que se va a emitir
	//    (el valor del proyecto va en el campo "minor")
	//
	Globales::elPuerto.escribir( "\n    beacon: minor(valor)=" );
	Globales::elPuerto.escribir( valor );
	Globales::elPuerto.escribir( " major=" );
	Globales::elPuerto.escribir( major );
	Globales::elPuerto.escribir( " rssi=" );
	Globales::elPuerto.escribir( (*this).RSSI );
	Globales::elPuerto.escribir( "\n" );

	//
	// 3. emitimos el beacon con el VALOR en el campo "minor" y lo dejamos anunciando
	//
	(*this).laEmisora.emitirAnuncioIBeacon( (*this).beaconUUID, 
											major,
											valor, // minor = valor del proyecto
											(*this).RSSI // rssi
									);

	Globales::elPuerto.escribir( "    anunciando (ventana de " );
	Globales::elPuerto.escribir( tiempoEspera );
	Globales::elPuerto.escribir( " ms)\n" );

	// 4. SIN delay(): el pararlo lo hace el loop() cuando pasa el intervalo.
	//    Asi el micro no se bloquea y los anuncios salen con normalidad.

  } // ()

  // ............................................................
  // Para el anuncio del beacon. La llama el loop() cuando ya ha pasado
  // el intervalo de emision.
  // ............................................................
  // ............................................................
  void pararAnuncio() {
	(*this).laEmisora.detenerAnuncio();
	Globales::elPuerto.escribir( "    anuncio parado\n" );
  } // ()

  // ............................................................
  // Dice si la radio esta anunciando ahora mismo. Lo usa el loop() para
  // saber si tiene que parar el beacon de la vuelta anterior.
  // ............................................................
  // ............................................................
  bool hayAnuncioEnCurso() {
	return (*this).laEmisora.estaAnunciando();
  } // ()

}; // class

// --------------------------------------------------------------
// --------------------------------------------------------------
// --------------------------------------------------------------
// --------------------------------------------------------------
#endif
