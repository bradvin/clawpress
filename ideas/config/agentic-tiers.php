<?php

declare(strict_types=1);

return [
    'SIMPLE' => [
        'primary' => 'moonshot/kimi-k2.5',
        'fallback' => [
            'anthropic/claude-haiku-4.5',
            'xai/grok-4-fast-non-reasoning',
            'openai/gpt-4o-mini',
        ],
    ],
    'MEDIUM' => [
        'primary' => 'xai/grok-code-fast-1',
        'fallback' => [
            'moonshot/kimi-k2.5',
            'anthropic/claude-haiku-4.5',
            'anthropic/claude-sonnet-4',
        ],
    ],
    'COMPLEX' => [
        'primary' => 'anthropic/claude-sonnet-4',
        'fallback' => [
            'anthropic/claude-opus-4.5',
            'openai/gpt-5.2',
            'xai/grok-4-0709',
        ],
    ],
    'REASONING' => [
        'primary' => 'anthropic/claude-sonnet-4',
        'fallback' => [
            'xai/grok-4-fast-reasoning',
            'moonshot/kimi-k2.5',
            'deepseek/deepseek-reasoner',
        ],
    ],
];
