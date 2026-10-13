# Arduino Design

*Componente Arduino: emite el iBeacon con el tipo y el valor de la medición.*
*Notación: Logical Design & Reverse Engineering Specification v3.*

## Component Design

El sketch de la plaquita (ESP32) anuncia un **iBeacon** cuyo `major`
codifica el tipo de medición y el valor. Se compone de los elementos siguientes.

### Elementos y responsabilidades

| Elemento | Tipo | Responsabilidad |
| :--- | :--- | :--- |
| `HolaMundoIBeacon.ino` | Sketch | `setup()` / `loop()`: coordinación general |
| `Publicador` (`Publicador.h`) | Lógica | Calcula el `major` y controla el anuncio |
| `EmisoraBLE` | Comunicación | Configura y emite el anuncio iBeacon |
| `PuertoSerie` | E/S | Mensajes por el monitor serie |

### Tipos y constantes

```text
TipoMedicion = { CO2 = 1, TEMPERATURA = 2, PRUEBA = 3 }

UUID      = "..."          // UUID del proyecto
MAJOR     = ( codigo << 8 ) | contador
VALOR_MINOR = 1234
INTERVALO_EMISION  = 5000 ms
DURACION_ANUNCIO   = 1000 ms
MS_ENTRE_MEDICIONES = 2000 ms
```

### Diagrama de comportamiento

```text
                 ---------------- Publicador ----------------
                 |
                 |   calcularMajor()
                 |
(codigo: N, contador: N) --> calcularMajor() --x--> major: N
                 |
                 |
                 |   emitirAnuncio() / pararAnuncio()
                 |
                 |   hayAnuncioEnCurso() <-- B
                 |
                 --------------------------------------------
                          |
                          v
                   EmisoraBLE.start()/stop()   -->  anuncio iBeacon
```

| Función | Tipo | Entrada | Salida |
| :--- | :--- | :--- | :--- |
| `calcularMajor()` | puro (`--x-->`) | `codigo: N, contador: N` | `major: N` |
| `emitirAnuncio()` | mutante (`-->`) | — | — |
| `pararAnuncio()` | mutante (`-->`) | — | — |
| `hayAnuncioEnCurso()` | solo lectura (`<--`) | — | `B` |

### Codificación del `major`

```text
major = ( codigo << 8 ) | contador
        // codigo: CO2=1 | TEMPERATURA=2 | PRUEBA=3
        // contador: 0..255, se reinicia al llegar a 256
```

## Design Clarifications

- El **valor** de la medición viaja en el `minor` (constante `VALOR_MINOR`, hoy
  `1234`); el **tipo** viaja codificado en el `major`.
- El anuncio dura `DURACION_ANUNCIO` y se repite cada `INTERVALO_EMISION`.
- El `contador` del `major` permite distinguir anuncios consecutivos del mismo tipo.
- La plaquita **no** conoce el servidor: solo emite; es la app Android quien
  interpreta el anuncio y lo sube.

## General Rules

- **Programming Language:** C++ (Arduino / ESP32).
- **Function/Method Headers:** cada función incluye su diseño lógico en un bloque de
  comentario delimitado por líneas discontinuas (`--------------------`):
  `entrada --> funcion() --> salida`.
- **Code Readability:** código claro y autoexplicativo; comentarios inline mínimos.
- **Automated Testing:** pruebas unitarias de la lógica pura
  (`test_publicador.cpp`), compilables con un compilador de C++, sin la plaquita.
