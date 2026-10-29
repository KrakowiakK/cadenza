<?php

declare(strict_types=1);

namespace App\Connection;

/**
 * The services the studio can be connected to: model APIs (any OpenAI-compatible chat API with tool calling) with the
 * settings we tested them with, and the services behind single features (image search, image generation).
 */
final class Providers
{
    /**
     * Model APIs. "roles" are the models we found to work best for each job on that API (absent: the chosen model
     * does everything); "prefer" orders the API's own model list when the owner picks one.
     *
     * @var array<string, array{label: string, baseUrl: string, keyUrl?: string, env?: list<string>, local?: bool, free?: string, roles?: array<string, string>, prefer?: list<string>, models?: string, check?: string}>
     */
    public const MODELS = [
        'ollama-cloud' => [
            'label' => 'Ollama Cloud',
            'baseUrl' => 'https://ollama.com',
            'keyUrl' => 'https://ollama.com/settings/keys',
            'env' => ['OLLAMA_API_KEY', 'OLLAMA_API'],
            'free' => 'free plan — the models Cadenza was tested with',
            'roles' => [
                'main' => 'beam?max_tokens=12000&reasoning_effort=low',
                'fallback' => 'gpt-oss:120b?max_tokens=12000',
                'vision' => 'gemma4:31b?max_tokens=12000',
                'review' => 'gemma4:31b',
                'seeing' => 'gemma4:31b?max_tokens=12000',
                'proofread' => 'gpt-oss:120b',
            ],
        ],
        'openrouter' => [
            'label' => 'OpenRouter',
            'baseUrl' => 'https://openrouter.ai/api',
            'keyUrl' => 'https://openrouter.ai/settings/keys',
            'env' => ['OPENROUTER_API_KEY'],
            'free' => 'free models (:free), 50–1000 requests a day',
            'roles' => [
                'main' => 'nvidia/nemotron-3-ultra-550b-a55b:free?max_tokens=12000',
                'fallback' => 'nvidia/nemotron-3-ultra-550b-a55b:free?max_tokens=12000',
            ],
            'check' => '/v1/key',
        ],
        'huggingface' => [
            'label' => 'Hugging Face',
            'baseUrl' => 'https://router.huggingface.co',
            'keyUrl' => 'https://huggingface.co/settings/tokens',
            'env' => ['HF_TOKEN', 'HUGGINGFACE_API_KEY', 'HUGGING_FACE_HUB_TOKEN'],
            'free' => 'monthly free credits (and the hackathon coupon)',
            'roles' => ['main' => 'Qwen/Qwen3-Coder-480B-A35B-Instruct'],
            'prefer' => ['Qwen/Qwen3-Coder-480B-A35B-Instruct', 'Qwen/Qwen3-235B-A22B-Instruct-2507', 'openai/gpt-oss-120b'],
        ],
        'openai' => [
            'label' => 'OpenAI',
            'baseUrl' => 'https://api.openai.com',
            'keyUrl' => 'https://platform.openai.com/api-keys',
            'env' => ['OPENAI_API_KEY'],
            'prefer' => ['gpt-5-mini', 'gpt-5', 'gpt-4.1-mini', 'gpt-4.1', 'gpt-4o-mini'],
        ],
        'deepseek' => [
            'label' => 'DeepSeek',
            'baseUrl' => 'https://api.deepseek.com',
            'keyUrl' => 'https://platform.deepseek.com/api_keys',
            'env' => ['DEEPSEEK_API_KEY'],
            'prefer' => ['deepseek-chat'],
        ],
        'ollama' => [
            'label' => 'Ollama on this computer',
            'baseUrl' => 'http://127.0.0.1:11434',
            'local' => true,
            'models' => '/api/tags',
        ],
        'lmstudio' => [
            'label' => 'LM Studio on this computer',
            'baseUrl' => 'http://127.0.0.1:1234',
            'local' => true,
        ],
        'custom' => [
            'label' => 'Another OpenAI-compatible API',
            'baseUrl' => '',
        ],
    ];

    /**
     * Services behind single features: the setting they need and how a key is checked.
     *
     * @var array<string, array{label: string, for: string, setting: string, keyUrl: string, env: list<string>, check: string, auth: string}>
     */
    public const SERVICES = [
        'pexels' => [
            'label' => 'Pexels',
            'for' => 'free stock photos the agent offers for your pages',
            'setting' => 'PEXELS_API_KEY',
            'keyUrl' => 'https://www.pexels.com/api/new/',
            'env' => ['PEXELS_API_KEY'],
            'check' => 'https://api.pexels.com/v1/search?query=bread&per_page=1',
            'auth' => '{key}',
        ],
        'unsplash' => [
            'label' => 'Unsplash',
            'for' => 'more free photos to choose from',
            'setting' => 'UNSPLASH_ACCESS_KEY',
            'keyUrl' => 'https://unsplash.com/oauth/applications',
            'env' => ['UNSPLASH_ACCESS_KEY'],
            'check' => 'https://api.unsplash.com/search/photos?query=bread&per_page=1',
            'auth' => 'Client-ID {key}',
        ],
        'huggingface-images' => [
            'label' => 'Hugging Face (pictures)',
            'for' => 'pictures drawn for you (FLUX) when no photo fits',
            'setting' => 'HUGGINGFACE_API_KEY',
            'keyUrl' => 'https://huggingface.co/settings/tokens',
            'env' => ['HUGGINGFACE_API_KEY', 'HF_TOKEN', 'HUGGING_FACE_HUB_TOKEN'],
            'check' => 'https://huggingface.co/api/whoami-v2',
            'auth' => 'Bearer {key}',
        ],
    ];

    /** The settings of each model role: [base URL, API key, model]. */
    public const ROLES = [
        'main' => ['CADENZA_LLM_BASE_URL', 'CADENZA_LLM_API_KEY', 'CADENZA_LLM_MODEL'],
        'fallback' => ['CADENZA_LLM_FALLBACK_BASE_URL', 'CADENZA_LLM_FALLBACK_API_KEY', 'CADENZA_LLM_FALLBACK_MODEL'],
        'vision' => ['CADENZA_LLM_VISION_BASE_URL', 'CADENZA_LLM_VISION_API_KEY', 'CADENZA_LLM_VISION_MODEL'],
    ];

    /** Models of the main API for single jobs (empty: the job uses the main or vision model). */
    public const JOBS = [
        'review' => 'CADENZA_REVIEW_MODEL',
        'seeing' => 'CADENZA_SEEING_MODEL',
        'proofread' => 'CADENZA_PROOFREAD_MODEL',
        'code' => 'CADENZA_CODE_MODEL',
    ];

    /**
     * The provider an API address belongs to (for showing what is connected).
     */
    public static function byBaseUrl(string $baseUrl): ?string
    {
        $host = parse_url($baseUrl, \PHP_URL_HOST);
        $port = parse_url($baseUrl, \PHP_URL_PORT);
        foreach (self::MODELS as $id => $provider) {
            if ('' !== $provider['baseUrl'] && parse_url($provider['baseUrl'], \PHP_URL_HOST) === $host && (empty($provider['local']) || parse_url($provider['baseUrl'], \PHP_URL_PORT) === $port)) {
                return $id;
            }
        }
        if (\in_array($host, ['127.0.0.1', 'localhost'], true)) {
            return 11434 === $port ? 'ollama' : 'lmstudio';
        }

        return null === $host ? null : 'custom';
    }
}
