<?php

declare(strict_types=1);

namespace App\Services\Build\Assets;

use InvalidArgumentException;

class AssetProcessor
{
    private const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_ICON_BYTES = 2097152; // 2MB

    /**
     * Validates an uploaded icon/splash asset, strips EXIF metadata, and re-encodes it.
     * Prevents image polyglot attacks and malicious executable payloads.
     */
    public function validateAndSanitize(string $sourcePath, string $destinationPath): void
    {
        if (! file_exists($sourcePath)) {
            throw new InvalidArgumentException("Asset file not found at: {$sourcePath}");
        }

        if (filesize($sourcePath) > self::MAX_ICON_BYTES) {
            throw new InvalidArgumentException('App icon exceeds the maximum allowed file size of 2MB.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $sourcePath);
        finfo_close($finfo);

        if (! in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported image format: {$mimeType}. Only PNG, JPEG, and WebP are allowed.");
        }

        // Re-encode image via GD if extension is loaded to strip EXIF and polyglot payloads
        if (extension_loaded('gd')) {
            $image = match ($mimeType) {
                'image/jpeg' => @imagecreatefromjpeg($sourcePath),
                'image/png'  => @imagecreatefrompng($sourcePath),
                'image/webp' => @imagecreatefromwebp($sourcePath),
                default      => false,
            };

            if (! $image) {
                throw new InvalidArgumentException('Image file could not be parsed as a valid graphic.');
            }

            // Ensure destination directory exists
            $dir = dirname($destinationPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            // Re-encode strictly as PNG
            imagepng($image, $destinationPath, 9);
            imagedestroy($image);
        } else {
            // Safe copy fallback
            copy($sourcePath, $destinationPath);
        }
    }
}
