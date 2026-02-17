<?php

declare(strict_types=1);

return [
	'codeKeywords'           => [
		// English
		'function',
		'class',
		'import',
		'def',
		'select',
		'async',
		'await',
		'const',
		'let',
		'var',
		'return',
		'```',

		// Chinese
		'函数', // function
		'类', // class
		'导入', // import
		'定义', // define
		'查询', // query
		'异步', // async
		'等待', // wait/await
		'常量', // constant
		'变量', // variable
		'返回', // return

		// Japanese
		'関数', // function
		'クラス', // class
		'インポート', // import
		'非同期', // async
		'定数', // constant
		'変数', // variable

		// Russian
		'функция', // function
		'класс', // class
		'импорт', // import
		'определ', // define (stem)
		'запрос', // query
		'асинхронный', // async
		'ожидать', // await/wait
		'константа', // constant
		'переменная', // variable
		'вернуть', // return

		// German
		'funktion', // function
		'klasse', // class
		'importieren', // import
		'definieren', // define
		'abfrage', // query
		'asynchron', // async
		'erwarten', // await/expect
		'konstante', // constant
		'variable', // variable
		'zurückgeben', // return
	],

	'reasoningKeywords'      => [
		// English
		'prove',
		'theorem',
		'derive',
		'step by step',
		'chain of thought',
		'formally',
		'mathematical',
		'proof',
		'logically',

		// Chinese
		'证明', // prove
		'定理', // theorem
		'推导', // derive
		'逐步', // step-by-step
		'思维链', // chain of thought
		'形式化', // formalized
		'数学', // mathematics
		'逻辑', // logic

		// Japanese
		'証明', // proof
		'定理', // theorem
		'導出', // derive
		'ステップバイステップ', // step-by-step
		'論理的', // logical

		// Russian
		'доказать', // prove
		'докажи', // prove (imperative)
		'доказательств', // proof(s)
		'теорема', // theorem
		'вывести', // derive
		'шаг за шагом', // step by step
		'пошагово', // stepwise
		'поэтапно', // in stages
		'цепочка рассуждений', // chain of reasoning
		'рассуждени', // reasoning (stem)
		'формально', // formally
		'математически', // mathematically
		'логически', // logically

		// German
		'beweisen', // prove
		'beweis', // proof
		'theorem',
		'ableiten', // derive
		'schritt für schritt', // step by step
		'gedankenkette', // chain of thought
		'formal',
		'mathematisch', // mathematical
		'logisch', // logical
	],

	'simpleKeywords'         => [
		// English
		'what is',
		'define',
		'translate',
		'hello',
		'yes or no',
		'capital of',
		'how old',
		'who is',
		'when was',

		// Chinese
		'什么是', // what is
		'定义', // define
		'翻译', // translate
		'你好', // hello
		'是否', // yes/no
		'首都', // capital city
		'多大', // how old/how big
		'谁是', // who is
		'何时', // when

		// Japanese
		'とは', // what is
		'定義', // definition
		'翻訳', // translation
		'こんにちは', // hello
		'はいかいいえ', // yes or no
		'首都', // capital city
		'誰', // who

		// Russian
		'что такое', // what is
		'определение', // definition
		'перевести', // translate
		'переведи', // translate (imperative)
		'привет', // hello
		'да или нет', // yes or no
		'столица', // capital city
		'сколько лет', // how old
		'кто такой', // who is
		'когда', // when
		'объясни', // explain

		// German
		'was ist', // what is
		'definiere', // define
		'übersetze', // translate
		'hallo',
		'ja oder nein', // yes or no
		'hauptstadt', // capital city
		'wie alt', // how old
		'wer ist', // who is
		'wann', // when
		'erkläre', // explain
	],

	'technicalKeywords'      => [
		'algorithm',
		'optimize',
		'architecture',
		'distributed',
		'kubernetes',
		'microservice',
		'database',
		'infrastructure',
		'算法', // algorithm
		'优化', // optimize
		'架构', // architecture
		'分布式', // distributed
		'微服务', // microservice
		'数据库', // database
		'基础设施', // infrastructure
		'アルゴリズム', // algorithm
		'最適化', // optimization
		'アーキテクチャ', // architecture
		'分散', // distributed
		'マイクロサービス', // microservice
		'データベース', // database
		'алгоритм', // algorithm
		'оптимизировать', // optimize
		'оптимизаци', // optimization (stem)
		'оптимизируй', // optimize (imperative)
		'архитектура', // architecture
		'распределённый', // distributed
		'микросервис', // microservice
		'база данных', // database
		'инфраструктура', // infrastructure
		'algorithmus', // algorithm
		'optimieren', // optimize
		'architektur', // architecture
		'verteilt', // distributed
		'mikroservice', // microservice
		'datenbank', // database
		'infrastruktur', // infrastructure
	],

	'creativeKeywords'       => [
		'story',
		'poem',
		'compose',
		'brainstorm',
		'creative',
		'imagine',
		'write a',
		'故事', // story
		'诗', // poem
		'创作', // create
		'头脑风暴', // brainstorm
		'创意', // creative idea
		'想象', // imagine
		'写一个', // write one
		'物語', // story
		'詩', // poem
		'作曲', // compose
		'ブレインストーム', // brainstorm
		'創造的', // creative
		'想像', // imagination
		'история', // story
		'рассказ', // story/narrative
		'стихотворение', // poem
		'сочинить', // compose
		'сочини', // compose (imperative)
		'мозговой штурм', // brainstorm
		'творческий', // creative
		'представить', // imagine
		'придумай', // invent/come up with
		'напиши', // write
		'geschichte', // story
		'gedicht', // poem
		'komponieren', // compose
		'brainstorming',
		'kreativ', // creative
		'vorstellen', // imagine
		'schreibe', // write
		'erzählung', // narrative
	],

	'imperativeVerbs'        => [
		'build',
		'create',
		'implement',
		'design',
		'develop',
		'construct',
		'generate',
		'deploy',
		'configure',
		'set up',
		'构建', // build
		'创建', // create
		'实现', // implement
		'设计', // design
		'开发', // develop
		'生成', // generate
		'部署', // deploy
		'配置', // configure
		'设置', // set up
		'構築', // build
		'作成', // create
		'実装', // implement
		'設計', // design
		'開発', // develop
		'デプロイ', // deploy
		'設定', // config/settings
		'построить', // build
		'построй', // build (imperative)
		'создать', // create
		'создай', // create (imperative)
		'реализовать', // implement
		'реализуй', // implement (imperative)
		'спроектировать', // design
		'разработать', // develop
		'разработай', // develop (imperative)
		'сконструировать', // construct
		'сгенерировать', // generate
		'сгенерируй', // generate (imperative)
		'развернуть', // deploy
		'разверни', // deploy (imperative)
		'настроить', // configure
		'настрой', // configure (imperative)
		'erstellen', // create
		'bauen', // build
		'implementieren', // implement
		'entwerfen', // design
		'entwickeln', // develop
		'konstruieren', // construct
		'generieren', // generate
		'bereitstellen', // deploy/provision
		'konfigurieren', // configure
		'einrichten', // set up
	],

	'constraintIndicators'   => [
		'under',
		'at most',
		'at least',
		'within',
		'no more than',
		'o(',
		'maximum',
		'minimum',
		'limit',
		'budget',
		'不超过', // no more than
		'至少', // at least
		'最多', // at most
		'在内', // within
		'最大', // maximum
		'最小', // minimum
		'限制', // limit
		'预算', // budget
		'以下', // less than / under
		'制限', // limit
		'予算', // budget
		'не более', // no more than
		'не менее', // no less than
		'как минимум', // at least
		'в пределах', // within
		'максимум', // maximum
		'минимум', // minimum
		'ограничение', // constraint/limit
		'бюджет', // budget
		'höchstens', // at most
		'mindestens', // at least
		'innerhalb', // within
		'nicht mehr als', // no more than
		'maximal', // maximum
		'minimal', // minimum
		'grenze', // limit/boundary
	],

	'outputFormatKeywords'   => [
		'json',
		'yaml',
		'xml',
		'table',
		'csv',
		'markdown',
		'schema',
		'format as',
		'structured',
		'表格', // table
		'格式化为', // format as
		'结构化', // structured
		'テーブル', // table
		'フォーマット', // format
		'構造化', // structured
		'таблица', // table
		'форматировать как', // format as
		'структурированный', // structured
		'tabelle', // table
		'formatieren als', // format as
		'strukturiert', // structured
	],

	'referenceKeywords'      => [
		'above',
		'below',
		'previous',
		'following',
		'the docs',
		'the api',
		'the code',
		'earlier',
		'attached',
		'上面', // above
		'下面', // below
		'之前', // previous/before
		'接下来', // following/next
		'文档', // docs
		'代码', // code
		'附件', // attachment
		'上記', // above-mentioned
		'下記', // below-mentioned
		'前の', // previous
		'次の', // next
		'ドキュメント', // documentation
		'コード', // code
		'выше', // above
		'ниже', // below
		'предыдущий', // previous
		'следующий', // following/next
		'документация', // documentation
		'код', // code
		'ранее', // earlier
		'вложение', // attachment
		'oben', // above
		'unten', // below
		'vorherige', // previous
		'folgende', // following
		'dokumentation', // documentation
		'der code', // the code
		'früher', // earlier
		'anhang', // attachment
	],

	'negationKeywords'       => [
		"don't",
		'do not',
		'avoid',
		'never',
		'without',
		'except',
		'exclude',
		'no longer',
		'不要', // do not
		'避免', // avoid
		'从不', // never
		'没有', // without/not have
		'除了', // except
		'排除', // exclude
		'しないで', // don't do
		'避ける', // avoid
		'決して', // never
		'なしで', // without
		'除く', // except
		'не делай', // do not do
		'не надо', // don't
		'нельзя', // not allowed
		'избегать', // avoid
		'никогда', // never
		'без', // without
		'кроме', // except
		'исключить', // exclude
		'больше не', // no longer
		'nicht', // not
		'vermeide', // avoid
		'niemals', // never
		'ohne', // without
		'außer', // except
		'ausschließen', // exclude
		'nicht mehr', // no longer
	],

	'domainSpecificKeywords' => [
		'quantum',
		'fpga',
		'vlsi',
		'risc-v',
		'asic',
		'photonics',
		'genomics',
		'proteomics',
		'topological',
		'homomorphic',
		'zero-knowledge',
		'lattice-based',
		'量子', // quantum
		'光子学', // photonics
		'基因组学', // genomics
		'蛋白质组学', // proteomics
		'拓扑', // topology/topological
		'同态', // homomorphic
		'零知识', // zero-knowledge
		'格密码', // lattice cryptography
		'フォトニクス', // photonics
		'ゲノミクス', // genomics
		'トポロジカル', // topological
		'квантовый', // quantum
		'фотоника', // photonics
		'геномика', // genomics
		'протеомика', // proteomics
		'топологический', // topological
		'гомоморфный', // homomorphic
		'с нулевым разглашением', // zero-knowledge
		'на основе решёток', // lattice-based
		'quanten', // quantum
		'photonik', // photonics
		'genomik', // genomics
		'proteomik', // proteomics
		'topologisch', // topological
		'homomorph', // homomorphic
		'gitterbasiert', // lattice-based
	],

	'agenticTaskKeywords'    => [
		// English
		'read file',
		'read the file',
		'look at',
		'check the',
		'open the',
		'edit',
		'modify',
		'update the',
		'change the',
		'write to',
		'create file',
		'execute',
		'deploy',
		'install',
		'npm',
		'pip',
		'compile',
		'after that',
		'and also',
		'once done',
		'step 1',
		'step 2',
		'fix',
		'debug',
		'until it works',
		'keep trying',
		'iterate',
		'make sure',
		'verify',
		'confirm',

		// Chinese
		'读取文件', // read file
		'查看', // inspect/view
		'打开', // open
		'编辑', // edit
		'修改', // modify
		'更新', // update
		'创建', // create
		'执行', // execute
		'部署', // deploy
		'安装', // install
		'第一步', // step 1
		'第二步', // step 2
		'修复', // fix
		'调试', // debug
		'直到', // until
		'确认', // confirm
		'验证', // verify
	],
];
