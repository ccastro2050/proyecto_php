# backupdb — respaldos de la base de datos

En esta carpeta se guardan los **respaldos (backups)** de `bdfacturas`.
Un respaldo es un **dump**: un archivo `.sql` con los `CREATE TABLE`, los
`INSERT` de todos los datos, los triggers y los procedimientos — todo lo
necesario para reconstruir la BD **tal como estaba** en ese momento.

> ¿En qué se diferencia de `db/init.sql`? En que `init.sql` crea la BD en su
> **estado inicial** (los datos de fábrica del curso), mientras que un
> backup captura **SU estado actual**: lo que usted insertó, editó o borró.
> Si solo quiere volver al estado inicial, no necesita backup:
> `docker compose down -v` y volver a subir.

Convención de nombres: `bdfacturas_mariadb_AAAA-MM-DD.sql` (si hace varios
el mismo día, agregue un sufijo: `_2.sql`).

---

## Cómo hacer un backup

Con el proyecto corriendo, desde la **raíz del repositorio** (dos comandos:
el dump se genera DENTRO del contenedor y luego se copia a esta carpeta —
así funciona igual en PowerShell, CMD o bash):

```powershell
docker compose exec mariadb sh -c "mariadb-dump -uparadigmas -pparadigmas123 --routines --triggers bdfacturas_mariadb_local > /tmp/backup.sql"
docker compose cp mariadb:/tmp/backup.sql backupdb/bdfacturas_mariadb_2026-08-08.sql
```

Qué hace cada pieza:

- `mariadb-dump` — la herramienta oficial de respaldo de MariaDB (dentro
  del contenedor, no hay que instalar nada).
- `--routines --triggers` — incluye los procedimientos almacenados y los
  triggers de facturación (sin esto solo salen tablas y datos).
- `docker compose cp` — copia el archivo del contenedor a su PC.

Abra el `.sql` generado: es legible — los `CREATE TABLE`, los `INSERT` y al
final los triggers. Ese archivo ES el respaldo.

## Cómo restaurar un backup (restore)

El camino inverso: copiar el archivo al contenedor y ejecutarlo con el
cliente `mariadb`. El dump trae `DROP TABLE IF EXISTS` antes de cada tabla,
así que **reemplaza** lo que haya:

```powershell
docker compose cp backupdb/bdfacturas_mariadb_2026-08-08.sql mariadb:/tmp/restore.sql
docker compose exec mariadb sh -c "mariadb -uroot -pparadigmas123 bdfacturas_mariadb_local < /tmp/restore.sql"
```

> El restore va con **root** (no con `paradigmas`) a propósito: el dump
> guarda los triggers con su `DEFINER=root`, y MariaDB solo deja recrear
> objetos "a nombre de otro" a un superusuario. Si lo intenta con
> `paradigmas` verá `ERROR 1227: Access denied; you need SET USER` a mitad
> del restore — las tablas quedan, pero los triggers no.

Verifique: `http://localhost:8022/api/producto` (o phpMyAdmin en
`http://localhost:8101`) debe mostrar los datos tal como estaban cuando
hizo el backup.

## Para probar el ciclo completo (ejercicio)

1. Haga un backup (arriba).
2. Cambie algo a propósito: cree un producto `PR999` desde phpMyAdmin (o
   edite el stock de uno existente).
3. Restaure el backup.
4. `PR999` desapareció (o el stock volvió) — la BD regresó EXACTAMENTE al
   momento del backup. Eso es un respaldo funcionando.

> ⚠️ El restore pisa TODO el contenido actual de la BD con el del archivo.
> Lo que haya cambiado DESPUÉS del backup se pierde. Por eso los respaldos
> se hacen ANTES de operaciones riesgosas (y en producción, con agenda).
