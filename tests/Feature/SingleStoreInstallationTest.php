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

        $this->actingAs($admin)->get('/?page=lojas')
            ->assertOk()
            ->assertSee('Configuração da empresa')
            ->assertSee('Dados da empresa')
            ->assertDontSee('Lojas / Unidades')
            ->assertDontSee('Controle por Loja')
            ->assertDontSee('Lojas liberadas');

        $this->actingAs($admin)->get('/?page=motos')
            ->assertOk()
            ->assertDontSee('<label>Loja<select', false);
    }
}
