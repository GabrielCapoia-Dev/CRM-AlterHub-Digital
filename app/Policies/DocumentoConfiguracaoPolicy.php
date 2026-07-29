<?php

namespace App\Policies;

use App\Enum\PermissoesEnum;
use App\Models\Acesso\User;
use App\Models\DocumentoConfiguracao;

class DocumentoConfiguracaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::AcessarConfiguracoesDocumentos->value);
    }

    public function view(User $user, DocumentoConfiguracao $configuracao): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarConfiguracoesDocumentos->value)
            && DocumentoConfiguracao::query()->where('chave', DocumentoConfiguracao::CHAVE_PADRAO)->doesntExist();
    }

    public function update(User $user, DocumentoConfiguracao $configuracao): bool
    {
        return $user->hasPermissionTo(PermissoesEnum::EditarConfiguracoesDocumentos->value);
    }

    public function delete(User $user, DocumentoConfiguracao $configuracao): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
