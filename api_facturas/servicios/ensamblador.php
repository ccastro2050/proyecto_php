<?php
/**
 * Ensamblador — el ÚNICO lugar del sistema que conoce clases concretas.
 *
 * Una sola función, sin arrays de motores ni selección: la v1 tiene UN motor
 * y el código lo dice (YAGNI con dirección). Cuando la v3 agregue PostgreSQL,
 * SOLO este archivo se convertirá en la fábrica real — controladores y
 * servicios no se tocarán: ese será el examen del principio abierto/cerrado.
 */
declare(strict_types=1);

require_once __DIR__ . '/ServicioProducto.php';
require_once __DIR__ . '/../repositorios/RepositorioProductoMariaDB.php';

function crearServicioProducto(): IServicioProducto
{
    // La configuración llega por variables de entorno (el compose las inyecta):
    $repositorio = new RepositorioProductoMariaDB(
        dsn:     getenv('DB_DSN')     ?: 'mysql:host=localhost;port=13326;dbname=bdfacturas_mariadb_local',
        usuario: getenv('DB_USUARIO') ?: 'paradigmas',
        clave:   getenv('DB_CLAVE')   ?: 'paradigmas123',
    );
    return new ServicioProducto($repositorio);
}
