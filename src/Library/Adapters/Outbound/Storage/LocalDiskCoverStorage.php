<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Storage;

use Library\Application\Ports\CoverStorage;
use Library\Application\ReadModels\StoredCover;

/**
 * Portadas en disco local (plan D-08). La clave es opaca (32 hex + extensión): nunca una ruta.
 * Cambiar a S3 sería otro adaptador y una línea en la composición (artículo IV, OCP).
 */
final class LocalDiskCoverStorage implements CoverStorage
{
    private const KEY_PATTERN = '/^[a-f0-9]{32}\.(jpg|png)$/';

    public function __construct(private readonly string $directory)
    {
    }

    public function put(string $bytes, string $extension): string
    {
        if (!in_array($extension, ['jpg', 'png'], true)) {
            throw new \InvalidArgumentException('Extensión de portada no admitida.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('No se pudo crear el directorio de portadas.');
        }
        $key = bin2hex(random_bytes(16)) . '.' . $extension;
        if (file_put_contents($this->path($key), $bytes) === false) {
            throw new \RuntimeException('No se pudo guardar la portada.');
        }

        return $key;
    }

    public function get(string $key): ?StoredCover
    {
        if (preg_match(self::KEY_PATTERN, $key) !== 1 || !is_file($this->path($key))) {
            return null;
        }
        $bytes = file_get_contents($this->path($key));
        if ($bytes === false) {
            return null;
        }

        return new StoredCover($bytes, str_ends_with($key, '.png') ? 'image/png' : 'image/jpeg');
    }

    public function delete(string $key): void
    {
        if (preg_match(self::KEY_PATTERN, $key) === 1 && is_file($this->path($key))) {
            unlink($this->path($key));
        }
    }

    private function path(string $key): string
    {
        return rtrim($this->directory, '/\\') . DIRECTORY_SEPARATOR . $key;
    }
}
