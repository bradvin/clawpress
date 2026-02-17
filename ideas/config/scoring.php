<?php

declare(strict_types=1);

$keywords = require __DIR__ . '/keywords.php';

return [
	'tokenCountThresholds'   => [
		'simple'  => 50,
		'complex' => 500,
	],

	'codeKeywords'           => $keywords['codeKeywords'],
	'reasoningKeywords'      => $keywords['reasoningKeywords'],
	'simpleKeywords'         => $keywords['simpleKeywords'],
	'technicalKeywords'      => $keywords['technicalKeywords'],
	'creativeKeywords'       => $keywords['creativeKeywords'],
	'imperativeVerbs'        => $keywords['imperativeVerbs'],
	'constraintIndicators'   => $keywords['constraintIndicators'],
	'outputFormatKeywords'   => $keywords['outputFormatKeywords'],
	'referenceKeywords'      => $keywords['referenceKeywords'],
	'negationKeywords'       => $keywords['negationKeywords'],
	'domainSpecificKeywords' => $keywords['domainSpecificKeywords'],
	'agenticTaskKeywords'    => $keywords['agenticTaskKeywords'],

	'dimensionWeights'       => [
		'tokenCount'          => 0.08,
		'codePresence'        => 0.15,
		'reasoningMarkers'    => 0.18,
		'technicalTerms'      => 0.10,
		'creativeMarkers'     => 0.05,
		'simpleIndicators'    => 0.02,
		'multiStepPatterns'   => 0.12,
		'questionComplexity'  => 0.05,
		'imperativeVerbs'     => 0.03,
		'constraintCount'     => 0.04,
		'outputFormat'        => 0.03,
		'referenceComplexity' => 0.02,
		'negationComplexity'  => 0.01,
		'domainSpecificity'   => 0.02,
		'agenticTask'         => 0.04,
	],

	'tierBoundaries'         => [
		'simpleMedium'     => 0.0,
		'mediumComplex'    => 0.18,
		'complexReasoning' => 0.4,
	],

	'confidenceSteepness'    => 12,
	'confidenceThreshold'    => 0.7,
];
