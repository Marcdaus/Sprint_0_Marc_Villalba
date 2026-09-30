# PROMPT 2 — Lógica del teléfono (Android)

Implementación del **PROMPT 2** sobre los tres esqueletos Android del proyecto
(`BTLEAlumnos2021/`, `PeticionarioREST/` y `HolaMundoServicio2021/`).

Los esqueletos originales están **sin tocar**: todo lo de este prompt está aquí,
copiado y completado, dentro de `Sprint_0_Marc_Villalba\android\`.

## Dónde está cada cosa

```
android\
└── app\
    ├── build.gradle-APP                 <- app/build.gradle (con las dependencias de las pruebas)
    └── src\
        ├── main\
        │   ├── AndroidManifest.xml      <- con permisos BLE/Internet y HTTP sin cifrar
        │   ├── java\
        │   │   ├── org\jordi\btlealumnos2021\
        │   │   │   ├── Utilidades.java          (copia sin cambios)
        │   │   │   ├── TramaIBeacon.java        (copia sin cambios)
        │   │   │   ├── Medicion.java            (NUEVO)
        │   │   │   └── AnalizadorBeacons.java   (NUEVO)
        │   │   ├── org\jordi\clienterestandroid\
        │   │   │   └── PeticionarioREST.java     (copia sin cambios)
        │   │   └── org\jordi\holamundoservicio\
        │   │       ├── MainActivity.java         (copia + comentario del hook de pantalla)
        │   │       └── ServicioEscuharBeacons.java  (COMPLETADO)
        │   └── res\layout\activity_main.xml (copia sin cambios)
        └── test\
            ├── java\
            │   ├── org\jordi\btlealumnos2021\
            │   │   ├── TramasDePrueba.java       (ayudante: monta tramas iBeacon)
            │   │   ├── MedicionTest.java
            │   │   └── AnalizadorBeaconsTest.java
            │   └── org\jordi\holamundoservicio\
            │       └── ServicioEscuharBeaconsTest.java
            └── resources\mockito-extensions\org.mockito.plugins.MockMaker
```

## Qué hace

1. `ServicioEscuharBeacons` arranca el escáner BLE antiguo
   (`BluetoothLeScanner.startScan`).
2. Por cada beacon llama a `AnalizadorBeacons.esNuestroBeacon()`: sólo pasa el
   nuestro (companyID `0x004C` de Apple + uuid `EPSG-GTI-MARC-3A`).
3. Con el nuestro, arma una `Medicion`, la pasa por `construirJSON()` y manda un
   **POST** a `http://192.168.1.5:8080/rest/guardarMedicion.php`.
4. Deja un **hook de pantalla** (`mostrarEnPantalla`) para el PROMPT 8.

## Dónde se cambian las cosas

Todo lo variable está al principio de `AnalizadorBeacons.java`:

```java
// ====== VARIABLES MODIFICABLES ======
public static final String UUID_BEACON     = "EPSG-GTI-MARC-3A";
public static final String NOMBRE_EMISORA  = "GTI-3A";
public static final String IP_SERVIDOR     = "192.168.1.5";   // <- la IP de tu PC
public static final int    PUERTO_SERVIDOR = 8080;
public static final String RUTA_GUARDAR    = "rest/guardarMedicion.php";
public static final int    RSSI_MINIMO     = -127;           // -127 = no descartar nada
// ======================================
```

**Ojo con `IP_SERVIDOR`**: `192.168.1.5` es un valor de ejemplo. Cámbialo por la
IP real del PC donde esté el servidor (PROMPT 3).

El `minor` **no** está en ese bloque a propósito: se lee de la trama del beacon en
cada lectura (`interpretarValor()`), nunca es una constante.

## Pruebas

**Están escritas, pero NO se han ejecutado** (las corre el dueño del proyecto).

```
gradlew.bat test
```

Ver el bloque `// ===== PRUEBAS =====` al final de cada fichero de prueba: ahí está
el comando y el resultado esperado de cada caso.

## Lo que falta para tener un proyecto Android que compile

Los tres esqueletos son ficheros sueltos, no un proyecto Gradle. Para montarlo en
Android Studio hay que:

1. Crear un proyecto vacío y copiar `app\src\main\java` dentro de
   `app\src\main\java` de ese proyecto.
2. Cambiar `app\build.gradle-APP` por el `app\build.gradle` del proyecto nuevo
   (manteniendo `applicationId`, `minSdkVersion 28` y `targetSdkVersion 29`).
3. Poner este `AndroidManifest.xml` en `app\src\main\AndroidManifest.xml`.
4. Dejar el `activity_main.xml` de `HolaMundoServicio2021` (el que tiene los
   botones de arrancar/parar el servicio), porque es el que usa `MainActivity`.
5. Añadir un `mipmap/ic_launcher` o quitar el icono del manifest.

No se ha hecho este paso porque en esta máquina no hay Android SDK ni Gradle
(`ANDROID_HOME` vacío), así que cualquier cosa escrita aquí no se podría comprobar.
