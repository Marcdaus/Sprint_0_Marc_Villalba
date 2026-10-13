# Android Design

*Componente Android (app BTLMARC): escucha los beacons, traduce cada anuncio a una
`Medicion` y la guarda en el servidor a través de su lógica de cliente.*
*Notación: Logical Design & Reverse Engineering Specification v3.*

## Component Design

La app Android escucha los anuncios iBeacon de la plaquita Arduino, se queda solo con
el beacon del proyecto, los traduce a una `Medicion` y la sube por HTTP al servidor.
Para no mezclar la pantalla con la red, el envío se hace a través de una **lógica de
cliente** (`LogicaFakeApp`) que actúa como **proxy** del backend.

### Clases y responsabilidades

| Clase | Tipo | Responsabilidad |
| :--- | :--- | :--- |
| `MainActivity` | GUI | Pantalla principal; arranca/para el servicio |
| `ServicioEscuharBeacons` | Servicio BLE | Escucha, filtra y **throttlea** las tramas; delega el guardado |
| `AnalizadorBeacons` | Utilidad / lógica pura | Filtra la trama, traduce tipo/valor y monta JSON y URL |
| `Medicion` | Modelo | Datos de una medición (dominio) |
| `LogicaFakeApp` | Lógica de cliente (proxy) | Guarda la `Medicion` hablando con el servidor REST |
| `PeticionarioREST` | Comunicación | Envía un `POST`/`GET` JSON al endpoint del servidor |
| `TramaIBeacon` | Utilidad | Parsea el `byte[]` de un iBeacon (uuid, major, minor, txPower) |
| `Utilidades` | Utilidad | Conversiones de bytes/enteros |

### Tipos de dominio

```text
Medicion = (
   nombre_emisora: Text,   // "GTI-3A"
   tipo_medicion:  Text,   // "CO2" | "temperatura" | "prueba"
   valor_medicion: Z       // valor del proyecto (el minor de la trama, hoy 1234)
)

ResultadoGuardado = ( ok: B, error: Text | vacío, clase: ClaseError | vacío )
ClaseError        = { VALIDACION, PERSISTENCIA }
```

### Lógica de cliente (proxy) — `LogicaFakeApp`

`LogicaFakeApp` es la **lógica fake del teléfono**: el equivalente móvil de
`src/frontend_business_logic/` de la web. Expone a la app **un subconjunto** de la
lógica de negocio del servidor (`business_logic_design.md`) con **firma idéntica**,
pero implementado como **proxy**: por dentro monta el JSON, la URL y hace el `POST`
al servidor en vez de tocar la base de datos.

```text
   -------------------- LogicaFakeApp (lógica de cliente) --------------------
   |
   |   // única operación del contrato (firma idéntica al backend)
   |
m: Medicion --> guardarMedicion() --> ResultadoGuardado
   |
   --------------------------------------------------------------------------
                |                              ^
                | HTTP (POST)                  | ResultadoGuardado
                v                              |
    src/communication/guardarMedicion.php
```

### Método (contrato)

| Método | Tipo | Entrada | Salida |
| :--- | :--- | :--- | :--- |
| `guardarMedicion( m )` | mutante (`-->`) | `Medicion` | `ResultadoGuardado` |

`Medicion` y `ResultadoGuardado` son los **mismos tipos de dominio** definidos en
`business_logic_design.md`; la app no inventa tipos nuevos. Como la red es asíncrona,
el `ResultadoGuardado` no se devuelve con `return`: se entrega por el callback
`RespuestaApp.onResultado(...)`.

### Identidad de firma con el backend

```text
   BACKEND  (business_logic_design.md):
      m: Medicion --> guardarMedicion() --> ResultadoGuardado

   APP      (este documento):
      m: Medicion --> guardarMedicion() --> ResultadoGuardado   // MISMA firma
```

Por la **regla del subconjunto**, la app solo declara la operación que usa (el
guardado); por la **regla del proxy**, la implementación llama al endpoint
`src/communication/guardarMedicion.php` en vez de a la base de datos.

### Flujo de datos (proxy)

```text
   guardarMedicion( m )
        |
        | 1. construirPeticion(m): monta el JSON y la URL (constantes de AnalizadorBeacons)
        | 2. POST a src/communication/guardarMedicion.php vía PeticionarioREST
        | 3. interpretarRespuesta( codigo, cuerpo ):
        |       200 -> ResultadoGuardado( ok=true )
        |       400 -> ResultadoGuardado( ok=false, clase='validacion', error )
        |       500 -> ResultadoGuardado( ok=false, clase='persistencia', error )
        |       otro/red -> ResultadoGuardado( ok=false, clase='persistencia', error )
        | 4. RespuestaApp.onResultado( resultado )
```

### Diagrama de comportamiento (servicio)

```text
   (BleScanner)
        |
        v   byte[] (ScanRecord)
   AnalizadorBeacons.motivoDescarte() --> Text     // filtro: ¿es nuestro beacon? (null = sí)
        |
        v
   Medicion m = new Medicion( tib, nombre )
        |
        v
   AnalizadorBeacons.debeEnviarMedicion() --> B    // throttle: no repetir demasiado seguido
        |
        | true
        v
   LogicaFakeApp.guardarMedicion( m ) --> ResultadoGuardado
        |
        v
   src/communication/guardarMedicion.php --> 200/400/500
```

### Configuración

`AnalizadorBeacons` define el bloque `VARIABLES MODIFICABLES` con `UUID_BEACON`,
`NOMBRE_EMISORA`, `IP_SERVIDOR`, `PUERTO_SERVIDOR`, `RUTA_GUARDAR`
(`src/communication/guardarMedicion.php`) y `RUTA_RECUPERAR`
(`src/communication/recuperarMedicion.php`). Es el único punto donde se fija a dónde
apunta la app.

## Design Clarifications

- `LogicaFakeApp` es la **lógica de cliente** de la app: por la **regla del
  subconjunto** solo declara `guardarMedicion()` (la app solo necesita enviar), y por
  la **regla del proxy** encapsula toda la comunicación con el servidor.
- El **servicio BLE** (`ServicioEscuharBeacons`) no sabe de JSON, direcciones ni
  códigos de estado: solo llama a `guardarMedicion()` y recibe un `ResultadoGuardado`.
  La dependencia se inyecta por `crearLogicaApp()` (método sobrescribible en pruebas).
- Las funciones `construirPeticion()`, `interpretarRespuesta()` y `leerError()` son
  **detalles de implementación** (no forman parte del contrato); son
  puras, no hacen red y se pueden probar en el PC.
- El **throttle** (`debeEnviarMedicion`) sigue en el servicio, no en el proxy: es una
  política de envío del escaneo, no una regla de guardado. El backend tampoco limita la
  frecuencia.
- El resultado se expresa siempre con **tipos de dominio** (`ok`, `error`,
  `clase`); la traducción desde el código HTTP es un detalle interno del proxy.
- La app **no** guarda mediciones localmente: cada medición se sube al servidor.

## General Rules

- **Programming Language:** Java (Android SDK).
- **Function/Method Headers:** cada método incluye su diseño lógico en un bloque de
  comentario delimitado por líneas discontinuas (`--------------------`):
  `entrada --> metodo() --> salida`.
- **Code Readability:** código claro y autoexplicativo; comentarios inline mínimos.
- **Automated Testing:** pruebas unitarias de la lógica pura (`MedicionTest.java` y
  `LogicaFakeAppTest.java` en el host) y una prueba instrumentada del guardado con un
  `PeticionarioREST` falso, sin necesidad de un dispositivo BLE.
