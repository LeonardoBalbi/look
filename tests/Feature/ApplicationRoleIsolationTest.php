<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ApplicationInitialSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationRoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_application_hides_central_license_portal(): void
    {
        config()->set('application_role.role', 'store');

        $this->get('/licencas-portal')->assertNotFound();
        $this->postJson('/api/licencas-portal/validar-licenca', [])->assertNotFound();
    }

    public function test_license_server_hides_store_operation_and_redirects_home(): void
    {
        config()->set('application_role.role', 'license_server');
        $this->seed(ApplicationInitialSeeder::class);
        $superAdmin = User::query()->where('email', 'superadmin@example.com')->firstOrFail();

        $this->get('/')->assertRedirect('/licencas-portal');
        $this->actingAs($superAdmin)->get('/')->assertRedirect('/licencas-portal');
        $this->actingAs($superAdmin)->get('/?page=crm')->assertRedirect('/licencas-portal');
        $this->actingAs($superAdmin)->post('/motos', [])->assertNotFound();
        $this->actingAs($superAdmin)->get('/licencas-portal')->assertOk();
    }

    public function test_license_server_accepts_only_super_admin_on_central_login(): void
    {
        config()->set('application_role.role', 'license_server');
        $this->seed(ApplicationInitialSeeder::class);

        $this->post('/login', [
            'email' => 'admin@example.com',
            'senha' => '123456',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', [
            'email' => 'superadmin@example.com',
            'senha' => '123456',
        ])->assertRedirect('/licencas-portal');
        $this->assertAuthenticated();
    }
}
