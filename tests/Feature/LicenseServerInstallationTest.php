<?php

namespace Tests\Feature;

use App\Models\Loja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseServerInstallationTest extends TestCase
{
    use RefreshDatabase;

    public function test_portal_seed_creates_only_its_super_admin(): void
    {
        $this->seed();

        $this->assertSame(1, User::query()->count());
        $this->assertSame(1, User::query()->where('perfil', 'super_admin')->count());
        $this->assertSame(0, Loja::query()->count());
    }
}
