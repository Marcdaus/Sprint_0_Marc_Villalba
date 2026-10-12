// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: Java
// Curso: 3ºGTI
// =====================================


package com.example.btlmarc;

// -----------------------------------------------------------------------------------
// Medicion: los datos de UNA lectura de nuestro beacon, ya interpretados.
// Solo getters: los campos son privados (encapsulados), nadie los cambia por fuera.
//
// Del beacon salen el TIPO y el VALOR; el nombre lo da Android.
//  - el tipo viaja CODIFICADO en los 8 bits altos del campo "major" de la
//    trama, y aqui se guarda ya como TEXTO ("CO2", "temperatura", "prueba"),
//    que es lo que entiende el servidor.
//  - el valor viaja en el campo "minor".
// -----------------------------------------------------------------------------------
public class Medicion {

    // ----- los datos guardados: los mismos campos que luego salen en el JSON -----

    private String nombre;        // nombre de la emisora (nombre_emisora del JSON)
    private String tipoMedicion;  // ya en texto: "CO2" | "temperatura" | "prueba"
    private int valorMedicion;    // valor del proyecto (el minor de la trama, hoy 1234)

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Crea una Medicion a partir de la trama del beacon y el nombre.
     * El tipo se traduce del major (los 8 bits altos) y el valor sale del minor.
     *
     * @param tib    la trama del beacon ya interpretada
     * @param nombre el nombre de la emisora
     */
    // tib: TramaIBeacon, nombre: Text --> Medicion() -->
    public Medicion(TramaIBeacon tib, String nombre) {

        this.nombre = nombre;

        // el tipo viaja CODIFICADO en los 8 bits altos del major:
        //     major = (codigo << 8) | contador
        // asi que con major >> 8 se recupera el codigo (1=CO2, 2=temperatura,
        // 3=prueba) y tipoDeMedicion() lo pasa a texto.
        int major = Utilidades.bytesToInt(tib.getMajor());
        this.tipoMedicion = AnalizadorBeacons.tipoDeMedicion(major >> 8);

        // el valor del proyecto viaja en el minor (2 bytes big-endian sin signo,
        // por eso bytesToInt() y no BigInteger)
        this.valorMedicion = Utilidades.bytesToInt(tib.getMinor());

    } // ()

    // -------------------------------------------------------------------------------
    // Devuelve el nombre de la emisora (nombre_emisora del JSON).
    // -------------------------------------------------------------------------------
    /**
     * Da el nombre de la emisora.
     * @return el nombre de la emisora
     */
    // --> getNombre() --> Text
    public String getNombre() {
        return this.nombre;
    }

    // -------------------------------------------------------------------------------
    // Devuelve el tipo de medicion ya en texto.
    // -------------------------------------------------------------------------------
    /**
     * Da el tipo de medicion ("CO2", "temperatura" o "prueba").
     * @return el tipo de medicion en texto
     */
    // --> getTipoMedicion() --> Text
    public String getTipoMedicion() {
        return this.tipoMedicion;
    }

    // -------------------------------------------------------------------------------
    // Devuelve el valor del proyecto que traia la trama (hoy 1234).
    // -------------------------------------------------------------------------------
    /**
     * Da el valor de la medicion.
     * @return el valor del proyecto
     */
    // --> getValorMedicion() --> Z
    public int getValorMedicion() {
        return this.valorMedicion;
    }

} // class
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
