// -*- mode: c++ -*-
// -------------------------------------------------------------------------------------------------
// test_sketch.cpp
//
// PRUEBAS AUTOMATICAS del completado del PROMPT 1 (Arduino / plaquita iBeacon),
// en su parte de "programa principal" (HolaMundoIBeacon.ino).
//
// El "loop()" corre en la placa y no se puede lanzar en el PC, asi que aqui
// se comprueba el CODIGO FUENTE del sketch: que se ha quedado solo con el
// valor del proyecto y que no le quedan restos del esqueleto.
//
//   1. El "loop()" NO llama a emitirAnuncioIBeaconLibre
//   2. El "loop()" NO emite el anuncio de prueba del esqueleto
//      (ni la carga "HolaHola...Hola" / "MolaMola...")
//
// IMPORTANTE: estas pruebas se entregan ESCRITAS y NO se han ejecutado.
// El propietario las corre (ver bloque "// ===== PRUEBAS =====" al final).
//
// Notacion: Logical Design & Reverse Engineering v3
// -------------------------------------------------------------------------------------------------

#include <cstdio>
#include <cstdlib>
#include <fstream>
#include <sstream>
#include <string>

// -------------------------------------------------------------------------------------------------
// Rutas: el ejecutable se lanza desde la carpeta "pruebas/"
// -------------------------------------------------------------------------------------------------
static const char * RUTA_SKETCH = "../HolaMundoIBeacon.ino";

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
// texto, trozo --> contiene() --x --> B
// -------------------------------------------------------------------------------------------------
static bool contiene( const std::string & texto, const std::string & trozo ) {
  return texto.find( trozo ) != std::string::npos;
} // ()

// -------------------------------------------------------------------------------------------------
// texto --> sinEspacios() --x --> Text
// Quita espacios y saltos de linea: asi se buscan las llamadas
// aunque esten repartidas en varias lineas.
// -------------------------------------------------------------------------------------------------
static std::string sinEspacios( const std::string & texto ) {
  std::string limpio;
  for ( std::string::size_type i = 0; i < texto.size(); i++ ) {
    char c = texto[i];
    if ( c != ' ' && c != '\t' && c != '\r' && c != '\n' ) {
      limpio += c;
    }
  }
  return limpio;
} // ()

// -------------------------------------------------------------------------------------------------
// programa
// -------------------------------------------------------------------------------------------------
int main() {
  std::printf( "=====================================================\n" );
  std::printf( " PRUEBAS DEL SKETCH (PROMPT 1 - HolaMundoIBeacon.ino)\n" );
  std::printf( "=====================================================\n" );

  const std::string sketch = leerFichero( RUTA_SKETCH );
  const std::string compacto = sinEspacios( sketch );

  std::printf( "\n[4] Sin filtraciones del esqueleto en el sketch\n" );

  comprobar( ! sketch.empty(),
             "se encuentra el sketch " RUTA_SKETCH );

  comprobar( ! contiene( compacto, "emitirAnuncioIBeaconLibre" ),
             "el sketch NO llama a emitirAnuncioIBeaconLibre" );

  comprobar( ! contiene( compacto, "MolaMolaMola" ),
             "el sketch NO lleva el anuncio de prueba \"MolaMola...\"" );

  comprobar( ! contiene( compacto, "'H','o','l','a'" ),
             "el sketch NO lleva la carga de prueba \"HolaHola...\"" );

  std::printf( "\n[5] El loop() publica el valor del proyecto\n" );

  comprobar( ! contiene( compacto, "publicarCO2(" ),
             "el sketch NO llama a publicarCO2" );

  comprobar( ! contiene( compacto, "publicarTemperatura(" ),
             "el sketch NO llama a publicarTemperatura" );

  comprobar( contiene( compacto, "publicarValor(VALOR_MINOR" ),
             "el sketch llama a publicarValor( VALOR_MINOR, ... )" );

  comprobar( contiene( compacto, "VARIABLESMODIFICABLES" ),
             "el sketch tiene el bloque VARIABLES MODIFICABLES" );

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
//        g++ -std=c++11 -Wall -o test_sketch.exe test_sketch.cpp
//        ./test_sketch.exe
//
//    con Visual Studio (MSVC), desde la misma carpeta:
//
//        cl /EHsc /nologo test_sketch.cpp
//        test_sketch.exe
//
// 2) RESULTADO ESPERADO:
//
//        =====================================================
//         PRUEBAS DEL SKETCH (PROMPT 1 - HolaMundoIBeacon.ino)
//        =====================================================
//
//        [4] Sin filtraciones del esqueleto en el sketch
//          OK    se encuentra el sketch ../HolaMundoIBeacon.ino
//          OK    el sketch NO llama a emitirAnuncioIBeaconLibre
//          OK    el sketch NO lleva el anuncio de prueba "MolaMola..."
//          OK    el sketch NO lleva la carga de prueba "HolaHola..."
//
//        [5] El loop() publica el valor del proyecto
//          OK    el sketch NO llama a publicarCO2
//          OK    el sketch NO llama a publicarTemperatura
//          OK    el sketch llama a publicarValor( VALOR_MINOR, ... )
//          OK    el sketch tiene el bloque VARIABLES MODIFICABLES
//
//        -----------------------------------------------------
//         8 pruebas, 0 fallos
//        -----------------------------------------------------
//
//    El codigo de salida es 0 si todo pasa y 1 si hay algun fallo.
// -------------------------------------------------------------------------------------------------
