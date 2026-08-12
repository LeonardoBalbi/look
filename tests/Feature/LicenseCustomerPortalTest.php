<?php

namespace Tests\Feature;

use App\Models\LicencaPortalCliente;
use App\Models\LicencaPortalLicenca;
use App\Models\LicencaPortalPagamento;
use App\Models\LicencaPortalPlano;
use App\Models\User;
use App\Notifications\LicencaClienteResetPasswordNotification;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LicenseCustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationInitialSeeder::class);
    }

    public function test_cliente_entra_e_visualiza_somente_seus_dados(): void
    {
        [$cliente, $licenca, $pagamento] = $this->cenario();
        $outro = LicencaPortalCliente::create([
            'nome' => 'Outra Empresa', 'email' => 'outra@example.com', 'senha' => Hash::make('12345678'),
            'portal_ativo' => true, 'status' => 'ativo',
        ]);
        LicencaPortalPagamento::create([
            'licenca_id' => $licenca->id, 'cliente_id' => $outro->id, 'plano_id' => $licenca->plano_id,
            'referencia_externa' => 'OUTRA-REF', 'gateway' => 'manual', 'valor_centavos' => 9900,
            'status' => 'pendente', 'meses_renovacao' => 1,
        ]);

        $this->post('/minha-licenca/login', ['email' => $cliente->email, 'senha' => 'Senha#Cliente2026'])
            ->assertRedirect('/minha-licenca');
        $this->assertAuthenticatedAs($cliente, 'licenca_cliente');
        $this->get('/minha-licenca')->assertOk()
            ->assertSee('Plano Profissional')
            ->assertSee('Pagar agora')
            ->assertSee($pagamento->link_pagamento)
            ->assertDontSee('OUTRA-REF');
    }

    public function test_visitante_e_direcionado_ao_login_da_licenca(): void
    {
        $this->get('/minha-licenca')->assertRedirect('/minha-licenca/login');
    }

    public function test_cliente_solicita_renovacao_e_super_admin_insere_link(): void
    {
        [$cliente, $licenca] = $this->cenario(false);
        $this->actingAs($cliente, 'licenca_cliente')->post('/minha-licenca/renovacoes', [
            'licenca_id' => $licenca->id,
            'meses' => 3,
        ])->assertSessionHas('success');

        $pagamento = LicencaPortalPagamento::firstOrFail();
        $this->assertSame(59970, $pagamento->valor_centavos);
        $this->assertSame('pendente', $pagamento->status);

        $admin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $this->actingAs($admin)->post('/licencas-portal/pagamentos', [
            'id' => $pagamento->id,
            'licenca_id' => $licenca->id,
            'plano_id' => $licenca->plano_id,
            'gateway' => 'asaas',
            'referencia_externa' => $pagamento->referencia_externa,
            'valor' => '599.70',
            'status' => 'pendente',
            'meses_renovacao' => 3,
            'vencimento' => now()->addDays(5)->format('Y-m-d'),
            'link_pagamento' => 'https://pay.example.com/cobranca-123',
        ])->assertRedirect();

        $this->assertDatabaseHas('licenca_portal_pagamentos', [
            'id' => $pagamento->id,
            'gateway' => 'asaas',
            'link_pagamento' => 'https://pay.example.com/cobranca-123',
        ]);
    }

    public function test_cliente_nao_consegue_solicitar_para_licenca_de_outra_empresa(): void
    {
        [$cliente] = $this->cenario(false);
        $outro = LicencaPortalCliente::create(['nome' => 'Outra', 'status' => 'ativo']);
        $plano = LicencaPortalPlano::firstOrFail();
        $licencaOutra = LicencaPortalLicenca::create([
            'cliente_id' => $outro->id, 'plano_id' => $plano->id, 'chave' => 'RENTAL-OUTRA',
            'status' => 'ativa', 'tolerancia_offline_dias' => 7,
        ]);

        $this->actingAs($cliente, 'licenca_cliente')->post('/minha-licenca/renovacoes', [
            'licenca_id' => $licencaOutra->id, 'meses' => 1,
        ])->assertNotFound();
        $this->assertDatabaseCount('licenca_portal_pagamentos', 0);
    }

    public function test_cliente_recupera_senha_do_portal(): void
    {
        Notification::fake();
        [$cliente] = $this->cenario(false);
        $token = null;

        $this->post('/minha-licenca/esqueci-senha', ['email' => $cliente->email])->assertSessionHas('status');
        Notification::assertSentTo($cliente, LicencaClienteResetPasswordNotification::class, function ($notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });
        $this->post('/minha-licenca/redefinir-senha', [
            'token' => $token, 'email' => $cliente->email,
            'senha' => 'NovaSenha#Cliente', 'senha_confirmation' => 'NovaSenha#Cliente',
        ])->assertRedirect('/minha-licenca/login');
        $this->assertTrue(Hash::check('NovaSenha#Cliente', $cliente->fresh()->senha));
    }

    private function cenario(bool $comPagamento = true): array
    {
        $cliente = LicencaPortalCliente::create([
            'nome' => 'Empresa Cliente', 'email' => 'empresa@example.com',
            'senha' => Hash::make('Senha#Cliente2026'), 'portal_ativo' => true, 'status' => 'ativo',
        ]);
        $plano = LicencaPortalPlano::create([
            'codigo' => 'profissional', 'nome' => 'Plano Profissional', 'preco_centavos' => 19990,
            'max_lojas' => 5, 'max_usuarios' => 20, 'modulos_json' => ['crm', 'pix'], 'ativo' => true,
        ]);
        $licenca = LicencaPortalLicenca::create([
            'cliente_id' => $cliente->id, 'plano_id' => $plano->id, 'chave' => 'RENTAL-CLIENTE-PORTAL',
            'status' => 'ativa', 'vence_em' => now()->addMonth(), 'tolerancia_offline_dias' => 7,
        ]);
        $pagamento = null;
        if ($comPagamento) {
            $pagamento = LicencaPortalPagamento::create([
                'licenca_id' => $licenca->id, 'cliente_id' => $cliente->id, 'plano_id' => $plano->id,
                'referencia_externa' => 'CLIENTE-PAY-1', 'gateway' => 'asaas', 'valor_centavos' => 19990,
                'status' => 'pendente', 'meses_renovacao' => 1, 'vencimento' => now()->addDays(5),
                'link_pagamento' => 'https://pay.example.com/cobranca-1',
            ]);
        }

        return [$cliente, $licenca, $pagamento];
    }
}
