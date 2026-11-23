<?php

declare(strict_types=1);

namespace TecControl\Aplicacion;

enum Rol: string
{
    case Administrador = 'administrador';
    case Cliente = 'cliente';
}
