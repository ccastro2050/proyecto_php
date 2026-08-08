<?php
/**
 * IRepositorioProducto — el CONTRATO de la capa de datos.
 *
 * Una interface nativa de PHP define QUÉ operaciones existen sobre producto,
 * sin decir CÓMO ni CONTRA QUÉ motor. Cualquier clase con
 * `implements IRepositorioProducto` puede ocupar este lugar: el MariaDB real
 * de la v1, el falso en memoria de las pruebas, o el PostgreSQL que llegará
 * en la v3 (polimorfismo).
 *
 * El servicio depende de ESTA interfaz, nunca de una clase concreta
 * (inversión de dependencias — la D de SOLID).
 */
declare(strict_types=1);

interface IRepositorioProducto
{
    /** Devuelve hasta $limite productos ordenados por código. */
    public function obtenerTodos(int $limite): array;

    /** Devuelve el producto con ese código, o null si no existe. */
    public function obtenerPorCodigo(string $codigo): ?array;

    /** Inserta un producto. Devuelve true si quedó insertado. */
    public function crear(array $datos): bool;

    /**
     * Escribe los campos de $datos (los usan PUT y PATCH).
     * Devuelve el número de filas afectadas (0 = el código no existe).
     */
    public function actualizar(string $codigo, array $datos): int;

    /** Elimina el producto. Devuelve filas eliminadas (0 = no existía). */
    public function eliminar(string $codigo): int;
}
