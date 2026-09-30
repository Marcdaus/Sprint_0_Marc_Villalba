<?php

// ===========================================================================
// configuracion.php  --  PROMPT 4 (logica de negocio)
//
// QUE ES ESTO: los datos de conexion a la base de datos.
//
// ESTAN EN UN FICHERO APARTE, Y NO EN LogicaDeNegocio.php, para no dejar
// las credenciales dentro del codigo de la logica: si algun dia hay que
// cambiar el usuario o la contraseña, se cambia aqui y en ningun otro
// sitio (ver PROMPT 4, seccion 4.5).
//
// QUE HACE ESTE FICHERO: nada. Solo deja preparada una variable con los
// datos de conexion y la devuelve, para que quien la necesite haga:
//
//     $config = require __DIR__ . '/configuracion.php';
//
// OJO: se carga con require() a proposito, NO con require_once(). Con
// require_once() la segunda vez NO se devuelve el valor del return, se
// devuelve true, y aqui se perderia la configuracion sin avisar.
//
// OJO: estos son los valores por defecto de MySQL en local (XAMPP), no
// credenciales reales de ningun servidor. La base de datos proyecto_beacon
// la crea el PROMPT 5 con phpMyAdmin.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

// ====== VARIABLES MODIFICABLES ======
// Datos de conexion a la base de datos proyecto_beacon.
$hostBd       = 'localhost';
$puertoBd     = 3306;
$usuarioBd    = 'root';
$contrasenaBd = '';          // en XAMPP el usuario root no tiene contraseña
$nombreBd     = 'proyecto_beacon';
$charset      = 'utf8mb4';
// =====================================

// se monta la configuracion en un objeto y se devuelve, en vez de dejar
// variables sueltas: asi el que la carga no se mixes con las suyas
$configuracion = new stdClass();
$configuracion->host       = $hostBd;
$configuracion->puerto     = $puertoBd;
$configuracion->usuario    = $usuarioBd;
$configuracion->contrasena = $contrasenaBd;
$configuracion->nombre     = $nombreBd;
$configuracion->charset    = $charset;

return $configuracion;

?>
