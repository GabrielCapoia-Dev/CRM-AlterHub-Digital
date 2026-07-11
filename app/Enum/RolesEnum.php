<?php

namespace App\Enum;

enum RolesEnum: string
{
    case SuperAdmin = 'Super Admin';
    case Admin = 'Admin';
    case Vendedor = 'Vendedor';
    case Usuario = 'Usuário';
}