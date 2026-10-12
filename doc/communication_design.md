# Communication Design (servidor)

*API REST del servidor: traduce peticiones HTTP a llamadas de la lógica de negocio.*
*Notación: Logical Design & Reverse Engineering Specification v3.*

## Component Design

La capa de comunicación es la **frontera** con el exterior. Recibe HTTP, extrae y
valida los datos, llama a la capa de negocio (`business_logic_design.md`) y
construye la respuesta (código de estado + JSON). No contiene reglas de negocio.

```text
   (cliente: app Android o web)
              |
              v   HTTP
   ------------ Communication (endpoints) ------------
   |                                                 |
   |  guardarMedicion.php       (POST)               |
   |  recuperarMedicion.php     (GET)                |
   |                                                 |
   ---------------------------------+----------------
              |                     |
              | HTTP<->dominio      | HTTP<->dominio
              v                     v
        LogicaDeNegocio.guardarMedicion() / recuperarMedicion()
              |                     |
              v                     v
        ------------------ mediciones ------------------
```

### Endpoints

| Método | Ruta | Entrada | Salida | Llama a |
| :--- | :--- | :--- | :--- | :--- |
| `POST` | `/src/communication/guardarMedicion.php` | cuerpo JSON `{ "nombre_emisora", "tipo_medicion", "valor_medicion" }` | `200` `{ "resultado": true, "id": N }` | `guardarMedicion()` |
| `GET` | `/src/communication/recuperarMedicion.php` | query `?desde&hasta&tipo_medicion&valor_medicion` | `200` `{ "mediciones": [ { "id","nombre_emisora","tipo_medicion","valor_medicion","fecha" } ] }` | `recuperarMedicion()` |

### Códigos de estado

| Situación | Código | Cuerpo |
| :--- | :--- | :--- |
| Operación correcta | `200 OK` | `{ "resultado": true, "id": N }` / `{ "mediciones": [ ... ] }` |
| Datos inválidos (JSON mal formado, tipo no permitido, valor ausente…) | `400 Bad Request` | `{ "resultado": false, "error": "..." }` |
| Fallo de la base de datos | `500 Internal Server Error` | `{ "resultado": false, "error": "..." }` |

### Traducción HTTP ↔ dominio

```text
guardarMedicion.php (POST)
   |
   | 1. leer cuerpo crudo y parsearJSON( cuerpo ) --> objeto
   |        ? JSON mal formado -> ResponderHTTP( 400, {ok:false, error} )
   |
   | 2. extraerCampos( objeto ) --> m: Medicion
   |
   | 3. LogicaDeNegocio.guardarMedicion( m ) --> ResultadoGuardado
   |        ? ok=false  -> ResponderHTTP( 400, {ok:false, error} )
   |        ? ok=true   -> ResponderHTTP( 200, {resultado:true, id} )

recuperarMedicion.php (GET)
   |
   | 1. extraerFiltros( $_GET ) --> f: FiltroMediciones
   |
   | 2. LogicaDeNegocio.recuperarMedicion( f ) --> ResultadoLista
   |        ? ok=false  -> ResponderHTTP( 500, {ok:false, error} )
   |        ? ok=true   -> ResponderHTTP( 200, {mediciones:[...]} )
```

### Funciones de la capa

| Función | Entrada | Salida |
| :--- | :--- | :--- |
| `guardarMedicion()` | `POST` (cuerpo JSON) | respuesta HTTP |
| `recuperarMedicion()` | `GET` (query) | respuesta HTTP |
| `parsearJSON( cuerpo: Text )` | texto del cuerpo | `objeto` (o error) |
| `extraerCampos( objeto )` | objeto JSON | `Medicion` |
| `extraerFiltros( query )` | `$_GET` | `FiltroMediciones` |
| `ResponderHTTP( codigo: N, objeto )` | código + objeto | cabeceras + JSON |

## Design Clarifications

- Cada fichero `.php` de `communication/` es un **endpoint autónomo**: arranca, hace
  su trabajo y responde. Ningún endpoint implementa reglas de negocio; toda
  validación de dominio vive en `business_logic_design.md`.
- El **canal de comunicación web es HTTP con JSON**; la capa de negocio no lo
  conoce. La conversión de `(codigo, cuerpo)` a dominio y viceversa se hace aquí.
- Un fallo de dominio por datos inválidos se traduce a `400`; un fallo de la base de
  datos se traduce a `500`.
- Las rutas son relativas a la raíz del servidor (`Sprint_0_Marc_Villalba`), por eso
  empiezan por `src/communication/`.

## General Rules

- **Programming Language:** PHP 8 (sin frameworks); salida en JSON.
- **Function/Method Headers:** cada endpoint y función auxiliar documenta su diseño
  lógico en un bloque de comentario delimitado por líneas discontinuas
  (`--------------------`): `entrada --> funcion() --> salida`.
- **Code Readability:** código claro y autoexplicativo; comentarios inline mínimos.
- **Automated Testing:** pruebas de las funciones auxiliares de comunicación
  (`testAuxiliaresRest.php`) y pruebas de los endpoints HTTP reales
  (`testEndpointsRest.php`), que arrancan el servidor y comprueban códigos y cuerpos.
