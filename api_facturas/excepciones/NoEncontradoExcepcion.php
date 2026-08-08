<?php
/**
 * NoEncontradoExcepcion — la excepción de negocio "el recurso no existe".
 *
 * La lanza el SERVICIO cuando un código no corresponde a ningún producto,
 * y el CONTROLADOR la traduce al código HTTP 404. Así el servicio comunica
 * problemas sin saber nada de HTTP (separación de capas).
 */
declare(strict_types=1);

class NoEncontradoExcepcion extends Exception
{
}
