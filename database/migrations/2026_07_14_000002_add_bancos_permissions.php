<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissoes = DB::table('usuario_permissoes')
            ->select('usuario_id', 'acao')
            ->whereIn('modulo', ['pagbank', 'asaas', 'sicoob', 'configuracoes'])
            ->distinct()
            ->get();

        foreach ($permissoes as $permissao) {
            DB::table('usuario_permissoes')->updateOrInsert(
                [
                    'usuario_id' => $permissao->usuario_id,
                    'modulo' => 'bancos',
                    'acao' => $permissao->acao,
                ],
                [
                    'usuario_id' => $permissao->usuario_id,
                    'modulo' => 'bancos',
                    'acao' => $permissao->acao,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('usuario_permissoes')
            ->where('modulo', 'bancos')
            ->delete();
    }
};
