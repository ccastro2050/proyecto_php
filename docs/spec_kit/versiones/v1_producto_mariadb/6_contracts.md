# Contratos HTTP — Versión 1: producto + MariaDB (PHP puro)

> **Versión 1** · Base: `http://localhost:8022`. En PHP puro no hay Swagger
> automático: **este documento ES el contrato publicado** (y el endpoint `/`
> lo enlaza).

---

## 0. Convenciones (y el HTTP que la v1 enseña)

La v1 usa **los cinco verbos** y las **tres vías de envío de datos**:

| Vía | Dónde viaja | Ejemplo | Se usa para |
|---|---|---|---|
| Parámetro de **ruta** | en la URL, parte del camino | `/api/producto/PR001` | Identificar UN recurso |
| **Query string** | en la URL, después de `?` | `/api/producto?limite=3` | Opciones de la consulta |
| **Body** JSON | en el cuerpo de la petición | `{"nombre": "…", "stock": 5}` | Los datos del recurso (POST/PUT/PATCH) |

| Verbo | Semántica | Endpoint en v1 |
|---|---|---|
| GET | Leer (nunca modifica) | listar · obtener por código · diagnóstico |
| POST | Crear un recurso nuevo | crear producto |
| PUT | **Reemplazar completo** (todos los campos) | reemplazar producto |
| PATCH | **Actualizar parcial** (solo los enviados) | actualizar campos sueltos |
| DELETE | Eliminar | eliminar producto |

- Todas las respuestas son JSON (`Content-Type: application/json`).
- Errores SIEMPRE con esta envoltura:

```json
{ "estado": 404, "mensaje": "Producto no encontrado.", "detalle": "..." }
```

| Origen | HTTP |
|---|---|
| Body inválido según la **validación del controlador** | **422** con `errores: [ "...", ... ]` |
| Regla de negocio (`limite ≤ 0`, PATCH con body vacío) | 400 |
| Código inexistente (`NoEncontradoExcepcion`) | 404 |
| Error del motor (PK duplicada, BD caída — `PDOException`) | 500 con el mensaje en `detalle` |

## 1. `GET /api/producto` — Listar (query string)

Query param opcional: `limite` (entero > 0, default 1000).

```
GET /api/producto
→ 200 { "tabla": "producto", "limite": 1000, "total": 8,
        "datos": [ { "codigo": "PR001", "nombre": "Laptop Lenovo IdeaPad",
                     "stock": 17, "valorunitario": 2500000 }, … ] }

GET /api/producto?limite=3
→ 200 con exactamente 3 productos (total: 3)
→ 400 si limite ≤ 0
→ 204 (cuerpo vacío) si no hay productos
```

## 2. `GET /api/producto/{codigo}` — Obtener uno (parámetro de ruta)

```
GET /api/producto/PR001
→ 200 { "codigo": "PR001", "nombre": "Laptop Lenovo IdeaPad",
        "stock": 17, "valorunitario": 2500000 }
→ 404 { "estado": 404, "mensaje": "Producto no encontrado.",
        "detalle": "No existe un producto con codigo = PR999" }
```

## 3. `POST /api/producto` — Crear (body completo)

Body (todos obligatorios; los valida el controlador):

```
POST /api/producto
body { "codigo": "PR009", "nombre": "Webcam Logitech", "stock": 5, "valorunitario": 120000 }
→ 200 { "estado": 200, "mensaje": "Producto creado exitosamente." }
→ 422 { "estado": 422, "mensaje": "Datos inválidos.",
        "errores": [ "El campo stock debe ser un entero mayor o igual a 0." ] }
→ 500 si el código ya existe (error del motor en detalle)
```

## 4. `PUT /api/producto/{codigo}` — Reemplazar completo

Body: `nombre`, `stock`, `valorunitario` — **todos obligatorios** (el código
va en la ruta y no cambia). Valida `validarReemplazo`.

```
PUT /api/producto/PR009
body { "nombre": "Webcam Logitech C920", "stock": 10, "valorunitario": 150000 }
→ 200 { "estado": 200, "mensaje": "Producto reemplazado exitosamente.",
        "filasAfectadas": 1 }
→ 422 si falta CUALQUIER campo — PUT reemplaza el recurso entero,
      no existe "dejar el que estaba"
→ 404 si el código no existe
```

## 5. `PATCH /api/producto/{codigo}` — Actualizar parcial

Body: los mismos campos pero **todos opcionales** — solo se modifican los
enviados (cada uno validado si llega, con `validarParcial`).

```
PATCH /api/producto/PR009      body { "stock": 7 }
→ 200 { "estado": 200, "mensaje": "Producto actualizado exitosamente.",
        "filasAfectadas": 1 }
→ 400 si el body viene vacío (no hay nada que actualizar)
→ 422 si algún campo enviado viola la validación (stock < 0)
→ 404 si el código no existe
```

**El contraste didáctico:** el body `{ "stock": 7 }` en PATCH es 200; ese mismo
body en PUT es 422 (le faltan `nombre` y `valorunitario`).

## 6. `DELETE /api/producto/{codigo}` — Eliminar

```
DELETE /api/producto/PR009
→ 200 { "estado": 200, "mensaje": "Producto eliminado exitosamente.",
        "filasEliminadas": 1 }
→ 404 si el código no existe
```

## 7. `GET /` — Diagnóstico

```
→ 200 { "mensaje": "API Facturas funcionando", "version": "v1",
        "contratos": "docs/spec_kit/versiones/v1_producto_mariadb/6_contracts.md" }
```

## 8. Estabilidad de este contrato

Estos 7 endpoints **no cambian en las versiones siguientes**: v2 agrega
entidades nuevas (rutas nuevas con este mismo patrón), v3/v4 cambian el motor
por configuración — si algún cambio futuro rompiera este contrato, es una
decisión mayor que debe quedar registrada en la spec de esa versión.
