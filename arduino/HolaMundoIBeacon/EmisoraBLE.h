// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: C++ (Arduino)
// Curso: 3ºGTI
// =====================================
//si no está definido lo define
#ifndef EMISORA_H_INCLUIDO
#define EMISORA_H_INCLUIDO

// Buena introducción: https://learn.adafruit.com/introduction-to-bluetooth-low-energy/gap
// https://os.mbed.com/blog/entry/BLE-Beacons-URIBeacon-AltBeacons-iBeacon/

// fuente: https://www.instructables.com/id/Beaconeddystone-and-Adafruit-NRF52-Advertise-Your-/
// https://github.com/nkolban/ESP32_BLE_Arduino/blob/master/src/BLEBeacon.h

// https://os.mbed.com/blog/entry/BLE-Beacons-URIBeacon-AltBeacons-iBeacon/
// https://learn.adafruit.com/bluefruit-nrf52-feather-learning-guide/bleadvertising

// ====== Icluir Clases adcionales =====
#include "ServicioEnEmisora.h"
// =====================================

class EmisoraBLE {
private:

  const char * nombreEmisora;
  const uint16_t fabricanteID;
  const int8_t txPower;

public:

  // .........................................................
  // .........................................................
  using CallbackConexionEstablecida = void ( uint16_t connHandle );
  using CallbackConexionTerminada = void ( uint16_t connHandle, uint8_t reason);

  // .........................................................
  // nombre: Text, fabricanteID: N, txPower: Z --> EmisoraBLE() -->
  // ====== constructor =====
  EmisoraBLE( const char * nombreEmisora_, const uint16_t fabricanteID_,const int8_t txPower_ ) : nombreEmisora( nombreEmisora_ ) ,fabricanteID( fabricanteID_ ) ,txPower( txPower_ )
  {
		//cuerpo constructor
  } 
// =====================================
	//función que arranca y configura la radio BLE una sola vez (la llama setup(),
	// a través de elPublicador.encenderEmisora()).
	// .........................................................
  // --> encenderEmisora() -->
  // .........................................................
  void encenderEmisora() {
	 Bluefruit.begin(); //Arranca la pila BLE de la placa 

	 Bluefruit.setTxPower( (*this).txPower ); //Fija la potencia de emisión de la radio (dBm). Más potencia = más alcance
	 Bluefruit.setName( (*this).nombreEmisora );//Fija el nombre GAP del dispositivo Aquí "GTI-3A-Marc"

	 Bluefruit.ScanResponse.clearData();// Vacía el contenido del scan response.
	 Bluefruit.ScanResponse.addName(); // Añade el nombre del dispositivo 

	 // Llama a detenerAnuncio(), que mira estaAnunciando() y, si había un anuncio activo,
	 (*this).detenerAnuncio();
  } // encenderEmisora()

  // .........................................................
  // cbce: CallbackConexionEstablecida, cbct: CallbackConexionTerminada --> encenderEmisora() -->
  // .........................................................
  void encenderEmisora( CallbackConexionEstablecida cbce,
						CallbackConexionTerminada cbct ) {

	encenderEmisora();

	instalarCallbackConexionEstablecida( cbce );
	instalarCallbackConexionTerminada( cbct );

  } // ()

  // .........................................................
  // --> detenerAnuncio() -->
  // .........................................................
  void detenerAnuncio() {

	if ( (*this).estaAnunciando() ) {
	  // Serial.println ( "Bluefruit.Advertising.stop() " );
	  Bluefruit.Advertising.stop(); 
	}

  }  // ()
  
  // .........................................................
  // B <-- estaAnunciando() <--
  // .........................................................
  bool estaAnunciando() {
	return Bluefruit.Advertising.isRunning();
  } // ()

  // .........................................................
  // uuid: [N]_16, major: Z, minor: Z, rssi: N --> emitirAnuncioIBeacon() -->
  // Emite el iBeacon y lo deja anunciando.
  // NO vuelve a tocar el nombre: eso lo hace encenderEmisora(), una sola vez
  // (ver el aviso de arriba sobre la fuga de memoria del pool de ScanResponse).
  // Devuelve true si el anuncio ha arrancado de verdad, para poder avisar.
  // .........................................................
  bool emitirAnuncioIBeacon( uint8_t * beaconUUID, int16_t major, int16_t minor, uint8_t rssi ) {

	//
	(*this).detenerAnuncio();

	//crear beacon
	BLEBeacon elBeacon( beaconUUID, major, minor, rssi );
	elBeacon.setManufacturer( (*this).fabricanteID );

	// limpiar el pool de datos del anuncio antes de meter los nuevos
	Bluefruit.Advertising.clearData();

	// poner el beacon
	Bluefruit.Advertising.setBeacon( elBeacon );

	//le dice a la radio: cuando se caiga una conexión, vuelve a anunciar solo.
	Bluefruit.Advertising.restartOnDisconnect(true);

	//intervalo entre anuncios, en unidades de 0,625 ms.
	Bluefruit.Advertising.setInterval(100, 100);

	//empieza el anuncio, 0 = tiempo indefinido (ya lo pararán)
	bool r = Bluefruit.Advertising.start( 0 );

	//si hak algun error en el puerto devuelve el error.
	if ( ! r ) {
		Globales::elPuerto.escribir( " ERROR: Advertising.start() ha fallado: no se anuncia\n" );
	}

	return r;

  } // emitirAnuncioIBeacon ()

  // .........................................................
  //
  // Ejemplo de Beacon (31 bytes)
  //
  // https://os.mbed.com/blog/entry/BLE-Beacons-URIBeacon-AltBeacons-iBeacon/
  //
  // The iBeacon Prefix contains the hex data : 0x0201061AFF004C0215. This breaks down as follows:
  //
  // 0x020106 defines the advertising packet as BLE General Discoverable and BR/EDR high-speed incompatible.
  // Effectively it says this is only broadcasting, not connecting.
  //
  // 0x1AFF says the following data is 26 bytes long and is Manufacturer Specific Data.
  //
  // 0x004C is Apple’s Bluetooth Sig ID and is the part of this spec that makes it Apple-dependent.
  //
  // 0x02 is a secondary ID that denotes a proximity beacon, which is used by all iBeacons.
  //
  // 0x15 defines the remaining length to be 21 bytes (16+2+2+1).
  //
  // Por ejemmplo:
  //
  // 1. prefijo: 9bytes
  //       0x02, 0x01, 0x06,       // advFlags 3bytes
  //       0x1a, 0xff,             // advHeader 2 (0x1a = 26 = 25(lenght de 0x4c a 0xca)+1)   0xFF -> BLE_GAP_AD_TYPE_MANUFACTURER_SPECIFIC_DATA
  //       0x4c, 0x00,             // companyID 2bytes
  //       0x02,                   // ibeacon type 1 byte
  //       0x15,                   // ibeacon length 1 byte (dec=21 lo que va a continuación: desde  la 'f' hasta 0x01)
  // 
  // 2. uuid: 16bytes
  // 'f', 'i', 's', 't', 'r', 'o', 'f', 'i', 's', 't', 'r', 'o', 0xa7, 0x10, 0x96, 0xe0
  // 
  // 2 major: 2bytes
  // 0x04, 0xd2,
  // 
  // minor: 2bytes
  // 0x10, 0xe1,
  // 
  // 0xca, // tx power : 1bytes
  //
  // 0x01, // este es el byte 31 = BLE_GAP_ADV_SET_DATA_SIZE_MAX, parece que sobra
  //
  // .........................................................
  // Para enviar como carga libre los últimos 21 bytes de un iBeacon (lo que normalmente sería uuid-16 major-2 minor-2 txPower-1)
  // .........................................................
  /*
  void emitirAnuncioIBeaconLibre( const char * carga ) {

	const uint8_t tamanyoCarga = strlen( carga );
  */
	// .........................................................
  // carga: Text, tamanyoCarga: N --> emitirAnuncioIBeaconLibre() -->
  // .........................................................
  void emitirAnuncioIBeaconLibre( const char * carga, const uint8_t tamanyoCarga ) {

	(*this).detenerAnuncio(); 

	Bluefruit.Advertising.clearData();
	Bluefruit.ScanResponse.clearData(); // hace falta?

	// Bluefruit.setTxPower( (*this).txPower ); creo que no lo pongo porque es uno de los bytes de la parte de carga que utilizo
	Bluefruit.setName( (*this).nombreEmisora );
	Bluefruit.ScanResponse.addName();

	Bluefruit.Advertising.addFlags(BLE_GAP_ADV_FLAGS_LE_ONLY_GENERAL_DISC_MODE);

	// con este parece que no va  !
	// Bluefruit.Advertising.addFlags(BLE_GAP_ADV_FLAG_LE_GENERAL_DISC_MODE);

	//
	// hasta ahora habrá, supongo, ya puestos los 5 primeros bytes. Efectivamente.
	// Falta poner 4 bytes fijos (company ID, beacon type, longitud) y 21 de carga
	//
	uint8_t restoPrefijoYCarga[4+21] = {
	  0x4c, 0x00, // companyID 2
	  0x02, // ibeacon type 1byte
	  21, // ibeacon length 1byte (dec=21)  longitud del resto // 0x15 // ibeacon length 1byte (dec=21)  longitud del resto
	  '-', '-', '-', '-', 
	  '-', '-', '-', '-', 
	  '-', '-', '-', '-', 
	  '-', '-', '-', '-', 
	  '-', '-', '-', '-', 
	  '-'
	};

	//
	// addData() hay que usarlo sólo una vez. Por eso copio la carga
	// en el anterior array, donde he dejado 21 sitios libres
	//
	memcpy( &restoPrefijoYCarga[4], &carga[0], ( tamanyoCarga > 21 ? 21 : tamanyoCarga ) ); 

	//
	// copio la carga para emitir
	//
	Bluefruit.Advertising.addData( BLE_GAP_AD_TYPE_MANUFACTURER_SPECIFIC_DATA,
								   &restoPrefijoYCarga[0],
								   4+21 );

	//
	// ? qué valores poner aquí ?
	//
	Bluefruit.Advertising.restartOnDisconnect(true);
	Bluefruit.Advertising.setInterval(100, 100);    // in unit of 0.625 ms

	Bluefruit.Advertising.setFastTimeout( 1 );      // number of seconds in fast mode
	//
	// empieza el anuncio, 0 = tiempo indefinido (ya lo pararán)
	//
	Bluefruit.Advertising.start( 0 ); 

	Globales::elPuerto.escribir( "emitiriBeacon libre  Bluefruit.Advertising.start( 0 );  \n");
  } // emitirAnuncioIBeaconLibre()

  // .........................................................
  // servicio: ServicioEnEmisora --> anyadirServicio() -->
  // B <--
  // .........................................................
  bool anyadirServicio( ServicioEnEmisora & servicio ) {

	Globales::elPuerto.escribir( " Bluefruit.Advertising.addService( servicio ); \n");

	bool r = Bluefruit.Advertising.addService( servicio );

	if ( ! r ) {
	  Serial.println( " SERVICION NO AÑADIDO \n");
	}
	

	return r;
	 // nota: uso conversión de tipo de servicio (ServicioEnEmisora) a BLEService
	 // para addService()
  } // ()

  
  // .........................................................
  // servicio: ServicioEnEmisora --> anyadirServicioConSusCaracteristicas() -->
  // B <--
  // .........................................................
  bool anyadirServicioConSusCaracteristicas( ServicioEnEmisora & servicio ) { 
	return (*this).anyadirServicio( servicio );
  } // 

  // .........................................................
  // servicio: ServicioEnEmisora, carac: Caracteristica, resto...: T --> anyadirServicioConSusCaracteristicas() -->
  // B <--
  // .........................................................
  template <typename ... T>
  bool anyadirServicioConSusCaracteristicas( ServicioEnEmisora & servicio,
											 ServicioEnEmisora::Caracteristica & caracteristica,
											 T& ... restoCaracteristicas) {

	servicio.anyadirCaracteristica( caracteristica );

	return anyadirServicioConSusCaracteristicas( servicio, restoCaracteristicas... );
	
  } // ()

  // .........................................................
  // servicio: ServicioEnEmisora, resto...: T --> anyadirServicioConSusCaracteristicasYActivar() -->
  // B <--
  // .........................................................
  template <typename ... T>
  bool anyadirServicioConSusCaracteristicasYActivar( ServicioEnEmisora & servicio,
													 // ServicioEnEmisora::Caracteristica & caracteristica,
													 T& ... restoCaracteristicas) {

	bool r = anyadirServicioConSusCaracteristicas( servicio, restoCaracteristicas... );

	servicio.activarServicio();

	return r;
	
  } // ()

  // .........................................................
  // cb: CallbackConexionEstablecida --> instalarCallbackConexionEstablecida() -->
  // .........................................................
  void instalarCallbackConexionEstablecida( CallbackConexionEstablecida cb ) {
	Bluefruit.Periph.setConnectCallback( cb );
  } // ()

  // .........................................................
  // cb: CallbackConexionTerminada --> instalarCallbackConexionTerminada() -->
  // .........................................................
  void instalarCallbackConexionTerminada( CallbackConexionTerminada cb ) {
	Bluefruit.Periph.setDisconnectCallback( cb );
  } // ()

  // .........................................................
  // connHandle: N --> getConexion() -->
  // Conexion <--
  // .........................................................
  BLEConnection * getConexion( uint16_t connHandle ) {
	return Bluefruit.Connection( connHandle );
  } // ()

}; // class EmisoraBLE

#endif

// ----------------------------------------------------------

