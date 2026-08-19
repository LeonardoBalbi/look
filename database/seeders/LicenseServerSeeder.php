<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class LicenseServerSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) env('INITIAL_ADMIN_PASSWORD', app()->environment('production') ? '' : '123456');
        if ($password === '') {
            throw new \RuntimeException('Defina INITIAL_ADMIN_PASSWORD antes de executar o seeder em produção.');
        }

        DB::table('usuarios')->updateOrInsert(
            ['email' => (string) env('INITIAL_SUPER_ADMIN_EMAIL', 'superadmin@example.com')],
            [
                'nome' => 'Super Administrador de Licenças',
                'senha' => Hash::make($password),
                'perfil' => 'super_admin',
                'loja_id' => null,
                'status' => 'ativo',
            ]
        );
    }
}
