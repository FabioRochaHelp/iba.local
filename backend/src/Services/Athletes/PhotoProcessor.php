<?php

declare(strict_types=1);

namespace App\Services\Athletes;

use App\Core\Exceptions\ValidationException;
use RuntimeException;

/**
 * Valida e normaliza a foto enviada (data URL ou base64).
 *
 * Segurança: o tipo é conferido pelo CONTEÚDO (não pelo nome/MIME informado),
 * as dimensões são limitadas antes de decodificar (evita "bomba" de pixels) e a
 * imagem é sempre REGRAVADA com o GD em JPEG quadrado — isso descarta EXIF
 * (inclusive GPS) e qualquer conteúdo embutido (arquivos poliglotas).
 */
final class PhotoProcessor
{
    public const SIZE = 400;
    public const MAX_INPUT_BYTES = 750_000;
    private const MAX_SIDE = 5000;
    private const MIN_SIDE = 64;
    private const ALLOWED = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

    /** @return string bytes do JPEG final */
    public function process(string $input): string
    {
        if (!function_exists('imagecreatefromstring')) {
            throw new RuntimeException('Extensão GD do PHP não instalada.');
        }

        $binary = $this->decode($input);
        $info = @getimagesizefromstring($binary);
        if ($info === false || !in_array($info[2], self::ALLOWED, true)) {
            throw $this->invalid('Envie uma imagem JPG, PNG ou WebP.');
        }
        [$width, $height] = $info;
        if ($width > self::MAX_SIDE || $height > self::MAX_SIDE) {
            throw $this->invalid('Imagem grande demais (máx. ' . self::MAX_SIDE . ' px de lado).');
        }
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE) {
            throw $this->invalid('Imagem pequena demais (mín. ' . self::MIN_SIDE . ' px de lado).');
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            throw $this->invalid('Não foi possível ler a imagem.');
        }

        // Recorte quadrado central, redimensionado para SIZE x SIZE.
        $side = min($width, $height);
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagefill($target, 0, 0, (int) imagecolorallocate($target, 255, 255, 255)); // fundo para PNG transparente
        imagecopyresampled(
            $target, $source, 0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side,
        );
        imagedestroy($source);

        ob_start();
        imagejpeg($target, null, 85);
        $jpeg = (string) ob_get_clean();
        imagedestroy($target);

        return $jpeg;
    }

    private function decode(string $input): string
    {
        $input = trim($input);
        if (preg_match('#^data:image/(jpeg|jpg|png|webp);base64,#i', $input, $m) === 1) {
            $input = substr($input, strlen($m[0]));
        } elseif (str_starts_with($input, 'data:')) {
            throw $this->invalid('Envie uma imagem JPG, PNG ou WebP.');
        }

        $binary = base64_decode($input, true);
        if ($binary === false || $binary === '') {
            throw $this->invalid('Imagem inválida.');
        }
        if (strlen($binary) > self::MAX_INPUT_BYTES) {
            throw $this->invalid('Imagem grande demais (máx. 750 KB).');
        }

        return $binary;
    }

    private function invalid(string $message): ValidationException
    {
        return ValidationException::withField('photo', $message);
    }
}
