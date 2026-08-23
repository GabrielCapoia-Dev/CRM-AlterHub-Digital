<?php

namespace App\Policies;

use App\Enum\RolesEnum;
use App\Models\Acesso\User;
use App\Models\DocumentoConfiguracao;

class DocumentoConfiguracaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, DocumentoConfiguracao $configuracao): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user)
            && DocumentoConfiguracao::query()->where('chave', DocumentoConfiguracao::CHAVE_PADRAO)->doesntExist();
    }

    public function update(User $user, DocumentoConfiguracao $configuracao): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, DocumentoConfiguracao $configuracao): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(RolesEnum::SuperAdmin->value);
    }
}
