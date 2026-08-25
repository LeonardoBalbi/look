<?php

namespace Tests\Feature;

use App\Models\Loja;
use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleStoreInstallationTest extends TestCase
{
    use RefreshDatabase;

    private function seedSingleStore(): User
    {
        config()->set('application_role.role', 'store');
        config()->set('installation.single_store', true);
        $this->seed(ApplicationInitialSeeder::class);

        return User::query()->where('email', 'superadmin@example.com')->firstOrFail();
    }

    public function test_installation_does_not_allow_a_second_store(): void
    {
        $admin = $this->seedSingleStore();

        $this->assertSame(1, Loja::query()->count());

        $this->actingAs($admin)->post('/lojas', [
            'nome' => 'Segunda loja',
            'cidade' => 'São Paulo',
            'status' => 'ativa',
        ])->assertUnprocessable();

        $this->assertSame(1, Loja::query()->count());
    }

    public function test_interface_presents_one_company_without_store_selection(): void
    {
        $admin = $this->seedSingleStore();

        $this->actingAs($admin)->get('/painel?page=lojas')
            ->assertOk()
            ->assertSee('Configuração da empresa')
            ->assertSee('Dados da empresa')
            ->assertDontSee('Lojas / Unidades')
            ->assertDontSee('Controle por Loja')
            ->assertDontSee('Lojas liberadas');

        $this->actingAs($admin)->get('/painel?page=motos')
            ->assertOk()
            ->assertDontSee('<label>Loja<select', false);
    }

    public function test_editing_the_company_updates_the_name_shown_by_the_application(): void
    {
        config()->set('branding.store_name', 'Nome inicial do ambiente');
        config()->set('branding.use_locx_logo', true);
        $admin = $this->seedSingleStore();
        $loja = Loja::query()->firstOrFail();

        $this->actingAs($admin)->post('/lojas', [
            'id' => $loja->id,
            'nome' => 'Barra',
            'cidade' => 'Rio de Janeiro',
            'status' => 'ativa',
        ])->assertRedirect('/painel?page=lojas&edit='.$loja->id);

        $this->get('/painel?page=dashboard')
            ->assertOk()
            ->assertSee('Barra')
            ->assertSee('assets/img/locx-logo.svg')
            ->assertDontSee('Nome inicial do ambiente');
    }
}
