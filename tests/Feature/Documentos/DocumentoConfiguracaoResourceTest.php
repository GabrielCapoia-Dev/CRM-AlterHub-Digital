<?php

namespace Tests\Feature\Documentos;

use App\Filament\Resources\DocumentoConfiguracoes\DocumentoConfiguracaoResource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Tests\TestCase;

class DocumentoConfiguracaoResourceTest extends TestCase
{
    public function test_identity_section_spans_the_full_action_form_width(): void
    {
        $schema = DocumentoConfiguracaoResource::form(
            Schema::make()->columns(2),
        );

        $section = collect($schema->getComponents(withHidden: true))
            ->first(fn ($component): bool => $component instanceof Section
                && $component->getHeading() === 'Identidade institucional');

        $this->assertInstanceOf(Section::class, $section);
        $this->assertSame('full', $section->getColumnSpan('default'));
    }
}
