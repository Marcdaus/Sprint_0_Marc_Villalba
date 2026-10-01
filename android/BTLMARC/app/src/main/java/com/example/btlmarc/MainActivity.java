
package com.example.btlmarc;

import android.Manifest;
import android.bluetooth.BluetoothAdapter;
import android.bluetooth.BluetoothDevice;
import android.bluetooth.BluetoothManager;
import android.bluetooth.le.BluetoothLeScanner;
import android.bluetooth.le.ScanCallback;
import android.bluetooth.le.ScanResult;
import android.bluetooth.le.ScanSettings;
import android.content.Context;
import android.content.Intent;
import android.content.pm.PackageManager;
import android.os.Bundle;
import android.util.Log;
import android.view.View;
import android.widget.TextView;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.app.ActivityCompat;
import androidx.core.content.ContextCompat;
import java.util.List;

// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
/**
 * Pantalla unica de la app. Aqui estan unidos los tres Activity de los esqueletos:
 *
 *   1) HolaMundoServicio2021 -> botones de arrancar y parar el servicio (PROMPT 2)
 *   2) BTLEAlumnos2021       -> botones de escanear beacons y volcar la trama
 *   3) PeticionarioREST      -> boton de prueba del cliente REST
 *
 * La logica de verdad NO esta aqui: vive en ServicioEscuharBeacons, AnalizadorBeacons y
 * Medicion. Esta pantalla solo la llama y ensena lo que devuelve.
 */
public class MainActivity extends AppCompatActivity {

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    private static final String ETIQUETA_LOG = ">>>>";

    // Numero que le ponemos a la peticion de permisos, para reconocer la respuesta
    private static final int CODIGO_PETICION_PERMISOS = 11223344;

    // Permisos de ejecucion que hacen falta para escanear (Android 12+).
    // BLUETOOTH y BLUETOOTH_ADMIN NO se piden: se dan al instalar.
    private static final String[] PERMISOS_NECESARIOS = {
            Manifest.permission.BLUETOOTH_SCAN,
            Manifest.permission.BLUETOOTH_CONNECT,
            Manifest.permission.ACCESS_FINE_LOCATION
    };

    // Metodo HTTP de la prueba REST
    private static final String METODO_GET = "GET";

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    private BluetoothLeScanner elEscanner = null;

    private ScanCallback callbackDelEscaneo = null;

    // Sonda de diagnostico: cuantos resultados ha entregado el escaner. Los primeros
    // 5 se enseñan aunque el filtro los rechace, para poder ver si el escaner funciona
    // o si lo que falla es el filtro. Es un campo (y no una variable local) porque
    // lo incrementa una clase anonima.
    private int contadorResultados = 0;

    private Intent elIntentDelServicio = null;

    private TextView textoEstadoPermisos = null;

    private TextView textoUltimaTrama = null;

    private TextView textoRespuestaRest = null;

    // ---------------------------------------------------------------------------------------------
    // (PROMPT 2) HOOK DE PANTALLA: el servicio avisa por aqui de cada trama que ve.
    // ---------------------------------------------------------------------------------------------
    private final ServicioEscuharBeacons.ObservadorDeTrama observador = new ServicioEscuharBeacons.ObservadorDeTrama() {
        @Override
        public void mostrarEnPantalla(String texto) {
            // el servicio puede avisar desde un hilo de BLE: para pintar, al hilo principal
            runOnUiThread(new Runnable() {
                @Override
                public void run() {
                    textoUltimaTrama.setText("Ultima trama: " + texto);
                }
            });
        }
    };

    // ==============================================================================================
    // BLOQUE 1)  SERVICIO DE BEACONS  (venia de HolaMundoServicio2021/MainActivity)
    // ==============================================================================================

    // ---------------------------------------------------------------------------------------------
    // Arranca el servicio. Antes pide los permisos y comprueba el bluetooth: sin eso
    // el servicio arranca pero no ve ningun beacon.
    // ---------------------------------------------------------------------------------------------
    public void botonArrancarServicioPulsado( View v ) {
        Log.d(ETIQUETA_LOG, " boton arrancar servicio Pulsado" );

        if ( this.elIntentDelServicio != null ) {
            return; // ya estaba arrancado
        }

        if ( !pidePermisosSiFaltan() ) {
            Log.d(ETIQUETA_LOG, " MainActivity: faltan permisos, no arranco el servicio");
            return;
        }

        if ( !estaElBluetoothEncendido() ) {
            Log.d(ETIQUETA_LOG, " MainActivity: el bluetooth esta apagado, no arranco el servicio");
            textoEstadoPermisos.setText("Permisos: bien. Bluetooth: APAGADO (enciendelo y pulsa de nuevo)");
            return;
        }

        Log.d(ETIQUETA_LOG, " MainActivity: voy a arrancar el servicio");

        this.elIntentDelServicio = new Intent(this, ServicioEscuharBeacons.class);
        this.elIntentDelServicio.putExtra("tiempoDeEspera", (long) 5000);

        startService( this.elIntentDelServicio );

    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    public void botonDetenerServicioPulsado( View v ) {

        if ( this.elIntentDelServicio == null ) {
            return; // no estaba arrancado
        }

        stopService( this.elIntentDelServicio );

        this.elIntentDelServicio = null;

        Log.d(ETIQUETA_LOG, " boton detener servicio Pulsado" );

    } // ()

    // ==============================================================================================
    // BLOQUE 2)  ESCANER BLE DE PRUEBA  (venia de BTLEAlumnos2021/MainActivity)
    // ==============================================================================================

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    public void botonBuscarDispositivosBTLEPulsado( View v ) {
        Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " boton buscar dispositivos BTLE Pulsado" );
        this.buscarTodosLosDispositivosBTLE();
    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    public void botonBuscarNuestroDispositivoBTLEPulsado( View v ) {
        Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " boton nuestro dispositivo BTLE Pulsado" );
        this.buscarEsteDispositivoBTLE( AnalizadorBeacons.UUID_BEACON );
    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    public void botonDetenerBusquedaDispositivosBTLEPulsado( View v ) {
        Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " boton detener busqueda dispositivos BTLE Pulsado" );
        this.detenerBusquedaDispositivosBTLE();
    } // ()

    // ---------------------------------------------------------------------------------------------
    // Arranca el escaner y avisa por el log de CADA beacon que vea, sin filtrar: sirve
    // para ver que hay cerca y comparar tramas.
    // ---------------------------------------------------------------------------------------------
    private void buscarTodosLosDispositivosBTLE() {

        String etiqueta = AnalizadorBeacons.ETIQUETA_TRAMA;

        if (this.elEscanner == null) {
            Log.d(etiqueta, " buscarTodosLosDispositivosBTLE(): NO HAY ESCANER."
                    + " Falta el permiso BLUETOOTH_SCAN o el bluetooth esta apagado.");
            return;
        }

        // el servicio ya tiene su propio escaner: este se para para no escanear por duplicado
        this.detenerBusquedaDispositivosBTLE();

        this.callbackDelEscaneo = new ScanCallback() {
            @Override
            public void onScanResult( int callbackType, ScanResult resultado ) {
                super.onScanResult(callbackType, resultado);
                mostrarInformacionDispositivoBTLE( resultado );
            }

            @Override
            public void onBatchScanResults(List<ScanResult> results) {
                super.onBatchScanResults(results);
                Log.d(etiqueta, " buscarTodosLosDispositivosBTLE(): " + results.size() + " beacons de golpe");
            }

            @Override
            public void onScanFailed(int errorCode) {
                super.onScanFailed(errorCode);
                Log.d(etiqueta, " buscarTodosLosDispositivosBTLE(): onScanFailed() errorCode=" + errorCode );
            }
        };

        Log.d(etiqueta, " buscarTodosLosDispositivosBTLE(): escaneando TODOS los dispositivos, sin filtro");

        // LOW_LATENCY: escaneo continuo, que es lo que hace nRF Connect.
        ScanSettings ajustes = new ScanSettings.Builder()
                .setScanMode( ScanSettings.SCAN_MODE_LOW_LATENCY )
                .setReportDelay( 0L )
                .build();

        try {
            this.elEscanner.startScan( null, ajustes, this.callbackDelEscaneo );
        } catch (SecurityException ex) {
            Log.d(etiqueta, " buscarTodosLosDispositivosBTLE(): sin permiso BLUETOOTH_SCAN: " + ex.getMessage());
        }

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Igual, pero buscando SOLO nuestro beacon.
    //
    // Antes este metodo tenia un parametro con el nombre del dispositivo, pero no lo usaba
    // para nada: hacia exactamente lo mismo que buscarTodosLosDispositivosBTLE(). Ademas
    // un iBeacon NO anuncia ningun nombre, asi que filtrar por nombre no habria servido.
    //
    // El filtro que de verdad importa es esNuestroBeacon(): compara byte a byte que
    // el UUID sea el del proyecto. Se aplica aqui, sobre cada resultado.
    //
    // Antes se pasaba ademas un ScanFilter con el UUID de servicio del iBeacon
    // (0xFEAA) por la via de startScan(List, ScanCallback), pero se ha quitado:
    //   - Android Studio no resolvia esa sobrecarga ("Cannot resolve method
    //     startScan(List<ScanFilter>, ScanCallback)"), y en vez de pelear con el
    //     IDE se usa el startScan(callback) de un argumento, que si resuelve.
    //   - Y ademas apenas ahorraba trabajo: el filtro por 0xFEAA descarta los
    //     dispositivos que NO son iBeacon, pero deja pasar TODOS los iBeacon
    //     ajenos, que es lo que hacia esNuestroBeacon() justo despues.
    // ---------------------------------------------------------------------------------------------
    private void buscarEsteDispositivoBTLE( String dispositivoBuscado ) {

        String etiqueta = AnalizadorBeacons.ETIQUETA_TRAMA;

        if (this.elEscanner == null) {
            Log.d(etiqueta, " buscarEsteDispositivoBTLE(): NO HAY ESCANER."
                    + " Falta el permiso BLUETOOTH_SCAN o el bluetooth esta apagado.");
            return;
        }

        this.detenerBusquedaDispositivosBTLE();

        this.callbackDelEscaneo = new ScanCallback() {
            @Override
            public void onScanResult( int callbackType, ScanResult resultado ) {
                super.onScanResult(callbackType, resultado);

                // Sonda de diagnostico: los primeros resultados que llegan se enseñan
                // SIEMPRE, escanen o no. Asi se distingue de un vistazo entre
                // "el escaner no entrega nada" y "el filtro se los come todos".
                if ( contadorResultados < 5 ) {
                    contadorResultados++;
                    Log.d(etiqueta, "resultado #" + contadorResultados
                            + " rssi=" + resultado.getRssi()
                            + " scanRecord=" + (resultado.getScanRecord() == null
                                              ? "NULL"
                                              : resultado.getScanRecord().getBytes().length + " bytes"));
                }

                // el filtro de verdad: solo nos interesan los beacons del proyecto.
                // Antes el return era en silencio y no habia forma de saber por que
                // no salia nada, asi que se pregunta el motivo y se escribe.
                String motivo = AnalizadorBeacons.motivoDescarte( resultado );

                if (motivo != null) {
                    Log.d(etiqueta, "descartado: " + motivo);
                    return;
                }

                mostrarInformacionDispositivoBTLE( resultado );
            }

            @Override
            public void onBatchScanResults(List<ScanResult> results) {
                super.onBatchScanResults(results);
            }

            @Override
            public void onScanFailed(int errorCode) {
                super.onScanFailed(errorCode);
                Log.d(etiqueta, " buscarEsteDispositivoBTLE(): onScanFailed() errorCode=" + errorCode );
            }
        };

        Log.d(etiqueta, " buscarEsteDispositivoBTLE(): escaneando, UUID de nuestro beacon = "
                + dispositivoBuscado);

        // ScanSettings: sin esto Android usa un modo de escaneo "ahorrador" que va por
        // rachas y se puede perder tramas de un beacon que emite una vez por segundo.
        // LOW_LATENCY escanea de forma continua, que es lo que hace nRF Connect.
        // reportDelay 0 = avisar en cuanto llega cada resultado, sin agruparlos.
        ScanSettings ajustes = new ScanSettings.Builder()
                .setScanMode( ScanSettings.SCAN_MODE_LOW_LATENCY )
                .setReportDelay( 0L )
                .build();

        try {
            this.elEscanner.startScan( null, ajustes, this.callbackDelEscaneo );
        } catch (SecurityException ex) {
            Log.d(etiqueta, " buscarEsteDispositivoBTLE(): sin permiso BLUETOOTH_SCAN: " + ex.getMessage());
        }

    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    private void detenerBusquedaDispositivosBTLE() {

        if ( this.callbackDelEscaneo == null || this.elEscanner == null ) {
            return;
        }

        try {
            this.elEscanner.stopScan( this.callbackDelEscaneo );
        } catch (SecurityException ex) {
            Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " detenerBusquedaDispositivosBTLE(): sin permiso BLUETOOTH_SCAN: " + ex.getMessage());
        }

        this.callbackDelEscaneo = null;

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Vuelca a logcat todos los datos de un beacon: nombre, direccion, rssi y la trama
    // interpretada (uuid, major, minor, txPower). Sirve para comparar con lo que manda el beacon.
    //
    // Sale con la etiqueta "tramaMarc", no con "trama": este metodo solo se llama cuando
    // el beacon ya ha pasado el filtro, o sea, cuando es el nuestro. Asi, escribiendo
    // "tag:tramaMarc" en logcat se ve unicamente nuestro beacon, sin el ruido de los
    // cientos de dispositivos ajenos que se descartan.
    // ---------------------------------------------------------------------------------------------
    private void mostrarInformacionDispositivoBTLE( ScanResult resultado ) {

        if (resultado == null || resultado.getScanRecord() == null) {
            return;
        }

        BluetoothDevice bluetoothDevice = resultado.getDevice();
        byte[] bytes = resultado.getScanRecord().getBytes();
        int rssi = resultado.getRssi();

        String etiqueta = AnalizadorBeacons.ETIQUETA_TRAMA_MARC;

        Log.d(etiqueta, " ****************************************************");
        Log.d(etiqueta, " ****** DISPOSITIVO DETECTADO BTLE ****************** ");
        Log.d(etiqueta, " ****************************************************");
        Log.d(etiqueta, " nombre = " + nombreSeguro( bluetoothDevice ));
        Log.d(etiqueta, " dirección = " + direccionSegura( bluetoothDevice ));
        Log.d(etiqueta, " rssi = " + rssi );
        Log.d(etiqueta, " bytes (" + bytes.length + ") = " + Utilidades.bytesToHexString(bytes));

        // si la trama no es de iBeacon, TramaIBeacon se sale al intentar leerla
        if (!AnalizadorBeacons.esTramaIBeacon(bytes)) {
            Log.d(etiqueta, " esto NO es un iBeacon, no se interpreta");
            return;
        }

        TramaIBeacon tib = new TramaIBeacon(bytes);

        Log.d(etiqueta, " uuid  = " + Utilidades.bytesToString(tib.getUUID())
                + "  (" + Utilidades.bytesToHexString(tib.getUUID()) + ")");
        Log.d(etiqueta, " major = " + Utilidades.bytesToInt(tib.getMajor()));
        Log.d(etiqueta, " minor = " + AnalizadorBeacons.interpretarValor(tib));
        Log.d(etiqueta, " txPower = " + tib.getTxPower());
        Log.d(etiqueta, " es nuestro beacon = " + AnalizadorBeacons.esNuestroBeacon(resultado));
        Log.d(etiqueta, " ****************************************************");

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Desde Android 12, getName() y getAddress() necesitan BLUETOOTH_CONNECT en tiempo
    // de ejecucion. Si no esta, lanzan SecurityException: aqui se captura y se avisa.
    // ---------------------------------------------------------------------------------------------
    private String nombreSeguro( BluetoothDevice dispositivo ) {
        try {
            return dispositivo.getName();
        } catch (SecurityException ex) {
            return "(sin permiso BLUETOOTH_CONNECT)";
        }
    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    private String direccionSegura( BluetoothDevice dispositivo ) {
        try {
            return dispositivo.getAddress();
        } catch (SecurityException ex) {
            return "(sin permiso BLUETOOTH_CONNECT)";
        }
    } // ()

    // ==============================================================================================
    // BLOQUE 3)  PRUEBA DEL SERVIDOR REST  (venia de PeticionarioREST/MainActivity)
    // ==============================================================================================

    // ---------------------------------------------------------------------------------------------
    // Manda un GET al servidor y enseña el codigo y el cuerpo de la respuesta. Sirve para
    // comprobar que el movil llega al PC (misma wifi) antes de esperar a los beacons.
    // ---------------------------------------------------------------------------------------------
    public void botonProbarRestPulsado( View v ) {
        Log.d(ETIQUETA_LOG, " boton probar REST Pulsado" );

        String url = AnalizadorBeacons.construirURL(
                AnalizadorBeacons.IP_SERVIDOR,
                AnalizadorBeacons.PUERTO_SERVIDOR,
                AnalizadorBeacons.RUTA_RECUPERAR);

        textoRespuestaRest.setText("Respuesta REST: esperando a " + url + " ...");

        // OJO: hay que crear uno nuevo cada vez que se manda una peticion
        PeticionarioREST elPeticionario = new PeticionarioREST();

        elPeticionario.hacerPeticionREST(METODO_GET, url, null,
                new PeticionarioREST.RespuestaREST() {
                    @Override
                    public void callback(int codigo, String cuerpo) {
                        // onPostExecute() ya viene al hilo principal, asi que se puede pintar
                        textoRespuestaRest.setText("Respuesta REST de " + url
                                + "\ncodigo = " + codigo
                                + "\ncuerpo = " + cuerpo);
                    }
                });

    } // ()

    // ==============================================================================================
    // PERMISOS Y ARRANQUE DE LA PANTALLA
    // ==============================================================================================

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        setContentView(R.layout.activity_main);

        Log.d(ETIQUETA_LOG, " MainActivity.onCreate: empieza ");

        this.textoEstadoPermisos = findViewById(R.id.textoEstadoPermisos);
        this.textoUltimaTrama = findViewById(R.id.textoUltimaTrama);
        this.textoRespuestaRest = findViewById(R.id.textoRespuestaRest);

        // enganchamos el servicio para que nos avise de cada trama (el PROMPT 8 cambiara esto)
        ServicioEscuharBeacons.setObservadorDeTrama(this.observador);

        this.elEscanner = obtenerEscaner();

        // pedimos los permisos al entrar, para que al pulsar "arrancar" ya esten
        pidePermisosSiFaltan();

        Log.d(ETIQUETA_LOG, " MainActivity.onCreate: termina ");

    } // onCreate()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    @Override
    protected void onDestroy() {
        this.detenerBusquedaDispositivosBTLE(); // no dejar el escaner de prueba encendido
        ServicioEscuharBeacons.setObservadorDeTrama(null); // no dejar el enganche puesto
        super.onDestroy();
    }

    // ---------------------------------------------------------------------------------------------
    // Mira si faltan los permisos de Bluetooth/ubicacion. Si faltan, se los pide al usuario.
    // ---------------------------------------------------------------------------------------------
    // --> pidePermisosSiFaltan() --> B
    private boolean pidePermisosSiFaltan() {

        if (faltanPermisos()) {

            Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " MainActivity: faltan permisos, se los pido al usuario");

            textoEstadoPermisos.setText("Permisos: faltan. Acceptalos para poder escanear beacons.");

            ActivityCompat.requestPermissions(this, PERMISOS_NECESARIOS, CODIGO_PETICION_PERMISOS);

            return false;
        }

        textoEstadoPermisos.setText("Permisos: concedidos. Ya puedes arrancar el servicio.");

        return true;

    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    // --> faltanPermisos() --> B
    private boolean faltanPermisos() {

        for (String permiso : PERMISOS_NECESARIOS) {
            if (ContextCompat.checkSelfPermission(this, permiso) != PackageManager.PERMISSION_GRANTED) {
                return true;
            }
        }

        return false;

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Coge el escaner BLE. OJO: hay que hacerlo DESPUES de que el usuario acepte los permisos.
    // ---------------------------------------------------------------------------------------------
    // --> obtenerEscaner() --> BluetoothLeScanner
    private BluetoothLeScanner obtenerEscaner() {

        if (faltanPermisos()) {
            return null; // sin permiso el sistema puede lanzar SecurityException
        }

        BluetoothManager elGestor = (BluetoothManager) getSystemService(Context.BLUETOOTH_SERVICE);

        if (elGestor == null || elGestor.getAdapter() == null) {
            return null;
        }

        try {
            if (!elGestor.getAdapter().isEnabled()) {
                Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " MainActivity.obtenerEscaner: el bluetooth esta apagado");
                return null;
            }
        } catch (SecurityException ex) {
            return null;
        }

        return elGestor.getAdapter().getBluetoothLeScanner();

    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    // --> estaElBluetoothEncendido() --> B
    private boolean estaElBluetoothEncendido() {

        BluetoothManager elGestor = (BluetoothManager) getSystemService(Context.BLUETOOTH_SERVICE);

        if (elGestor == null || elGestor.getAdapter() == null) {
            return false;
        }

        try {
            return elGestor.getAdapter().isEnabled();
        } catch (SecurityException ex) {
            return false; // si falta BLUETOOTH_CONNECT, aqui no podemos ni preguntar
        }

    } // ()

    // ---------------------------------------------------------------------------------------------
    // ---------------------------------------------------------------------------------------------
    @Override
    public void onRequestPermissionsResult(int requestCode, String[] permissions, int[] grantResults) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults);

        if (requestCode != CODIGO_PETICION_PERMISOS) {
            return;
        }

        if (faltanPermisos()) {
            Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " MainActivity: el usuario NO ha concedido los permisos");
            textoEstadoPermisos.setText("Permisos: DENEGADOS. Sin ellos no se puede escanear.");
            return;
        }

        Log.d(AnalizadorBeacons.ETIQUETA_TRAMA, " MainActivity: permisos concedidos");

        textoEstadoPermisos.setText("Permisos: concedidos. Ya puedes arrancar el servicio.");

        // sin el escaner no se puede buscar nada: lo pido ahora que ya hay permiso
        this.elEscanner = obtenerEscaner();

    } // ()

} // class
// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
