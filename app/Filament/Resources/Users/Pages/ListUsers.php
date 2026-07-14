<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                CreateAction::make(),
                Action::make('niveis_de_acesso')
                    ->label('Niveis de acesso')
                    ->icon('heroicon-o-shield-check')
                    ->url(fn (): string => RoleResource::getUrl('index'))
                    ->visible(fn (): bool => RoleResource::canViewAny()),
            ])
                ->label('Ações')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
                ->color('gray'),
        ];
    }
}
