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

    public function test_installation_does_not_allow_a_second_store(): void
    {
        config()->set('installation.single_store', true);

        $this->seed(ApplicationInitialSeeder::class);
        $admin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();

        $this->assertSame(1, Loja::query()->count());

        $this->actingAs($admin)->post('/lojas', [
            'nome' => 'Segunda loja',
            'cidade' => 'São Paulo',
            'status' => 'ativa',
        ])->assertUnprocessable();

        $this->assertSame(1, Loja::query()->count());
    }
}
