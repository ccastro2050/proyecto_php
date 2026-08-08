# Quickstart — Versión 1: producto + MariaDB (PHP puro)

> **Versión 1** · Validación rápida de la v1 ya construida. Si aún no hay
> nada, empiece por [8_tasks.md](8_tasks.md).

---

## 1. Arrancar TODO (un solo comando)

```powershell
# desde la raíz del proyecto (terminal integrada de VS Code):
docker compose up -d --build
```

Eso deja corriendo la BD (bdfacturas completa en MariaDB) y la API PHP.
No hay venv ni dependencias que instalar: **PHP no necesita nada más**.

Alternativa para desarrollo fase a fase — la API local (requiere PHP 8.3 con
`pdo_mysql`) contra la BD del compose:

```powershell
docker compose up -d mariadb
$env:DB_DSN = "mysql:host=localhost;port=13326;dbname=bdfacturas_mariadb_local"
$env:DB_USUARIO = "paradigmas"
$env:DB_CLAVE = "paradigmas123"
cd api_facturas
php -S localhost:8022 index.php
```

## 2. Smoke test (los 6 criterios de aceptación, en orden)

```powershell
# 1. Diagnóstico (y de paso: edite el mensaje en index.php, guarde y
#    refresque — el cambio aparece SIN reiniciar nada: PHP reinterpreta)
curl http://localhost:8022/

# 2. Listar — los 8 productos, y el query string en acción
curl http://localhost:8022/api/producto                      # total: 8
curl "http://localhost:8022/api/producto?limite=3"           # total: 3

# 3. Obtener uno / inexistente (parámetro de ruta)
curl http://localhost:8022/api/producto/PR001    # 200 Laptop Lenovo
curl -i http://localhost:8022/api/producto/PR999 # 404

# 4. Ciclo con los 5 verbos
curl -X POST http://localhost:8022/api/producto -H "Content-Type: application/json" `
     -d '{\"codigo\":\"PR009\",\"nombre\":\"Webcam Logitech\",\"stock\":5,\"valorunitario\":120000}'
curl -X PUT  http://localhost:8022/api/producto/PR009 -H "Content-Type: application/json" `
     -d '{\"nombre\":\"Webcam Logitech C920\",\"stock\":10,\"valorunitario\":150000}'   # reemplazo COMPLETO
curl -X PATCH http://localhost:8022/api/producto/PR009 -H "Content-Type: application/json" `
     -d '{\"stock\":7}'                                       # parcial: solo el stock
curl http://localhost:8022/api/producto/PR009    # nombre C920, stock = 7
curl -X DELETE http://localhost:8022/api/producto/PR009
curl -i -X DELETE http://localhost:8022/api/producto/PR009   # 404 (ya no existe)

# 4b. La diferencia PUT vs PATCH — el MISMO body, distinto veredicto
curl -i -X PUT   http://localhost:8022/api/producto/PR001 -H "Content-Type: application/json" `
     -d '{\"stock\":99}'    # 422: a PUT le faltan nombre y valorunitario
curl -i -X PATCH http://localhost:8022/api/producto/PR001 -H "Content-Type: application/json" `
     -d '{\"stock\":17}'    # 200: PATCH acepta el subconjunto

# 5. La validación como frontera — nunca llega a la BD
curl -i -X POST http://localhost:8022/api/producto -H "Content-Type: application/json" `
     -d '{\"codigo\":\"PRX\",\"nombre\":\"Test\",\"stock\":-5,\"valorunitario\":100}'   # 422 con errores[]
curl -i -X POST http://localhost:8022/api/producto -H "Content-Type: application/json" `
     -d '{\"codigo\":\"PR001\",\"nombre\":\"Dup\",\"stock\":1,\"valorunitario\":1}' # 500 (PK duplicada)

# extra: PATCH body vacío y límite inválido (reglas de negocio → 400)
curl -i -X PATCH http://localhost:8022/api/producto/PR001 -H "Content-Type: application/json" -d '{}'
curl -i "http://localhost:8022/api/producto?limite=0"
```

**6. Prueba de capas** (sin MariaDB): un script que instancie
`ServicioProducto` con un repositorio falso en memoria que implemente
`IRepositorioProducto` y verifique crear/listar/eliminar — si funciona, las
capas quedaron bien cortadas ([8_tasks.md](8_tasks.md) Fase 4). Se corre con
`php` a secas (dentro o fuera del contenedor):

```powershell
docker compose exec api-facturas php pruebas/prueba_capas.php
```

## 3. Si algo falla

| Síntoma | Causa probable |
|---|---|
| `could not find driver` | Falta la extensión `pdo_mysql` (en Docker ya viene; local: habilítela en `php.ini`) |
| 500 en todos los endpoints | MariaDB apagada o DSN mal apuntado (Docker: host `mariadb`; local: `localhost:13326`) |
| 204 donde esperaba los 8 productos | La tabla está vacía: `docker compose down -v` y `up -d` recargan `db/init.sql` |
| El `LIMIT` falla con error de sintaxis | El `:limite` se enlazó como string — debe ser `bindValue(..., PDO::PARAM_INT)` ([3_plan.md](3_plan.md) §4.4) |
| PATCH con el mismo valor responde 404 | Falta `PDO::MYSQL_ATTR_FOUND_ROWS => true` en la conexión: sin él, MariaDB reporta 0 filas cuando el valor no cambió ([3_plan.md](3_plan.md) §4.4) |
| Edité un `.php` y no veo el cambio | Caché del navegador: fuerce con Ctrl+F5 (PHP siempre reinterpreta; el navegador a veces no) |
| Puerto 8022 o 13326 ocupado | Otro proyecto del curso está corriendo — apáguelo (`docker compose down` en ESE proyecto) |
