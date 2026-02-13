# local-classifier (TS → PHP) logic notes

Updated port: this is no longer a thin wrapper.

## What was extracted from ClawRouter source

From these files:
- `src/router/config.ts`
- `src/router/rules.ts`
- `src/router/index.ts`
- `src/router/selector.ts`

The PHP file now includes:

1. **Default routing config array**
   - scoring thresholds
   - keyword lists
   - dimension weights
   - tier boundaries
   - confidence params
   - normal tiers + agentic tiers
   - override settings

2. **Rule scoring functions (ported)**
   - token count scoring
   - keyword scoring helper
   - multi-step pattern scoring
   - question complexity scoring
   - agentic task scoring
   - confidence sigmoid calibration

3. **Classifier logic (ported)**
   - 14 dimensions + agentic dimension
   - weighted score aggregation
   - reasoning-keyword override to REASONING tier
   - boundary mapping to SIMPLE/MEDIUM/COMPLEX/REASONING
   - ambiguity fallback when confidence is below threshold

4. **Router logic (ported)**
   - token estimation
   - classify-by-rules call
   - auto/explicit agentic mode switching
   - large-context force-COMPLEX override
   - structured-output minimum-tier upgrade
   - default tier for ambiguous scores

5. **Model selection + cost estimation (ported)**
   - tier primary model selection
   - estimated input/output cost
   - baseline cost vs `anthropic/claude-opus-4`
   - savings percentage

## Files

- `local-classifier.ts` — original TS source (as requested)
- `local-classifier.php` — extracted + ported logic/config
- `local-classifier.md` — this document
