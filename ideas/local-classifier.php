<?php

declare(strict_types=1);

/**
 * PHP translation of local-classifier.ts from ClawRouter.
 *
 * This is intentionally dependency-injected so it can mirror the original TS logic
 * without forcing one specific app/framework structure.
 */

/**
 * Pure local complexity classification (no model call).
 *
 * @param string      $prompt
 * @param string|null $systemPrompt
 * @param callable    $classifyByRules function(
 *     string $prompt,
 *     ?string $systemPrompt,
 *     int $estimatedTokens,
 *     array $scoringConfig
 * ): array
 * @param array       $defaultRoutingConfig Must include ['scoring' => ...]
 *
 * @return array ScoringResult-like payload
 */
function classify_locally(
    string $prompt,
    ?string $systemPrompt,
    callable $classifyByRules,
    array $defaultRoutingConfig
): array {
    $fullText = trim(($systemPrompt ?? '') . ' ' . $prompt);
    $estimatedTokens = (int) ceil(strlen($fullText) / 4);

    return $classifyByRules(
        $prompt,
        $systemPrompt,
        $estimatedTokens,
        $defaultRoutingConfig['scoring'] ?? []
    );
}

/**
 * Full local route decision (tier + model selection), still no LLM classification.
 *
 * @param string      $prompt
 * @param array       $options {
 *     @type array       $modelPricing   Required map/array of model pricing
 *     @type string|null $systemPrompt   Optional system prompt
 *     @type int|null    $maxOutputTokens Defaults to 4096
 *     @type array       $configOverrides Optional config overrides
 * }
 * @param callable    $route function(
 *     string $prompt,
 *     ?string $systemPrompt,
 *     int $maxOutputTokens,
 *     array $context
 * ): array
 * @param array       $defaultRoutingConfig
 *
 * @return array RoutingDecision-like payload
 */
function route_locally_no_llm(
    string $prompt,
    array $options,
    callable $route,
    array $defaultRoutingConfig
): array {
    if (!isset($options['modelPricing'])) {
        throw new InvalidArgumentException('options["modelPricing"] is required');
    }

    $configOverrides = $options['configOverrides'] ?? [];
    $config = array_replace_recursive($defaultRoutingConfig, $configOverrides);

    return $route(
        $prompt,
        $options['systemPrompt'] ?? null,
        (int) ($options['maxOutputTokens'] ?? 4096),
        [
            'config' => $config,
            'modelPricing' => $options['modelPricing'],
        ]
    );
}
