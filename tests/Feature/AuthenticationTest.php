<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_pode_entrar_e_ver_o_dashboard(): void
    {
        $this->seed(ApplicationInitialSeeder::class);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'senha' => '123456',
        ])->assertRedirect('/');

        $this->assertAuthenticatedAs(User::where('email', 'admin@example.com')->first());
        $this->get('/')->assertOk()->assertSee('Dashboard');
    }

    public function test_senha_invalida_nao_autentica(): void
    {
        $this->seed(ApplicationInitialSeeder::class);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'senha' => 'errada',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
