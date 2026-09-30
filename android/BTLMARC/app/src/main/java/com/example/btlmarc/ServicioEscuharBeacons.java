
package com.example.btlmarc;

import android.app.IntentService;
import android.app.Service;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothManager;
import android.bluetooth.le.BluetoothLeScanner;
import android.bluetooth.le.ScanCallback;
import android.bluetooth.le.ScanResult;
import android.bluetooth.le.ScanSettings;
import android.content.Context;
import android.content.Intent;
import android.util.Log;

// AnalizadorBeacons, Medicion, TramaIBeacon y PeticionarioREST estan en este mismo
// paquete (com.example.btlmarc), asi que aqui no hace falta importar nada de ellas.

import java.util.List;

import static android.app.Service.START_STICKY;

// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
public class ServicioEscuharBeacons  extends IntentService {

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    private static final String ETIQUETA_LOG = ">>>>";

    // Metodo HTTP que se usa para mandar la medicion
    private static final String METODO_POST = "POST";

    // ---------------------------------------------------------------------------------------------
    // HOOK DE PANTALLA (para el PROMPT 8, que hara la UX).
    // La UI se engancha con setObservadorDeTrama() y recibe un texto con la ultima trama.
    // ---------------------------------------------------------------------------------------------
    public interface ObservadorDeTrama {
        void mostrarEnPantalla(String texto);
    }

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    private static ObservadorDeTrama observadorDeTrama = null;

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    /**
     * Engancha (o desengancha, pasando null) lo que quiera ver los beacons en pantalla.
     * Lo usara el PROMPT 8; aqui solo esta el hueco.
     *
     * @param observador el que quiera el texto de la ultima trama, o null para desengancharlo
     */
    // observador: ObservadorDeTrama --> setObservadorDeTrama() -->
    public static void setObservadorDeTrama(ObservadorDeTrama observador) {
        observadorDeTrama = observador;
    } // ()

    // ---------------------------------------------------------------------------------------------
    // (new) el cliente REST que se manda el POST
    // ---------------------------------------------------------------------------------------------
    private final PeticionarioREST.RespuestaREST respuestaDelPOST = new PeticionarioREST.RespuestaREST() {
        @Override
        public void callback(int codigo, String cuerpo) {
            // esta respuesta solo llega si se ha postureado una medicion NUESTRA,
            // asi que va con la etiqueta "tramaMarc" (lo de nuestro beacon)
            Log.d(AnalizadorBeacons.ETIQUETA_TRAMA_MARC, "respuesta del servidor: codigo=" + codigo
                    + " cuerpo=" + cuerpo);
        }
    };

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    private long tiempoDeEspera = 10000;

    private boolean seguir = true;

    // (new) el escaner BLE antiguo
    private BluetoothLeScanner elEscanner = null;

    // (new) lo que el escaner llama cuando ve un beacon
    private final ScanCallback callbackDelEscaneo = new ScanCallback() {

        @Override
        public void onScanResult(int callbackType, ScanResult resultado) {
            manejarBeaconRecibido(resultado);
        }

        @Override
        public void onBatchScanResults(List<ScanResult> resultados) {
            for (ScanResult resultado : resultados) {
                manejarBeaconRecibido(resultado);
            }
        }

        @Override
        public void onScanFailed(int errorCode) {
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onScanFailed: errorCode=" + errorCode);
        }

    };

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    public ServicioEscuharBeacons(  ) {
        super("HelloIntentService");

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.constructor: termina");
    }

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    /*
    @Override
    public int onStartCommand( Intent elIntent, int losFlags, int startId) {

        // creo que este método no es necesario usarlo. Lo ejecuta el thread principal !!!
        super.onStartCommand( elIntent, losFlags, startId );

        this.tiempoDeEspera = elIntent.getLongExtra("tiempoDeEspera", 50000);

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onStartCommand : empieza: thread=" + Thread.currentThread().getId() );

        return Service.START_CONTINUATION_MASK | Service.START_STICKY;
    } // ()

     */

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    public void parar () {

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.parar() " );


        if ( this.seguir == false ) {
            return;
        }

        this.seguir = false;
        this.stopSelf();

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.parar() : acaba " );

    }

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    public void onDestroy() {

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onDestroy() " );


        this.parar(); // posiblemente no haga falta, si stopService() ya se carga el servicio y su worker thread
    }

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    /**
     * The IntentService calls this method from the default worker thread with
     * the intent that started the service. When this method returns, IntentService
     * stops the service, as appropriate.
     */
    @Override
    protected void onHandleIntent(Intent intent) {

        this.tiempoDeEspera = intent.getLongExtra("tiempoDeEspera", /* default */ 50000);
        this.seguir = true;

        // esto lo ejecuta un WORKER THREAD !

        long contador = 1;

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onHandleIntent: empieza : thread=" + Thread.currentThread().getId() );

        try {

            // 1. arrancar la escucha BLE: a partir de aqui ya nos llegan los beacons
            this.iniciarEscuchaBTLE();

            // 2. mientras nobody llame a parar(), el servicio se queda vivo aqui
            while ( this.seguir ) {
                Thread.sleep(tiempoDeEspera);
                Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onHandleIntent: tras la espera:  " + contador );
                contador++;
            }

            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onHandleIntent : tarea terminada ( tras while(true) )" );


        } catch (InterruptedException e) {
            // Restore interrupt status.
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onHandleItent: problema con el thread");

            Thread.currentThread().interrupt();
        }

        // 3. si nos llaman a parar() el servicio se muere: el escaner tambien fuera
        this.detenerEscuchaBTLE();

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.onHandleItent: termina");

    }

    // ---------------------------------------------------------------------------------------------
    // (new) ------------------------------------------------------------------------------------
    /**
     * Crea el escaner BLE antiguo y le pone el callback que nos avisa de cada beacon.
     * Si el Bluetooth esta apagado o el movil no tiene BLE, lo dice y no hace nada.
     */
    // --> iniciarEscuchaBTLE() -->
    private void iniciarEscuchaBTLE() {

        BluetoothAdapter elAdaptador = obtenerAdaptador();

        if (elAdaptador == null) {
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.iniciarEscuchaBTLE(): este movil no tiene BLE");
            return;
        }

        if (!estaEncendido(elAdaptador)) {
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.iniciarEscuchaBTLE(): el bluetooth esta apagado");
            return;
        }

        BluetoothManager elGestor = (BluetoothManager) getSystemService(Context.BLUETOOTH_SERVICE);

        if (elGestor == null) {
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.iniciarEscuchaBTLE(): no hay BluetoothManager");
            return;
        }

        this.elEscanner = elGestor.getAdapter().getBluetoothLeScanner();

        if (this.elEscanner == null) {
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.iniciarEscuchaBTLE(): no hay escaner BLE");
            return;
        }

        // filtros a null: aqui NO se filtra, el filtro de verdad es esNuestroBeacon()
        // ScanSettings LOW_LATENCY: sin esto Android va por rachas y se puede perder
        // tramas de un beacon que emite una vez por segundo. reportDelay 0 = sin agrupar.
        ScanSettings ajustes = new ScanSettings.Builder()
                .setScanMode( ScanSettings.SCAN_MODE_LOW_LATENCY )
                .setReportDelay( 0L )
                .build();

        try {
            this.elEscanner.startScan(null, ajustes, this.callbackDelEscaneo);
        } catch (SecurityException ex) {
            // desde Android 12 hace falta el permiso BLUETOOTH_SCAN en tiempo de ejecucion
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.iniciarEscuchaBTLE(): sin permiso BLUETOOTH_SCAN: "
                    + ex.getMessage());
            this.elEscanner = null;
            return;
        }

        Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.iniciarEscuchaBTLE(): escaneo arrancado");

    } // ()

    // ---------------------------------------------------------------------------------------------
    // (new) ------------------------------------------------------------------------------------
    /**
     * Para el escaneo BLE, si habia escaner. Se puede llamar aunque el escaner sea null.
     */
    // --> detenerEscuchaBTLE() -->
    private void detenerEscuchaBTLE() {

        if (this.elEscanner == null) {
            return;
        }

        try {
            this.elEscanner.stopScan(this.callbackDelEscaneo);
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.detenerEscuchaBTLE(): escaneo parado");
        } catch (SecurityException ex) {
            // si le quitaron el permiso BLUETOOTH_SCAN por debajo, da error: solo lo digo
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.detenerEscuchaBTLE(): sin permiso BLUETOOTH_SCAN: "
                    + ex.getMessage());
        } catch (Exception ex) {
            // si el escaner ya estaba parado, da error: no pasa nada, solo lo digo
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.detenerEscuchaBTLE(): " + ex.getMessage());
        }

        this.elEscanner = null;

    } // ()

    // ---------------------------------------------------------------------------------------------
    // (new) ------------------------------------------------------------------------------------
    /**
     * Lo que se hace con CADA beacon que ve el escaner:
     * si no es el nuestro no se hace nada; si lo es, se monta la Medicion y se manda
     * por POST al servidor.
     *
     * @param resultado la lectura del escaner BLE
     */
    // resultado: ScanResult --> manejarBeaconRecibido() -->
    protected void manejarBeaconRecibido(ScanResult resultado) {

        // 0. si nos pasan algo vacio, aqui se acaba
        if (resultado == null) {
            return;
        }

        // 1. el nombre que da Android del emisor (a veces es null)
        String nombre = null;

        try {
            nombre = (resultado.getDevice() != null) ? resultado.getDevice().getName() : null;
        } catch (SecurityException ex) {
            // sin BLUETOOTH_CONNECT no se puede leer el nombre: seguimos con el del proyecto
            Log.d(ETIQUETA_LOG, " ServicioEscucharBeacons.manejarBeaconRecibido: sin permiso BLUETOOTH_CONNECT");
        }

        if (nombre == null || nombre.isEmpty()) {
            nombre = AnalizadorBeacons.NOMBRE_EMISORA; // si no hay nombre, pongo el del proyecto
        }

        // 2. leer la trama
        byte[] bytesTrama = (resultado.getScanRecord() != null) ? resultado.getScanRecord().getBytes() : null;

        // 3. es el nuestro? si no, aqui se acaba
        String motivoDescarte = AnalizadorBeacons.motivoDescarte(resultado);

        if (motivoDescarte != null) {
            Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, "descartado: " + motivoDescarte);
            return;
        }

        TramaIBeacon tib = new TramaIBeacon(bytesTrama);

        // 4. volcar la trama al log y avisar a la pantalla (hook del PROMPT 8)
        AnalizadorBeacons.mostrarTrama(tib);
        this.mostrarEnPantalla(AnalizadorBeacons.NOMBRE_EMISORA
                + " | valor=" + AnalizadorBeacons.interpretarValor(tib)
                + " | rssi=" + resultado.getRssi());

        // 5. montar la medicion
        Medicion m = new Medicion(tib, nombre, resultado.getRssi());

        // 6. el cuerpo del POST
        String cuerpo = AnalizadorBeacons.construirJSON(m);

        // 6.1. el JSON tambien al log, con la etiqueta "trama", para verlo y copiarlo
        AnalizadorBeacons.mostrarJSON(cuerpo);

        // 7. la URL del servidor
        String url = AnalizadorBeacons.construirURL(
                AnalizadorBeacons.IP_SERVIDOR,
                AnalizadorBeacons.PUERTO_SERVIDOR,
                AnalizadorBeacons.RUTA_GUARDAR);

        Log.d(AnalizadorBeacons.ETIQUETA_TRAMA_MARC, "POST " + url + " cuerpo=" + cuerpo);

        // 8. mandar el POST.
        // Ojo: PeticionarioREST es un AsyncTask y execute() pide el hilo principal.
        // onScanResult ya llega al hilo principal, asi que aqui se puede llamar tranquilo.
        this.crearPeticionarioREST().hacerPeticionREST(METODO_POST, url, cuerpo, this.respuestaDelPOST);

    } // ()

    // ---------------------------------------------------------------------------------------------
    // (new) ------------------------------------------------------------------------------------
    /**
     * Manda un texto a la pantalla, que ahora mismo solo va al log.
     * El PROMPT 8 cambiara el cuerpo de este metodo (o llamara al observador) para
     * enseñarlo en la Activity, sin tocar el resto de la logica.
     *
     * @param texto el texto a ensenar
     */
    // texto: Text --> mostrarEnPantalla() -->
    protected void mostrarEnPantalla(String texto) {

        Log.d(ETIQUETA_LOG, " PANTALLA: " + texto );

        if (observadorDeTrama != null) {
            observadorDeTrama.mostrarEnPantalla(texto);
        }

    } // ()

    // ---------------------------------------------------------------------------------------------
    // (new) ------------------------------------------------------------------------------------
    /**
     * Crea el cliente REST. Existe como metodo aparte para que las pruebas automáticas
     * puedan meter uno falso (mock) y comprobar el POST sin tocar la red.
     *
     * @return un cliente REST listo para hacer peticiones
     */
    // --> crearPeticionarioREST() --> PeticionarioREST
    protected PeticionarioREST crearPeticionarioREST() {
        return new PeticionarioREST();
    } // ()

    // ---------------------------------------------------------------------------------------------
    // (new) ------------------------------------------------------------------------------------
    /**
     * Mira si el bluetooth esta encendido. Desde Android 12 hace falta BLUETOOTH_CONNECT,
     * asi que va protegido: si no hay permiso, decimos que no esta encendido.
     *
     * @param elAdaptador el adaptador de bluetooth
     * @return true si el bluetooth esta encendido
     */
    // elAdaptador: BluetoothAdapter --> estaEncendido() --> B
    private boolean estaEncendido(BluetoothAdapter elAdaptador) {

        try {
            return elAdaptador.isEnabled();
        } catch (SecurityException ex) {
            return false;
        }

    } // ()

    // ---------------------------------------------------------------------------------------------
    // (new) ------------------------------------------------------------------------------------
    /**
     * Saca el adaptador de Bluetooth del sistema.
     *
     * @return el adaptador, o null si este movil no lo tiene
     */
    // --> obtenerAdaptador() --> BluetoothAdapter
    private BluetoothAdapter obtenerAdaptador() {

        BluetoothManager elGestor = (BluetoothManager) getSystemService(Context.BLUETOOTH_SERVICE);

        if (elGestor == null || elGestor.getAdapter() == null) {
            return null;
        }

        return elGestor.getAdapter();

    } // ()

} // class
// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
