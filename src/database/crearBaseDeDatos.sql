-- =====================================
-- Autor: Marc Villalba Meseguer
-- Proyecto: Sprint_0_Marc_Villalba
-- Lenguaje: SQL
-- Curso: 3ºGTI
-- Fecha: 13/10/2026
-- =====================================

-- ===========================================================================
-- crearBaseDeDatos.sql  --  PROMPT 5 (base de datos)
--
-- QUE CREA ESTE SCRIPT: la base de datos proyecto_beacon y, dentro, la
-- tabla mediciones. Es lo unico que guarda el proyecto: una fila por cada
-- beacon que el movil ve y sube al servidor.
--
-- A QUE OBJETIVO SIRVE: el servidor REST (PROMPTs 3 y 4) inserta aqui el
-- tipo de medicion y su valor que recibe de la app, y la web (PROMPTs 6 y 7)
-- lee de aqui las ultimas mediciones para ensenarlas.
--
-- COMO SE USA ESTE FICHERO: desde phpMyAdmin, en la pestaña "Importar",
-- eligiendo este fichero. O desde la consola de MySQL:
--
--     mysql -u root < crearBaseDeDatos.sql
--
-- SE PUEDE VOLVER A EJECUTAR: los CREATE son IF NOT EXISTS, asi que no
-- borran nada de lo que ya haya. OJO: los INSERT de ejemplo del final SI
-- que se duplican cada vez que se ejecuta el script. Si no quieres que
-- pasa, comenta el bloque entero del final antes de volver a importarlo.
--
-- Notacion: Logical Design & Reverse Engineering v3
-- ===========================================================================


-- ====== VARIABLES MODIFICABLES ======
-- Los valores de ejemplo. Los INSERT del final los leen de aqui, asi que
-- para cambiar el valor de la demo (RN2), se cambia SOLO en @valorEjemplo
-- y se vuelve a ejecutar el script.
SET @nombreEjemplo = 'GTI-3A';   -- lo que pone el movil como nombre
SET @tipoEjemplo   = 'prueba';   -- RN1: "CO2" | "temperatura" | "prueba"
SET @valorEjemplo  = 1234;       -- valor del proyecto (RN2, modificable)
-- =====================================


-- ---------------------------------------------------------------------------
-- La base de datos. utf8mb4 para que valgan todos los caracteres, no solo
-- los del alfabeto latino.
-- ---------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS proyecto_beacon
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- a partir de aqui, todo lo que sigue va dentro de proyecto_beacon
USE proyecto_beacon;


-- ---------------------------------------------------------------------------
-- La tabla mediciones: una fila por cada beacon recibido.
--
-- La clave es NATURAL y COMPUESTA ( nombre_emisora, fecha ): NO hay columna id
-- artificial. Una medicion se identifica por QUIEN la hizo y CUANDO. Consecuencia:
-- la misma emisora no puede guardar DOS mediciones dentro del mismo segundo
-- (se duplicaria la clave primaria); el movil emite cada 5 s, asi que no llega
-- a pasar. La web sigue sin tocar esta clave: la clave la forma la fecha que
-- pone la BD sola (RN3).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mediciones (
  nombre_emisora VARCHAR(20)       NOT NULL,   -- "GTI-3A"
  tipo_medicion  VARCHAR(20)       NOT NULL,   -- RN1: "CO2" | "temperatura" | "prueba"
  valor_medicion SMALLINT          NOT NULL,   -- RN2: valor del proyecto (hoy 1234; modificable)
  fecha          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- RN3: la pone la BD, no la app ni el servidor
  PRIMARY KEY ( nombre_emisora, fecha ),
  INDEX idx_mediciones_fecha ( fecha )   -- para el ORDER BY fecha DESC LIMIT 50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------------
-- TRES MEDICIONES DE EJEMPLO, para que la web tenga algo que ensenar antes
-- de que llegue el movil. Los tres valores salen de las variables de arriba.
--
-- OJO, ahora que la clave es ( nombre_emisora, fecha ): la misma emisora NO
-- puede guardar dos mediciones dentro del MISMO segundo (se duplicaria la
-- clave primaria). Las tres filas de ejemplo son del mismo emisora y el
-- script se importa entero en un instante, asi que estas tres llevan fecha a
-- mano (una distinta cada una). Son datos de DEMO, no llegan del movil.
--
-- Con el movil la fecha NO se toca: la pone la base de datos sola con
-- CURRENT_TIMESTAMP (RN3). Si alguien anade aqui una fecha para el movil,
-- dejaria de mandar la fecha, que es justo lo que no tiene que pasar.
-- ---------------------------------------------------------------------------
INSERT INTO mediciones (nombre_emisora, tipo_medicion, valor_medicion, fecha)
VALUES (@nombreEjemplo, @tipoEjemplo, @valorEjemplo, '2026-10-12 09:00:00');

INSERT INTO mediciones (nombre_emisora, tipo_medicion, valor_medicion, fecha)
VALUES (@nombreEjemplo, @tipoEjemplo, @valorEjemplo, '2026-10-12 09:00:05');

INSERT INTO mediciones (nombre_emisora, tipo_medicion, valor_medicion, fecha)
VALUES (@nombreEjemplo, @tipoEjemplo, @valorEjemplo, '2026-10-12 09:00:10');
