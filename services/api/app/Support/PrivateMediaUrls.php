<?php
namespace App\Support;
use Aws\S3\S3Client;
class PrivateMediaUrls {
    public static function signedUrl(string $operation, string $key, array $extra = []): string {
        $config = config('filesystems.disks.s3');
        $client = new S3Client([
            'version' => 'latest', 'region' => $config['region'],
            'endpoint' => env('AWS_PUBLIC_ENDPOINT'),
            'use_path_style_endpoint' => true,
            'credentials' => ['key' => $config['key'], 'secret' => $config['secret']],
        ]);
        $command = $client->getCommand($operation, array_merge(['Bucket' => $config['bucket'], 'Key' => $key], $extra));
        return (string) $client->createPresignedRequest($command, $operation === 'PutObject' ? '+10 minutes' : '+5 minutes')->getUri();
    }
}