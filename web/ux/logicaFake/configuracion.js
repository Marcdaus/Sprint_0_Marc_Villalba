// ---------------------------------------------------
//
// Valores para hablar con el servidor REST.
//
// Este es el UNICO sitio que hay que tocar para cambiar a donde apunta la
// web: el host, el puerto y la ruta del PHP que devuelve las mediciones.
//
// OJO con la ruta: el servidor PHP se arranca con raiz en la carpeta
// Sprint_0_Marc_Villalba (no en servidor/, ni en web/), y por eso la ruta
// empieza por "servidor/". Si cambias donde se arranca el servidor,
// cambia esta linea.
//
// El "minor" NO esta aqui a proposito: se enseña tal cual llega del
// servidor y puede cambiar (hoy es 1234). No se fija aqui.
//
// ---------------------------------------------------

// ====== VARIABLES MODIFICABLES ======
const HOST = "localhost"; // host del servidor
const PUERTO = 8080; // puerto del servidor
const RUTA_RECUPERAR = "servidor/rest/recuperarMedicion.php"; // PHP que devuelve las mediciones
// =====================================