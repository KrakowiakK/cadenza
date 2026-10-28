<?php

declare(strict_types=1);

namespace App\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Text-to-image through Hugging Face Inference Providers (FLUX by default). Enabled when HUGGINGFACE_API_KEY is set.
 */
final class ImageGenerator
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly UploadStore $uploads,
        #[Autowire('%env(default::HUGGINGFACE_API_KEY)%')]
        private readonly ?string $apiKey = null,
        #[Autowire('%env(default:cadenza_image_model:CADENZA_IMAGE_MODEL)%')]
        private readonly string $model = 'black-forest-labs/FLUX.1-schnell',
    ) {
    }

    public function isAvailable(): bool
    {
        return '' !== (string) $this->apiKey;
    }

    /**
     * @return array{id: string, url: string, name: string, mime: string, width: int, height: int}
     */
    public function generate(string $prompt, string $orientation = 'landscape'): array
    {
        if (!$this->isAvailable()) {
            throw new \RuntimeException('Image generation is not configured (set HUGGINGFACE_API_KEY).');
        }
        [$width, $height] = match ($orientation) {
            'portrait' => [832, 1216],
            'square' => [1024, 1024],
            default => [1344, 768],
        };
        $response = $this->http->request('POST', 'https://router.huggingface.co/hf-inference/models/'.$this->model, [
            'timeout' => 120,
            'headers' => ['Authorization' => 'Bearer '.$this->apiKey, 'Accept' => 'image/png'],
            'json' => ['inputs' => $prompt, 'parameters' => ['width' => $width, 'height' => $height]],
        ]);

        return $this->uploads->save($response->getContent(), 'generated: '.mb_substr($prompt, 0, 80));
    }
}
