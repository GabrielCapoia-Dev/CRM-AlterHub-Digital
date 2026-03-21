<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Home extends Page
{
    protected string $view = 'filament.pages.home';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Home;
    protected static ?string $navigationLabel = 'Início';

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }
}