<?php

declare(strict_types=1);

return [
	'SIMPLE'    => [
		'primary'  => 'nvidia/kimi-k2.5',
		'fallback' => [
			'google/gemini-2.5-flash',
			'nvidia/gpt-oss-120b',
			'nvidia/gpt-oss-20b',
			'deepseek/deepseek-chat',
		],
	],
	'MEDIUM'    => [
		'primary'  => 'xai/grok-code-fast-1',
		'fallback' => [
			'xai/grok-4-1-fast-non-reasoning',
			'deepseek/deepseek-chat',
			'google/gemini-2.5-flash',
		],
	],
	'COMPLEX'   => [
		'primary'  => 'google/gemini-2.5-pro',
		'fallback' => [
			'openai/gpt-5.2',
			'anthropic/claude-sonnet-4',
			'xai/grok-4-0709',
			'openai/gpt-4o',
		],
	],
	'REASONING' => [
		'primary'  => 'xai/grok-4-1-fast-reasoning',
		'fallback' => [
			'xai/grok-4-fast-reasoning',
			'openai/o3',
			'deepseek/deepseek-reasoner',
			'moonshot/kimi-k2.5',
		],
	],
];
