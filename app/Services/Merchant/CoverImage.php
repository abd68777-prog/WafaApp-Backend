<?php

namespace App\Services\Merchant;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Turns an uploaded photo into the shop's cover: cropped around its centre
 * to 2:1, shrunk to at most 1200×600 (never enlarged) and saved as WebP.
 *
 * Drawing the pixels into a new image keeps nothing else from the file, so
 * metadata (a phone's GPS position) and anything appended to the image are
 * gone from what is stored.
 */
final class CoverImage
{
    public const WIDTH = 1200;

    public const HEIGHT = 600;

    private const QUALITY = 80;

    /**
     * @return string The WebP bytes.
     *
     * @throws ValidationException When GD cannot read the file as an image.
     */
    public function fromUpload(UploadedFile $file): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['cover' => 'The cover must be a readable image.']);
        }

        [$cropX, $cropY, $cropWidth, $cropHeight] = $this->centreCrop(imagesx($source), imagesy($source));

        $width = min(self::WIDTH, $cropWidth);
        $height = max(1, intdiv($width, 2));

        $cover = imagecreatetruecolor($width, $height);
        imagecopyresampled($cover, $source, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight);

        ob_start();
        imagewebp($cover, null, self::QUALITY);

        return (string) ob_get_clean();
    }

    /**
     * The largest 2:1 area in the middle of the photo.
     *
     * @return array{int, int, int, int} x, y, width and height.
     */
    private function centreCrop(int $width, int $height): array
    {
        if ($width >= $height * 2) {
            $cropWidth = $height * 2;

            return [intdiv($width - $cropWidth, 2), 0, $cropWidth, $height];
        }

        $cropHeight = max(1, intdiv($width, 2));

        return [0, intdiv($height - $cropHeight, 2), $width, $cropHeight];
    }
}
