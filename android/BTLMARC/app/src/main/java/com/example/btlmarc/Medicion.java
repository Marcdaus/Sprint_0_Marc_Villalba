
package com.example.btlmarc;

import java.util.Arrays;

// -----------------------------------------------------------------------------------
// Medicion: los datos de UNA lectura de nuestro beacon, ya interpretados.
// Solo getters: los campos son privados (encapsulados), nadie los cambia por fuera.
// Los valores salen de la trama (TramaIBeacon) + el nombre y el RSSI que da Android.
// -----------------------------------------------------------------------------------
public class Medicion {

    // ----- los datos guardados: los mismos campos que luego salen en el JSON -----

    private byte[] uuid;      // 16 bytes del beacon
    private String nombre;    // nombre del emisor que anuncia Android
    private int major;        // major de la trama
    private int minor;        // minor de la trama (valor del proyecto, hoy 1234)
    private int txPower;      // tx_power de la trama
    private int rssi;         // intensidad de la señal, en dBm

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Crea una Medicion a partir de la trama del beacon, el nombre y el RSSI.
     * El uuid, el major, el minor y el txPower se copian de la trama.
     *
     * @param tib    la trama del beacon ya interpretada
     * @param nombre el nombre del emisor
     * @param rssi   la intensidad de la señal en dBm
     */
    // tib: TramaIBeacon, nombre: Text, rssi: Z --> Medicion() -->
    public Medicion(TramaIBeacon tib, String nombre, int rssi) {

        this.uuid = Arrays.copyOf(tib.getUUID(), tib.getUUID().length); // copia defensiva
        this.nombre = nombre;

        // major y minor son 2 bytes BIG-ENDIAN en la trama, y bytesToInt() los lee
        // como un numero grande SIN signo, asi que ya salen en el orden correcto.
        // major = 3584 + contador y sube cada segundo: si se leyera con signo
        // (BigInteger) a partir de 32767 saldria negativo.
        this.major = Utilidades.bytesToInt(tib.getMajor());
        this.minor = Utilidades.bytesToInt(tib.getMinor());

        // el txPower es SI signo: 0xC6 son -58 dBm, que es un valor normal.
        this.txPower = Utilidades.leerByteConSigno(tib.getTxPower());
        this.rssi = rssi;

    } // ()

    // -------------------------------------------------------------------------------
    // Devuelve los 16 bytes del uuid del beacon.
    // -------------------------------------------------------------------------------
    /**
     * Da los 16 bytes del uuid del beacon.
     * @return una copia de los 16 bytes del uuid
     */
    // --> getUUID() --> [N]_16
    public byte[] getUUID() {
        return Arrays.copyOf(this.uuid, this.uuid.length); // copia defensiva
    }

    // -------------------------------------------------------------------------------
    // Devuelve el nombre del emisor (nombre_emisora del JSON).
    // -------------------------------------------------------------------------------
    /**
     * Da el nombre del emisor.
     * @return el nombre del emisor
     */
    // --> getNombre() --> Text
    public String getNombre() {
        return this.nombre;
    }

    // -------------------------------------------------------------------------------
    // Devuelve el major que traía la trama.
    // -------------------------------------------------------------------------------
    /**
     * Da el major de la trama.
     * @return el major
     */
    // --> getMajor() --> Z
    public int getMajor() {
        return this.major;
    }

    // -------------------------------------------------------------------------------
    // Devuelve el minor que traía la trama (valor del proyecto, hoy 1234).
    // -------------------------------------------------------------------------------
    /**
     * Da el minor de la trama.
     * @return el minor
     */
    // --> getMinor() --> Z
    public int getMinor() {
        return this.minor;
    }

    // -------------------------------------------------------------------------------
    // Devuelve el tx_power que traía la trama.
    // -------------------------------------------------------------------------------
    /**
     * Da el tx_power de la trama.
     * @return el tx_power
     */
    // --> getTxPower() --> Z
    public int getTxPower() {
        return this.txPower;
    }

    // -------------------------------------------------------------------------------
    // Devuelve el RSSI con el que se vio el beacon.
    // -------------------------------------------------------------------------------
    /**
     * Da el RSSI de la lectura.
     * @return el RSSI en dBm
     */
    // --> getRssi() --> Z
    public int getRssi() {
        return this.rssi;
    }

} // class
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
