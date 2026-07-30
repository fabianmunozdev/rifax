<?php

namespace Tests\Feature\Filament\AdminResources;

use App\Filament\Resources\ContentEntries\Pages\CreateContentEntry;
use App\Filament\Resources\ContentEntries\Pages\EditContentEntry;
use App\Models\ContentEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ContentEntryScopedValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_blocks_duplicate_key_for_the_same_locale_and_channel(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        ContentEntry::query()->create($this->makeContentEntryData([
            'key' => 'faq.payment.proof',
            'title' => 'Comprobante base',
        ]));

        Livewire::test(CreateContentEntry::class)
            ->set('data', $this->makeContentEntryData([
                'key' => 'faq.payment.proof',
                'title' => 'Comprobante duplicado',
            ]))
            ->call('create')
            ->assertHasErrors(['key']);
    }

    public function test_edit_page_allows_saving_the_same_record_without_triggering_duplicate_scope_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $entry = ContentEntry::query()->create($this->makeContentEntryData([
            'key' => 'faq.raffle.rules',
            'title' => 'Reglas',
        ]));

        Livewire::test(EditContentEntry::class, ['record' => $entry->getRouteKey()])
            ->set('data.title', 'Reglas actualizadas')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Reglas actualizadas', $entry->fresh()->title);
    }

    public function test_template_bridge_requires_intent_variables_and_fallback_text(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateContentEntry::class)
            ->set('data', $this->makeContentEntryData([
                'type' => 'template_bridge',
                'key' => 'template.payment.followup',
                'title' => 'Seguimiento de pago',
                'trigger_intent' => null,
                'variables_json' => [],
                'fallback_text' => null,
            ]))
            ->call('create')
            ->assertHasErrors([
                'data.trigger_intent',
                'data.variables_json',
                'data.fallback_text',
            ]);
    }

    public function test_non_faq_entries_are_persisted_with_ai_eligibility_disabled(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateContentEntry::class)
            ->set('data', $this->makeContentEntryData([
                'type' => 'system_message',
                'key' => 'system.followup.message',
                'title' => 'Seguimiento',
                'is_ai_eligible' => true,
            ]))
            ->call('create')
            ->assertHasNoErrors();

        $entry = ContentEntry::query()->where('key', 'system.followup.message')->firstOrFail();

        $this->assertFalse($entry->is_ai_eligible);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function makeContentEntryData(array $overrides = []): array
    {
        return array_replace([
            'type' => 'faq_fixed',
            'key' => 'faq.base',
            'title' => 'Contenido base',
            'category' => 'faq',
            'locale' => 'es',
            'channel' => 'whatsapp',
            'status' => 'draft',
            'trigger_intent' => null,
            'priority' => 100,
            'is_ai_eligible' => false,
            'is_public' => false,
            'published_at' => null,
            'body_text' => 'Contenido de ejemplo para el admin.',
            'variables_json' => [],
            'fallback_text' => null,
            'notes' => null,
            'created_by' => null,
            'updated_by' => null,
        ], $overrides);
    }
}
