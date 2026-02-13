<?php

declare(strict_types=1);

/**
 * Extracted/ported from ClawRouter router config + rules + selector + route logic.
 * Local-only classifier/router (no LLM/API calls inside this file).
 */

const DEFAULT_ROUTING_CONFIG = [
    'version' => '2.0',
    'scoring' => [
        'tokenCountThresholds' => ['simple' => 50, 'complex' => 500],

        // These lists are taken from the source and kept as arrays for local matching.
        'codeKeywords' => ['function','class','import','def','select','async','await','const','let','var','return','```','函数','类','导入','定义','查询','异步','等待','常量','变量','返回','関数','クラス','インポート','非同期','定数','変数','функция','класс','импорт','определ','запрос','асинхронный','ожидать','константа','переменная','вернуть','funktion','klasse','importieren','definieren','abfrage','asynchron','erwarten','konstante','variable','zurückgeben'],
        'reasoningKeywords' => ['prove','theorem','derive','step by step','chain of thought','formally','mathematical','proof','logically','证明','定理','推导','逐步','思维链','形式化','数学','逻辑','証明','定理','導出','ステップバイステップ','論理的','доказать','докажи','доказательств','теорема','вывести','шаг за шагом','пошагово','поэтапно','цепочка рассуждений','рассуждени','формально','математически','логически','beweisen','beweis','theorem','ableiten','schritt für schritt','gedankenkette','formal','mathematisch','logisch'],
        'simpleKeywords' => ['what is','define','translate','hello','yes or no','capital of','how old','who is','when was','什么是','定义','翻译','你好','是否','首都','多大','谁是','何时','とは','定義','翻訳','こんにちは','はいかいいえ','首都','誰','что такое','определение','перевести','переведи','привет','да или нет','столица','сколько лет','кто такой','когда','объясни','was ist','definiere','übersetze','hallo','ja oder nein','hauptstadt','wie alt','wer ist','wann','erkläre'],
        'technicalKeywords' => ['algorithm','optimize','architecture','distributed','kubernetes','microservice','database','infrastructure','算法','优化','架构','分布式','微服务','数据库','基础设施','アルゴリズム','最適化','アーキテクチャ','分散','マイクロサービス','データベース','алгоритм','оптимизировать','оптимизаци','оптимизируй','архитектура','распределённый','микросервис','база данных','инфраструктура','algorithmus','optimieren','architektur','verteilt','kubernetes','mikroservice','datenbank','infrastruktur'],
        'creativeKeywords' => ['story','poem','compose','brainstorm','creative','imagine','write a','故事','诗','创作','头脑风暴','创意','想象','写一个','物語','詩','作曲','ブレインストーム','創造的','想像','история','рассказ','стихотворение','сочинить','сочини','мозговой штурм','творческий','представить','придумай','напиши','geschichte','gedicht','komponieren','brainstorming','kreativ','vorstellen','schreibe','erzählung'],
        'imperativeVerbs' => ['build','create','implement','design','develop','construct','generate','deploy','configure','set up','构建','创建','实现','设计','开发','生成','部署','配置','设置','構築','作成','実装','設計','開発','生成','デプロイ','設定','построить','построй','создать','создай','реализовать','реализуй','спроектировать','разработать','разработай','сконструировать','сгенерировать','сгенерируй','развернуть','разверни','настроить','настрой','erstellen','bauen','implementieren','entwerfen','entwickeln','konstruieren','generieren','bereitstellen','konfigurieren','einrichten'],
        'constraintIndicators' => ['under','at most','at least','within','no more than','o(','maximum','minimum','limit','budget','不超过','至少','最多','在内','最大','最小','限制','预算','以下','最大','最小','制限','予算','не более','не менее','как минимум','в пределах','максимум','минимум','ограничение','бюджет','höchstens','mindestens','innerhalb','nicht mehr als','maximal','minimal','grenze','budget'],
        'outputFormatKeywords' => ['json','yaml','xml','table','csv','markdown','schema','format as','structured','表格','格式化为','结构化','テーブル','フォーマット','構造化','таблица','форматировать как','структурированный','tabelle','formatieren als','strukturiert'],
        'referenceKeywords' => ['above','below','previous','following','the docs','the api','the code','earlier','attached','上面','下面','之前','接下来','文档','代码','附件','上記','下記','前の','次の','ドキュメント','コード','выше','ниже','предыдущий','следующий','документация','код','ранее','вложение','oben','unten','vorherige','folgende','dokumentation','der code','früher','anhang'],
        'negationKeywords' => ["don't",'do not','avoid','never','without','except','exclude','no longer','不要','避免','从不','没有','除了','排除','しないで','避ける','決して','なしで','除く','не делай','не надо','нельзя','избегать','никогда','без','кроме','исключить','больше не','nicht','vermeide','niemals','ohne','außer','ausschließen','nicht mehr'],
        'domainSpecificKeywords' => ['quantum','fpga','vlsi','risc-v','asic','photonics','genomics','proteomics','topological','homomorphic','zero-knowledge','lattice-based','量子','光子学','基因组学','蛋白质组学','拓扑','同态','零知识','格密码','量子','フォトニクス','ゲノミクス','トポロジカル','квантовый','фотоника','геномика','протеомика','топологический','гомоморфный','с нулевым разглашением','на основе решёток','quanten','photonik','genomik','proteomik','topologisch','homomorph','zero-knowledge','gitterbasiert'],
        'agenticTaskKeywords' => ['read file','read the file','look at','check the','open the','edit','modify','update the','change the','write to','create file','execute','deploy','install','npm','pip','compile','after that','and also','once done','step 1','step 2','fix','debug','until it works','keep trying','iterate','make sure','verify','confirm','读取文件','查看','打开','编辑','修改','更新','创建','执行','部署','安装','第一步','第二步','修复','调试','直到','确认','验证'],

        'dimensionWeights' => [
            'tokenCount' => 0.08,
            'codePresence' => 0.15,
            'reasoningMarkers' => 0.18,
            'technicalTerms' => 0.10,
            'creativeMarkers' => 0.05,
            'simpleIndicators' => 0.02,
            'multiStepPatterns' => 0.12,
            'questionComplexity' => 0.05,
            'imperativeVerbs' => 0.03,
            'constraintCount' => 0.04,
            'outputFormat' => 0.03,
            'referenceComplexity' => 0.02,
            'negationComplexity' => 0.01,
            'domainSpecificity' => 0.02,
            'agenticTask' => 0.04,
        ],
        'tierBoundaries' => [
            'simpleMedium' => 0.0,
            'mediumComplex' => 0.18,
            'complexReasoning' => 0.4,
        ],
        'confidenceSteepness' => 12,
        'confidenceThreshold' => 0.7,
    ],

    'tiers' => [
        'SIMPLE' => ['primary' => 'nvidia/kimi-k2.5', 'fallback' => ['google/gemini-2.5-flash','nvidia/gpt-oss-120b','nvidia/gpt-oss-20b','deepseek/deepseek-chat']],
        'MEDIUM' => ['primary' => 'xai/grok-code-fast-1', 'fallback' => ['xai/grok-4-1-fast-non-reasoning','deepseek/deepseek-chat','google/gemini-2.5-flash']],
        'COMPLEX' => ['primary' => 'google/gemini-2.5-pro', 'fallback' => ['openai/gpt-5.2','anthropic/claude-sonnet-4','xai/grok-4-0709','openai/gpt-4o']],
        'REASONING' => ['primary' => 'xai/grok-4-1-fast-reasoning', 'fallback' => ['xai/grok-4-fast-reasoning','openai/o3','deepseek/deepseek-reasoner','moonshot/kimi-k2.5']],
    ],

    'agenticTiers' => [
        'SIMPLE' => ['primary' => 'moonshot/kimi-k2.5', 'fallback' => ['anthropic/claude-haiku-4.5','xai/grok-4-fast-non-reasoning','openai/gpt-4o-mini']],
        'MEDIUM' => ['primary' => 'xai/grok-code-fast-1', 'fallback' => ['moonshot/kimi-k2.5','anthropic/claude-haiku-4.5','anthropic/claude-sonnet-4']],
        'COMPLEX' => ['primary' => 'anthropic/claude-sonnet-4', 'fallback' => ['anthropic/claude-opus-4.5','openai/gpt-5.2','xai/grok-4-0709']],
        'REASONING' => ['primary' => 'anthropic/claude-sonnet-4', 'fallback' => ['xai/grok-4-fast-reasoning','moonshot/kimi-k2.5','deepseek/deepseek-reasoner']],
    ],

    'overrides' => [
        'maxTokensForceComplex' => 100000,
        'structuredOutputMinTier' => 'MEDIUM',
        'ambiguousDefaultTier' => 'MEDIUM',
        'agenticMode' => false,
    ],
];

function lc_score_token_count(int $estimatedTokens, array $thresholds): array {
    if ($estimatedTokens < $thresholds['simple']) {
        return ['name' => 'tokenCount', 'score' => -1.0, 'signal' => "short ({$estimatedTokens} tokens)"];
    }
    if ($estimatedTokens > $thresholds['complex']) {
        return ['name' => 'tokenCount', 'score' => 1.0, 'signal' => "long ({$estimatedTokens} tokens)"];
    }
    return ['name' => 'tokenCount', 'score' => 0.0, 'signal' => null];
}

function lc_score_keyword_match(string $text, array $keywords, string $name, string $label, array $thresholds, array $scores): array {
    $matches = [];
    foreach ($keywords as $kw) {
        if (str_contains($text, mb_strtolower($kw))) $matches[] = $kw;
    }
    $top = implode(', ', array_slice($matches, 0, 3));

    if (count($matches) >= $thresholds['high']) return ['name' => $name, 'score' => $scores['high'], 'signal' => "$label ($top)"];
    if (count($matches) >= $thresholds['low']) return ['name' => $name, 'score' => $scores['low'], 'signal' => "$label ($top)"];
    return ['name' => $name, 'score' => $scores['none'], 'signal' => null];
}

function lc_score_multi_step(string $text): array {
    $patterns = ['/first.*then/i', '/step\s+\d/i', '/\d\.\s/'];
    foreach ($patterns as $p) {
        if (preg_match($p, $text)) return ['name' => 'multiStepPatterns', 'score' => 0.5, 'signal' => 'multi-step'];
    }
    return ['name' => 'multiStepPatterns', 'score' => 0.0, 'signal' => null];
}

function lc_score_question_complexity(string $prompt): array {
    $count = substr_count($prompt, '?');
    if ($count > 3) return ['name' => 'questionComplexity', 'score' => 0.5, 'signal' => "$count questions"];
    return ['name' => 'questionComplexity', 'score' => 0.0, 'signal' => null];
}

function lc_score_agentic_task(string $text, array $keywords): array {
    $matchCount = 0;
    $signals = [];
    foreach ($keywords as $kw) {
        if (str_contains($text, mb_strtolower($kw))) {
            $matchCount++;
            if (count($signals) < 3) $signals[] = $kw;
        }
    }

    if ($matchCount >= 4) {
        return ['dimensionScore' => ['name' => 'agenticTask', 'score' => 1.0, 'signal' => 'agentic (' . implode(', ', $signals) . ')'], 'agenticScore' => 1.0];
    }
    if ($matchCount >= 3) {
        return ['dimensionScore' => ['name' => 'agenticTask', 'score' => 0.6, 'signal' => 'agentic (' . implode(', ', $signals) . ')'], 'agenticScore' => 0.6];
    }
    if ($matchCount >= 1) {
        return ['dimensionScore' => ['name' => 'agenticTask', 'score' => 0.2, 'signal' => 'agentic-light (' . implode(', ', $signals) . ')'], 'agenticScore' => 0.2];
    }

    return ['dimensionScore' => ['name' => 'agenticTask', 'score' => 0.0, 'signal' => null], 'agenticScore' => 0.0];
}

function lc_calibrate_confidence(float $distance, float $steepness): float {
    return 1.0 / (1.0 + exp(-$steepness * $distance));
}

function classify_by_rules(string $prompt, ?string $systemPrompt, int $estimatedTokens, array $config): array {
    $text = mb_strtolower(trim(($systemPrompt ?? '') . ' ' . $prompt));
    $userText = mb_strtolower($prompt);

    $dimensions = [
        lc_score_token_count($estimatedTokens, $config['tokenCountThresholds']),
        lc_score_keyword_match($text, $config['codeKeywords'], 'codePresence', 'code', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>0.5,'high'=>1.0]),
        lc_score_keyword_match($userText, $config['reasoningKeywords'], 'reasoningMarkers', 'reasoning', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>0.7,'high'=>1.0]),
        lc_score_keyword_match($text, $config['technicalKeywords'], 'technicalTerms', 'technical', ['low'=>2,'high'=>4], ['none'=>0.0,'low'=>0.5,'high'=>1.0]),
        lc_score_keyword_match($text, $config['creativeKeywords'], 'creativeMarkers', 'creative', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>0.5,'high'=>0.7]),
        lc_score_keyword_match($text, $config['simpleKeywords'], 'simpleIndicators', 'simple', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>-1.0,'high'=>-1.0]),
        lc_score_multi_step($text),
        lc_score_question_complexity($prompt),
        lc_score_keyword_match($text, $config['imperativeVerbs'], 'imperativeVerbs', 'imperative', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>0.3,'high'=>0.5]),
        lc_score_keyword_match($text, $config['constraintIndicators'], 'constraintCount', 'constraints', ['low'=>1,'high'=>3], ['none'=>0.0,'low'=>0.3,'high'=>0.7]),
        lc_score_keyword_match($text, $config['outputFormatKeywords'], 'outputFormat', 'format', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>0.4,'high'=>0.7]),
        lc_score_keyword_match($text, $config['referenceKeywords'], 'referenceComplexity', 'references', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>0.3,'high'=>0.5]),
        lc_score_keyword_match($text, $config['negationKeywords'], 'negationComplexity', 'negation', ['low'=>2,'high'=>3], ['none'=>0.0,'low'=>0.3,'high'=>0.5]),
        lc_score_keyword_match($text, $config['domainSpecificKeywords'], 'domainSpecificity', 'domain-specific', ['low'=>1,'high'=>2], ['none'=>0.0,'low'=>0.5,'high'=>0.8]),
    ];

    $agenticResult = lc_score_agentic_task($text, $config['agenticTaskKeywords']);
    $dimensions[] = $agenticResult['dimensionScore'];
    $agenticScore = $agenticResult['agenticScore'];

    $signals = [];
    foreach ($dimensions as $d) if (!empty($d['signal'])) $signals[] = $d['signal'];

    $weightedScore = 0.0;
    foreach ($dimensions as $d) {
        $w = $config['dimensionWeights'][$d['name']] ?? 0.0;
        $weightedScore += $d['score'] * $w;
    }

    $reasoningMatches = 0;
    foreach ($config['reasoningKeywords'] as $kw) if (str_contains($userText, mb_strtolower($kw))) $reasoningMatches++;

    if ($reasoningMatches >= 2) {
        $confidence = lc_calibrate_confidence(max($weightedScore, 0.3), $config['confidenceSteepness']);
        return ['score' => $weightedScore, 'tier' => 'REASONING', 'confidence' => max($confidence, 0.85), 'signals' => $signals, 'agenticScore' => $agenticScore];
    }

    $b = $config['tierBoundaries'];
    if ($weightedScore < $b['simpleMedium']) {
        $tier = 'SIMPLE';
        $distance = $b['simpleMedium'] - $weightedScore;
    } elseif ($weightedScore < $b['mediumComplex']) {
        $tier = 'MEDIUM';
        $distance = min($weightedScore - $b['simpleMedium'], $b['mediumComplex'] - $weightedScore);
    } elseif ($weightedScore < $b['complexReasoning']) {
        $tier = 'COMPLEX';
        $distance = min($weightedScore - $b['mediumComplex'], $b['complexReasoning'] - $weightedScore);
    } else {
        $tier = 'REASONING';
        $distance = $weightedScore - $b['complexReasoning'];
    }

    $confidence = lc_calibrate_confidence($distance, $config['confidenceSteepness']);
    if ($confidence < $config['confidenceThreshold']) {
        return ['score' => $weightedScore, 'tier' => null, 'confidence' => $confidence, 'signals' => $signals, 'agenticScore' => $agenticScore];
    }

    return ['score' => $weightedScore, 'tier' => $tier, 'confidence' => $confidence, 'signals' => $signals, 'agenticScore' => $agenticScore];
}

function select_model(string $tier, float $confidence, string $method, string $reasoning, array $tierConfigs, array $modelPricing, int $estimatedInputTokens, int $maxOutputTokens): array {
    $model = $tierConfigs[$tier]['primary'];
    $pricing = $modelPricing[$model] ?? ['inputPrice' => 0.0, 'outputPrice' => 0.0];

    $costEstimate = ($estimatedInputTokens / 1_000_000.0) * ($pricing['inputPrice'] ?? 0.0)
                  + ($maxOutputTokens / 1_000_000.0) * ($pricing['outputPrice'] ?? 0.0);

    $opus = $modelPricing['anthropic/claude-opus-4'] ?? ['inputPrice' => 0.0, 'outputPrice' => 0.0];
    $baselineCost = ($estimatedInputTokens / 1_000_000.0) * ($opus['inputPrice'] ?? 0.0)
                  + ($maxOutputTokens / 1_000_000.0) * ($opus['outputPrice'] ?? 0.0);

    $savings = $baselineCost > 0 ? max(0.0, ($baselineCost - $costEstimate) / $baselineCost) : 0.0;

    return [
        'model' => $model,
        'tier' => $tier,
        'confidence' => $confidence,
        'method' => $method,
        'reasoning' => $reasoning,
        'costEstimate' => $costEstimate,
        'baselineCost' => $baselineCost,
        'savings' => $savings,
    ];
}

function route_locally_no_llm(string $prompt, array $options): array {
    $config = array_replace_recursive(DEFAULT_ROUTING_CONFIG, $options['configOverrides'] ?? []);

    $systemPrompt = $options['systemPrompt'] ?? null;
    $maxOutputTokens = (int)($options['maxOutputTokens'] ?? 4096);
    $modelPricing = $options['modelPricing'] ?? [];

    $fullText = trim(($systemPrompt ?? '') . ' ' . $prompt);
    $estimatedTokens = (int)ceil(mb_strlen($fullText) / 4);

    $ruleResult = classify_by_rules($prompt, $systemPrompt, $estimatedTokens, $config['scoring']);

    $agenticScore = (float)($ruleResult['agenticScore'] ?? 0.0);
    $isAutoAgentic = $agenticScore >= 0.69;
    $isExplicitAgentic = (bool)($config['overrides']['agenticMode'] ?? false);
    $useAgenticTiers = ($isAutoAgentic || $isExplicitAgentic) && isset($config['agenticTiers']);
    $tierConfigs = $useAgenticTiers ? $config['agenticTiers'] : $config['tiers'];

    if ($estimatedTokens > $config['overrides']['maxTokensForceComplex']) {
        return select_model(
            'COMPLEX',
            0.95,
            'rules',
            'Input exceeds ' . $config['overrides']['maxTokensForceComplex'] . ' tokens' . ($useAgenticTiers ? ' | agentic' : ''),
            $tierConfigs,
            $modelPricing,
            $estimatedTokens,
            $maxOutputTokens
        );
    }

    $hasStructuredOutput = $systemPrompt ? (bool)preg_match('/json|structured|schema/i', $systemPrompt) : false;

    $tier = $ruleResult['tier'] ?? null;
    $confidence = (float)$ruleResult['confidence'];
    $reasoning = 'score=' . number_format((float)$ruleResult['score'], 2, '.', '') . ' | ' . implode(', ', $ruleResult['signals']);

    if ($tier === null) {
        $tier = $config['overrides']['ambiguousDefaultTier'];
        $confidence = 0.5;
        $reasoning .= ' | ambiguous -> default: ' . $tier;
    }

    if ($hasStructuredOutput) {
        $rank = ['SIMPLE' => 0, 'MEDIUM' => 1, 'COMPLEX' => 2, 'REASONING' => 3];
        $minTier = $config['overrides']['structuredOutputMinTier'];
        if ($rank[$tier] < $rank[$minTier]) {
            $reasoning .= ' | upgraded to ' . $minTier . ' (structured output)';
            $tier = $minTier;
        }
    }

    if ($isAutoAgentic) $reasoning .= ' | auto-agentic';
    elseif ($isExplicitAgentic) $reasoning .= ' | agentic';

    return select_model($tier, $confidence, 'rules', $reasoning, $tierConfigs, $modelPricing, $estimatedTokens, $maxOutputTokens);
}
