<?php

// ===========================================================================
// guardarMedicion.php  --  PROMPT 4
//
// QUE ES ESTO: un puntero, nada mas.
//
// El endpoint rest/guardarMedicion.php (PROMPT 3) busca la logica de
// negocio en logica/guardarMedicion.php, y este fichero no es el que la
// tiene: esta en logica/LogicaDeNegocio.php, junto a recuperarMedicion().
//
// Los dos van en el mismo fichero a proposito, porque son las dos caras
// de la misma logica (guardar y recuperar) y comparten la conexion, el
// formato de la RespuestaServidor y los helpers. Partirlas en dos ficheros
// obligaria a duplicar todo eso.
//
// Asi que este fichero solo carga el de verdad. Los endpoints no se tocan.
//
// Notacion: Logical Design & Reverse Engineering v3
// ===========================================================================

require_once __DIR__ . '/LogicaDeNegocio.php';

?>
