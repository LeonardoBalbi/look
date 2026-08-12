<?php

namespace Tests\Feature;

use App\Models\LicencaPortalCliente;
use App\Models\LicencaPortalLicenca;
use App\Models\LicencaPortalPagamento;
use App\Models\LicencaPortalPlano;
use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CommercialLicenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ApplicationInitialSeeder::class);
    }

    public function test_super_admin_edita_troca_plano_bloqueia_e_desbloqueia_licenca(): void
    {
        [$admin, $licenca, $planoNovo] = $this->cenario();

        $this->actingAs($admin)->post('/licencas-portal/licencas', [
            'id' => $licenca->id,
            'cliente_id' => $licenca->cliente_id,
            'plano_id' => $planoNovo->id,
            'chave' => $licenca->chave,
            'status' => 'ativa',
            'vence_em' => '2027-01-31',
            'tolerancia_offline_dias' => 10,
            'renovacao_automatica' => '1',
            'meses_por_renovacao' => 3,
            'mensagem' => 'Plano alterado.',
        ])->assertRedirect();

        $this->assertDatabaseHas('licenca_portal_licencas', [
            'id' => $licenca->id,
            'plano_id' => $planoNovo->id,
            'renovacao_automatica' => 1,
            'meses_por_renovacao' => 3,
        ]);

        $this->actingAs($admin)->post("/licencas-portal/licencas/{$licenca->id}/bloqueio")->assertRedirect();
        $this->assertSame('bloqueada', $licenca->fresh()->status);
        $this->actingAs($admin)->post("/licencas-portal/licencas/{$licenca->id}/bloqueio")->assertRedirect();
        $this->assertSame('ativa', $licenca->fresh()->status);
    }

    public function test_renovacao_manual_registra_pagamento_e_estende_vencimento(): void
    {
        [$admin, $licenca] = $this->cenario();
        $licenca->update(['vence_em' => '2026-09-15']);

        $this->actingAs($admin)->post("/licencas-portal/licencas/{$licenca->id}/renovar", [
            'meses' => 2,
            'valor' => '399.80',
        ])->assertRedirect();

        $this->assertSame('2026-11-15', $licenca->fresh()->vence_em->format('Y-m-d'));
        $this->assertDatabaseHas('licenca_portal_pagamentos', [
            'licenca_id' => $licenca->id,
            'status' => 'pago',
            'valor_centavos' => 39980,
            'meses_renovacao' => 2,
        ]);
        $this->assertNotNull(LicencaPortalPagamento::firstOrFail()->renovado_em);
    }

    public function test_webhook_pago_renova_automaticamente_uma_unica_vez(): void
    {
        [, $licenca] = $this->cenario();
        $licenca->update(['vence_em' => '2026-09-15', 'renovacao_automatica' => true]);
        LicencaPortalPagamento::create([
            'licenca_id' => $licenca->id,
            'cliente_id' => $licenca->cliente_id,
            'plano_id' => $licenca->plano_id,
            'gateway' => 'asaas',
            'referencia_externa' => 'pay_123',
            'valor_centavos' => 19990,
            'status' => 'pendente',
            'meses_renovacao' => 1,
        ]);
        config(['services.license_payments.webhook_token' => 'segredo-teste']);
        $payload = ['external_reference' => 'pay_123', 'status' => 'confirmed'];

        $this->withHeader('X-License-Webhook-Token', 'segredo-teste')
            ->postJson('/api/licencas-portal/webhooks/pagamentos/asaas', $payload)
            ->assertOk()->assertJsonPath('vence_em', '2026-10-15');
        $this->withHeader('X-License-Webhook-Token', 'segredo-teste')
            ->postJson('/api/licencas-portal/webhooks/pagamentos/asaas', $payload)
            ->assertOk()->assertJsonPath('vence_em', '2026-10-15');

        $this->assertSame('2026-10-15', $licenca->fresh()->vence_em->format('Y-m-d'));
    }

    public function test_webhook_exige_token_valido(): void
    {
        config(['services.license_payments.webhook_token' => 'segredo-teste']);
        $this->postJson('/api/licencas-portal/webhooks/pagamentos/asaas', [
            'external_reference' => 'inexistente',
            'status' => 'paid',
        ])->assertUnauthorized();
    }

    public function test_usuario_recupera_senha_com_token_enviado_por_email(): void
    {
        Notification::fake();
        $usuario = User::where('email', 'admin@example.com')->firstOrFail();
        $token = null;

        $this->post('/esqueci-senha', ['email' => $usuario->email])
            ->assertSessionHas('status');
        Notification::assertSentTo($usuario, ResetPasswordNotification::class, function (ResetPasswordNotification $notification) use (&$token): bool {
            $token = $notification->token;

            return true;
        });

        $this->post('/redefinir-senha', [
            'token' => $token,
            'email' => $usuario->email,
            'senha' => 'NovaSenha#2026',
            'senha_confirmation' => 'NovaSenha#2026',
        ])->assertRedirect('/login');

        $this->assertTrue(Hash::check('NovaSenha#2026', $usuario->fresh()->senha));
    }

    private function cenario(): array
    {
        $admin = User::where('email', 'superadmin@example.com')->firstOrFail();
        $cliente = LicencaPortalCliente::create(['nome' => 'Cliente Comercial', 'status' => 'ativo']);
        $plano = LicencaPortalPlano::create([
            'codigo' => 'basico', 'nome' => 'Básico', 'preco_centavos' => 19990,
            'max_lojas' => 1, 'max_usuarios' => 5, 'modulos_json' => ['crm'], 'ativo' => true,
        ]);
        $planoNovo = LicencaPortalPlano::create([
            'codigo' => 'profissional', 'nome' => 'Profissional', 'preco_centavos' => 39990,
            'max_lojas' => 5, 'max_usuarios' => 20, 'modulos_json' => ['crm', 'pix'], 'ativo' => true,
        ]);
        $licenca = LicencaPortalLicenca::create([
            'cliente_id' => $cliente->id, 'plano_id' => $plano->id, 'chave' => 'RENTAL-COMERCIAL-TESTE',
            'status' => 'ativa', 'vence_em' => '2026-09-15', 'tolerancia_offline_dias' => 7,
            'renovacao_automatica' => false, 'meses_por_renovacao' => 1,
        ]);

        return [$admin, $licenca, $planoNovo];
    }
}
