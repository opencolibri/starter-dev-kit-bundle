<?php

declare(strict_types=1);

namespace OpenColibri\Starter\DevKitBundle\Import;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Hands an uploaded image or zip over to castor-api: the file goes to the .uploads directory of the
 * .castor/import/var directory, and the dataset tasks (sylius:dataset:product, :collection, :import) receive its name.
 * They remove it once used.
 */
final readonly class DatasetUploadStorage
{
    private const array IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/avif' => 'avif'];

    private const array ZIP_TYPES = ['application/zip' => 'zip', 'application/x-zip-compressed' => 'zip', 'application/x-zip' => 'zip'];

    public function __construct(
        #[Autowire('%opencolibri_dev_kit.import_var_dir%')]
        private string $importVarDir,
    ) {}

    /**
     * @return string the name of the upload, to pass to the task
     */
    public function saveImage(UploadedFile $file): string
    {
        return $this->save($file, self::IMAGE_TYPES, 'The image must be a JPEG, PNG, GIF, WebP or AVIF file.');
    }

    public function saveZip(UploadedFile $file): string
    {
        return $this->save($file, self::ZIP_TYPES, 'The file must be a zip archive.');
    }

    /**
     * Removes an upload the task did not use (it failed before).
     */
    public function remove(string $upload): void
    {
        $path = $this->directory() . '/' . basename($upload);

        if (is_file($path)) {
            unlink($path);
        }
    }

    /**
     * @param array<string, string> $types MIME type => extension
     */
    private function save(UploadedFile $file, array $types, string $error): string
    {
        if (!$file->isValid()) {
            throw new \RuntimeException(\sprintf('The upload failed: %s', $file->getErrorMessage()));
        }

        // The content decides, not the name or the type the browser sent.
        $type = (new \finfo(\FILEINFO_MIME_TYPE))->file($file->getPathname());
        $extension = $types[\is_string($type) ? $type : ''] ?? throw new \RuntimeException($error);
        $directory = $this->directory();

        if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException(\sprintf('Could not create the upload directory %s.', $directory));
        }

        $name = bin2hex(random_bytes(12)) . '.' . $extension;
        $file->move($directory, $name);

        return $name;
    }

    private function directory(): string
    {
        return rtrim($this->importVarDir, '/\\') . '/.uploads';
    }
}
