<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Motocicleta extends BaseModel
{
    protected $table = 'motocicletas';

    protected $casts = ['data_aquisicao' => 'date'];

    public static function marcasConhecidas(): array
    {
        return ['Honda', 'Yamaha'];
    }

    public static function modelosConhecidos(): array
    {
        return [
            'Honda CG 160 Start',
            'Honda CG 160 Titan S Flex',
            'Honda CG 160 Fan Flex',
            'Yamaha YS 150 Fazer SED/Flex',
            'Yamaha YBR 150 Factor Flex',
            'Yamaha YBR 150 Factor DX Flex',
            'Yamaha XTZ 150 Crosser S Flex',
            'Yamaha XTZ 150 Crosser Z Flex',
        ];
    }

    public function getMarcaNomeAttribute(): string
    {
        $marca = trim((string) $this->marca);

        return [
            '5' => 'Honda',
            '8' => 'Yamaha',
        ][$marca] ?? $marca;
    }

    public function getModeloNomeAttribute(): string
    {
        $modelo = trim((string) $this->modelo);
        $marca = trim((string) $this->marca);

        if (! preg_match('/^Modelo\s+(\d+)$/i', $modelo, $match)) {
            return $modelo;
        }

        $marcaCodigo = [
            'Honda' => '5',
            'Yamaha' => '8',
        ][$this->marca_nome] ?? $marca;

        $nomes = [
            '5:3' => 'Honda CG 160 Start',
            '5:7' => 'Honda CG 160 Titan S Flex',
            '5:14' => 'Honda CG 160 Fan Flex',
            '8:2' => 'Yamaha YS 150 Fazer SED/Flex',
            '8:4' => 'Yamaha YS 150 Fazer SED/Flex',
            '8:5' => 'Yamaha YBR 150 Factor Flex',
            '8:6' => 'Yamaha YBR 150 Factor Flex',
            '8:9' => 'Yamaha YBR 150 Factor DX Flex',
            '8:12' => 'Yamaha XTZ 150 Crosser S Flex',
            '8:13' => 'Yamaha XTZ 150 Crosser Z Flex',
        ];

        return $nomes[$marcaCodigo.':'.$match[1]] ?? $modelo;
    }

    public function loja(): BelongsTo
    {
        return $this->belongsTo(Loja::class);
    }

    public function ordensServico(): HasMany
    {
        return $this->hasMany(OrdemServico::class);
    }

    public function multasTransito(): HasMany
    {
        return $this->hasMany(MultaTransito::class);
    }
}
