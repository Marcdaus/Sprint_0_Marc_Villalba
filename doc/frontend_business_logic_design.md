# Frontend Business Logic Design (web)

*Lógica de negocio del cliente web: pide las mediciones al servidor y las adapta para la pantalla.*
*Notación: Logical Design & Reverse Engineering Specification v3.*

## Component Design

La lógica de negocio del frontend es el **intermediario** entre la interfaz
(`gui/`) y el servidor REST (`communication/`). Expone a la interfaz un subconjunto
de la lógica de negocio del servidor, con **firmas idénticas**: una operación de
lectura de mediciones.

```text
   ---------------------- LógicaFrontend ----------------------
   |
   |   // operación de SOLO LECTURA (misma firma que el backend)
   |
   |   recuperarMedicion() --> [ Medicion ]            (proxy: pide al servidor)
   |
   |   construirURL()        <-- Text                 (compone la dirección)
   |   adaptarMediciones()   <-- [ Medicion ]         (renombra campos)
   |   formatearFecha()      <-- Text                 (fecha legible)
   |
   ------------------------------------------------------------
                    |                     ^
                    | HTTP (XHR)          | Medicion[]
                    v                     |
        src/communication/recuperarMedicion.php
```

### Funciones

| Función | Tipo | Entrada | Salida | Misión |
| :--- | :--- | :--- | :--- | :--- |
| `recuperarMedicion()` | solo lectura (`<--`) | — | `[ Medicion ]` | Pide al servidor la lista de mediciones (proxy) |
| `construirURL( host, puerto, ruta )` | puro (`--x-->`) | `Text, N, Text` | `Text` | Monta la dirección absoluta del endpoint |
| `adaptarMediciones( datos )` | puro (`--x-->`) | `[ MedicionServidor ]` | `[ Medicion ]` | Renombra `nombre_emisora→nombre`, `tipo_medicion→tipo`, `valor_medicion→valor` |
| `formatearFecha( fecha )` | puro (`--x-->`) | `Text` | `Text` | Pasa `aaaa-mm-dd hh:mm:ss` a formato legible |

### Identidad de firma con el backend

La operación de lectura del frontend coincide con la del backend
(`business_logic_design.md`), aplicando la **regla del subconjunto** (el cliente
expone igual o menos, nunca más) y la **regla del proxy** (la implementación
llama al servicio remoto en vez de tocar la base de datos):

```text
   BACKEND  (business_logic_design.md):
      f: FiltroMediciones --> recuperarMedicion() <-- [ Medicion ]

   FRONTEND (este documento):
      recuperarMedicion() --> [ Medicion ]      // subconjunto sin filtros; proxy HTTP
```

`Medicion` es el **mismo tipo lógico** en ambos lados. El cliente no usa
`FiltroMediciones` (es un subconjunto) y obtiene los datos por HTTP del endpoint
`src/communication/recuperarMedicion.php`.

### Flujo de datos

```text
   construirURL( HOST, PUERTO, RUTA_RECUPERAR ) --> url: Text
        |
   XHR GET url  --> { "mediciones": [ {id, nombre_emisora, tipo_medicion, valor_medicion, fecha} ] }
        |
   adaptarMediciones( mediciones ) --> [ {id, nombre, tipo, valor, fecha} ]
        |
   formatearFecha( fecha ) --> texto legible  ->  (se pinta en gui/)
```

### Configuración

`configuracion.js` es el **único punto** donde se fijan las constantes de conexión:
`HOST`, `PUERTO` y `RUTA_RECUPERAR` (`"src/communication/recuperarMedicion.php"`).

## Design Clarifications

- El frontend expone **solo lectura**: no hay `guardarMedicion()` en el cliente,
  porque la subida de mediciones la hace la app Android.
- Las funciones puras (`construirURL`, `adaptarMediciones`, `formatearFecha`) no
  hacen red: se pueden probar con Node sin servidor.
- `recuperarMedicion()` usa `XMLHttpRequest`; es la única función que habla con el
  servidor.
- Los ficheros `diHola.js` y `hacerLogin.js` son **legado** de un tutorial: no
  forman parte del subconjunto de negocio del proyecto.

## General Rules

- **Programming Language:** JavaScript (ES5, sin frameworks; se ejecuta también en
  Node para las pruebas).
- **Function/Method Headers:** cada función incluye su diseño lógico en un bloque de
  comentario delimitado por líneas discontinuas (`--------------------`):
  `entrada --> funcion() --> salida`.
- **Code Readability:** código claro y autoexplicativo; comentarios inline mínimos.
- **Automated Testing:** pruebas unitarias de las funciones puras
  (`pruebasLogicaFake.js`, con Node), sin necesidad del servidor.
