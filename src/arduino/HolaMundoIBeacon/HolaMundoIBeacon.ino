// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: C++ (Arduino)
// Curso: 3ºGTI
// =====================================

// --------------------------------------------------------------
// HolaMundoIBeacon: programa principal de la plaquita del
// proyecto.
//
// La plaquita (Adafruit Feather nRF52840) anuncia un iBeacon
// estandar con:
//    - uuid   = "EPSG-GTI-MARC-3A"  (16 bytes, los del campo uuid)
//    - major  = (PRUEBA << 8) | contador   -> 0x0300 | contador
//               (los 8 bits altos son el TIPO de medicion, los 8
//                bajos el contador)
//    - minor  = VALOR_MINOR   (el VALOR DEL PROYECTO, hoy 1234)
// Es el unico dato que la app Android necesita: ella se queda
// solo con nuestro beacon (companyID 0x004C), saca el tipo y el
// "minor" de la trama, traduce el tipo a texto y lo sube por POST
// al servidor REST, que lo guarda en la BD; una web lo muestra.
//
// Notacion: Logical Design & Reverse Engineering v3
// --------------------------------------------------------------



// https://learn.sparkfun.com/tutorials/nrf52840-development-with-arduino-and-circuitpython

// https://stackoverflow.com/questions/29246805/can-an-ibeacon-have-a-data-payload

// ====== VARIABLES MODIFICABLES ======
// Valor del proyecto que viaja en el "minor" del iBeacon (el tipo de
// medicion "prueba" viaja, codificado, en el "major").
int VALOR_MINOR = 1234;
// Cada cuanto empieza un beacon (milisegundos). 
long INTERVALO_EMISION = 5000;
// Cuanto dura el anuncio antes de callarse (milisegundos). 
long DURACION_ANUNCIO = 1000;
// =====================================

#include <bluefruit.h>

// ====== Icluir Clases adcionales =====
#include "LED.h"
#include "PuertoSerie.h"

// =====================================

namespace Globales {
  
  LED elLED ( 7 ); //PIN LED

  PuertoSerie elPuerto ( 115200 );//baudios en el serial monitor
};

// ====== Icluir Clases adcionales =====
#include "EmisoraBLE.h"
#include "Publicador.h"
#include "Medidor.h"
// =====================================



// ====== Declara objetos globales =====
//Por qué un namespace y no variables normales: es una forma de tener globales agrupados y con nombre propio (Globales::) sin ensuciar el espacio global 
namespace Globales {

  Publicador elPublicador;

  Medidor elMedidor;

}; 
// =====================================


// --------------------------------------------------------------
// setup()
// --> setup() -->
// --------------------------------------------------------------
void setup() {

  //encender emisora para empezar a emitir
  Globales::elPublicador.encenderEmisora();
  
  Globales::elMedidor.iniciarMedidor();

  esperar( 1000 );

  Globales::elPuerto.escribir( "---- setup(): fin ---- \n " );

} // setup ()


// --------------------------------------------------------------
// Grupo de variables "Loop" ya que son las que se utilizaran en el metodo loop
// --------------------------------------------------------------
namespace Loop {
  uint8_t cont = 0;                  // contador de beacons: viaja en los 8 bits bajos del "major"
  unsigned long instanteEmision = 0; // millis() en que arranco el beacon que se esta anunciando
  unsigned long instanteFaseLuz = 0; // millis() del ultimo cambio de fase del LED
  uint8_t faseLuz = 0;               // fase actual del parpadeo (indice en DURACION_FASES/LUZ_ENCENDIDA)
  bool anunciando = false;           // true mientras la radio anuncia el beacon de esta vuelta
};
//==================PARPADEO DE LUCES==============================
// Cada fase: quantos milisegundos dura, y si el LED queda encendido.
// Mismo ritmo que la lucescitas() del esqueleto.
const long DURACION_FASES[] = {  100, 400, 100, 400, 100, 400, 1000, 1000 };
const bool LUZ_ENCENDIDA[]  = { true, false, true, false, true, false, true, false };
const int NUM_FASES_LUZ = 8;

// --------------------------------------------------------------
// --> avanzarLuces() -->
// --------------------------------------------------------------
// Hace avanzar el parpadeo del LED SIN delay(). Se llama en cada
// vuelta del loop(): casi siempre no hace nada y, cuando se ha
// cumplido la duracion de la fase actual, pasa a la siguiente.
// No recibe nada y no devuelve nada.
// --------------------------------------------------------------
void avanzarLuces() {

  // de aqui salen faseLuz / instanteFaseLuz 
  using namespace Loop;
  using namespace Globales;

  // 1. que hora es ahora mismo
  unsigned long ahora = millis();

  // 2.Si la fase actual aún no ha durado lo suyo → return 
  if ( ahora - instanteFaseLuz < DURACION_FASES[ faseLuz ] ) {
    return;
  }

  // 3. si toca: la fase actual se da por terminada, empezamos a contar
  //    la nueva desde este instante y pasamos a la fase siguiente.
  instanteFaseLuz = ahora;
  faseLuz = ( faseLuz + 1 ) % NUM_FASES_LUZ;

  // 4. aplicamos la luz que toca en esta fase nueva: encendido o apagado
  if ( LUZ_ENCENDIDA[ faseLuz ] ) {
    elLED.encender();
  } else {
    elLED.apagar();
  }

} // avanzarLuces()

// --------------------------------------------------------------
// --------------------------------------------------------------
// --> loop() -->
// --------------------------------------------------------------
//
//   a) EL BEACON: lleva el ciclo de emision, que es como un interruptor
//      de dos estados, "anunciando" y "callado":
//        - si esta ANUNCIANDO y ya ha durado DURACION_ANUNCIO -> se calla;
//        - si esta CALLADO y ya ha pasado INTERVALO_EMISION -> emite otro.
//      Toda la cuenta se hace comparando la hora actual (millis()) con
//      instanteEmision, que es cuando arranco el beacon de esta vuelta.
//
//   b) EL LED: llama a avanzarLuces() en cada pasada, que va moviendo el
//      parpadeo segun el patron (DURACION_FASES / LUZ_ENCENDIDA).
//
// No recibe nada y no devuelve nada.
// --------------------------------------------------------------
void loop () {
//declaramos los grupos de variables que vamos a utilizar
  using namespace Loop;
  using namespace Globales;

  // 1. que hora es ahora mismo
  unsigned long ahora = millis();

  if ( anunciando ) {

    // 2. ESTA ANUNCIANDO: cuando el anuncio lleva DURACION_ANUNCIO ms
    //    puesto, lo paramos y quedamos "callados" hasta la vuelta siguiente.
    if ( ahora - instanteEmision >= DURACION_ANUNCIO ) {
      elPublicador.pararAnuncio();
      anunciando = false;
    }

  } else if ( ahora - instanteEmision >= INTERVALO_EMISION ) {

    // 3. ESTA CALLADO y ya se cumple INTERVALO_EMISION: toca emitir otro
    //    beacon. instanteEmision marca el arranque de ESTE beacon; de ahi
    //    se cuentan tanto lo que durara el anuncio como la espera siguiente.
    instanteEmision = ahora;
    cont++;

    //    publicarValor() solo arranca el anuncio y vuelve enseguida 
    //    , para que el micro quede libre y la radio pueda anunciar.
    //    El "minor" del beacon es VALOR_MINOR .
    elPublicador.publicarValor( VALOR_MINOR, cont, DURACION_ANUNCIO );
    anunciando = true;
  }

  // 4. en cada pasada, ademas, movemos el parpadeo del LED
  avanzarLuces();

} // loop ()
// --------------------------------------------------------------
