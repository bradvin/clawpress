const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		panel: path.resolve( process.cwd(), 'src', 'panel', 'index.jsx' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( process.cwd(), 'build', 'panel' ),
		filename: 'panel.js',
	},
	optimization: {
		...defaultConfig.optimization,
		splitChunks: false,
		runtimeChunk: false,
	},
	plugins: defaultConfig.plugins.filter(
		( plugin ) => plugin?.constructor?.name !== 'CopyPlugin'
	),
};
