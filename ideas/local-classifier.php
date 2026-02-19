<?php

declare(strict_types=1);

/**
 * Extracted/ported from ClawRouter router config + rules + selector + route logic.
 * Local-only classifier/router (no LLM/API calls inside this file).
 */

const DEFAULT_ROUTING_CONFIG = require __DIR__ . '/config/default-routing.php';

function lc_score_token_count( int $estimatedTokens, array $thresholds ): array {
	if ( $estimatedTokens < $thresholds['simple'] ) {
		return [
			'name'   => 'tokenCount',
			'score'  => -1.0,
			'signal' => "short ({$estimatedTokens} tokens)",
		];
	}
	if ( $estimatedTokens > $thresholds['complex'] ) {
		return [
			'name'   => 'tokenCount',
			'score'  => 1.0,
			'signal' => "long ({$estimatedTokens} tokens)",
		];
	}
	return [
		'name'   => 'tokenCount',
		'score'  => 0.0,
		'signal' => null,
	];
}

function lc_score_keyword_match( string $text, array $keywords, string $name, string $label, array $thresholds, array $scores ): array {
	$matches = [];
	foreach ( $keywords as $kw ) {
		if ( str_contains( $text, mb_strtolower( $kw ) ) ) {
			$matches[] = $kw;
		}
	}
	$top = implode( ', ', array_slice( $matches, 0, 3 ) );

	if ( count( $matches ) >= $thresholds['high'] ) {
		return [
			'name'   => $name,
			'score'  => $scores['high'],
			'signal' => "$label ($top)",
		];
	}
	if ( count( $matches ) >= $thresholds['low'] ) {
		return [
			'name'   => $name,
			'score'  => $scores['low'],
			'signal' => "$label ($top)",
		];
	}
	return [
		'name'   => $name,
		'score'  => $scores['none'],
		'signal' => null,
	];
}

function lc_score_multi_step( string $text ): array {
	$patterns = [ '/first.*then/i', '/step\s+\d/i', '/\d\.\s/' ];
	foreach ( $patterns as $p ) {
		if ( preg_match( $p, $text ) ) {
			return [
				'name'   => 'multiStepPatterns',
				'score'  => 0.5,
				'signal' => 'multi-step',
			];
		}
	}
	return [
		'name'   => 'multiStepPatterns',
		'score'  => 0.0,
		'signal' => null,
	];
}

function lc_score_question_complexity( string $prompt ): array {
	$count = substr_count( $prompt, '?' );
	if ( $count > 3 ) {
		return [
			'name'   => 'questionComplexity',
			'score'  => 0.5,
			'signal' => "$count questions",
		];
	}
	return [
		'name'   => 'questionComplexity',
		'score'  => 0.0,
		'signal' => null,
	];
}

function lc_score_agentic_task( string $text, array $keywords ): array {
	$matchCount = 0;
	$signals    = [];
	foreach ( $keywords as $kw ) {
		if ( str_contains( $text, mb_strtolower( $kw ) ) ) {
			++$matchCount;
			if ( count( $signals ) < 3 ) {
				$signals[] = $kw;
			}
		}
	}

	if ( $matchCount >= 4 ) {
		return [
			'dimensionScore' => [
				'name'   => 'agenticTask',
				'score'  => 1.0,
				'signal' => 'agentic (' . implode( ', ', $signals ) . ')',
			],
			'agenticScore'   => 1.0,
		];
	}
	if ( $matchCount >= 3 ) {
		return [
			'dimensionScore' => [
				'name'   => 'agenticTask',
				'score'  => 0.6,
				'signal' => 'agentic (' . implode( ', ', $signals ) . ')',
			],
			'agenticScore'   => 0.6,
		];
	}
	if ( $matchCount >= 1 ) {
		return [
			'dimensionScore' => [
				'name'   => 'agenticTask',
				'score'  => 0.2,
				'signal' => 'agentic-light (' . implode( ', ', $signals ) . ')',
			],
			'agenticScore'   => 0.2,
		];
	}

	return [
		'dimensionScore' => [
			'name'   => 'agenticTask',
			'score'  => 0.0,
			'signal' => null,
		],
		'agenticScore'   => 0.0,
	];
}

function lc_calibrate_confidence( float $distance, float $steepness ): float {
	return 1.0 / ( 1.0 + exp( -$steepness * $distance ) );
}

function classify_by_rules( string $prompt, ?string $systemPrompt, int $estimatedTokens, array $config ): array {
	$text     = mb_strtolower( trim( ( $systemPrompt ?? '' ) . ' ' . $prompt ) );
	$userText = mb_strtolower( $prompt );

	$dimensions = [
		lc_score_token_count( $estimatedTokens, $config['tokenCountThresholds'] ),
		lc_score_keyword_match(
			$text,
			$config['codeKeywords'],
			'codePresence',
			'code',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => 0.5,
				'high' => 1.0,
			]
		),
		lc_score_keyword_match(
			$userText,
			$config['reasoningKeywords'],
			'reasoningMarkers',
			'reasoning',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => 0.7,
				'high' => 1.0,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['technicalKeywords'],
			'technicalTerms',
			'technical',
			[
				'low'  => 2,
				'high' => 4,
			],
			[
				'none' => 0.0,
				'low'  => 0.5,
				'high' => 1.0,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['creativeKeywords'],
			'creativeMarkers',
			'creative',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => 0.5,
				'high' => 0.7,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['simpleKeywords'],
			'simpleIndicators',
			'simple',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => -1.0,
				'high' => -1.0,
			]
		),
		lc_score_multi_step( $text ),
		lc_score_question_complexity( $prompt ),
		lc_score_keyword_match(
			$text,
			$config['imperativeVerbs'],
			'imperativeVerbs',
			'imperative',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => 0.3,
				'high' => 0.5,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['constraintIndicators'],
			'constraintCount',
			'constraints',
			[
				'low'  => 1,
				'high' => 3,
			],
			[
				'none' => 0.0,
				'low'  => 0.3,
				'high' => 0.7,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['outputFormatKeywords'],
			'outputFormat',
			'format',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => 0.4,
				'high' => 0.7,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['referenceKeywords'],
			'referenceComplexity',
			'references',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => 0.3,
				'high' => 0.5,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['negationKeywords'],
			'negationComplexity',
			'negation',
			[
				'low'  => 2,
				'high' => 3,
			],
			[
				'none' => 0.0,
				'low'  => 0.3,
				'high' => 0.5,
			]
		),
		lc_score_keyword_match(
			$text,
			$config['domainSpecificKeywords'],
			'domainSpecificity',
			'domain-specific',
			[
				'low'  => 1,
				'high' => 2,
			],
			[
				'none' => 0.0,
				'low'  => 0.5,
				'high' => 0.8,
			]
		),
	];

	$agenticResult = lc_score_agentic_task( $text, $config['agenticTaskKeywords'] );
	$dimensions[]  = $agenticResult['dimensionScore'];
	$agenticScore  = $agenticResult['agenticScore'];

	$signals = [];
	foreach ( $dimensions as $d ) {
		if ( ! empty( $d['signal'] ) ) {
			$signals[] = $d['signal'];
		}
	}

	$weightedScore = 0.0;
	foreach ( $dimensions as $d ) {
		$w              = $config['dimensionWeights'][ $d['name'] ] ?? 0.0;
		$weightedScore += $d['score'] * $w;
	}

	$reasoningMatches = 0;
	foreach ( $config['reasoningKeywords'] as $kw ) {
		if ( str_contains( $userText, mb_strtolower( $kw ) ) ) {
			++$reasoningMatches;
		}
	}

	if ( $reasoningMatches >= 2 ) {
		$confidence = lc_calibrate_confidence( max( $weightedScore, 0.3 ), $config['confidenceSteepness'] );
		return [
			'score'        => $weightedScore,
			'tier'         => 'REASONING',
			'confidence'   => max( $confidence, 0.85 ),
			'signals'      => $signals,
			'agenticScore' => $agenticScore,
		];
	}

	$b = $config['tierBoundaries'];
	if ( $weightedScore < $b['simpleMedium'] ) {
		$tier     = 'SIMPLE';
		$distance = $b['simpleMedium'] - $weightedScore;
	} elseif ( $weightedScore < $b['mediumComplex'] ) {
		$tier     = 'MEDIUM';
		$distance = min( $weightedScore - $b['simpleMedium'], $b['mediumComplex'] - $weightedScore );
	} elseif ( $weightedScore < $b['complexReasoning'] ) {
		$tier     = 'COMPLEX';
		$distance = min( $weightedScore - $b['mediumComplex'], $b['complexReasoning'] - $weightedScore );
	} else {
		$tier     = 'REASONING';
		$distance = $weightedScore - $b['complexReasoning'];
	}

	$confidence = lc_calibrate_confidence( $distance, $config['confidenceSteepness'] );
	if ( $confidence < $config['confidenceThreshold'] ) {
		return [
			'score'        => $weightedScore,
			'tier'         => null,
			'confidence'   => $confidence,
			'signals'      => $signals,
			'agenticScore' => $agenticScore,
		];
	}

	return [
		'score'        => $weightedScore,
		'tier'         => $tier,
		'confidence'   => $confidence,
		'signals'      => $signals,
		'agenticScore' => $agenticScore,
	];
}

function select_model( string $tier, float $confidence, string $method, string $reasoning, array $tierConfigs, array $modelPricing, int $estimatedInputTokens, int $maxOutputTokens ): array {
	$model   = $tierConfigs[ $tier ]['primary'];
	$pricing = $modelPricing[ $model ] ?? [
		'inputPrice'  => 0.0,
		'outputPrice' => 0.0,
	];

	$costEstimate = ( $estimatedInputTokens / 1_000_000.0 ) * ( $pricing['inputPrice'] ?? 0.0 )
					+ ( $maxOutputTokens / 1_000_000.0 ) * ( $pricing['outputPrice'] ?? 0.0 );

	$opus         = $modelPricing['anthropic/claude-opus-4'] ?? [
		'inputPrice'  => 0.0,
		'outputPrice' => 0.0,
	];
	$baselineCost = ( $estimatedInputTokens / 1_000_000.0 ) * ( $opus['inputPrice'] ?? 0.0 )
					+ ( $maxOutputTokens / 1_000_000.0 ) * ( $opus['outputPrice'] ?? 0.0 );

	$savings = $baselineCost > 0 ? max( 0.0, ( $baselineCost - $costEstimate ) / $baselineCost ) : 0.0;

	return [
		'model'        => $model,
		'tier'         => $tier,
		'confidence'   => $confidence,
		'method'       => $method,
		'reasoning'    => $reasoning,
		'costEstimate' => $costEstimate,
		'baselineCost' => $baselineCost,
		'savings'      => $savings,
	];
}

function route_locally_no_llm( string $prompt, array $options ): array {
	$config = array_replace_recursive( DEFAULT_ROUTING_CONFIG, $options['configOverrides'] ?? [] );

	$systemPrompt    = $options['systemPrompt'] ?? null;
	$maxOutputTokens = (int) ( $options['maxOutputTokens'] ?? 4096 );
	$modelPricing    = $options['modelPricing'] ?? [];

	$fullText        = trim( ( $systemPrompt ?? '' ) . ' ' . $prompt );
	$estimatedTokens = (int) ceil( mb_strlen( $fullText ) / 4 );

	$ruleResult = classify_by_rules( $prompt, $systemPrompt, $estimatedTokens, $config['scoring'] );

	$agenticScore      = (float) ( $ruleResult['agenticScore'] ?? 0.0 );
	$isAutoAgentic     = $agenticScore >= 0.69;
	$isExplicitAgentic = (bool) ( $config['overrides']['agenticMode'] ?? false );
	$useAgenticTiers   = ( $isAutoAgentic || $isExplicitAgentic ) && isset( $config['agenticTiers'] );
	$tierConfigs       = $useAgenticTiers ? $config['agenticTiers'] : $config['tiers'];

	if ( $estimatedTokens > $config['overrides']['maxTokensForceComplex'] ) {
		return select_model(
			'COMPLEX',
			0.95,
			'rules',
			'Input exceeds ' . $config['overrides']['maxTokensForceComplex'] . ' tokens' . ( $useAgenticTiers ? ' | agentic' : '' ),
			$tierConfigs,
			$modelPricing,
			$estimatedTokens,
			$maxOutputTokens
		);
	}

	$hasStructuredOutput = $systemPrompt ? (bool) preg_match( '/json|structured|schema/i', $systemPrompt ) : false;

	$tier       = $ruleResult['tier'] ?? null;
	$confidence = (float) $ruleResult['confidence'];
	$reasoning  = 'score=' . number_format( (float) $ruleResult['score'], 2, '.', '' ) . ' | ' . implode( ', ', $ruleResult['signals'] );

	if ( $tier === null ) {
		$tier       = $config['overrides']['ambiguousDefaultTier'];
		$confidence = 0.5;
		$reasoning .= ' | ambiguous -> default: ' . $tier;
	}

	if ( $hasStructuredOutput ) {
		$rank    = [
			'SIMPLE'    => 0,
			'MEDIUM'    => 1,
			'COMPLEX'   => 2,
			'REASONING' => 3,
		];
		$minTier = $config['overrides']['structuredOutputMinTier'];
		if ( $rank[ $tier ] < $rank[ $minTier ] ) {
			$reasoning .= ' | upgraded to ' . $minTier . ' (structured output)';
			$tier       = $minTier;
		}
	}

	if ( $isAutoAgentic ) {
		$reasoning .= ' | auto-agentic';
	} elseif ( $isExplicitAgentic ) {
		$reasoning .= ' | agentic';
	}

	return select_model( $tier, $confidence, 'rules', $reasoning, $tierConfigs, $modelPricing, $estimatedTokens, $maxOutputTokens );
}
