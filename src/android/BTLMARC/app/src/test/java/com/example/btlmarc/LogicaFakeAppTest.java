// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: Java
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================

package com.example.btlmarc;

import org.junit.Test;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertTrue;

// -----------------------------------------------------------------------------------
// LogicaFakeAppTest: pruebas de HOST (PC) de la logica fake del telefono.
//
// Prueban las piezas PURAS del proxy (no necesitan Android, ni red, ni dispositivo):
//
//   1. construirPeticion(): el metodo, la URL y el JSON que se mandan al servidor,
//   2. interpretarRespuesta(): como se traduce la respuesta del servidor (codigo +
//      cuerpo) a un ResultadoGuardado de dominio: 200, 400, 500, red caida y casos
//      raros (sin JSON).
//
// La parte que SI hace red (guardarMedicion() con un POST de verdad) se prueba en el
// telefono con un PeticionarioREST falso: ver LogicaFakeAppInstrumentedTest.
//
// Se ejecutan desde Android Studio (boton de correr del metodo) o por consola:
//
//     ./gradlew :app:testDebugUnitTest
//
// Notacion: Logical Design & Reverse Engineering v3
// -----------------------------------------------------------------------------------
public class LogicaFakeAppTest {

    // Trama iBeacon de prueba: uuid del proyecto, major=(codigo<<8)|contador,
    // minor=valor del proyecto y txPower=4. Se copia del MedicionTest para no
    // depender de la red.
    private static byte[] trama(int codigo, int contador, int valorMinor) {

        int major = (codigo << 8) | contador;

        return new byte[] {
                0x02, 0x01, 0x1a,                              // flags
                0x1a, (byte) 0xff, 0x4c, 0x00,                 // fabricante Apple, companyID 0x004C
                0x02, 0x15,                                    // tipo y longitud de iBeacon
                'E', 'P', 'S', 'G', '-', 'G', 'T', 'I',        // uuid: "EPSG-GTI-MARC-3A"
                '-', 'M', 'A', 'R', 'C', '-', '3', 'A',
                (byte) (major >> 8), (byte) (major & 0xFF),    // major (tipo + contador)
                (byte) (valorMinor >> 8), (byte) (valorMinor & 0xFF), // minor (valor)
                0x04                                           // txPower
        };

    } // ()

    @Test
    public void construirPeticion_montaMetodoUrlYCuerpo() {
        TramaIBeacon tib = new TramaIBeacon(trama(3, 0, 1234));
        Medicion m = new Medicion(tib, "GTI-3A");

        LogicaFakeApp.Peticion peticion = LogicaFakeApp.construirPeticion(m);

        assertEquals("POST", peticion.metodo);
        assertEquals("http://" + AnalizadorBeacons.IP_SERVIDOR + ":"
                + AnalizadorBeacons.PUERTO_SERVIDOR + "/"
                + AnalizadorBeacons.RUTA_GUARDAR, peticion.url);
        assertEquals("{\"id_plaquita\":\"GTI-3A\",\"tipo_medicion\":\"prueba\","
                + "\"valor_medicion\":1234}", peticion.cuerpo);
    }

    @Test
    public void interpretarRespuesta_200_devuelveOk() {
        LogicaFakeApp.ResultadoGuardado r = LogicaFakeApp.interpretarRespuesta(200,
                "{\"ok\":true}");

        assertTrue(r.ok);
        assertEquals("", r.error);
        assertEquals("", r.clase);
    }

    @Test
    public void interpretarRespuesta_400_esValidacion() {
        LogicaFakeApp.ResultadoGuardado r = LogicaFakeApp.interpretarRespuesta(400,
                "{\"error\":\"tipo de medicion desconocido\"}");

        assertFalse(r.ok);
        assertEquals(LogicaFakeApp.CLASE_VALIDACION, r.clase);
        assertEquals("tipo de medicion desconocido", r.error);
    }

    @Test
    public void interpretarRespuesta_500_esPersistencia() {
        LogicaFakeApp.ResultadoGuardado r = LogicaFakeApp.interpretarRespuesta(500,
                "{\"error\":\"la base de datos no responde\"}");

        assertFalse(r.ok);
        assertEquals(LogicaFakeApp.CLASE_PERSISTENCIA, r.clase);
        assertEquals("la base de datos no responde", r.error);
    }

    @Test
    public void interpretarRespuesta_redCaida_esPersistencia() {
        LogicaFakeApp.ResultadoGuardado r = LogicaFakeApp.interpretarRespuesta(0, "");

        assertFalse(r.ok);
        assertEquals(LogicaFakeApp.CLASE_PERSISTENCIA, r.clase);
        assertTrue(r.error.indexOf("conectar") != -1);
    }

    @Test
    public void interpretarRespuesta_200_sinCuerpoDevuelveOk() {
        LogicaFakeApp.ResultadoGuardado r = LogicaFakeApp.interpretarRespuesta(200, "");

        assertTrue(r.ok);
    }

    @Test
    public void interpretarRespuesta_errorSinJson_usaTextoGenerico() {
        LogicaFakeApp.ResultadoGuardado r = LogicaFakeApp.interpretarRespuesta(400, "no es json");

        assertFalse(r.ok);
        assertEquals(LogicaFakeApp.CLASE_VALIDACION, r.clase);
        assertTrue(r.error.length() > 0);
    }

} // class
