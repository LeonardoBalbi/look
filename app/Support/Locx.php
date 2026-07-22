<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class Locx
{
    public const MODULOS = [
        'dashboard' => 'Dashboard',
        'reservas' => 'Reservas',
        'crm' => 'CRM',
        'clientes' => 'Clientes',
        'motos' => 'Motocicletas',
        'contratos' => 'Contratos',
        'manutencao' => 'Manutenção',
        'estoque' => 'Estoque',
        'multas' => 'Multas',
        'financeiro' => 'Financeiro',
        'contas' => 'Contas',
        'cobrancas' => 'Cobranças',
        'inadimplencia' => 'Inadimplência',
        'bancos' => 'Bancos PIX',
        'pagbank' => 'PagBank',
        'asaas' => 'Asaas',
        'sicoob' => 'Sicoob',
        'whatsapp' => 'WhatsApp API',
        'telegram' => 'Telegram',
        'relatorios' => 'Relatórios',
        'documentos' => 'Documentos',
        'lojas' => 'Lojas / Unidades',
        'usuarios' => 'Usuários',
        'configuracoes' => 'Configurações',
    ];

    public const MENU_GRUPOS = [
        'Principal' => ['dashboard', 'reservas', 'crm'],
        'Operação' => ['clientes', 'motos', 'contratos', 'manutencao', 'estoque', 'multas'],
        'Financeiro' => ['financeiro', 'contas', 'cobrancas', 'inadimplencia', 'bancos'],
        'Canais' => ['whatsapp', 'telegram', 'documentos'],
        'Gestão' => ['relatorios', 'lojas', 'usuarios', 'configuracoes'],
    ];

    public const ACOES = [
        'visualizar' => 'Visualizar',
        'criar' => 'Criar',
        'editar' => 'Editar',
        'excluir' => 'Excluir',
    ];

    public static function moeda(float|int|string|null $valor): string
    {
        return 'R$ '.number_format((float) $valor, 2, ',', '.');
    }

    public static function perfil(string $perfil): string
    {
        return [
            'administrador_geral' => 'Administrador Geral',
            'diretor' => 'Diretor',
            'financeiro' => 'Financeiro',
            'gerente_loja' => 'Gerente de Loja',
            'atendente' => 'Atendente',
            'cobranca' => 'Cobrança',
        ][$perfil] ?? $perfil;
    }

    public static function asset(string $path): string
    {
        $relative = 'locx/'.ltrim($path, '/');
        $file = public_path(str_replace('/', DIRECTORY_SEPARATOR, $relative));
        $url = asset($relative);

        return is_file($file) ? $url.'?v='.filemtime($file) : $url;
    }

    public static function status(?string $status): HtmlString
    {
        $status ??= '';
        $classe = [
            'ativo' => 'ok', 'ativa' => 'ok', 'disponivel' => 'ok', 'paga' => 'ok',
            'concluida' => 'ok', 'vinculado' => 'ok', 'recebido' => 'ok',
            'alugada' => 'info', 'enviado' => 'info', 'conciliado' => 'ok', 'transferida' => 'info', 'demo' => 'info',
            'aberta' => 'warn', 'parcial' => 'warn', 'pendente' => 'warn', 'agendada' => 'warn', 'processando' => 'warn', 'em_andamento' => 'warn',
            'aguardando_peca' => 'warn', 'em_recurso' => 'warn',
            'suspenso' => 'warn', 'manutencao' => 'warn',
            'inadimplente' => 'danger', 'atrasada' => 'danger', 'bloqueado' => 'danger', 'falha' => 'danger', 'erro' => 'danger',
            'recuperacao' => 'danger', 'inativa' => 'danger',
            'encerrado' => 'muted', 'encerrada' => 'muted', 'cancelada' => 'muted', 'cancelado' => 'muted', 'não vinculado' => 'muted', 'ignorado' => 'muted',
        ][$status] ?? '';

        return new HtmlString('<span class="tag '.$classe.'">'.e($status).'</span>');
    }

    public static function limitarPorLoja(Builder $query, User $user, string $coluna = 'loja_id'): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        $ids = $user->lojaIdsPermitidas();

        return $ids ? $query->whereIn($coluna, $ids) : $query->whereRaw('1 = 0');
    }

    public static function icon(string $modulo): HtmlString
    {
        $paths = [
            'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
            'reservas' => '<path d="M7 3v4"/><path d="M17 3v4"/><rect x="4" y="5" width="16" height="16" rx="2"/><path d="M4 10h16"/><path d="M8 14h3"/><path d="M8 18h6"/>',
            'crm' => '<path d="M4 7h16"/><path d="M4 12h10"/><path d="M4 17h7"/><circle cx="18" cy="15" r="3"/><path d="m20.5 17.5 1.5 1.5"/>',
            'clientes' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20c.7-3.6 2.8-5.4 5.5-5.4s4.8 1.8 5.5 5.4"/><circle cx="17" cy="9" r="2.4"/><path d="M15.3 15.2c2.6.2 4.2 1.7 5.2 4.8"/>',
            'fornecedores' => '<path d="M3 7h18"/><path d="M5 7v12h14V7"/><path d="M8 7V5h8v2"/><path d="M8 12h8"/><path d="M8 16h5"/>',
            'motos' => '<circle cx="6.5" cy="17" r="3"/><circle cx="17.5" cy="17" r="3"/><path d="M9.5 17h4.5l-2-5H9.2l-2.7 5"/><path d="M12 12h3.3l2.2 5"/><path d="M13.7 8h3.8"/><path d="M15.5 8l1.2 4"/>',
            'contratos' => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/><path d="M9.5 13h5"/><path d="M9.5 17h5"/>',
            'manutencao' => '<path d="M14.7 6.3a4 4 0 0 0-5 5L4 17l3 3 5.7-5.7a4 4 0 0 0 5-5l-2.5 2.5-3-3z"/>',
            'estoque' => '<path d="M4 7.5 12 3l8 4.5-8 4.5z"/><path d="M4 7.5v9L12 21l8-4.5v-9"/><path d="M12 12v9"/>',
            'multas' => '<path d="M12 3 22 20H2z"/><path d="M12 9v5"/><path d="M12 17h.01"/><path d="M8.5 20h7"/>',
            'rastreadores' => '<path d="M12 21s7-5.2 7-11a7 7 0 1 0-14 0c0 5.8 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/><path d="M8 3.8 6.5 2"/><path d="M16 3.8 17.5 2"/>',
            'financeiro' => '<path d="M4 19h16"/><path d="M6 16l4-4 3 3 5-7"/><path d="M16 8h2v2"/>',
            'contas' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h10"/><path d="M7 13h5"/><path d="M16 15.5c1.1 0 2-.7 2-1.5s-.9-1.5-2-1.5-2-.7-2-1.5.9-1.5 2-1.5"/><path d="M16 8.5v8"/>',
            'cobrancas' => '<rect x="3" y="6" width="18" height="12" rx="2.5"/><path d="M3 10h18"/><path d="M7 15h5"/>',
            'inadimplencia' => '<path d="M12 3 22 20H2z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'pix' => '<path d="M12 3.5 20.5 12 12 20.5 3.5 12z"/><path d="M8.5 12h7"/><path d="M12 8.5v7"/>',
            'bancos' => '<path d="M4 10 12 4l8 6"/><path d="M5.5 10v9h13v-9"/><path d="M4 19h16"/><path d="M8 15h8"/>',
            'pagbank' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="M3 10h18"/><path d="M7 15h4"/>',
            'asaas' => '<path d="M4 16.5 12 4l8 12.5"/><path d="M7.5 13h9"/><path d="M9.5 17h5"/><path d="M12 4v16"/>',
            'sicoob' => '<path d="M4 10 12 4l8 6"/><path d="M5.5 10v9h13v-9"/><path d="M8 19v-5h3v5"/><path d="M14 19v-5h2"/><path d="M4 19h16"/>',
            'whatsapp' => '<path d="M20.5 11.7a8.5 8.5 0 0 1-12.6 7.4L3.5 20.5l1.4-4.2A8.5 8.5 0 1 1 20.5 11.7z"/><path d="M9 8.8c.2 3.5 2.1 5.4 5.4 6.2l1.5-1.5-2.2-1.2-.8.8c-1.1-.5-2-1.3-2.5-2.5l.8-.8L10 7.5z"/>',
            'telegram' => '<path d="M21 3 3.8 9.7c-1.2.5-1.2 1.2-.2 1.5l4.4 1.4 1.7 5.2c.2.6.1.9.8.9.5 0 .8-.2 1-.4l2.2-2.1 4.5 3.3c.8.5 1.4.3 1.6-.8L22.7 5c.3-1.4-.5-2-1.7-2z"/><path d="m8.1 12.4 10.2-6.3c.5-.3 1-.1.6.3l-8.4 7.6-.3 3.5z"/>',
            'relatorios' => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-5"/><path d="M12 16V8"/><path d="M16 16v-9"/>',
            'bi' => '<path d="M4 19V5"/><path d="M4 19h16"/><rect x="7" y="11" width="3" height="5" rx="1"/><rect x="12" y="7" width="3" height="9" rx="1"/><rect x="17" y="9" width="3" height="7" rx="1"/>',
            'documentos' => '<path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h4"/>',
            'juridico' => '<path d="M12 3v18"/><path d="M5 6h14"/><path d="M6 6l-3 7h6z"/><path d="M18 6l-3 7h6z"/><path d="M8 21h8"/>',
            'auditoria' => '<path d="M9 11l2 2 4-4"/><path d="M20 11.5V7l-8-4-8 4v5c0 5 3.4 8 8 9 4.1-.9 7.3-3.5 8-7.8"/>',
            'usuarios' => '<circle cx="8.5" cy="8" r="3"/><path d="M3 20c.7-3.7 2.8-5.5 5.5-5.5S13.3 16.3 14 20"/><circle cx="16.5" cy="9" r="2.5"/><path d="M15 14.8c2.4.3 4.3 2 5 5.2"/>',
            'lojas' => '<path d="M4 10h16l-1.5-5h-13z"/><path d="M5.5 10v10h13V10"/><path d="M9 20v-6h6v6"/>',
            'configuracoes' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.8 1.8 0 0 0 .3 2l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.8 1.8 0 0 0-2-.3 1.8 1.8 0 0 0-1 1.6V22h-4v-.1a1.8 1.8 0 0 0-1-1.6 1.8 1.8 0 0 0-2 .3l-.1.1A2 2 0 1 1 4 17.9l.1-.1a1.8 1.8 0 0 0 .3-2 1.8 1.8 0 0 0-1.6-1H2v-4h.8a1.8 1.8 0 0 0 1.6-1 1.8 1.8 0 0 0-.3-2L4 7.7A2 2 0 1 1 6.8 4.9l.1.1a1.8 1.8 0 0 0 2 .3 1.8 1.8 0 0 0 1-1.6V3.5h4v.2a1.8 1.8 0 0 0 1 1.6 1.8 1.8 0 0 0 2-.3l.1-.1A2 2 0 1 1 19.8 7.7l-.1.1a1.8 1.8 0 0 0-.3 2 1.8 1.8 0 0 0 1.6 1h1v4h-1a1.8 1.8 0 0 0-1.6.9z"/>',
        ];
        $path = $paths[$modulo] ?? '<circle cx="12" cy="12" r="4"/>';

        return new HtmlString('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$path.'</svg>');
    }
}
