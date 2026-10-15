# GUI Design (web)

*Interfaz gráfica de usuario de la web: pantallas y elementos de presentación.*
*Notación: Logical Design & Reverse Engineering Specification v3.*

## Component Design

La GUI web es la capa de **presentación**: solo pinta lo que le da la lógica de
negocio del frontend (`frontend_business_logic_design.md`). No habla con el
servidor directamente.

### Pantallas

| Pantalla | Fichero | Descripción |
| :--- | :--- | :--- |
| **Listado de mediciones** | `index.html` | Tabla de las últimas mediciones con 4 columnas: **tipo, valor, nombre, fecha** |
| **Login legacy** | `Aplicacion.html` | Pantalla de ejemplo del tutorial (no forma parte del flujo real) |

### Elementos de presentación

```text
---------------------------- index.html ----------------------------
|                                                                  |
|   Título: "Mediciones del proyecto"                              |
|   -----------------------------------------------------------------
|   | nombre | tipo | valor | fecha |    <- tabla de mediciones
|   -----------------------------------------------------------------
|                                                                  |
|   (se recarga al pulsar el botón "Actualizar")                   |
--------------------------------------------------------------------
        ^
        |  datos ya adaptados ( Medicion: nombre, tipo, valor, fecha )
        |
   frontend_business_logic/consultarMediciones.js
```

### Estilos

| Fichero | Contenido |
| :--- | :--- |
| `estilos.css` | Estilos de la tabla y del layout |
| `variables.css` | Colores y tamaños reutilizables |

### Diccionario de tipos lógicos → HTML

| Tipo lógico | Representación HTML |
| :--- | :--- |
| `Text` | texto en celda (`<td>`) |
| `N` / `Z` | número en celda |
| `Tiempo` | texto de fecha ya formateado |
| `[ Medicion ]` | filas (`<tr>`) de la tabla |

### Diseño de las funciones de pintado (notación v3)

La GUI no define clases: solo **funciones de pintado** en `index.html`. Ninguna habla
con la red: usan lo que les entrega `consultarMediciones()`. A la izquierda, `-->`
apunta a la función cuando recibe datos y `<--` sale de ella cuando devuelve.

```text
                 ------------------ index.html (pintado) ------------------
                 |
                 |
texto: Text --> aviso() -->                    // escribe el <p id="estado">
                 |
                 |
valor: Text --> celda() --> td                 // una celda; null/undefined -> vacío
                 |
                 |
cuerpo: tbody, medicion: Medicion Vista --> pintarFila() -->
                 |
                 |
lista: [ Medicion Vista ] --> pintarMediciones() -->
                 |
                 |
mensaje: Text --> pintarError() -->
                 |
                 |
                 --> iniciar() -->              // al cargar: pide la lista al prompt 6
                 |
                 ---------------------------------------------------
```

| Función | Tipo | Entrada | Salida |
| :--- | :--- | :--- | :--- |
| `aviso()` | pintado (`-->`) | `texto: Text` | — (escribe en el `<p id="estado">`) |
| `celda()` | pintado (`-->`) | `valor: Text` | `td` |
| `pintarFila()` | pintado (`-->`) | `cuerpo: tbody, medicion: Medicion Vista` | — |
| `pintarMediciones()` | pintado (`-->`) | `lista: [ Medicion Vista ]` | — |
| `pintarError()` | pintado (`-->`) | `mensaje: Text` | — |
| `iniciar()` | arranque (`-->`) | — | — (llama a `consultarMediciones()`) |

## Design Clarifications

- `index.html` carga la lógica del frontend con rutas relativas
  (`../frontend_business_logic/*.js`) porque vive en `src/gui/`.
- La tabla **no tiene columna `id`**: la clave de cada fila es la natural compuesta
  `(id_plaquita, fecha)`, y la web no la enseña.
- `Aplicacion.html` es **legado** del tutorial; se conserva pero no es la pantalla
  del proyecto.

## General Rules

- **Programming Language:** HTML5 + CSS3 (con JavaScript para el pintado).
- **Function/Method Headers:** las funciones de pintado llevan su diseño lógico en
  un bloque de comentario delimitado por líneas discontinuas (`--------------------`).
- **Code Readability:** HTML claro y autoexplicativo; comentarios mínimos.
- **Automated Testing:** fuera del alcance del Sprint; la GUI se verifica a mano en
  el navegador.
