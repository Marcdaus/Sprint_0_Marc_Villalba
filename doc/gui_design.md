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
| **Listado de mediciones** | `index.html` | Tabla de las últimas mediciones con 5 columnas: **id, nombre, tipo, valor, fecha** |
| **Login legacy** | `Aplicacion.html` | Pantalla de ejemplo del tutorial (no forma parte del flujo real) |

### Elementos de presentación

```text
---------------------------- index.html ----------------------------
|                                                                  |
|   Título: "Mediciones del proyecto"                              |
|   -----------------------------------------------------------------
|   | id | nombre | tipo | valor | fecha |    <- tabla de mediciones
|   -----------------------------------------------------------------
|                                                                  |
|   (se recarga al pulsar el botón "Actualizar")                   |
--------------------------------------------------------------------
        ^
        |  datos ya adaptados ( Medicion: id, nombre, tipo, valor, fecha )
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

## Design Clarifications

- `index.html` carga la lógica del frontend con rutas relativas
  (`../frontend_business_logic/*.js`) porque vive en `src/gui/`.
- La columna `id` muestra el identificador real de la base de datos.
- `Aplicacion.html` es **legado** del tutorial; se conserva pero no es la pantalla
  del proyecto.

## General Rules

- **Programming Language:** HTML5 + CSS3 (con JavaScript para el pintado).
- **Function/Method Headers:** las funciones de pintado llevan su diseño lógico en
  un bloque de comentario delimitado por líneas discontinuas (`--------------------`).
- **Code Readability:** HTML claro y autoexplicativo; comentarios mínimos.
- **Automated Testing:** fuera del alcance del Sprint; la GUI se verifica a mano en
  el navegador.
