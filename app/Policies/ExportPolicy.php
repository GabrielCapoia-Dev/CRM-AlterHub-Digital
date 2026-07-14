<?php

namespace App\Policies;

use App\Models\Acesso\User;
use Filament\Actions\Exports\Models\Export;

class ExportPolicy
{
    public function view(User $user, Export $export): bool
    {
        return (int) $export->user_id === (int) $user->getKey();
    }
}
