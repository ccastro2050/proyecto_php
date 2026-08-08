# Investigación y decisiones — Versión 1: producto + MariaDB (PHP puro)

> **Versión 1** · **Lectura opcional** (el porqué de las decisiones del plan,
> con las alternativas que se evaluaron y descartaron). Complementa a
> [3_plan.md](3_plan.md); el orden de trabajo está en [8_tasks.md](8_tasks.md).

---

## D1 — PHP puro: sin framework y sin Composer

**Alternativas descartadas:** Slim 4 (micro-framework con routing PSR-7) y
Laravel (framework completo con ORM).
**Decisión:** PHP "vanilla" — front controller propio, PDO directo, cero
dependencias.
**Por qué:** el objetivo es aprender **PHP y arquitectura**, no un framework.
Laravel esconde exactamente lo que el curso quiere mostrar (el SQL, el
enrutamiento, la validación); Slim es razonable pero mete Composer, `vendor/`
y estándares PSR que compiten por la atención del estudiante. Escribir el
router de ~60 líneas y el Validador a mano ES el contenido. **Precio
asumido:** características gratis que no tendremos (middleware, DI container,
docs automáticas) — ninguna es objetivo de la v1.

## D2 — Capas completas desde el día 1 (y no un MVP en un solo archivo)

**Alternativa descartada:** v1 = todo en `index.php` y refactorizar a capas
después.
**Decisión:** controlador → servicio → repositorio con interfaces desde v1.
**Por qué:** el valor de la v1 es el **esqueleto** sobre el que crecen las
demás versiones sin reescribir. El criterio de aceptación 6 (probar el
servicio con un repositorio falso, sin MariaDB) **solo es posible** si el
servicio depende de una `interface` — la prueba objetiva de que las capas
quedaron bien cortadas. Bonus de PHP: las interfaces son nativas del lenguaje
(`interface` / `implements`), más explícitas incluso que los Protocol de
otros lenguajes.

## D3 — Sin fábrica ni selección de motor: un ensamblador de una función

**Alternativa descartada:** escribir de una vez la fábrica multi-motor.
**Decisión:** `ensamblador.php` con una función que instancia la única
combinación existente (YAGNI con dirección).
**Por qué:** una fábrica con un solo producto es código muerto. La interfaz
`IRepositorioProducto` SÍ se escribe hoy — es la puerta por la que entrará
MariaDB — pero el mecanismo de selección llega cuando exista algo que
seleccionar (v3). El examen del principio abierto/cerrado será ese: en v3,
solo `ensamblador.php` cambia.

## D4 — La BD completa desde la v1 (la API solo toca `producto`)

**Alternativa descartada:** una BD mínima que crece con cada versión.
**Decisión:** `db/init.sql` crea `bdfacturas` COMPLETA (12 tablas, trigger,
SPs); la regla es que el código de v1 solo puede nombrar `producto`.
**Por qué:** los estudiantes ya vieron bases de datos — la BD es
**infraestructura dada**; lo que se construye por versiones es la API. Evita
migraciones entre versiones y deja el trigger de facturación esperando a la
v2. Costo asumido: 11 tablas a la vista que aún no se usan — por eso la regla
se declara explícita en la spec.

## D5 — El Validador manual (nuestra "frontera de entrada"), con un método por verbo

**Alternativa descartada:** validar dentro del servicio, o no validar y dejar
que la BD rechace.
**Decisión:** `ValidadorProducto` con `validarCrear` (POST, todo
obligatorio), `validarReemplazo` (PUT, todo obligatorio) y `validarParcial`
(PATCH, solo lo enviado) → 422 con lista de errores ANTES de tocar el
servicio.
**Por qué:** en los stacks con Pydantic la frontera viene gratis; en PHP puro
**construirla** enseña qué hace realmente una frontera de entrada. Y los tres
métodos materializan la semántica de cada verbo: el mismo body `{"stock": 7}`
falla en PUT (le faltan campos) y pasa en PATCH — la diferencia queda escrita
en código, no en comentarios.

## D6 — PDO con prepared statements (SQL visible)

**Alternativa descartada:** un ORM (Eloquent/Doctrine) o funciones `pg_*`.
**Decisión:** PDO + `prepare`/`execute` con parámetros nombrados.
**Por qué:** la constitución exige SQL visible y parametrizado. PDO es el
estándar del lenguaje, funciona con los tres motores de la ruta (v3/v4 solo
cambian el DSN y el dialecto) y sus prepared statements son la defensa
canónica contra inyección SQL. Detalle didáctico: el driver mysql entrega `DECIMAL`
como string — el repositorio castea al serializar, y ese matiz (cada driver
serializa distinto) es lección del curso.

## D7 — `php -S` (built-in server) en vez de Apache/nginx

**Alternativa descartada:** `php:8.3-apache` con mod_rewrite y `.htaccess`.
**Decisión:** el servidor embebido de PHP con `index.php` como router:
`php -S 0.0.0.0:8022 index.php`.
**Por qué:** con `php -S … index.php` TODAS las peticiones pasan por el front
controller sin configurar rewrite — cero archivos de configuración de
servidor, que no son contenido de la v1. Producción real usaría
nginx + PHP-FPM — fuera del alcance (documentado). El compose además monta el
código como volumen: guardar un `.php` = refrescar el navegador, porque PHP
reinterpreta cada petición (ni siquiera existe "reload").

## D8 — Docker compose desde la v1 (dos servicios)

**Alternativa descartada:** `docker run` a mano para la BD y PHP local como
única forma de correr.
**Decisión:** `docker-compose.yml` con `mariadb` + `api-facturas` desde v1 —
`docker compose up -d --build` deja todo funcionando.
**Por qué:** el Artículo 4 de la constitución ("un solo comando") es
permanente — y la constitución gana. El compose de v1 es mínimo y **crece por
versiones** (v3 suma MariaDB, v4 SQL Server…): la infraestructura también se
construye por incrementos.
