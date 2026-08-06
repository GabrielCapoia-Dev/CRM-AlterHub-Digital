<?php

use App\Enum\PermissoesEnum;
use App\Enum\RolesEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, list<string>> */
    private array $rolesPorPermissao = [
        PermissoesEnum::VisualizarTodasVendasOperacao->value => [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
            RolesEnum::Estoquista->value,
        ],
        PermissoesEnum::SepararPedidos->value => [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Gestor->value,
            RolesEnum::Estoquista->value,
        ],
        PermissoesEnum::MovimentarEstoqueProdutos->value => [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Estoquista->value,
        ],
        PermissoesEnum::MovimentarEstoqueInsumos->value => [
            RolesEnum::SuperAdmin->value,
            RolesEnum::Admin->value,
            RolesEnum::Estoquista->value,
        ],
    ];

    public function up(): void
    {
        foreach ($this->rolesPorPermissao as $nomePermissao => $nomesRoles) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $nomePermissao, 'guard_name' => 'web'],
                ['updated_at' => now(), 'created_at' => now()],
            );
            $permissionId = DB::table('permissions')
                ->where('name', $nomePermissao)
                ->where('guard_name', 'web')
                ->value('id');
            $roleIds = DB::table('roles')
                ->where('guard_name', 'web')
                ->whereIn('name', $nomesRoles)
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_keys($this->rolesPorPermissao))
            ->pluck('id');

        DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
