<?php
/**
 * IServicioProducto — el CONTRATO de la capa de negocio.
 *
 * El controlador depende de esta interfaz: no sabe (ni debe saber) qué hay
 * detrás. Los métodos comunican problemas con excepciones de NEGOCIO que el
 * controlador traduce a códigos HTTP:
 *   InvalidArgumentException → 400 · NoEncontradoExcepcion → 404 ·
 *   PDOException y demás → 500.
 */
declare(strict_types=1);

interface IServicioProducto
{
    /** Hasta $limite productos. InvalidArgumentException si limite <= 0. */
    public function listar(int $limite): array;

    /** El producto con ese código. NoEncontradoExcepcion si no existe. */
    public function obtener(string $codigo): array;

    /** Crea el producto (los datos ya vienen validados por el Validador). */
    public function crear(array $datos): void;

    /**
     * Escribe los campos enviados (PUT manda todos, PATCH un subconjunto).
     * InvalidArgumentException si no llegó ningún campo ·
     * NoEncontradoExcepcion si el código no existe · devuelve filas afectadas.
     */
    public function actualizar(string $codigo, array $datos): int;

    /** Elimina. NoEncontradoExcepcion si no existe · devuelve filas eliminadas. */
    public function eliminar(string $codigo): int;
}
