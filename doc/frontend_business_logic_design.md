# Frontend Business Logic Design (web)

*Lógica de negocio del cliente web: pide las mediciones al servidor y las adapta para la pantalla.*
*Notación: Logical Design & Reverse Engineering Specification v3.*

## Component Design

La lógica de negocio del frontend es el **intermediario** entre la interfaz
(`gui/`) y el servidor REST (`communication/`). Expone a la interfaz **un
subconjunto** de la lógica de negocio del servidor, con **firma idéntica**: la
misma operación de lectura que el backend (`business_logic_design.md`), pero
implementada como **proxy** (por dentro llama al servicio remoto en vez de tocar la
base de datos).

```text
   ---------------------- LógicaFrontend ----------------------
   |
   |   // operación de SOLO LECTURA (firma idéntica al backend)
   |
f: FiltroMediciones --> recuperarMedicion() <-- ResultadoLista
   |
   ------------------------------------------------------------
                |                          ^
                | HTTP (XHR)               | ResultadoLista
                v                          |
    src/communication/recuperarMedicion.php
```

### Método (contrato)

| Método | Tipo | Entrada | Salida |
| :--- | :--- | :--- | :--- |
| `recuperarMedicion( f )` | solo lectura (`<--`) | `FiltroMediciones` | `ResultadoLista` |

`FiltroMediciones` y `ResultadoLista` son los **mismos tipos de dominio** definidos en
`business_logic_design.md`; el cliente no inventa tipos nuevos.

### Identidad de firma con el backend

```text
   BACKEND  (business_logic_design.md):
      f: FiltroMediciones --> recuperarMedicion() <-- ResultadoLista

   FRONTEND (este documento):
      f: FiltroMediciones --> recuperarMedicion() <-- ResultadoLista   // MISMA firma
```

Por la **regla del subconjunto**, el cliente solo declara las operaciones que usa la
interfaz (aquí, la lectura); por la **regla del proxy**, la implementación llama al
endpoint `src/communication/recuperarMedicion.php` en vez de a la base de datos.

### Flujo de datos (proxy)

```text
   recuperarMedicion( f )
        |
        | 1. montar la dirección con las constantes de configuracion.js
        | 2. GET (XHR) a src/communication/recuperarMedicion.php
        |       -> { "mediciones": [ {id_plaquita, tipo_medicion, valor_medicion, fecha} ] }
        | 3. adaptar los campos (id_plaquita->nombre, tipo_medicion->tipo, valor_medicion->valor)
        |       -> ResultadoLista( ok=true, mediciones )
        | 4. (red caída / error del servidor) -> ResultadoLista( ok=false, error )
```

### Configuración

`configuracion.js` es el **único punto** donde se fijan las constantes de conexión:
`HOST`, `PUERTO` y `RUTA_RECUPERAR` (`"src/communication/recuperarMedicion.php"`).

## Design Clarifications

- El frontend expone **solo lectura**: no hay `guardarMedicion()` en el cliente,
  porque la subida de mediciones la hace la app Android.
- `recuperarMedicion( f )` encapsula toda la comunicación: es el **único** método del
  contrato y el único que usa `XMLHttpRequest`. La interfaz (`gui/`) nunca habla con
  la red.
- Las funciones `construirURL()`, `adaptarMediciones()` y `formatearFecha()` son
  **detalles de implementación**, no forman parte del contrato (igual que las
  funciones auxiliares del backend). Son puras, no hacen red y se pueden probar con
  Node sin servidor.
- El resultado se entrega a la interfaz con los **mismos campos de dominio** del
  backend (`ok`, `mediciones`, `error`, `clase`); renombrar los campos de la tabla
  concreta es un detalle interno del proxy.

## General Rules

- **Programming Language:** JavaScript (ES5, sin frameworks; se ejecuta también en
  Node para las pruebas).
- **Function/Method Headers:** cada función incluye su diseño lógico en un bloque de
  comentario delimitado por líneas discontinuas (`--------------------`):
  `entrada --> funcion() --> salida`.
- **Code Readability:** código claro y autoexplicativo; comentarios inline mínimos.
- **Automated Testing:** pruebas unitarias de las funciones puras
  (`pruebasLogicaFake.js`, con Node), sin necesidad del servidor.
