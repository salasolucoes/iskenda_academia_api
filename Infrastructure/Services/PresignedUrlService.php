<?php

namespace Infrastructure\Services;

use Aws\Credentials\Credentials;
use Aws\S3\S3Client;

class PresignedUrlService
{
    private ?S3Client $client = null;

    public function __construct(
        private readonly string $disk = 'minio',
        private readonly string $duration = '+30 minutes',
    ) {}

    public function generate(string $key): string
    {
        try {
            return (string) $this->getClient()
                ->createPresignedRequest(
                    $this->getClient()->getCommand('GetObject', [
                        'Bucket' => $this->getDiskConfig('bucket'),
                        'Key' => $key,
                    ]),
                    $this->duration,
                )->getUri();
        } catch (\Throwable) {
            return $this->getDiskConfig('url').'/'
                .$this->getDiskConfig('bucket').'/'
                .ltrim($key, '/');
        }
    }

    public function extractKeyFromUrl(string $url, string $prefix): string
    {
        $parsed = parse_url($url);
        $fullPath = $parsed['path'] ?? $url;

        $marker = "/{$prefix}/";
        $pos = strpos($fullPath, $marker);

        if ($pos === false) {
            return ltrim($fullPath, '/');
        }

        return substr($fullPath, $pos + strlen($marker));
    }

    private function getClient(): S3Client
    {
        if ($this->client === null) {
            $this->client = new S3Client([
                'credentials' => new Credentials(
                    $this->getDiskConfig('key'),
                    $this->getDiskConfig('secret'),
                ),
                'region' => $this->getDiskConfig('region'),
                'endpoint' => $this->getDiskConfig('url'),
                'use_path_style_endpoint' => $this->getDiskConfig('use_path_style_endpoint') ?? true,
            ]);
        }

        return $this->client;
    }

    private function getDiskConfig(string $key): mixed
    {
        return config("filesystems.disks.{$this->disk}.{$key}");
    }
}
