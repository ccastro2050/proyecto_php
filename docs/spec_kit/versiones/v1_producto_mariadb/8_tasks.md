# Tareas — Versión 1: api_facturas con producto + MariaDB (PHP puro)

> **Versión 1** · El orden de construcción, partiendo de CERO. Cada fase termina
> en algo **verificable**. Requisitos: [2_spec.md](2_spec.md) · técnica:
> [3_plan.md](3_plan.md) · contratos: [6_contracts.md](6_contracts.md) ·
> validación final: [7_quickstart.md](7_quickstart.md).

---

## Fase 0 — Base de datos y esqueleto
- [ ] Copiar a `db/init.sql` el script **provisto** con esta versión (la BD
      `bdfacturas` COMPLETA en dialecto MariaDB — no se escribe ni se genera
      con IA; ver [5_data_model.md](5_data_model.md) §1).
- [ ] Crear el `docker-compose.yml` con el servicio `mariadb` (imagen
      `mariadb:11`, volumen `mariadbdata`, `db/init.sql` montado en
      `/docker-entrypoint-initdb.d/`, puerto 13326, healthcheck — ver
      [3_plan.md](3_plan.md) §5) y levantarlo: `docker compose up -d`.
- [ ] Crear la carpeta `api_facturas/` con subcarpetas `modelos/`,
      `controladores/`, `servicios/`, `repositorios/` y `excepciones/`.

**Verificar:** un cliente SQL (HeidiSQL/DBeaver a `localhost:13326`) ve las
**12 tablas** y `SELECT count(*) FROM producto` da **8**.

## Fase 1 — El modelo Producto (la clase entidad)
- [ ] `modelos/Producto.php`: la clase entidad al estilo clásico —
      4 propiedades **privadas** (`codigo` string, `nombre` string,
      `stock` int, `valorunitario` float), constructor que las asigna,
      **getters** para las 4, **setters** para las 3 que pueden cambiar
      (el código es la llave: sin setter) y `toArray()` que devuelve el
      array columna→valor para el JSON.

**Verificar:** en un script suelto (o `php -a`),
`$p = new Producto('PR001', 'Prueba', 5, 100.0);` construye el objeto,
`$p->getStock()` devuelve 5, `$p->setStock(9)` lo cambia, y
`json_encode($p->toArray())` devuelve el JSON con las 4 columnas.

## Fase 2 — Contratos (interfaces) y excepción de negocio
- [ ] `repositorios/IRepositorioProducto.php`: interface con los 5 métodos
      (`obtenerTodos(int $limite)`, `obtenerPorCodigo`, `crear`,
      `actualizar` — la usan PUT y PATCH — y `eliminar`).
- [ ] `servicios/IServicioProducto.php`: interface del servicio.
- [ ] `excepciones/NoEncontradoExcepcion.php`: la excepción que el
      controlador traducirá a 404.

**Verificar:** `php -l` pasa en los tres archivos (sintaxis válida).

## Fase 3 — Repositorio MariaDB
- [ ] `repositorios/RepositorioProductoMariaDB.php`: PDO perezoso
      (`ERRMODE_EXCEPTION`, `ATTR_EMULATE_PREPARES => false`), los 5 métodos
      con prepared statements de [3_plan.md](3_plan.md) §4.4, `:limite` con
      `PDO::PARAM_INT`, y casteo al serializar (`stock → int`,
      `valorunitario → float`).

**Verificar:** un script suelto que instancie el repositorio con el DSN de
`localhost:13326` lista los 8 productos y trae PR001 por código.

## Fase 4 — Servicio (y la prueba de capas)
- [ ] `servicios/ServicioProducto.php`: recibe `IRepositorioProducto` por
      constructor; valida reglas de negocio (`limite > 0`, código no vacío,
      PATCH sin campos → `InvalidArgumentException`); traduce "no existe" a
      `NoEncontradoExcepcion`.
- [ ] `servicios/ensamblador.php`: `crearServicioProducto()` — la función de
      [3_plan.md](3_plan.md) §4.3 (sin fábrica multi-motor: eso es v3).

**Verificar (criterio 6 de la spec):** `pruebas/prueba_capas.php` instancia
`ServicioProducto` con un **repositorio falso en memoria** (una clase con
`implements IRepositorioProducto` sobre un array) y hace
crear/listar/eliminar SIN MariaDB corriendo. Si esto funciona, las capas
quedaron bien.

## Fase 5 — Controlador y front controller
- [ ] `controladores/ControladorProducto.php`: los 6 métodos de producto con
      la validación del controlador (422), la traducción de excepciones de
      [3_plan.md](3_plan.md) §4.5 (400/404/500) y el 204 para lista vacía.
- [ ] `index.php`: requires, header JSON, router con `if` por ruta y verbo
      ([3_plan.md](3_plan.md) §4.6), endpoint `/` de diagnóstico y 404 por
      defecto.

**Verificar:** con la BD arriba y `php -S localhost:8022 index.php` (o el
contenedor), probar: listar (200 con 8 y `?limite=3` con 3), obtener PR001
(200), PR999 (404), POST inválido (422 con `errores[]`), y el contraste
PUT vs PATCH con `{"stock": 7}` (422 vs 200).

## Fase 6 — Docker: un solo comando
- [ ] `api_facturas/Dockerfile`: `php:8.3-cli`, `docker-php-ext-install
      pdo_mysql`, copiar el código, `CMD php -S 0.0.0.0:8022 index.php`.
- [ ] Agregar al `docker-compose.yml` el servicio `api-facturas`: `build:`,
      código montado como volumen, puerto 8022, variables `DB_DSN` /
      `DB_USUARIO` / `DB_CLAVE` con el host interno `mariadb:3306`, y
      `depends_on` con `condition: service_healthy`.

**Verificar:** `docker compose down` y luego `docker compose up -d --build`
— UN comando deja BD y API funcionando (criterio 1 de la spec); editar un
`.php`, guardar y refrescar muestra el cambio sin reiniciar nada.

## Fase 7 — Cierre de la versión
- [ ] Correr el smoke test completo de [7_quickstart.md](7_quickstart.md) §2 —
      equivale a los 6 criterios de aceptación de [2_spec.md](2_spec.md) §5.
- [ ] `.gitignore` (`*.session.sql`, archivos de IDE).
- [ ] Commit y tag `v1`.

**La v1 está TERMINADA.** Solo ahora se escribe la spec de la v2
([mapa de versiones](../0_mapa_versiones.md)).
