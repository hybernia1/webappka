<?php

namespace Web\App;

class UploadManager
{
    private string $basePath;
    private string $publicPrefix;

    public function __construct(?string $basePath = null, string $publicPrefix = '/uploads')
    {
        $this->basePath = $basePath ?: dirname(__DIR__, 2) . '/uploads';
        $this->publicPrefix = rtrim($publicPrefix, '/');
    }

    public function storeImage(array $file): string
    {
        return $this->store($file, 'images');
    }

    public function storeFile(array $file): string
    {
        return $this->store($file, 'files');
    }

    private function store(array $file, string $type): string
    {
        $this->assertValidUpload($file);

        $dateSegment = $this->getDateSegment();
        $targetDir = $this->basePath . '/' . $type . '/' . $dateSegment;

        if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Nepodařilo se vytvořit adresář pro upload.');
        }

        $safeName = $this->slugify(pathinfo($file['name'], PATHINFO_FILENAME));
        $extension = $this->resolveExtension($file['name']);
        $filename = $safeName !== '' ? $safeName : 'soubor';
        $uniqueName = $filename . '-' . uniqid('', true) . ($extension ? '.' . $extension : '');

        $destination = $targetDir . '/' . $uniqueName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Upload souboru selhal.');
        }

        return $this->publicPrefix . '/' . $type . '/' . $dateSegment . '/' . $uniqueName;
    }

    private function assertValidUpload(array $file): void
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \InvalidArgumentException('Neplatný formát uploadu.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new \RuntimeException('Nebyl vybrán žádný soubor.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \RuntimeException('Soubor je příliš velký.');
            default:
                throw new \RuntimeException('Upload se nepodařil kvůli chybě serveru.');
        }
    }

    private function getDateSegment(): string
    {
        $now = new \DateTimeImmutable('now');
        return $now->format('Y') . '/' . $now->format('m');
    }

    private function slugify(string $text): string
    {
        $text = preg_replace('~[\p{L}\p{N}]+~u', '$0', $text);
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');

        return $text;
    }

    private function resolveExtension(string $filename): string
    {
        $ext = pathinfo($filename, PATHINFO_EXTENSION);

        if ($ext !== '') {
            return strtolower($ext);
        }

        return '';
    }
}
