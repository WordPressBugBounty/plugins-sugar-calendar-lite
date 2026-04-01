const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const glob = require( 'glob' );
const path = require( 'path' );

const getJSXEntries = () => {
	const entries = {};

	const entryPoints = [
		...glob
			.sync( 'assets/jsx/src/**/*.{js,jsx,ts,tsx,scss}' ),
	];

	entryPoints.forEach( ( entryPointPath ) => {
		const entryName = entryPointPath
			.replace( /^assets\/jsx\/src/, '/' )
			.replace( /\.(js|jsx|ts|tsx|scss)$/, '' )
			.replace( /\.index$/, '' );

		entries[ entryName ] = path.resolve( process.cwd(), entryPointPath );
	} );

	return entries;
};

module.exports = {
	...defaultConfig,
	entry: {
		...defaultConfig.entry(),
		...getJSXEntries(),
	},
	output: {
		path: path.resolve( process.cwd(), 'assets/dist' ),
		filename: '[name].js',
	},
};
