const defaultConfig = require( '@wordpress/scripts/config/.eslintrc.js' );

const coreModules = new Set( [
	...( defaultConfig.settings?.[ 'import/core-modules' ] || [] ),
	'@wordpress/components',
	'@wordpress/core-data',
	'@wordpress/data',
	'@wordpress/dataviews',
	'@wordpress/dom-ready',
	'@wordpress/element',
	'@wordpress/i18n',
	'@wordpress/icons',
] );

module.exports = {
	...defaultConfig,
	settings: {
		...( defaultConfig.settings || {} ),
		'import/core-modules': [ ...coreModules ],
	},
	rules: {
		...( defaultConfig.rules || {} ),
		'import/no-unresolved': [
			'error',
			{
				ignore: [ '^@wordpress/' ],
			},
		],
	},
};
