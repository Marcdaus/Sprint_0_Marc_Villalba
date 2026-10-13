# Sprint_0_Marc_Villalba

**Autor:** Marc Villalba Meseguer — 3º GTI
**Notación:** *Logical Design & Reverse Engineering v3*

## Descripción del proyecto

Una medición viaja de punta a punta por toda la arquitectura del proyecto:

1. La plaquita **Arduino** (Adafruit Feather nRF52840) emite un **iBeacon** cuyo
   *tipo* de medición viaja codificado en los bits altos del campo `major`
   (`major = (código << 8) | contador`) y cuyo *valor* viaja en el campo `minor`.
2. La app **Android** (Java) escucha los beacons, se queda solo con el del
   proyecto (`uuid "EPSG-GTI-MARC-3A"`), traduce el código del `major` a texto
   (`"CO2" | "temperatura" | "prueba"`) y sube el tipo y el valor al servidor.
3. La **API REST** (PHP) recibe la petición y delega en la **lógica de negocio**.
4. La lógica de negocio valida (RN1..RN5) y **persiste** la medición en la base
   de datos **MySQL** (`proyecto_beacon.mediciones`). Es 100 % independiente del
   transporte: no conoce HTTP ni JSON.
5. La **web** (HTML/CSS/JS) lee las últimas mediciones a través de su propia
   lógica de cliente (*fake* / proxy) y las muestra.

El diseño previo de cada componente está en `doc/`. La implementación coincide
con esos planos.

## Estructura del repositorio

```
doc/                          los diseños (xxx_design.md), uno por componente
src/communication/            endpoints REST (PHP)
src/business_logic/           lógica de negocio (PHP)
src/database/                 DDL de la base de datos (SQL)
src/frontend_business_logic/  lógica del cliente web (JS, fake/proxy)
src/gui/                      páginas web (HTML/CSS)
src/pruebas/                  pruebas automáticas (PHP y JS)
src/android/                  app Android (Java, Gradle)
src/arduino/                  sketch Arduino (C++)
Agente-Revisar-Sprint/        agente revisor y especificaciones de contexto
COMANDOS.txt                  chuleta de todos los comandos del proyecto
```

## Requisitos

- **XAMPP** (PHP 8 + MySQL) — servidor y base de datos.
- **Node.js** — para las pruebas de la lógica del cliente web.
- **Arduino IDE** con la librería *Adafruit nRF52 BLE* (para la plaquita).
- **Android Studio** (para compilar e instalar la app en el teléfono).

## Despliegue

### 1. Base de datos

Importar `src/database/crearBaseDeDatos.sql` desde phpMyAdmin (pestaña
*Importar*) o por consola:

```
C:\xampp\mysql\bin\mysql.exe -u root < src\database\crearBaseDeDatos.sql
```

El script crea la base de datos `proyecto_beacon` y la tabla `mediciones`.

### 2. Servidor PHP

Desde la raíz del proyecto:

```
php -S 0.0.0.0:8080 -t .
```

Si `php` no está en el PATH:

```
C:\xampp\php\php.exe -S 0.0.0.0:8080 -t .
```

### 3. Web

Con el servidor arrancado, abrir:

```
http://localhost:8080/src/gui/index.html
```

### 4. App Android

Poner la **IP actual del PC** en `IP_SERVIDOR`
(`src/android/BTLMARC/app/src/main/java/com/example/btlmarc/AnalizadorBeacons.java`)
y volver a compilar/instalar. La IP se consulta con:

```
Get-NetIPAddress -AddressFamily IPv4 | Where-Object { $_.IPAddress -notlike '127.*' -and $_.IPAddress -notlike '169.254.*' } | Select-Object IPAddress,InterfaceAlias
```

### 5. Plaquita Arduino

Abrir `src/arduino/HolaMundoIBeacon/HolaMundoIBeacon.ino` y cargarlo en la
Adafruit Feather nRF52840.

Los comandos completos (arranque, parada, IP, direcciones) están en `COMANDOS.txt`.

## Cómo ejecutar los tests

Todos se lanzan desde la raíz del proyecto y **no usan frameworks** (ni PHPUnit
ni Jest): cada fichero imprime `"N pruebas, M fallos"` y devuelve código de
salida `0` si todo va bien.

| Prueba | Comando | Resultado esperado |
| :--- | :--- | :--- |
| Lógica de negocio | `C:\xampp\php\php.exe -f src\pruebas\testLogicaDeNegocio.php` | `66 pruebas, 0 fallos` |
| Base de datos (necesita MySQL) | `C:\xampp\php\php.exe -f src\pruebas\testBaseDeDatos.php` | `28 pruebas, 0 fallos` |
| Auxiliares de comunicación | `C:\xampp\php\php.exe -f src\pruebas\testAuxiliaresRest.php` | `19 pruebas, 0 fallos` |
| Lógica del cliente web | `"C:\Program Files\nodejs\node.exe" src\pruebas\pruebasLogicaFake.js` | `33 pruebas, 0 fallos` |
| Endpoints REST por HTTP | `C:\xampp\php\php.exe -f src\pruebas\testEndpointsRest.php` | (necesita el servidor y MySQL) |
| App Android (host) | `cd src\android\BTLMARC` y `gradlew.bat :app:testDebugUnitTest` | 0 fallos |
| App Android (instrumentada) | `gradlew.bat :app:connectedDebugAndroidTest` | 0 fallos |

Detalle y trazabilidad de cada prueba en `doc/pruebas_design.md`.
