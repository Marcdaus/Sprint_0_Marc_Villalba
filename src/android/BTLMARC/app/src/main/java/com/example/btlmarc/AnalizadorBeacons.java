// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: Java
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================


package com.example.btlmarc;

import android.bluetooth.le.ScanResult;
import android.util.Log;

import java.util.Arrays;

// -----------------------------------------------------------------------------------
// AnalizadorBeacons: utilidades PURAS (todo estatico y sin estado) para decidir si un
// beacon es el nuestro y para preparar lo que se manda al servidor.
// No guarda datos: solo recibe y devuelve.
// -----------------------------------------------------------------------------------
public class AnalizadorBeacons {

    // ====== VARIABLES MODIFICABLES ======
    // (public: el servicio vive en otro paquete y las tiene que poder leer)
    public static final String UUID_BEACON     = "EPSG-GTI-MARC-3A";       // uuid de NUESTRO beacon (16 caracteres)
    public static final String ID_PLAQUITA     = "GTI-3A";                 // id_plaquita que va en el JSON

    // IP del PC con el servidor REST. OJO: esta IP es la del PC en la red de
    // casa, y el router la puede cambiar (cada vez que se reinicia, por
    // ejemplo). Hay que mirarla en el PC antes de la demo. En Windows:
    // "ipconfig" y buscar IPv4.
    public static final String IP_SERVIDOR     = "10.128.11.39";

    public static final int    PUERTO_SERVIDOR = 8080;                    // puerto del servidor REST
    // La ruta lleva "src/communication/" delante porque el servidor PHP se
    // arranca con raiz en la carpeta Sprint_0_Marc_Villalba, y ahi la API
    // cuelga de src/communication/. Si cambias donde se arranca el servidor,
    // cambialo aqui.
    public static final String RUTA_GUARDAR    = "src/communication/guardarMedicion.php"; // ruta del PHP que guarda la medicion
    public static final String RUTA_RECUPERAR  = "src/communication/recuperarMedicion.php"; // ruta del PHP que devuelve las mediciones
    public static final int    RSSI_MINIMO     = -127;                    // RSSI mas bajo que se acepta (dBm)
    // Cada cuanto se manda COMO MUCHO una medicion al servidor (milisegundos).
    // La plaquita emite durante DURACION_ANUNCIO (1 s) y calla otros 4 s; en esa
    // ventana de 1 s el escaner ve muchos paquetes y sin esto se mandaria un POST
    // por cada uno (decenas de golpe). Con 2000 ms pasa una medicion por ventana,
    // y como las ventanas estan a 5 s no se pierde ninguna.
    public static final long   MS_ENTRE_MEDICIONES = 2000;

    // Codigos de tipo de medicion que viajan en los 8 bits altos del "major"
    // del beacon (los mismos que anuncia la plaquita: Publicador.h) y el texto
    // en el que se traducen. El servidor espera el TEXTO, no el codigo.
    public static final int    CODIGO_CO2         = 1;
    public static final int    CODIGO_TEMPERATURA = 2;
    public static final int    CODIGO_PRUEBA      = 3;

    public static final String TIPO_CO2         = "CO2";
    public static final String TIPO_TEMPERATURA = "temperatura";
    public static final String TIPO_PRUEBA      = "prueba";
    public static final String TIPO_DESCONOCIDO = "desconocido"; // por si llega un codigo que no conocemos
    // ======================================

    // ----- Constantes de la trama iBeacon (no se tocan) -----

    // companyID de Apple: es el que dice "esto es un iBeacon"
    private static final int COMPANY_ID_APPLE = 0x004C;

    private static final int TAM_UUID = 16;

    // Etiqueta de los datos de los beacons. En logcat se filtra asi:
    //   adb logcat -s trama          (por consola)
    //   o en Android Studio: escribir "tag:trama" en el filtro de logcat
    // A esta etiqueta sale TODO: los beacons que se descartan y los nuestros.
    public static final String ETIQUETA_TRAMA = "trama";

    // Etiqueta de SOLO nuestro beacon. Aqui no sale nada mas: ni los beacons
    // que se descartan, ni los botones, ni los mensajes de arranque.
    // Es la que hay que mirar para ver "cuando aparece mi beacon":
    //   adb logcat -s tramaMarc
    //   o en Android Studio: escribir "tag:tramaMarc" en el filtro de logcat
    public static final String ETIQUETA_TRAMA_MARC = "tramaMarc";

    // Etiqueta de los mensajes que no son de beacons (arrancar/parar, hilos, etc.)
    private static final String ETIQUETA_LOG = ">>>>";

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Dice si el beacon recibido es NUESTRO: companyID de Apple (0x004C) y nuestro uuid.
     * Primero delega en el mismo filtro por bytes, que es el que se puede probar sin Android.
     *
     * @param resultado la lectura del escaner BLE
     * @return true si es nuestro beacon, false si no lo es (o si no es un iBeacon)
     */
    // resultado: ScanResult --> esNuestroBeacon() --> B
    public static boolean esNuestroBeacon(ScanResult resultado) {

        if (resultado == null) {
            return false;
        }

        if (resultado.getScanRecord() == null) {
            return false; // el paquete no traia registro de escaneo: no hay trama que mirar
        }

        // el RSSI va de -127 a 0 dBm. Con -127 no se descarta nada; si se sube el valor
        // en VARIABLES MODIFICABLES, se empiezan a ignorar los beacons mas lejanos.
        if (resultado.getRssi() < RSSI_MINIMO) {
            return false;
        }

        return esNuestroBeacon(resultado.getScanRecord().getBytes());

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * El filtro de verdad, sobre los bytes de la trama, para poder probarlo sin Android.
     * Descarta lo que no es iBeacon y compara companyID y uuid.
     *
     * @param bytesTrama los bytes de la trama que trae el beacon
     * @return true si es nuestro beacon, false si no lo es
     */
    // bytesTrama: [N]_* --> esNuestroBeacon() --> B
    public static boolean esNuestroBeacon(byte[] bytesTrama) {

        int inicio = TramaIBeacon.buscarEstructuraIBeacon(bytesTrama);

        if (inicio < 0) {
            return false; // no hay ninguna estructura de iBeacon de Apple
        }

        // el companyID ya lo ha comprobado buscarEstructuraIBeacon()
        // (es little-endian: los bytes 4c 00 son el valor 0x004C)

        // el uuid son los 16 bytes que van detras de companyID(2) + tipo(1) + longitud(1)
        byte[] uuidTrama = Arrays.copyOfRange(bytesTrama, inicio + 6, inicio + 6 + TAM_UUID);

        // nuestro uuid son 16 caracteres ASCII, asi que se comparan los bytes con el texto
        byte[] uuidPropio = Utilidades.stringToBytes(UUID_BEACON);

        return Arrays.equals(uuidTrama, uuidPropio);

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Dice si los bytes contienen datos de iBeacon de Apple. No mira posiciones
     * fijas: TramaIBeacon recorre la cadena de estructuras AD de la trama.
     *
     * @param bytesTrama los bytes de la trama
     * @return true si la trama contiene un iBeacon, false si no lo es
     */
    // bytesTrama: [N]_* --> esTramaIBeacon() --> B
    public static boolean esTramaIBeacon(byte[] bytesTrama) {
        return TramaIBeacon.esTramaIBeacon(bytesTrama);
    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Dice POR QUE se ha descartado una lectura, y no solo si se ha descartado.
     *
     * Sin esto, cuando el boton de buscar nuestro beacon no encuentra nada, el
     * filtro se limitaba a devolver false en silencio y no habia forma de saber
     * si era por el RSSI, por no ser un iBeacon, o por un uuid que no es el
     * nuestro. Devuelve:
     *   - null  si la lectura SI es nuestro beacon (no hay motivo de descarte)
     *   - el texto del motivo si se ha descartado
     *
     * @param resultado la lectura del escaner BLE
     * @return null si es nuestro beacon, o el motivo del descarte
     */
    // resultado: ScanResult --> motivoDescarte() --> Text
    public static String motivoDescarte(ScanResult resultado) {

        if (resultado == null) {
            return "la lectura es nula";
        }

        if (resultado.getScanRecord() == null) {
            return "el paquete no trae registro de escaneo (scanRecord == null)";
        }

        int rssi = resultado.getRssi();

        if (rssi < RSSI_MINIMO) {
            return "RSSI " + rssi + " dBm esta por debajo del minimo " + RSSI_MINIMO;
        }

        byte[] bytesTrama = resultado.getScanRecord().getBytes();

        int inicio = TramaIBeacon.buscarEstructuraIBeacon(bytesTrama);

        if (inicio < 0) {
            return "no es una trama iBeacon (llegan " + bytesTrama.length + " bytes: "
                    + Utilidades.bytesToHexString(bytesTrama) + ")";
        }

        // el companyID y el tipo 0x02 0x15 los ya ha comprobado
        // buscarEstructuraIBeacon(), asi que aqui vamos directos al uuid

        byte[] uuidTrama = Arrays.copyOfRange(bytesTrama, inicio + 6, inicio + 6 + TAM_UUID);
        byte[] uuidPropio = Utilidades.stringToBytes(UUID_BEACON);

        if (!Arrays.equals(uuidTrama, uuidPropio)) {
            return "el uuid no es el nuestro: llega '" + Utilidades.bytesToString(uuidTrama)
                    + "' y esperamos '" + UUID_BEACON + "'";
        }

        return null; // si hemos llegado aqui, SI es nuestro beacon

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Saca el valor del proyecto (el minor) de la trama. OJO: el minor NO es una
     * constante de la app, llega dentro del anuncio del beacon y se devuelve tal cual.
     *
     * @param tib la trama del beacon
     * @return el minor de la trama (hoy 1234)
     */
    // tib: TramaIBeacon --> interpretarValor() --> Z
    public static int interpretarValor(TramaIBeacon tib) {

        if (tib == null) {
            return 0;
        }

        // los 2 bytes del minor van big-endian: bytesToInt() ya los lee en ese orden
        return Utilidades.bytesToInt(tib.getMinor());

    } // ()

    // -------------------------------------------------------------------------------
    // Traduce el codigo de tipo de medicion a texto.
    // -------------------------------------------------------------------------------
    /**
     * Pasa el codigo de tipo de medicion (los 8 bits altos del major) a texto.
     * Es una funcion PURA: no toca Android, asi que se puede probar en el PC.
     *
     * @param codigo 1=CO2, 2=temperatura, 3=prueba
     * @return "CO2", "temperatura", "prueba", o "desconocido" si el codigo no es ninguno
     */
    // codigo: Z --> tipoDeMedicion() --> Text
    public static String tipoDeMedicion(int codigo) {

        switch (codigo) {
            case CODIGO_CO2:         return TIPO_CO2;
            case CODIGO_TEMPERATURA: return TIPO_TEMPERATURA;
            case CODIGO_PRUEBA:      return TIPO_PRUEBA;
            default:                 return TIPO_DESCONOCIDO;
        }

    } // ()

    // -------------------------------------------------------------------------------
    // Saca el tipo de medicion de la trama (los 8 bits altos del major).
    // -------------------------------------------------------------------------------
    /**
     * Lee el major de la trama y traduce su codigo de tipo (8 bits altos) a texto.
     *
     * @param tib la trama del beacon
     * @return el tipo de medicion en texto
     */
    // tib: TramaIBeacon --> tipoDeMedicion() --> Text
    public static String tipoDeMedicion(TramaIBeacon tib) {

        if (tib == null) {
            return TIPO_DESCONOCIDO;
        }

        // el tipo va en los 8 bits altos: major = (codigo << 8) | contador
        int major = Utilidades.bytesToInt(tib.getMajor());

        return tipoDeMedicion(major >> 8);

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Monta el JSON de la medicion con los campos y en el orden que espera el servidor.
     * NO manda "id" ni "fecha": eso lo pone la base de datos. Y NO manda uuid ni
     * major/minor/tx_power/rssi: el tipo y el valor ya van interpretados en texto
     * y en numero.
     *
     * @param m la medicion con los datos de una lectura
     * @return el texto del JSON
     */
    // m: Medicion --> construirJSON() --> Text
    public static String construirJSON(Medicion m) {

        if (m == null) {
            return "";
        }

        return construirJSON(m.getNombre(), m.getTipoMedicion(), m.getValorMedicion());

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Igual que construirJSON(Medicion), pero con los tres valores sueltos.
     * Se separa asi para poder probar el JSON sin montar una Medicion entera.
     *
     * @param nombre el nombre de la emisora
     * @param tipo   el tipo de medicion ya en texto
     * @param valor  el valor de la medicion
     * @return el texto del JSON
     */
    // nombre: Text, tipo: Text, valor: Z --> construirJSON() --> Text
    public static String construirJSON(String nombre, String tipo, int valor) {

        StringBuilder sb = new StringBuilder();

        sb.append("{");
        sb.append("\"id_plaquita\":\"").append(escaparTexto(nombre)).append("\",");
        sb.append("\"tipo_medicion\":\"").append(escaparTexto(tipo)).append("\",");
        sb.append("\"valor_medicion\":").append(valor);
        sb.append("}");

        return sb.toString();

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Cambia por su forma de JSON los caracteres rompen un texto: comillas y saltos.
     * Si el texto es null devuelve "", para no dejar un null suelto en el JSON.
     *
     * @param texto el texto a preparar
     * @return el texto listo para meter entre comillas en el JSON
     */
    // texto: Text --> escaparTexto() --> Text
    private static String escaparTexto(String texto) {

        if (texto == null) {
            return "";
        }

        return texto
                .replace("\\", "\\\\")   // barra invertida
                .replace("\"", "\\\"")  // comillas dobles
                .replace("\n", "\\n")   // salto de linea
                .replace("\r", "\\r")   // retorno de carro
                .replace("\t", "\\t");  // tabulador

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Monta la URL del servidor REST a partir de las tres partes.
     *
     * @param host  la IP o nombre del servidor
     * @param puerto el puerto del servidor
     * @param ruta  la ruta del recurso, por ejemplo "rest/guardarMedicion.php"
     * @return la URL completa
     */
    // host: Text, puerto: N, ruta: Text --> construirURL() --> Text
    public static String construirURL(String host, int puerto, String ruta) {
        return "http://" + host + ":" + puerto + "/" + ruta;
    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Dice si ya toca mandar otra medicion al servidor: ha pasado MS_ENTRE_MEDICIONES
     * desde el ultimo envio. Es PURA (recibe los dos instantes), asi que se puede
     * probar en el PC sin Android.
     *
     * La usa el servicio para no mandar un POST por cada paquete de la misma ventana
     * de anuncio (saldrian decenas de golpe).
     *
     * @param ahora       el instante actual (milisegundos)
     * @param ultimoEnvio el instante del ultimo envio (milisegundos); 0 si aun no se ha mandado ninguna
     * @return true si ya se puede mandar otra, false si todavia no toca
     */
    // ahora: Z, ultimoEnvio: Z --> debeEnviarMedicion() --> B
    public static boolean debeEnviarMedicion(long ahora, long ultimoEnvio) {
        return (ahora - ultimoEnvio) >= MS_ENTRE_MEDICIONES;
    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Vuelca a logcat (etiqueta ">>>>") todos los campos de la trama, para poder
     * mirarlos desde Android Studio con "logcat" mientras se prueba.
     *
     * @param tib la trama del beacon
     */
    // tib: TramaIBeacon --> mostrarTrama() -->
    public static void mostrarTrama(TramaIBeacon tib) {

        if (tib == null) {
            return;
        }

        Log.d(ETIQUETA_TRAMA_MARC, "----- TRAMA IBEACON -----");
        Log.d(ETIQUETA_TRAMA_MARC, "  prefijo     = " + Utilidades.bytesToHexString(tib.getPrefijo()));
        Log.d(ETIQUETA_TRAMA_MARC, "  advFlags    = " + Utilidades.bytesToHexString(tib.getAdvFlags()));
        Log.d(ETIQUETA_TRAMA_MARC, "  advHeader   = " + Utilidades.bytesToHexString(tib.getAdvHeader()));
        Log.d(ETIQUETA_TRAMA_MARC, "  companyID   = " + Utilidades.bytesToHexString(tib.getCompanyID())
                + "  (0x" + Integer.toHexString(
                        (tib.getCompanyID()[0] & 0xFF) | ((tib.getCompanyID()[1] & 0xFF) << 8)) + ")");
        Log.d(ETIQUETA_TRAMA_MARC, "  iBeaconType = 0x" + Integer.toHexString(tib.getiBeaconType() & 0xFF));
        Log.d(ETIQUETA_TRAMA_MARC, "  iBeaconLen  = 0x" + Integer.toHexString(tib.getiBeaconLength() & 0xFF));
        Log.d(ETIQUETA_TRAMA_MARC, "  uuid        = " + Utilidades.bytesToHexString(tib.getUUID())
                + "  (" + Utilidades.bytesToString(tib.getUUID()) + ")");
        Log.d(ETIQUETA_TRAMA_MARC, "  major       = " + Utilidades.bytesToHexString(tib.getMajor())
                + "  (" + Utilidades.bytesToInt(tib.getMajor()) + ")");
        Log.d(ETIQUETA_TRAMA_MARC, "  minor       = " + Utilidades.bytesToHexString(tib.getMinor())
                + "  (" + Utilidades.bytesToInt(tib.getMinor()) + ")");
        Log.d(ETIQUETA_TRAMA_MARC, "  txPower     = " + Utilidades.leerByteConSigno(tib.getTxPower()));
        Log.d(ETIQUETA_TRAMA_MARC, "---------------------------");

    } // ()

    // -------------------------------------------------------------------------------
    // -------------------------------------------------------------------------------
    /**
     * Escribe el JSON que se manda al servidor en una sola linea, con la etiqueta "trama",
     * para poder copiarlo y pegarlo tal cual en el navegador o en el Postman.
     *
     * @param cuerpo el texto del JSON
     */
    // cuerpo: Text --> mostrarJSON() -->
    public static void mostrarJSON(String cuerpo) {

        Log.d(ETIQUETA_TRAMA_MARC, "JSON: " + cuerpo);

    } // ()

} // class
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
// -----------------------------------------------------------------------------------
