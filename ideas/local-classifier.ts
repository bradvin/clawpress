/**
 * Extracted local-only classifier from ClawRouter.
 *
 * No LLM/API call path:
 * - classifyByRules() from src/router/rules.ts
 * - route() from src/router/index.ts (uses rules + model selector only)
 */

import { classifyByRules } from "../src/router/rules.js";
import { route, type RouterOptions } from "../src/router/index.js";
import { DEFAULT_ROUTING_CONFIG } from "../src/router/config.js";
import type { ScoringResult, RoutingDecision } from "../src/router/types.js";
import type { ModelPricing } from "../src/router/selector.js";

/**
 * Pure local complexity classification (no model call).
 */
export function classifyLocally(prompt: string, systemPrompt?: string): ScoringResult {
  const fullText = `${systemPrompt ?? ""} ${prompt}`;
  const estimatedTokens = Math.ceil(fullText.length / 4);

  return classifyByRules(
    prompt,
    systemPrompt,
    estimatedTokens,
    DEFAULT_ROUTING_CONFIG.scoring,
  );
}

/**
 * Full local route decision (tier + model selection), still no LLM classification.
 *
 * NOTE: you must pass model pricing map.
 */
export function routeLocallyNoLLM(
  prompt: string,
  options: {
    modelPricing: Map<string, ModelPricing>;
    systemPrompt?: string;
    maxOutputTokens?: number;
    configOverrides?: Partial<RouterOptions["config"]>;
  },
): RoutingDecision {
  const config = {
    ...DEFAULT_ROUTING_CONFIG,
    ...options.configOverrides,
  };

  return route(prompt, options.systemPrompt, options.maxOutputTokens ?? 4096, {
    config,
    modelPricing: options.modelPricing,
  });
}
