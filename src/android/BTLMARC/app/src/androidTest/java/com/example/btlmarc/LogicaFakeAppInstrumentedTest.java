// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: Java
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================

package com.example.btlmarc;

import androidx.test.ext.junit.runners.AndroidJUnit4;

import org.junit.Test;
import org.junit.runner.RunWith;

import static org.junit.Assert.assertEquals;
import static org.junit.Assert.assertFalse;
import static org.junit.Assert.assertNotNull;
import static org.junit.Assert.assertTrue;

// -----------------------------------------------------------------------------------
// LogicaFakeAppInstrumentedTest: prueba del guardado COMPLETO en el telefono.
//
// Prueba guardarMedicion() sin tocar la red: se mete un PeticionarioREST falso que,
// en vez de abrir una conexion HTTP, apunta lo que le mandan y contesta lo que se le
// diga. Asi se comprueba que:
//
//   1. con una Medicion se hace UN POST al URL y con el cuerpo correctos,
//   2. la respuesta del servidor se traduce a un ResultadoGuardado de dominio,
//   3. sin Medicion (null) NO se hace ningun POST y se devuelve un fallo de validacion.
//
// Esta prueba necesita un dispositivo/emulador (las de la red y los logs de Android
// no funcionan en el PC). Se ejecuta con:
//
//     ./gradlew :app:connectedDebugAndroidTest
//
// Notacion: Logical Design & Reverse Engineering v3
// -----------------------------------------------------------------------------------
@RunWith(AndroidJUnit4.class)
public class LogicaFakeAppInstrumentedTest {

    // Trama iBeacon de prueba: uuid del proyecto, major=(codigo<<8)|contador, minor=valor.
    private static byte[] trama(int codigo, int contador, int valorMinor) {

        int major = (codigo << 8) | contador;

        return new byte[] {
                0x02, 0x01, 0x1a,
                0x1a, (byte) 0xff, 0x4c, 0x00,
                0x02, 0x15,
                'E', 'P', 'S', 'G', '-', 'G', 'T', 'I',
                '-', 'M', 'A', 'R', 'C', '-', '3', 'A',
                (byte) (major >> 8), (byte) (major & 0xFF),
                (byte) (valorMinor >> 8), (byte) (valorMinor & 0xFF),
                0x04
        };

    } // ()

    // PeticionarioREST falso: no toca la red; apunta lo que le mandan y contesta lo que se le diga.
    private static class PeticionarioRESTFalso extends PeticionarioREST {

        String metodo;
        String url;
        String cuerpo;
        int llamadas = 0;

        private final int codigoRespuesta;
        private final String cuerpoRespuesta;

        PeticionarioRESTFalso(int codigoRespuesta, String cuerpoRespuesta) {
            this.codigoRespuesta = codigoRespuesta;
            this.cuerpoRespuesta = cuerpoRespuesta;
        }

        @Override
        // metodo: Text, url: Text, cuerpo: Text, respuesta: RespuestaREST --> hacerPeticionREST() -->
        public void hacerPeticionREST(String metodo, String url, String cuerpo,
                                      RespuestaREST respuesta) {
            this.metodo = metodo;
            this.url = url;
            this.cuerpo = cuerpo;
            this.llamadas++;
            if (respuesta != null) {
                respuesta.callback(this.codigoRespuesta, this.cuerpoRespuesta);
            }
        }

    } // class PeticionarioRESTFalso

    // LogicaFakeApp de prueba: usa el PeticionarioREST falso en vez del de verdad.
    private static class LogicaFakeAppDePrueba extends LogicaFakeApp {

        private final PeticionarioREST peticionario;

        LogicaFakeAppDePrueba(PeticionarioREST peticionario) {
            this.peticionario = peticionario;
        }

        @Override
        // --> crearPeticionarioREST() --> PeticionarioREST
        protected PeticionarioREST crearPeticionarioREST() {
            return this.peticionario;
        }

    } // class LogicaFakeAppDePrueba

    @Test
    public void guardarMedicion_haceUnPostYTraduceLaRespuesta() {
        PeticionarioRESTFalso falso = new PeticionarioRESTFalso(200, "{\"ok\":true}");
        LogicaFakeAppDePrueba logica = new LogicaFakeAppDePrueba(falso);

        TramaIBeacon tib = new TramaIBeacon(trama(3, 0, 1234));
        Medicion m = new Medicion(tib, "GTI-3A");

        final LogicaFakeApp.ResultadoGuardado[] recibido = new LogicaFakeApp.ResultadoGuardado[1];

        logica.guardarMedicion(m, new LogicaFakeApp.RespuestaApp() {
            @Override
            // resultado: ResultadoGuardado --> onResultado() -->
            public void onResultado(LogicaFakeApp.ResultadoGuardado resultado) {
                recibido[0] = resultado;
            }
        });

        assertEquals(1, falso.llamadas);
        assertEquals("POST", falso.metodo);
        assertTrue(falso.url.endsWith(AnalizadorBeacons.RUTA_GUARDAR));
        assertTrue(falso.cuerpo.indexOf("\"tipo_medicion\":\"prueba\"") != -1);
        assertTrue(falso.cuerpo.indexOf("\"valor_medicion\":1234") != -1);

        assertNotNull(recibido[0]);
        assertTrue(recibido[0].ok);
        assertTrue(recibido[0].error.length() == 0);
    }

    @Test
    public void guardarMedicion_sinMedicionNoHacePost() {
        PeticionarioRESTFalso falso = new PeticionarioRESTFalso(200, "{\"ok\":true}");
        LogicaFakeAppDePrueba logica = new LogicaFakeAppDePrueba(falso);

        final LogicaFakeApp.ResultadoGuardado[] recibido = new LogicaFakeApp.ResultadoGuardado[1];

        logica.guardarMedicion(null, new LogicaFakeApp.RespuestaApp() {
            @Override
            // resultado: ResultadoGuardado --> onResultado() -->
            public void onResultado(LogicaFakeApp.ResultadoGuardado resultado) {
                recibido[0] = resultado;
            }
        });

        assertEquals(0, falso.llamadas);
        assertNotNull(recibido[0]);
        assertFalse(recibido[0].ok);
        assertEquals(LogicaFakeApp.CLASE_VALIDACION, recibido[0].clase);
    }

} // class
