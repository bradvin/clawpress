# local-classifier (TS → PHP) logic notes

This folder captures a direct idea-port of `local-classifier.ts` into PHP.

## What the original TypeScript does

The TS file exposes two helpers and intentionally avoids LLM/API calls.

1. `classifyLocally(prompt, systemPrompt?)`
   - Builds `fullText = systemPrompt + prompt`
   - Estimates token count with `ceil(fullText.length / 4)`
   - Calls `classifyByRules(...)` with:
     - prompt
     - system prompt
     - estimated token count
     - `DEFAULT_ROUTING_CONFIG.scoring`
   - Returns a `ScoringResult`

2. `routeLocallyNoLLM(prompt, options)`
   - Merges `DEFAULT_ROUTING_CONFIG` with optional `configOverrides`
   - Calls `route(...)` with:
     - prompt
     - optional system prompt
     - `maxOutputTokens` defaulting to `4096`
     - context containing merged config + `modelPricing`
   - Returns a `RoutingDecision`

## PHP translation design choices

Because the original TS depends on imported functions/types from ClawRouter, the PHP version uses **dependency injection**:

- `classify_locally(...)` accepts a `$classifyByRules` callable
- `route_locally_no_llm(...)` accepts a `$route` callable
- `DEFAULT_ROUTING_CONFIG` is represented by `$defaultRoutingConfig` array input

This keeps behavior faithful while making the PHP file portable inside this repo.

## Behavioral parity details

- Same token estimate heuristic: `ceil(strlen(fullText) / 4)`
- Same default max output tokens: `4096`
- Same requirement that model pricing is provided
- Same "local-only" intent (no API call path in these helpers)

## Files in this folder

- `local-classifier.ts` — original TS source copied as requested
- `local-classifier.php` — PHP port of the same logic
- `local-classifier.md` — this explanation
