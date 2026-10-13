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
// MedicionTest: pruebas de HOST (PC) del nuevo modelo de datos.
//
// Comprueban que:
//   1. el codigo de tipo que viaja en el major se traduce a texto,
//   2. una Medicion saca el tipo (del major) y el valor (del minor) de la trama,
//   3. el JSON que se manda al servidor lleva SOLO nombre, tipo y valor,
//   4. el limite de frecuencia (debeEnviarMedicion) deja pasar una medicion cada
//      MS_ENTRE_MEDICIONES y descarta las demas de la misma ventana.
//
// No necesitan Android de verdad: Utilidades, TramaIBeacon y los metodos puros
// de AnalizadorBeacons no tocan el sistema.
//
// Se ejecutan desde Android Studio (boton de correr del metodo) o por consola:
//
//     ./gradlew :app:testDebugUnitTest
//
// Notacion: Logical Design & Reverse Engineering v3
// -----------------------------------------------------------------------------------
public class MedicionTest {

    // Trama iBeacon de prueba: uuid del proyecto, major=(codigo<<8)|contador,
    // minor=valor del proyecto y txPower=4.
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
    public void tipoDeMedicion_traduceLosTresCodigos() {
        assertEquals("CO2", AnalizadorBeacons.tipoDeMedicion(1));
        assertEquals("temperatura", AnalizadorBeacons.tipoDeMedicion(2));
        assertEquals("prueba", AnalizadorBeacons.tipoDeMedicion(3));
    }

    @Test
    public void tipoDeMedicion_codigoDesconocido() {
        assertEquals("desconocido", AnalizadorBeacons.tipoDeMedicion(99));
    }

    @Test
    public void medicion_leeTipoYValorDeLaTrama() {
        TramaIBeacon tib = new TramaIBeacon(trama(3, 0, 1234));
        Medicion m = new Medicion(tib, "GTI-3A");

        assertEquals("GTI-3A", m.getNombre());
        assertEquals("prueba", m.getTipoMedicion());
        assertEquals(1234, m.getValorMedicion());
    }

    @Test
    public void medicion_traduceElTipoCO2() {
        TramaIBeacon tib = new TramaIBeacon(trama(1, 5, 235));
        Medicion m = new Medicion(tib, "GTI-3A");

        assertEquals("CO2", m.getTipoMedicion());
        assertEquals(235, m.getValorMedicion());
    }

    @Test
    public void construirJSON_mandaSoloNombreTipoYValor() {
        String json = AnalizadorBeacons.construirJSON("GTI-3A", "prueba", 1234);

        assertEquals("{\"id_plaquita\":\"GTI-3A\",\"tipo_medicion\":\"prueba\","
                + "\"valor_medicion\":1234}", json);
    }

    @Test
    public void construirJSON_desdeMedicion() {
        TramaIBeacon tib = new TramaIBeacon(trama(2, 1, 17));
        Medicion m = new Medicion(tib, "GTI-3A");

        String json = AnalizadorBeacons.construirJSON(m);

        assertEquals("{\"id_plaquita\":\"GTI-3A\",\"tipo_medicion\":\"temperatura\","
                + "\"valor_medicion\":17}", json);
    }

    @Test
    public void debeEnviarMedicion_laPrimeraVezSi() {
        // ultimoEnvio = 0 (aun no se ha mandado nada): la primera pasa
        assertTrue(AnalizadorBeacons.debeEnviarMedicion(10000, 0));
    }

    @Test
    public void debeEnviarMedicion_segundoPaqueteDeLaMismaVentanaNo() {
        // solo han pasado 1000 ms (menos que MS_ENTRE_MEDICIONES): se descarta
        assertFalse(AnalizadorBeacons.debeEnviarMedicion(10000, 9000));
    }

    @Test
    public void debeEnviarMedicion_justoAlCumplirElLimiteSi() {
        long limite = AnalizadorBeacons.MS_ENTRE_MEDICIONES;
        assertTrue(AnalizadorBeacons.debeEnviarMedicion(limite, 0));
    }

    @Test
    public void debeEnviarMedicion_pasadoElLimiteSi() {
        long limite = AnalizadorBeacons.MS_ENTRE_MEDICIONES;
        assertTrue(AnalizadorBeacons.debeEnviarMedicion(limite + 1000, 0));
    }

} // class
