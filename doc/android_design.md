# Android Design

*Componente Android (app BTLMARC): escucha los beacons y sube las mediciones al servidor.*
*Notación: Logical Design & Reverse Engineering Specification v3.*

## Component Design

La app Android escucha los anuncios iBeacon de la plaquita Arduino, los traduce a
una `Medicion` y la sube por HTTP al servidor. Se compone de las clases siguientes.

### Clases y responsabilidades

| Clase | Tipo | Responsabilidad |
| :--- | :--- | :--- |
| `MainActivity` | GUI | Pantalla principal; arranca/para el servicio |
| `ServicioEscuharBeacons` | Servicio BLE | Escucha, filtra y **throttlea** las tramas; envía las mediciones |
| `AnalizadorBeacons` | Lógica | Transforma una trama en una `Medicion` y decide si se envía |
| `Medicion` | Modelo | Datos de una medición (dominio) |
| `PeticionarioREST` | Comunicación | Envía un `POST` JSON al endpoint del servidor |
| `TramaIBeacon` | Utilidad | Parsea el `byte[]` de un iBeacon (uuid, major, minor, txPower) |
| `Utilidades` | Utilidad | Conversiones de bytes/enteros y distancia |

### Tipos de dominio

```text
Medicion = (
   nombre_emisora: Text,   // "GTI-3A"
   tipo_medicion:  Text,   // "CO2" | "temperatura" | "prueba"
   valor_medicion: Z       // valor del proyecto
)

TipoMedicion = { CO2, TEMPERATURA, PRUEBA }
```

### Diagrama de comportamiento

```text
   (BleScanner)
        |
        v   byte[] (ScanRecord)
   trama: byte[] --> AnalizadorBeacons.analizarTrama() --> ( meters: N, trama: TramaIBeacon )
        |
        v
   AnalizadorBeacons.debeEnviarMedicion() --> B     // throttle: no repetir demasiado seguido
        |
        | true
        v
   AnalizadorBeacons.getMedicion() --> Medicion
        |
        v
   ServicioEscuharBeacons --> PeticionarioREST.peticionREST() --> 200/400/500
```

| Función | Tipo | Entrada | Salida |
| :--- | :--- | :--- | :--- |
| `analizarTrama()` | puro (`--x-->`) | `byte[]` | `TramaIBeacon` |
| `debeEnviarMedicion()` | solo lectura (`<--`) | — | `B` |
| `getMedicion()` | solo lectura (`<--`) | — | `Medicion` |
| `peticionREST()` | mutante (`-->`) | `Text, Text` | `N` (código) |

### Constantes de conexión

`AnalizadorBeacons` define `IP_SERVIDOR`, `PUERTO_SERVIDOR`, `RUTA_GUARDAR`
(`src/communication/guardarMedicion.php`) y `RUTA_RECUPERAR`
(`src/communication/recuperarMedicion.php`).

## Design Clarifications

- `ServicioEscuharBeacons` aplica un **throttle** (`debeEnviarMedicion`) para no
  inundar el servidor con el mismo dato repetido.
- La trama iBeacon no se guarda entera: solo se extrae lo necesario para construir
  la `Medicion`.
- La `Medicion` se serializa a JSON con las claves que espera el servidor
  (`nombre_emisora`, `tipo_medicion`, `valor_medicion`).
- La app **no** guarda mediciones localmente; cada medición se sube al servidor.

## General Rules

- **Programming Language:** Java (Android SDK).
- **Function/Method Headers:** cada método incluye su diseño lógico en un bloque de
  comentario delimitado por líneas discontinuas (`--------------------`):
  `entrada --> metodo() --> salida`.
- **Code Readability:** código claro y autoexplicativo; comentarios inline mínimos.
- **Automated Testing:** pruebas unitarias de la lógica pura
  (`MedicionTest.java`) con JUnit, sin necesidad de un dispositivo BLE.
