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
// Cada cuanto empieza un beacon (milisegundos). 5000 = uno cada 5 segundos.
// OJO: esto es CUANDO empieza, no cuanto dura. El beacon anuncia durante
// DURACION_ANUNCIO y luego se calla; vuelve a sonar cuando se cumple este
// intervalo. Mientras anuncia, la radio repite el MISMO paquete cada 62,5 ms
// (setInterval(100, 100)) para que no se pierda ninguno aunque el micro este
// ocupado; eso NO son varios beacons, es el mismo repetido.
long INTERVALO_EMISION = 5000;
// Cuanto dura el anuncio antes de callarse (milisegundos). 1000 = anuncia 1 s
// y se queda callado el resto del intervalo. Ponlo menor que INTERVALO_EMISION.
long DURACION_ANUNCIO = 1000;
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
// Las lucecitas, pero SIN delay(): antes brillar() llamaba a esperar(), que es
// delay(), y la secuencia entera tumbaba el loop 3,5 s. Ahora el loop va mirando
// la hora con millis() y va cambiando de fase cuando toca, asi que el beacon
// sale puntual.
// --------------------------------------------------------------
// --> avanzarLuces() -->
// --------------------------------------------------------------
namespace Loop {
  uint8_t cont = 0;
  unsigned long instanteEmision = 0;
  unsigned long instanteFaseLuz = 0;
  uint8_t faseLuz = 0;
  bool anunciando = false;
};

// Cada fase: quantos milisegundos dura, y si el LED queda encendido.
// Mismo ritmo que la lucescitas() del esqueleto.
const long DURACION_FASES[] = {  100, 400, 100, 400, 100, 400, 1000, 1000 };
const bool LUZ_ENCENDIDA[]  = { true, false, true, false, true, false, true, false };
const int NUM_FASES_LUZ = 8;

void avanzarLuces() {

  using namespace Loop;
  using namespace Globales;

  unsigned long ahora = millis();

  // aun no toca cambiar de fase
  if ( ahora - instanteFaseLuz < DURACION_FASES[ faseLuz ] ) {
    return;
  }

  instanteFaseLuz = ahora;
  faseLuz = ( faseLuz + 1 ) % NUM_FASES_LUZ;

  if ( LUZ_ENCENDIDA[ faseLuz ] ) {
    elLED.encender();
  } else {
    elLED.apagar();
  }

} // ()

// --------------------------------------------------------------
// Emite el valor del proyecto como un iBeacon y despues se calla.
// Anuncia durante DURACION_ANUNCIO y espera el resto hasta que se
// cumple INTERVALO_EMISION; entonces emite el siguiente.
// No lleva delay(): el micro queda libre para que la radio anuncie.
// No recibe nada y no devuelve nada.
// --------------------------------------------------------------
// --> loop() -->
// --------------------------------------------------------------
// --------------------------------------------------------------
void loop () {

  using namespace Loop;
  using namespace Globales;

  unsigned long ahora = millis();

  if ( anunciando ) {

    // 1. esta anunciando: cuando se cumple DURACION_ANUNCIO, se calla.
    if ( ahora - instanteEmision >= DURACION_ANUNCIO ) {
      elPublicador.pararAnuncio();
      anunciando = false;
    }

  } else if ( ahora - instanteEmision >= INTERVALO_EMISION ) {

    // 2. esta callado: cuando se cumple INTERVALO_EMISION, empieza otro beacon.
    //    instanteEmision marca el arranque de ESTE beacon; de ahi se cuentan
    //    tanto la duracion del anuncio como la espera hasta el siguiente.
    instanteEmision = ahora;
    cont++;

    //    publicarValor() solo arranca el anuncio y vuelve enseguida (sin delay()),
    //    para que el micro quede libre y la radio pueda ir anunciando.
    elPublicador.publicarValor( VALOR_MINOR, cont, DURACION_ANUNCIO );
    anunciando = true;
  }

  avanzarLuces();

} // loop ()
// --------------------------------------------------------------
