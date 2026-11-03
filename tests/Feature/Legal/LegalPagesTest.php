<?php

namespace Tests\Feature\Legal;

use App\Filament\Resources\LegalPages\Pages\EditLegalPage;
use App\Models\CompanySetting;
use App\Models\LegalPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_legal_pages_are_published_and_fill_in_company_data(): void
    {
        CompanySetting::query()->create([
            'whatsapp_bot_phone' => '+573009998877',
            'support_phone' => '+573001112233',
            'currency_code' => 'COP',
            'default_locale' => 'es',
            'trade_name' => 'Mega Elite',
            'legal_name' => 'Mega Elite S.A.S.',
            'tax_id' => '900123456-7',
            'support_email' => 'soporte@example.com',
        ]);

        $this->get('/terminos-y-condiciones')
            ->assertOk()
            ->assertSee('Términos y condiciones')
            ->assertSee('Mega Elite S.A.S.')
            ->assertSee('900123456-7')
            ->assertSee('Ley 643 de 2001')
            ->assertDontSee('{nit}');

        $this->get('/politica-de-privacidad')
            ->assertOk()
            ->assertSee('Ley 1581 de 2012')
            ->assertSee('soporte@example.com');
    }

    public function test_unpublished_page_returns_404_and_disappears_from_the_footer(): void
    {
        LegalPage::query()->where('slug', LegalPage::TERMS)->update(['is_published' => false]);

        $this->get('/terminos-y-condiciones')->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('/terminos-y-condiciones', false)->assertSee('/politica-de-privacidad', false);
    }

    public function test_rendered_body_strips_scripts(): void
    {
        LegalPage::query()->where('slug', LegalPage::TERMS)->update([
            'body' => '<p>Hola</p><script>alert(1)</script><p onclick="alert(2)">Chao</p>',
        ]);

        $this->get('/terminos-y-condiciones')
            ->assertOk()
            ->assertSee('Chao')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('onclick', false);
    }

    public function test_admin_can_edit_a_legal_page_and_others_cannot(): void
    {
        $page = LegalPage::query()->where('slug', LegalPage::TERMS)->firstOrFail();

        $this->actingAs(User::factory()->create(['role' => null]))
            ->get(route('filament.admin.resources.legal-pages.index'))
            ->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        Livewire::test(EditLegalPage::class, ['record' => $page->getRouteKey()])
            ->fillForm(['title' => 'Términos nuevos', 'body' => '<p>Texto nuevo de {empresa}</p>'])
            ->call('save')
            ->assertHasNoFormErrors();

        $page->refresh();
        $this->assertSame('Términos nuevos', $page->title);
        $this->assertSame($admin->id, $page->updated_by);

        $this->get('/terminos-y-condiciones')->assertOk()->assertSee('Texto nuevo de');
    }
}
