<?php
/**
 * Producto — el MODELO de la v1: la clase que representa una fila de la
 * tabla producto como un objeto con tipos.
 *
 * Eso es todo lo que es un modelo: en vez de cargar el dato en un array
 * anónimo ($fila['stock']), se carga en un objeto con propiedades tipadas
 * ($producto->stock, que SIEMPRE es un int). El resto del sistema trabaja
 * con este objeto.
 */

// Modo estricto de tipos (ver explicación completa en index.php):
declare(strict_types=1);

class Producto
{
    // "Promoción de propiedades" (PHP 8): declarar los parámetros del
    // constructor con "public" los convierte automáticamente en propiedades
    // del objeto. Estas 4 líneas equivalen a declarar 4 propiedades arriba
    // y asignarlas una a una dentro del constructor.
    //
    // Cada propiedad lleva su TIPO: si alguien intenta meter "siete" en
    // stock, PHP lo rechaza (gracias a strict_types).
    public function __construct(
        public string $codigo,        // ej. "PR001" (la llave primaria)
        public string $nombre,        // ej. "Laptop Lenovo IdeaPad"
        public int $stock,            // unidades disponibles (entero)
        public float $valorunitario,  // precio (decimal)
    ) {
    }

    // Nada más. Ni siquiera necesita código para volverse JSON:
    // json_encode($producto) serializa solo las propiedades públicas →
    // {"codigo":"PR001","nombre":"...","stock":17,"valorunitario":2500000}
}
