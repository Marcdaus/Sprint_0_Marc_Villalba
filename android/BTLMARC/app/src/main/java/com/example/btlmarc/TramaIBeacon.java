
package com.example.btlmarc;

import java.util.Arrays;

// -----------------------------------------------------------------------------------
// TramaIBeacon: interpreta UNA lectura de escaner BLE.
//
// OJO, y esto es lo que se rompia antes: una trama BLE NO es siempre un iBeacon
// con el prefijo en las posiciones 0..8. Es una CADENA de estructuras AD, cada una
// con su byte de longitud, su byte de tipo y sus datos:
//
//   longitud tipo  datos...
//      0x02  0x01  0x1a                 <-- flags (3 bytes en total)
//      0x1A  0xFF  4c 00 02 15 ...      <-- datos de fabricante: aqui esta el iBeacon
//      0x03  0x09  47 54 49             <-- nombre completo
//
// La estructura del iBeacon puede empezar en CUALQUIER posicion, y delante puede
// haber otras estructuras. Por eso NO se puede mirar bytes[7] y bytes[8] a ciegas:
// hay que recorrer la cadena. Android ademas devuelve el ScanRecord con un buffer
// de tamano fijo relleno con ceros, asi que la longitud de la trama tampoco dice
// nada: el final de los datos se ve por el byte de longitud de cada estructura.
//
// companyID va en LITTLE-ENDIAN: los bytes 4c 00 son el valor 0x004C (Apple).
// Leerlos como 0x4C00 es lo que hacia que se rechazaran todos los beacons.
// -----------------------------------------------------------------------------------
// @author: Jordi Bataller i Mascarell
// -----------------------------------------------------------------------------------
public class TramaIBeacon {

    // ----- Constantes de la estructura AD (no se tocan) -----

    // 0xFF = "Manufacturer Specific Data": la estructura donde van los beacons.
    private static final int TIPO_MANUFACTURER = 0xFF;

    // companyID de Apple: el que dice "esto es un iBeacon". Ojo, en little-endian.
    private static final int COMPANY_ID_APPLE = 0x004C;

    // bytes de datos de iBeacon que van detras del companyID:
    // tipo(1) + longitud(1) + uuid(16) + major(2) + minor(2) + txPower(1) = 23
    private static final int TAM_DATOS_IBEACON = 23;

    // ----- Campos de la trama, ya interpretados -----

    private byte[] prefijo = null; // la estructura de fabricante completa (27 bytes)
    private byte[] uuid = null; // 16 bytes
    private byte[] major = null; // 2 bytes
    private byte[] minor = null; // 2 bytes
    private byte txPower = 0; // 1 byte

    private byte[] losBytes;

    private byte[] advFlags = null; // 3 bytes
    private byte[] advHeader = null; // 2 bytes
    private byte[] companyID = new byte[2]; // 2 bytes
    private byte iBeaconType = 0 ; // 1 byte
    private byte iBeaconLength = 0 ; // 1 byte

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getPrefijo() {
        return prefijo;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getUUID() {
        return uuid;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getMajor() {
        return major;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getMinor() {
        return minor;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte getTxPower() {
        return txPower;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getLosBytes() {
        return losBytes;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getAdvFlags() {
        return advFlags;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getAdvHeader() {
        return advHeader;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte[] getCompanyID() {
        return companyID;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte getiBeaconType() {
        return iBeaconType;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public byte getiBeaconLength() {
        return iBeaconLength;
    }

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    public TramaIBeacon(byte[] bytes ) {
        this.losBytes = bytes;

        int inicio = buscarEstructuraIBeacon(losBytes); // donde empieza la del iBeacon

        if (inicio < 0) {
            throw new IllegalArgumentException("TramaIBeacon: la trama no contiene datos de iBeacon");
        }

        // desde 'inicio' los bytes van:
        //   inicio+0 longitud AD | inicio+1 tipo 0xFF | inicio+2..3 companyID
        //   inicio+4 tipo iBeacon | inicio+5 longitud | inicio+6..21 uuid
        //   inicio+22..23 major | inicio+24..25 minor | inicio+26 txPower
        int base = inicio + 2; // el companyID empieza aqui

        prefijo = Arrays.copyOfRange(losBytes, inicio, inicio + 27); // la estructura entera
        companyID = Arrays.copyOfRange(losBytes, base, base + 2); // 2 bytes
        iBeaconType = losBytes[ inicio + 4 ]; // 1 byte
        iBeaconLength = losBytes[ inicio + 5 ]; // 1 byte

        uuid = Arrays.copyOfRange(losBytes, inicio + 6, inicio + 6 + 16); // 16 bytes
        major = Arrays.copyOfRange(losBytes, inicio + 22, inicio + 24); // 2 bytes
        minor = Arrays.copyOfRange(losBytes, inicio + 24, inicio + 26); // 2 bytes
        txPower = losBytes[ inicio + 26 ]; // 1 byte

        // los flags y el advHeader salen de lo que haya justo delante, si esta
        // la estructura de flags (0x02 0x01 ...). Si no, se dejan vacios.
        if (inicio >= 3 && (losBytes[ inicio - 3 ] & 0xFF) == 0x02
                && (losBytes[ inicio - 2 ] & 0xFF) == 0x01) {
            advFlags = Arrays.copyOfRange(losBytes, inicio - 3, inicio); // 3 bytes
            advHeader = Arrays.copyOfRange(losBytes, inicio, inicio + 2); // longitud + 0xFF
        } else {
            advFlags = new byte[0];
            advHeader = Arrays.copyOfRange(losBytes, inicio, inicio + 2);
        }

    } // ()

    // -------------------------------------------------------------------------------
    // Recorre la cadena de estructuras AD buscando la del iBeacon de Apple.
    //
    // Devuelve la posicion donde empieza esa estructura, o -1 si no la hay.
    // Antes de la posicion encontrada puede haber las estructuras que sean
    // (flags, nombre, servicios...), y detras vienen los bytes de relleno del buffer.
    // -------------------------------------------------------------------------------
    /**
     * Busca la estructura de fabricante de Apple con datos de iBeacon en la trama.
     *
     * @param bytes los bytes de la trama
     * @return la posicion donde empieza la estructura del iBeacon, o -1 si no hay ninguna
     */
    // bytes: [N]_* --> buscarEstructuraIBeacon() --> Z
    public static int buscarEstructuraIBeacon( byte[] bytes ) {

        if (bytes == null) {
            return -1;
        }

        int i = 0;

        // mientras queden bytes por mirar
        while (i < bytes.length - 1) {

            int longitud = bytes[i] & 0xFF;

            // longitud 0 = fin de los datos: lo que viene detras es relleno del buffer
            if (longitud == 0) {
                return -1;
            }

            // la estructura necesita su byte de tipo, y los datos no pueden
            // salirse del final de la trama
            if (longitud > bytes.length - i - 1) {
                return -1;
            }

            int tipo = bytes[i + 1] & 0xFF;

            if (tipo == TIPO_MANUFACTURER && longitud >= 3) {
                // companyID en LITTLE-ENDIAN: 4c 00 -> 0x004C
                int companyID = (bytes[i + 2] & 0xFF) | ((bytes[i + 3] & 0xFF) << 8);

                // longitud - 3 = bytes de datos detras del companyID
                int datos = longitud - 3;

                if (companyID == COMPANY_ID_APPLE && datos >= TAM_DATOS_IBEACON
                        && (bytes[i + 4] & 0xFF) == 0x02
                        && (bytes[i + 5] & 0xFF) == 0x15) {
                    return i; // esta es la estructura del iBeacon
                }
            }

            // siguiente estructura: la actual ocupa 'longitud' bytes mas el de longitud
            i += longitud + 1;
        }

        return -1; // no hemos encontrado ninguna estructura de iBeacon

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Dice si la trama contiene datos de iBeacon de Apple, sin interpretarlos.
     *
     * @param bytes los bytes de la trama
     * @return true si contiene un iBeacon, false si no
     */
    // bytes: [N]_* --> esTramaIBeacon() --> B
    public static boolean esTramaIBeacon( byte[] bytes ) {
        return buscarEstructuraIBeacon( bytes ) >= 0;
    }

} // class
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------


