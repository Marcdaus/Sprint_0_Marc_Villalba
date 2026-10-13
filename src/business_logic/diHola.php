<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// Fecha: 13/10/2026
// =====================================

// -------------------------------------------------
//
// usuario:Texto -> diHola() -> (nombre:Texto, saludo:Texto) 
//
// usuario: Text --> diHola() --x --> ( nombre: Text, saludo: Text )
// -------------------------------------------------

function diHola( $usuario ) {
  $objetoResultado = new stdClass;
  $objetoResultado->nombre = $usuario;
  $objetoResultado->saludo = "That's all folks";

  return $objetoResultado;
}

?>
