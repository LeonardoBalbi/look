<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_publica_apresenta_a_locx_e_os_acessos(): void
    {
        $this->get('https://barra.locx.com.br/')
            ->assertOk()
            ->assertSee('Mais liberdade')
            ->assertSee('locx-storefront.jpeg')
            ->assertSee('href="/portal/login"', false)
            ->assertSee('href="/login"', false);
    }

    public function test_site_principal_encaminha_para_selecao_de_unidade(): void
    {
        $this->get('https://locx.com.br/')
            ->assertOk()
            ->assertSee('href="/acesso?tipo=equipe"', false)
            ->assertSee('href="/acesso?tipo=cliente"', false);

        $this->get('/acesso?tipo=equipe')
            ->assertOk()
            ->assertSee('Barra da Tijuca')
            ->assertSee('https://admin.locx.com.br/login');

        $this->get('/acesso/equipe/barra')
            ->assertRedirect('https://barra.locx.com.br/login');
        $this->get('/acesso/cliente/barra')
            ->assertRedirect('https://barra.locx.com.br/portal/login');
    }

    public function test_usuario_pode_entrar_e_ver_o_dashboard(): void
    {
        config()->set('branding.use_locx_logo', true);
        $this->seed(ApplicationInitialSeeder::class);

        $this->get('/login')
            ->assertOk()
            ->assertSee('assets/img/locx-logo.svg');

        $this->post('/login', [
            'email' => 'admin@example.com',
            'senha' => '123456',
        ])->assertRedirect('/painel');

        $this->assertAuthenticatedAs(User::where('email', 'admin@example.com')->first());
        $this->get('/login')
            ->assertOk()
            ->assertSee('login-card', false);

        $this->get('/painel')
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('assets/img/locx-logo.svg');
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
