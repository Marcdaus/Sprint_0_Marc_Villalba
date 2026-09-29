// -*- mode: c++ -*-
// -------------------------------------------------------------------------------------------------
// test_publicador.cpp
//
// PRUEBAS AUTOMATICAS del completado del PROMPT 1 (Arduino / plaquita iBeacon).
// Son pruebas de HOST (PC): no necesitan la placa nRF52840, compilan el
// "Publicador.h" real contra una "EmisoraBLE" FALSA (mock) que graba las
// llamadas, y comprueban:
//
//   1. La aritmetica del "major"  ->  calcularMajor( DATOS, contador )
//   2. La secuencia de publicarValor()  ->  emitir -> esperar -> detener
//   3. El valor viaja en el "minor" y es el de VALOR_MINOR (leido del .ino)
//   4. El "loop()" ya no llama a emitirAnuncioIBeaconLibre ni al anuncio
//      de prueba del esqueleto                        (esta en test_sketch.cpp)
//
// IMPORTANTE: estas pruebas se entregan ESCRITAS y NO se han ejecutado.
// El propietario las corre (ver bloque "// ===== PRUEBAS =====" al final).
//
// Notacion: Logical Design & Reverse Engineering v3
// -------------------------------------------------------------------------------------------------

#include <stdint.h>

#include <cstdio>
#include <cstdlib>
#include <cstring>
#include <fstream>
#include <sstream>
#include <string>
#include <vector>

// -------------------------------------------------------------------------------------------------
// Rutas: el ejecutable se lanza desde la carpeta "pruebas/"
// -------------------------------------------------------------------------------------------------
static const char * RUTA_SKETCH = "../HolaMundoIBeacon.ino";

// -------------------------------------------------------------------------------------------------
// Grabador de la secuencia de llamadas (mock)
// -------------------------------------------------------------------------------------------------
static std::vector<std::string> g_eventos;   // "emitir" | "esperar" | "detener"
static int16_t g_ultimoMinor  = 0;           // ultimo "minor" entregado a la emisora
static int16_t g_ultimoMajor  = 0;           // ultimo "major" entregado a la emisora
static std::string g_ultimoUuid = "";         // ultimo uuid entregado a la emisora
static long     g_ultimaEspera = 0;           // ultimo tiempo pedido a esperar()

// -------------------------------------------------------------------------------------------------
// La clase "esperar()" que declara LED.h en la placa: aqui la FALSAS
// solo para poder observar cuando se llama y con que tiempo.
// -------------------------------------------------------------------------------------------------
// t: N --> esperar() --x
// -------------------------------------------------------------------------------------------------
inline void esperar( long tiempo ) {
  g_ultimaEspera = tiempo;
  g_eventos.push_back( "esperar" );
} // ()

// -------------------------------------------------------------------------------------------------
// EmisoraBLE FALSA (mock): misma firma que la real, pero no habla con el
// Bluetooth: se limita a apuntar lo que le piden.
// -------------------------------------------------------------------------------------------------
class EmisoraBLE {
private:
  const char * nombreEmisora;
  const uint16_t fabricanteID;
  const int8_t txPower;

public:
  EmisoraBLE( const char * nombreEmisora_, const uint16_t fabricanteID_,
              const int8_t txPower_ )
    : nombreEmisora( nombreEmisora_ ),
      fabricanteID( fabricanteID_ ),
      txPower( txPower_ )
  {
  } // ()

  void encenderEmisora() {
  } // ()

  void detenerAnuncio() {
    g_eventos.push_back( "detener" );
  } // ()

  bool estaAnunciando() {
    return false;
  } // ()

  void emitirAnuncioIBeacon( uint8_t * beaconUUID, int16_t major,
                             int16_t minor, uint8_t rssi ) {
    g_eventos.push_back( "emitir" );
    g_ultimoMinor = minor;
    g_ultimoMajor = major;
    g_ultimoUuid.assign( (const char *) beaconUUID, 16 );
  } // ()

}; // class

// -------------------------------------------------------------------------------------------------
// PuertoSerie FALSO: el sketch real declara Globales::elPuerto (PuertoSerie.h) y
// Publicador.h lo usa para avisar de cada beacon emitido. Aqui se sustituye por
// uno que solo graba los mensajes, para poder comprobarlos.
// -------------------------------------------------------------------------------------------------
namespace Globales {

  static std::vector<std::string> g_mensajes;

  class PuertoSerieFalsa {
  public:
    template <typename T>
    void escribir( T mensaje ) {
      std::stringstream conversion;
      conversion << mensaje;
      g_mensajes.push_back( conversion.str() );
    } // ()
  }; // class

  static PuertoSerieFalsa elPuerto;

}; // namespace

// -------------------------------------------------------------------------------------------------
// Ahora si, el codigo real que hay que probar
// -------------------------------------------------------------------------------------------------
#include "Publicador.h"

// -------------------------------------------------------------------------------------------------
// Micro-comprobador de pruebas (sin librerias externas)
// -------------------------------------------------------------------------------------------------
static int g_pruebas = 0;
static int g_fallos = 0;

static void comprobar( bool condicion, const std::string & nombre ) {
  g_pruebas++;
  if ( condicion ) {
    std::printf( "  OK    %s\n", nombre.c_str() );
  } else {
    g_fallos++;
    std::printf( "  FALLO %s\n", nombre.c_str() );
  }
} // ()

// -------------------------------------------------------------------------------------------------
// texto --> leerFichero() --x --> Text
// Lee un fichero entero de disco. Si no existe, devuelve "".
// -------------------------------------------------------------------------------------------------
static std::string leerFichero( const std::string & ruta ) {
  std::ifstream f( ruta.c_str() );
  if ( ! f.good() ) {
    return "";
  }
  std::stringstream buffer;
  buffer << f.rdbuf();
  return buffer.str();
} // ()

// -------------------------------------------------------------------------------------------------
// texto, nombre --> leerEnteroDeVariable() --x --> N
// Busca "int <nombre> = <numero>;" en el texto y devuelve ese numero.
// Asi el test lee SIEMPRE la constante del bloque VARIABLES MODIFICABLES:
// si se cambia VALOR_MINOR en el .ino, el test usa el valor nuevo.
// Devuelve -999999 si no la encuentra.
// -------------------------------------------------------------------------------------------------
static long leerEnteroDeVariable( const std::string & texto,
                                  const std::string & nombre ) {
  std::string buscar = nombre;
  std::string::size_type donde = texto.find( buscar );
  if ( donde == std::string::npos ) {
    return -999999;
  }
  std::string::size_type igual = texto.find( '=', donde + buscar.size() );
  if ( igual == std::string::npos ) {
    return -999999;
  }
  return std::atol( texto.c_str() + igual + 1 );
} // ()

// -------------------------------------------------------------------------------------------------
// PRUEBA 1: aritmetica del "major"
//   calcularMajor( DATOS, contador ) == (14 << 8) | contador
// -------------------------------------------------------------------------------------------------
static void pruebaMajor() {
  std::printf( "\n[1] Aritmetica del major (calcularMajor)\n" );

  const uint8_t DATOS = Publicador::MedicionesID::DATOS;

  comprobar( DATOS == 14,
             "MedicionesID::DATOS vale 14 (0x0E)" );

  comprobar( Publicador::calcularMajor( DATOS, 0 )    == 3584,
             "calcularMajor(DATOS, 0)   == 3584  (0x0E00)" );

  comprobar( Publicador::calcularMajor( DATOS, 1 )    == 3585,
             "calcularMajor(DATOS, 1)   == 3585  (0x0E01)" );

  comprobar( Publicador::calcularMajor( DATOS, 255 )  == 3839,
             "calcularMajor(DATOS, 255) == 3839  (0x0EFF)" );
} // ()

// -------------------------------------------------------------------------------------------------
// PRUEBA 2 y 3: secuencia de publicarValor() y valor en el "minor"
//   Se pasa una VALOR_MINOR leido del .ino y se comprueba que es lo que
//   acaba en el "minor" del anuncio.
// -------------------------------------------------------------------------------------------------
static void pruebaSecuenciaYMinor() {
  std::printf( "\n[2] Secuencia de publicarValor() con emisora FALSA\n" );

  const std::string sketch = leerFichero( RUTA_SKETCH );
  const long VALOR_MINOR = leerEnteroDeVariable( sketch, "VALOR_MINOR" );

  comprobar( ! sketch.empty(),
             "se encuentra el sketch " RUTA_SKETCH );

  comprobar( VALOR_MINOR != -999999,
             "el .ino declara VALOR_MINOR en VARIABLES MODIFICABLES" );

  // --- secuencia: emitir -> esperar -> detener, en ese orden ---
  g_eventos.clear();
  g_ultimoMinor = 0;

  Publicador elPublicador;

  elPublicador.publicarValor( (int16_t) VALOR_MINOR, 7, 1000 );

  std::vector<std::string> esperado;
  esperado.push_back( "emitir" );
  esperado.push_back( "esperar" );
  esperado.push_back( "detener" );

  comprobar( g_eventos == esperado,
             "llama a emitirAnuncioIBeacon, esperar y detenerAnuncio, en ese orden" );

  comprobar( g_ultimaEspera == 1000,
             "espera el tiempoEspera recibido (1000 ms)" );

  // --- aviso por el puerto serie: uno por cada beacon emitido ---
  comprobar( ! Globales::g_mensajes.empty(),
             "avisa por el puerto serie cada vez que emite un beacon" );

  // --- major: (DATOS << 8) | contador, con el contador usado ---
  comprobar( g_ultimoMajor == ( (14 << 8) | 7 ),
             "el major es (14 << 8) | contador" );

  // --- minor: exactamente VALOR_MINOR ---
  std::printf( "\n[3] El valor viaja en el minor\n" );
  std::printf( "       VALOR_MINOR leido del .ino = %ld\n", VALOR_MINOR );

  comprobar( g_ultimoMinor == (int16_t) VALOR_MINOR,
             "el minor entregado a la emisora es VALOR_MINOR" );

  // --- uuid del beacon: "EPSG-GTI-MARC-3A" (16 bytes del esqueleto) ---
  comprobar( g_ultimoUuid == "EPSG-GTI-MARC-3A",
             "el uuid anunciado es EPSG-GTI-MARC-3A" );
} // ()

// -------------------------------------------------------------------------------------------------
// programa
// -------------------------------------------------------------------------------------------------
int main() {
  std::printf( "=====================================================\n" );
  std::printf( " PRUEBAS DEL PUBLICADOR (PROMPT 1 - plaquita iBeacon)\n" );
  std::printf( "=====================================================\n" );

  pruebaMajor();
  pruebaSecuenciaYMinor();

  std::printf( "\n-----------------------------------------------------\n" );
  std::printf( " %d pruebas, %d fallos\n", g_pruebas, g_fallos );
  std::printf( "-----------------------------------------------------\n" );

  return ( g_fallos == 0 ? 0 : 1 );
} // ()

// -------------------------------------------------------------------------------------------------
// ===== PRUEBAS =====
//
// QUE HAY QUE EJECUTAR (las pruebas las corre el propietario, NO la IA):
//
// 1) Compilar y ejecutar (GNU g++, desde la carpeta "pruebas"):
//
//        cd  Sprint_0_Marc_Villalba/arduino/HolaMundoIBeacon/pruebas
//        g++ -std=c++11 -Wall -I .. -o test_publicador.exe test_publicador.cpp
//        ./test_publicador.exe
//
//    con Visual Studio (MSVC), desde la misma carpeta:
//
//        cl /EHsc /nologo /I .. test_publicador.cpp
//        test_publicador.exe
//
//    con PlatformIO / Arduino no hace falta nada: estas pruebas son de PC.
//
// 2) RESULTADO ESPERADO:
//
//        =====================================================
//         PRUEBAS DEL PUBLICADOR (PROMPT 1 - plaquita iBeacon)
//        =====================================================
//
//        [1] Aritmetica del major (calcularMajor)
//          OK    MedicionesID::DATOS vale 14 (0x0E)
//          OK    calcularMajor(DATOS, 0)   == 3584  (0x0E00)
//          OK    calcularMajor(DATOS, 1)   == 3585  (0x0E01)
//          OK    calcularMajor(DATOS, 255) == 3839  (0x0EFF)
//
//        [2] Secuencia de publicarValor() con emisora FALSA
//          OK    se encuentra el sketch ../HolaMundoIBeacon.ino
//          OK    el .ino declara VALOR_MINOR en VARIABLES MODIFICABLES
//          OK    llama a emitirAnuncioIBeacon, esperar y detenerAnuncio, en ese orden
//          OK    espera el tiempoEspera recibido (1000 ms)
//          OK    avisa por el puerto serie cada vez que emite un beacon
//          OK    el major es (14 << 8) | contador
//
//        [3] El valor viaja en el minor
//               VALOR_MINOR leido del .ino = 1234
//          OK    el minor entregado a la emisora es VALOR_MINOR
//          OK    el uuid anunciado es EPSG-GTI-MARC-3A
//
//        -----------------------------------------------------
//         12 pruebas, 0 fallos
//        -----------------------------------------------------
//
//    El codigo de salida es 0 si todo pasa y 1 si hay algun fallo.
//
// 3) COMPROBACION EN LA PLACA (manual, con la nRF52840 conectada):
//    - Volcar el sketch "HolaMundoIBeacon.ino" en la placa.
//    - Abrir el monitor serie a 115200. Cada beacon emitido escribe:
//
//          ---- loop(): empieza 1
//              beacon: minor(valor)=1234 major=3585 rssi=-53
//              anunciando 1000 ms
//              anuncio parado
//
//      Si sale ese aviso, el sketch esta emitiendo; si no sale, el problema
//      esta antes (placa, puerto serie o alimentacion).
//    - Con un escaner BLE (nRF Connect, LightBlue...) comprobar que aparece
//      un iBeacon con companyID 0x004C, uuid "EPSG-GTI-MARC-3A",
//      major 0x0E.. y minor 1234 (= VALOR_MINOR).
// -------------------------------------------------------------------------------------------------
