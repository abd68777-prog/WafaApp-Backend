<?php

namespace App\Support\Cloudinary;

use DateTimeInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Throwable;

/**
 * Stores the app's images — shop logos and payment proofs — on Cloudinary
 * and serves them from its CDN, through Laravel's Storage like any disk.
 *
 * Talks to Cloudinary's REST API with Laravel's HTTP client: the official SDK
 * still needs Guzzle 7, and this app runs on Guzzle 8.
 *
 * A stored path keeps its extension (`merchants/logos/abc.webp`); Cloudinary's
 * public id is the path without it, and the delivery URL is the path itself.
 * Only what the app does is supported: write, delete, check and link.
 */
final class CloudinaryAdapter implements FilesystemAdapter
{
    private const API = 'https://api.cloudinary.com/v1_1/';

    private const DELIVERY = 'https://res.cloudinary.com/';

    public function __construct(
        private readonly string $cloudName,
        private readonly string $apiKey,
        private readonly string $apiSecret,
    ) {}

    /**
     * From `cloudinary://<api_key>:<api_secret>@<cloud_name>`, the form the
     * Cloudinary console gives as CLOUDINARY_URL.
     */
    public static function fromUrl(string $url): self
    {
        $parts = parse_url($url);

        if (($parts['scheme'] ?? null) !== 'cloudinary' || empty($parts['host']) || empty($parts['user']) || empty($parts['pass'])) {
            throw new InvalidArgumentException('CLOUDINARY_URL must look like cloudinary://<api_key>:<api_secret>@<cloud_name>.');
        }

        return new self($parts['host'], urldecode($parts['user']), urldecode($parts['pass']));
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->upload($path, $contents);
    }

    public function delete(string $path): void
    {
        try {
            $this->signedPost('image/destroy', ['public_id' => $this->publicId($path), 'invalidate' => 'true'])->throw();
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function fileExists(string $path): bool
    {
        try {
            $response = Http::withBasicAuth($this->apiKey, $this->apiSecret)
                ->acceptJson()
                ->get(self::API.$this->cloudName.'/resources/image/upload/'.$this->publicId($path));
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }

        if ($response->status() === 404) {
            return false;
        }

        if (! $response->successful()) {
            throw UnableToCheckExistence::forLocation($path);
        }

        return true;
    }

    /**
     * The CDN address of a stored image, used by `Storage::url()`.
     */
    public function getUrl(string $path): string
    {
        return self::DELIVERY.$this->cloudName.'/image/upload/'.ltrim($path, '/');
    }

    /**
     * Images are delivered publicly, so a "temporary" link is the CDN link.
     *
     * @param  array<string, mixed>  $options
     */
    public function getTemporaryUrl(string $path, DateTimeInterface $expiration, array $options = []): string
    {
        return $this->getUrl($path);
    }

    public function directoryExists(string $path): bool
    {
        return false;
    }

    public function read(string $path): string
    {
        throw UnableToReadFile::fromLocation($path, 'Cloudinary images are read from their URL.');
    }

    public function readStream(string $path)
    {
        throw UnableToReadFile::fromLocation($path, 'Cloudinary images are read from their URL.');
    }

    public function deleteDirectory(string $path): void
    {
        throw UnableToDeleteDirectory::atLocation($path, 'Not supported on Cloudinary.');
    }

    public function createDirectory(string $path, Config $config): void
    {
        throw UnableToCreateDirectory::atLocation($path, 'Not supported on Cloudinary.');
    }

    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Cloudinary images are always public.');
    }

    public function visibility(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::visibility($path, 'Not supported on Cloudinary.');
    }

    public function mimeType(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::mimeType($path, 'Not supported on Cloudinary.');
    }

    public function lastModified(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::lastModified($path, 'Not supported on Cloudinary.');
    }

    public function fileSize(string $path): FileAttributes
    {
        throw UnableToRetrieveMetadata::fileSize($path, 'Not supported on Cloudinary.');
    }

    public function listContents(string $path, bool $deep): iterable
    {
        return [];
    }

    public function move(string $source, string $destination, Config $config): void
    {
        throw UnableToMoveFile::fromLocationTo($source, $destination);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        throw UnableToCopyFile::fromLocationTo($source, $destination);
    }

    /**
     * @param  string|resource  $contents
     */
    private function upload(string $path, $contents): void
    {
        try {
            $this->signedPost('image/upload', ['public_id' => $this->publicId($path)], $contents, basename($path))->throw();
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    /**
     * A request signed as Cloudinary expects: every parameter but the file and
     * the key, sorted, joined as a query string, followed by the secret, SHA-1.
     *
     * @param  array<string, string>  $params
     * @param  string|resource|null  $file
     */
    private function signedPost(string $endpoint, array $params, $file = null, ?string $filename = null): Response
    {
        $params['timestamp'] = (string) now()->getTimestamp();
        ksort($params);
        $params['signature'] = sha1(urldecode(http_build_query($params)).$this->apiSecret);
        $params['api_key'] = $this->apiKey;

        $request = Http::acceptJson()->timeout(60);

        if ($file !== null) {
            $request = $request->attach('file', $file, $filename);
        } else {
            $request = $request->asForm();
        }

        return $request->post(self::API.$this->cloudName.'/'.$endpoint, $params);
    }

    private function publicId(string $path): string
    {
        $path = ltrim($path, '/');
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension === '' ? $path : substr($path, 0, -strlen($extension) - 1);
    }
}
