<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;

class PixQrCode
{
    public static function dataUri(?string $pixCopiaCola, ?string $imagemAtual = null, int $tamanho = 420): ?string
    {
        if (blank($pixCopiaCola) && blank($imagemAtual)) {
            return null;
        }

        if (filled($pixCopiaCola)) {
            return self::gerarSvgDataUri((string) $pixCopiaCola, $tamanho);
        }

        if (Str::startsWith((string) $imagemAtual, ['http://', 'https://', 'data:image/'])) {
            return $imagemAtual;
        }

        return null;
    }

    public static function gerarSvgDataUri(string $conteudo, int $tamanho = 420): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($tamanho, 4),
            new SvgImageBackEnd
        );

        $svg = (new Writer($renderer))->writeString($conteudo);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
