<?php
/**
 * prueba_capas.php — Criterio 6 de la v1: el servicio funciona con un
 * repositorio FALSO en memoria que implementa IRepositorioProducto —
 * sin MariaDB corriendo.
 *
 * Si esto pasa, las capas quedaron bien cortadas (polimorfismo + inversión
 * de dependencias). Ejecutar:  php pruebas/prueba_capas.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../servicios/ServicioProducto.php';

/** Cumple el contrato con un simple array — cero SQL, cero red. */
class RepositorioFalsoEnMemoria implements IRepositorioProducto
{
    private array $datos = [];

    public function obtenerTodos(int $limite): array
    {
        return array_slice(array_values($this->datos), 0, $limite);
    }

    public function obtenerPorCodigo(string $codigo): ?array
    {
        return $this->datos[$codigo] ?? null;
    }

    public function crear(array $datos): bool
    {
        $this->datos[$datos['codigo']] = $datos;
        return true;
    }

    public function actualizar(string $codigo, array $datos): int
    {
        if (!isset($this->datos[$codigo])) {
            return 0;
        }
        $this->datos[$codigo] = array_merge($this->datos[$codigo], $datos);
        return 1;
    }

    public function eliminar(string $codigo): int
    {
        if (!isset($this->datos[$codigo])) {
            return 0;
        }
        unset($this->datos[$codigo]);
        return 1;
    }
}

// ----------------------------------------------------------------------
// La prueba: el MISMO ServicioProducto, con otro repositorio (polimorfismo)
// ----------------------------------------------------------------------
$servicio = new ServicioProducto(new RepositorioFalsoEnMemoria());

function verificar(bool $condicion, string $descripcion): void
{
    if (!$condicion) {
        fwrite(STDERR, "FALLÓ: $descripcion\n");
        exit(1);
    }
}

$servicio->crear(['codigo' => 'T1', 'nombre' => 'Test', 'stock' => 5, 'valorunitario' => 100.0]);
verificar($servicio->listar(10)[0]['codigo'] === 'T1',        'crear + listar');
verificar($servicio->obtener('T1')['nombre'] === 'Test',      'obtener por código');
verificar($servicio->actualizar('T1', ['stock' => 9]) === 1,  'actualizar');
verificar($servicio->obtener('T1')['stock'] === 9,            'el stock quedó en 9');
verificar($servicio->eliminar('T1') === 1,                    'eliminar');

// Las excepciones de negocio también funcionan sin BD:
try { $servicio->obtener('NOEXISTE'); verificar(false, 'debió lanzar NoEncontradoExcepcion'); }
catch (NoEncontradoExcepcion) { /* esperado */ }

try { $servicio->actualizar('T1', []); verificar(false, 'debió lanzar InvalidArgumentException'); }
catch (InvalidArgumentException) { /* esperado */ }

try { $servicio->listar(0); verificar(false, 'debió lanzar InvalidArgumentException'); }
catch (InvalidArgumentException) { /* esperado */ }

echo "CRITERIO 6 OK: el servicio funciona con el repositorio falso, sin MariaDB\n";
