// -*-c++-*-

// --------------------------------------------------------------
// HolaMundoIBeacon: programa principal de la plaquita del
// proyecto.
//
// La plaquita (Adafruit Feather nRF52840) anuncia un iBeacon
// estandar con:
//    - uuid   = "EPSG-GTI-MARC-3A"  (16 bytes, los del campo uuid)
//    - major  = (DATOS << 8) | contador   -> 0x0E00 | contador
//    - minor  = VALOR_MINOR   (el VALOR DEL PROYECTO, hoy 1234)
//    - txPower = 4
// Es el unico dato que la app Android necesita: ella se queda
// solo con nuestro beacon (companyID 0x004C), saca el "minor" de
// la trama y lo sube por POST al servidor REST, que lo guarda en
// la BD; una web lo muestra.
//
// El "loop()" ya no emite los datos ficticios de CO2/temperatura
// ni la carga de prueba del esqueleto: solo publica el valor.
//
// Notacion: Logical Design & Reverse Engineering v3
// --------------------------------------------------------------
//
// Jordi Bataller i Mascarell
// 2019-07-07
//
// --------------------------------------------------------------

// https://learn.sparkfun.com/tutorials/nrf52840-development-with-arduino-and-circuitpython

// https://stackoverflow.com/questions/29246805/can-an-ibeacon-have-a-data-payload

// --------------------------------------------------------------
// --------------------------------------------------------------
// --------------------------------------------------------------
// ====== VARIABLES MODIFICABLES ======
// Valor del proyecto que viaja en el "minor" del iBeacon.
// Cambiar aqui para la demo: no esta clavado en el codigo.
int VALOR_MINOR = 1234;
// Tiempo que cada beacon se mantiene anunciando (milisegundos).
int TIEMPO_EMISION = 1000;
// Tiempo de reposo entre publicaciones del "loop()" (milisegundos).
int TIEMPO_ESPERA = 2000;
// =====================================
// --------------------------------------------------------------
// --------------------------------------------------------------
#include <bluefruit.h>

#undef min // vaya tela, están definidos en bluefruit.h y  !
#undef max // colisionan con los de la biblioteca estándar

// --------------------------------------------------------------
// --------------------------------------------------------------
#include "LED.h"
#include "PuertoSerie.h"

// --------------------------------------------------------------
// --------------------------------------------------------------
namespace Globales {
  
  LED elLED ( /* NUMERO DEL PIN LED = */ 7 );

  PuertoSerie elPuerto ( /* velocidad = */ 115200 ); // 115200 o 9600 o ...

  // Serial1 en el ejemplo de Curro creo que es la conexión placa-sensor 
};

// --------------------------------------------------------------
// --------------------------------------------------------------
#include "EmisoraBLE.h"
#include "Publicador.h"
#include "Medidor.h"


// --------------------------------------------------------------
// --------------------------------------------------------------
namespace Globales {

  Publicador elPublicador;

  Medidor elMedidor;

}; // namespace

// --------------------------------------------------------------
// --------------------------------------------------------------
void inicializarPlaquita () {

  // de momento nada

} // ()

// --------------------------------------------------------------
// setup()
// --------------------------------------------------------------
void setup() {

 // Globales::elPuerto.esperarDisponible();

  inicializarPlaquita();

  // Suspend Loop() to save power
  // suspendLoop();

  Globales::elPublicador.encenderEmisora();

  // Globales::elPublicador.laEmisora.pruebaEmision();
  
  Globales::elMedidor.iniciarMedidor();

  // 
  // 
  // 
  esperar( 1000 );

  Globales::elPuerto.escribir( "---- setup(): fin ---- \n " );

} // setup ()

// --------------------------------------------------------------
// --------------------------------------------------------------
inline void lucecitas() {
  using namespace Globales;

  elLED.brillar( 100 ); // 100 encendido
  esperar ( 400 ); //  100 apagado
  elLED.brillar( 100 ); // 100 encendido
  esperar ( 400 ); //  100 apagado
  Globales::elLED.brillar( 100 ); // 100 encendido
  esperar ( 400 ); //  100 apagado
  Globales::elLED.brillar( 1000 ); // 1000 encendido
  esperar ( 1000 ); //  100 apagado
} // ()

// --------------------------------------------------------------
// loop ()
// --------------------------------------------------------------
namespace Loop {
  uint8_t cont = 0;
};

// ..............................................................
// ..............................................................
// Emite el valor del proyecto como un iBeacon y para el anuncio.
// No recibe nada y no devuelve nada.
// ..............................................................
// ..............................................................
// --> loop() -->
// ..............................................................
// ..............................................................
void loop () {

  using namespace Loop;
  using namespace Globales;

  cont++;

  elPuerto.escribir( "\n---- loop(): empieza " );
  elPuerto.escribir( cont );
  elPuerto.escribir( "\n" );


  lucecitas();

  // 
  // publico el VALOR DEL PROYECTO:
  // el valor (VALOR_MINOR) viaja en el campo "minor" del iBeacon
  // 
  elPublicador.publicarValor( VALOR_MINOR,
                              cont,
                              TIEMPO_EMISION // intervalo de emisión
                              );

  esperar( TIEMPO_ESPERA );

  elPublicador.laEmisora.detenerAnuncio();
  
  // 
  // 
  // 
  elPuerto.escribir( "---- loop(): acaba **** " );
  elPuerto.escribir( cont );
  elPuerto.escribir( "\n" );
  
} // loop ()
// --------------------------------------------------------------
