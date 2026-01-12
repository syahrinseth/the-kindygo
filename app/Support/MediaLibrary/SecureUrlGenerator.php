<?php

namespace App\Support\MediaLibrary;

use DateTimeInterface;
use Spatie\MediaLibrary\Support\UrlGenerator\BaseUrlGenerator;

class SecureUrlGenerator extends BaseUrlGenerator
{
    /**
     * Get the URL for the media file.
     */
    public function getUrl(): string
    {
        return route('media.show', ['media' => $this->media->id]);
    }

    /**
     * Get the URL for a specific conversion.
     */
    public function getTemporaryUrl(DateTimeInterface $expiration, array $options = []): string
    {
        // For private files, we'll use our secure route instead of temporary URLs
        return $this->getUrl();
    }

    /**
     * Get the URL for the conversion.
     */
    public function getConversionUrl(string $conversionName): string
    {
        return route('media.conversion', [
            'media' => $this->media->id,
            'conversion' => $conversionName,
        ]);
    }

    /**
     * Get the full server path for the media file.
     */
    public function getPath(): string
    {
        // Return the full server path to the file
        return $this->getRootOfDisk().$this->getPathRelativeToRoot();
    }

    /**
     * Get the responsive images URLs.
     */
    public function getResponsiveImagesDirectoryUrl(): string
    {
        return $this->getUrl();
    }

    /**
     * Get the base media directory URL.
     */
    public function getBaseMediaDirectoryUrl(): string
    {
        return route('media.show', ['media' => '']);
    }

    /**
     * Get the root directory of the disk.
     */
    protected function getRootOfDisk(): string
    {
        return $this->getDisk()->path('/');
    }
}
