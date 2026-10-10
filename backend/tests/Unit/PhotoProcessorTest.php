<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\ValidationException;
use App\Services\Athletes\PhotoProcessor;
use PHPUnit\Framework\TestCase;

final class PhotoProcessorTest extends TestCase
{
    protected function setUp(): void
    {
        if (!function_exists('imagecreatetruecolor')) {
            self::markTestSkipped('GD não instalado.');
        }
    }

    private function png(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, (int) imagecolorallocate($img, 200, 30, 30));
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    private function jpegWithExif(): string
    {
        $img = imagecreatetruecolor(300, 200);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        // Segmento APP1 "Exif" falso logo após o SOI, como uma câmera faria.
        $payload = "Exif\0\0GPS-LATITUDE-SECRET";
        $app1 = "\xFF\xE1" . pack('n', strlen($payload) + 2) . $payload;

        return substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2);
    }

    public function testConvertsToSquareJpegFromDataUrl(): void
    {
        $out = (new PhotoProcessor())->process('data:image/png;base64,' . base64_encode($this->png(800, 500)));

        $info = getimagesizefromstring($out);
        self::assertSame(IMAGETYPE_JPEG, $info[2]);
        self::assertSame([PhotoProcessor::SIZE, PhotoProcessor::SIZE], [$info[0], $info[1]]);
    }

    public function testStripsExifMetadata(): void
    {
        $input = $this->jpegWithExif();
        self::assertStringContainsString('GPS-LATITUDE-SECRET', $input);

        $out = (new PhotoProcessor())->process(base64_encode($input));

        self::assertStringNotContainsString('GPS-LATITUDE-SECRET', $out);
        self::assertStringNotContainsString('Exif', $out);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidInputs(): iterable
    {
        yield 'não é base64' => ['%%%não-é-base64%%%'];
        yield 'texto qualquer' => [base64_encode('<?php echo 1; ?>')];
        yield 'svg' => ['data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')];
        yield 'html disfarçado' => ['data:image/png;base64,' . base64_encode('<html><script>alert(1)</script></html>')];
        yield 'gif (não permitido)' => [base64_encode(base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='))];
    }

    /** @dataProvider invalidInputs */
    public function testRejectsNonImages(string $input): void
    {
        $this->expectException(ValidationException::class);
        (new PhotoProcessor())->process($input);
    }

    public function testRejectsTinyImage(): void
    {
        $this->expectException(ValidationException::class);
        (new PhotoProcessor())->process(base64_encode($this->png(20, 20)));
    }

    public function testRejectsOversizedPayload(): void
    {
        $this->expectException(ValidationException::class);
        (new PhotoProcessor())->process(base64_encode(str_repeat('a', PhotoProcessor::MAX_INPUT_BYTES + 1)));
    }
}
