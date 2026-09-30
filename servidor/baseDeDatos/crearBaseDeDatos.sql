-- ===========================================================================
-- crearBaseDeDatos.sql  --  PROMPT 5 (base de datos)
--
-- QUE CREA ESTE SCRIPT: la base de datos proyecto_beacon y, dentro, la
-- tabla mediciones. Es lo unico que guarda el proyecto: una fila por cada
-- beacon que el movil ve y sube al servidor.
--
-- A QUE OBJETIVO SIRVE: el servidor REST (PROMPTs 3 y 4) inserta aqui el
-- minor que recibe de la app, y la web (PROMPTs 6 y 7) lee de aqui las
-- ultimas mediciones para ensenarlas.
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
-- para cambiar el valor del minor de la demo (RN2), se cambia SOLO en
-- @minorEjemplo y se vuelve a ejecutar el script.
SET @uuidEjemplo    = 'EPSG-GTI-MARC-3A';   -- la uuid que emite la plaquita
SET @nombreEjemplo  = 'GTI-3A';             -- lo que pone el movil como nombre
SET @majorEjemplo   = 3584;
SET @minorEjemplo   = 1234;                 -- valor del proyecto (RN2, modificable)
SET @txPowerEjemplo = 4;
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
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS mediciones (
  id             INT UNSIGNED      NOT NULL AUTO_INCREMENT,
  uuid_beacon    VARCHAR(32)       NOT NULL,   -- RN1: "EPSG-GTI-MARC-3A"
  nombre_emisora VARCHAR(20)       NOT NULL,   -- "GTI-3A"
  major          SMALLINT          NOT NULL,   -- 3584
  minor          SMALLINT          NOT NULL,   -- RN2: valor del proyecto (hoy 1234; modificable)
  tx_power       TINYINT           NOT NULL,   -- 4
  rssi           TINYINT           NOT NULL,   -- -53 (puede ser negativo)
  fecha          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,   -- RN3: la pone la BD, no la app ni el servidor
  PRIMARY KEY ( id ),
  INDEX idx_mediciones_fecha ( fecha )   -- para el ORDER BY fecha DESC LIMIT 50
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------------------
-- TRES MEDICIONES DE EJEMPLO, para que la web tenga algo que enseñar antes
-- de que llegue el movil. Los tres valores salen de las variables de arriba.
--
-- OJO: la columna fecha NO aparece en el INSERT. No hay que ponerla: la
-- pone la base de datos sola con CURRENT_TIMESTAMP (RN3). Si alguien anade
-- aqui una fecha a mano, el movil dejaria de mandar la fecha, que es justo
-- lo que no tiene que pasar.
-- ---------------------------------------------------------------------------
INSERT INTO mediciones (uuid_beacon, nombre_emisora, major, minor, tx_power, rssi)
VALUES (@uuidEjemplo, @nombreEjemplo, @majorEjemplo, @minorEjemplo, @txPowerEjemplo, -53);

INSERT INTO mediciones (uuid_beacon, nombre_emisora, major, minor, tx_power, rssi)
VALUES (@uuidEjemplo, @nombreEjemplo, @majorEjemplo, @minorEjemplo, @txPowerEjemplo, -61);

INSERT INTO mediciones (uuid_beacon, nombre_emisora, major, minor, tx_power, rssi)
VALUES (@uuidEjemplo, @nombreEjemplo, @majorEjemplo, @minorEjemplo, @txPowerEjemplo, -48);