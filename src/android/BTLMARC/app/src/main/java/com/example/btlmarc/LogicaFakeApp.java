// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: Java
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================


package com.example.btlmarc;

import android.util.Log;

// -------------------------------------------------------------------------------------------------
// LogicaFakeApp: la "logica fake" del telefono (la logica de negocio del cliente Android).
//
// Es el equivalente movil de src/frontend_business_logic/ de la web: expone a la app UN
// subconjunto de la logica de negocio del servidor (business_logic_design.md) con la MISMA
// firma -- aqui, guardarMedicion( m: Medicion ) --> ResultadoGuardado -- pero por dentro es un
// PROXY: en vez de tocar la base de datos, monta el JSON, la URL y hace el POST al servidor
// REST (src/communication/guardarMedicion.php), y traduce la respuesta HTTP a un resultado de
// dominio (ok / error / clase).
//
// Con esto, el servicio BLE (ServicioEscuharBeacons) deja de saber de JSON, direcciones y
// codigos de estado: solo llama a guardarMedicion() y recibe un ResultadoGuardado.
// -------------------------------------------------------------------------------------------------
public class LogicaFakeApp {

    // ====== VARIABLES MODIFICABLES ======
    // Metodo HTTP con el que se manda la medicion al servidor.
    private static final String METODO_POST = "POST";
    // =====================================

    // ---------------------------------------------------------------------------------------------
    // Tipos de dominio: los MISMOS que declara el backend en business_logic_design.md.
    // ---------------------------------------------------------------------------------------------

    // Los dos motivos de fallo que distingue el backend.
    public static final String CLASE_VALIDACION   = "validacion";   // dato invalido
    public static final String CLASE_PERSISTENCIA = "persistencia"; // fallo al guardar

    // ---------------------------------------------------------------------------------------------
    // ResultadoGuardado: lo que devuelve guardarMedicion().
    // Igual que en el backend: ok (si ok) o error + clase (si no). NO hay id:
    // la clave de la fila la forman nombre_emisora y fecha en la base de datos.
    // ---------------------------------------------------------------------------------------------
    public static class ResultadoGuardado {

        public final boolean ok;
        public final String error;  // texto del fallo (vacio si ok)
        public final String clase;  // CLASE_VALIDACION | CLASE_PERSISTENCIA (vacio si ok)

        // ok: B, error: Text, clase: Text --> ResultadoGuardado() -->
        private ResultadoGuardado(boolean ok, String error, String clase) {
            this.ok = ok;
            this.error = error;
            this.clase = clase;
        }

        // --> exito() --> ResultadoGuardado
        public static ResultadoGuardado exito() {
            return new ResultadoGuardado(true, "", "");
        }

        // clase: Text, error: Text --> fallo() --> ResultadoGuardado
        public static ResultadoGuardado fallo(String clase, String error) {
            return new ResultadoGuardado(false, error, clase);
        }

    } // class ResultadoGuardado

    // ---------------------------------------------------------------------------------------------
    // RespuestaApp: por donde llega el ResultadoGuardado (la red es asincrona, igual que el XHR
    // de la web). guardarMedicion() avisa UNA sola vez por esta interfaz.
    // ---------------------------------------------------------------------------------------------
    public interface RespuestaApp {
        // resultado: ResultadoGuardado --> onResultado() -->
        void onResultado(ResultadoGuardado resultado);
    }

    // ---------------------------------------------------------------------------------------------
    // Peticion: el metodo, la URL y el cuerpo ya montados (detalle interno del proxy).
    // ---------------------------------------------------------------------------------------------
    public static class Peticion {
        public final String metodo;
        public final String url;
        public final String cuerpo;

        // metodo: Text, url: Text, cuerpo: Text --> Peticion() -->
        private Peticion(String metodo, String url, String cuerpo) {
            this.metodo = metodo;
            this.url = url;
            this.cuerpo = cuerpo;
        }
    } // class Peticion

    // ---------------------------------------------------------------------------------------------
    // guardarMedicion(): el UNICO metodo del contrato (firma identica al backend).
    //
    //   BACKEND  (business_logic_design.md):  m: Medicion --> guardarMedicion() --> ResultadoGuardado
    //   APP      (este proxy):                m: Medicion --> guardarMedicion() --> ResultadoGuardado
    //
    // Como la red es asincrona, el ResultadoGuardado no se devuelve con "return": se entrega por
    // respuesta.onResultado(...). (El backend si lo devuelve directo porque no hay red.)
    // ---------------------------------------------------------------------------------------------
    // m: Medicion, respuesta: RespuestaApp --> guardarMedicion() -->
    public void guardarMedicion(Medicion m, RespuestaApp respuesta) {

        // 1. sin medicion no hay nada que guardar (fallo de validacion, igual que el backend)
        if (m == null) {
            avisar(respuesta, ResultadoGuardado.fallo(CLASE_VALIDACION, "no hay medicion que guardar"));
            return;
        }

        // 2. montar la peticion (JSON + URL). Es puro: se puede probar sin Android.
        Peticion peticion = construirPeticion(m);

        // 3. el JSON tambien al log, para verlo y copiarlo (depuracion)
        AnalizadorBeacons.mostrarJSON(peticion.cuerpo);

        Log.d(AnalizadorBeacons.ETIQUETA_TRAMA_MARC, "POST " + peticion.url + " cuerpo=" + peticion.cuerpo);

        // 4. mandar el POST y traducir la respuesta a un resultado de dominio.
        //    Ojo: PeticionarioREST es un AsyncTask y execute() pide el hilo principal.
        this.crearPeticionarioREST().hacerPeticionREST(
                peticion.metodo, peticion.url, peticion.cuerpo,
                new PeticionarioREST.RespuestaREST() {
                    @Override
                    // codigo: Z, cuerpo: Text --> callback() -->
                    public void callback(int codigo, String cuerpoRespuesta) {
                        avisar(respuesta, interpretarRespuesta(codigo, cuerpoRespuesta));
                    }
                });

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Monta la peticion de guardado: el JSON de la Medicion y la URL del endpoint.
    // Es PURA (no hace red ni toca Android), asi que se puede probar en el PC.
    // ---------------------------------------------------------------------------------------------
    // m: Medicion --> construirPeticion() --> Peticion
    public static Peticion construirPeticion(Medicion m) {

        String cuerpo = AnalizadorBeacons.construirJSON(m);

        String url = AnalizadorBeacons.construirURL(
                AnalizadorBeacons.IP_SERVIDOR,
                AnalizadorBeacons.PUERTO_SERVIDOR,
                AnalizadorBeacons.RUTA_GUARDAR);

        return new Peticion(METODO_POST, url, cuerpo);

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Traduce la respuesta del servidor (codigo + cuerpo) a un resultado de dominio.
    // Es PURA, asi que se puede probar en el PC sin Android.
    //   200 -> ok | 400 -> validacion | 500 -> persistencia | otro -> persistencia (red)
    // ---------------------------------------------------------------------------------------------
    // codigo: Z, cuerpo: Text --> interpretarRespuesta() --> ResultadoGuardado
    public static ResultadoGuardado interpretarRespuesta(int codigo, String cuerpo) {

        if (codigo == 200) {
            // el servidor contesta {"ok":true} y nada mas: no hay id que leer,
            // la clave de la fila la forma la base de datos (nombre_emisora, fecha)
            return ResultadoGuardado.exito();
        }

        if (codigo == 400) {
            return ResultadoGuardado.fallo(CLASE_VALIDACION, leerError(cuerpo));
        }

        if (codigo == 500) {
            return ResultadoGuardado.fallo(CLASE_PERSISTENCIA, leerError(cuerpo));
        }

        // sin respuesta del servidor (red caida, servidor apagado...): no es culpa del dato
        return ResultadoGuardado.fallo(CLASE_PERSISTENCIA,
                "no se ha podido conectar con el servidor (codigo " + codigo + ")");

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Saca el "error" del JSON {"error":"..."}. Si no esta, usa un texto generico.
    // ---------------------------------------------------------------------------------------------
    // cuerpo: Text --> leerError() --> Text
    private static String leerError(String cuerpo) {

        int posicion = cuerpo.indexOf("\"error\"");

        if (posicion < 0) {
            return "el servidor ha devuelto un error";
        }

        int inicio = cuerpo.indexOf('"', cuerpo.indexOf(':', posicion) + 1);

        if (inicio < 0) {
            return "el servidor ha devuelto un error";
        }

        int fin = cuerpo.indexOf('"', inicio + 1);

        if (fin < 0) {
            return "el servidor ha devuelto un error";
        }

        return cuerpo.substring(inicio + 1, fin);

    } // ()

    // ---------------------------------------------------------------------------------------------
    // Avisa al interesado del resultado. Si no hay interesado, no hace nada.
    // ---------------------------------------------------------------------------------------------
    // respuesta: RespuestaApp, resultado: ResultadoGuardado --> avisar() -->
    private static void avisar(RespuestaApp respuesta, ResultadoGuardado resultado) {
        if (respuesta != null) {
            respuesta.onResultado(resultado);
        }
    } // ()

    // ---------------------------------------------------------------------------------------------
    // Crea el cliente REST. Existe como metodo aparte para que las pruebas puedan meter uno
    // falso (mock) y comprobar el POST sin tocar la red (igual que el backend prueba con un
    // PDO falso).
    // ---------------------------------------------------------------------------------------------
    // --> crearPeticionarioREST() --> PeticionarioREST
    protected PeticionarioREST crearPeticionarioREST() {
        return new PeticionarioREST();
    } // ()

} // class
// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
// -------------------------------------------------------------------------------------------------
