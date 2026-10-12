<?php
// =====================================
// Autor: Marc Villalba Meseguer
// Proyecto: Sprint_0_Marc_Villalba
// Lenguaje: PHP
// Curso: 3ºGTI
// =====================================


// ---------------------------------------------------------------
//
// nombre:Texto, password:Texto -> hacerLogin() -> VoF
//
// nombre: Text, password: Text --> hacerLogin() --x --> B
// ---------------------------------------------------------------

function hacerLogin( $nombre, $password ) {

  // comprobación "rigurosa" del password
  if ( $password == "1234" ) {
    return true;
  }

  return false;
}
?>
