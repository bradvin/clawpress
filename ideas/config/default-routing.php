<?php

declare(strict_types=1);

return [
	'version'      => '2.0',
	'scoring'      => require __DIR__ . '/scoring.php',
	'tiers'        => require __DIR__ . '/tiers.php',
	'agenticTiers' => require __DIR__ . '/agentic-tiers.php',
	'overrides'    => require __DIR__ . '/overrides.php',
];
