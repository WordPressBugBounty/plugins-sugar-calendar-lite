const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config' );

module.exports = {
	...defaultConfig,
	rootDir: __dirname,
	testMatch: [ '<rootDir>/assets/jsx/src/**/__tests__/**/*.test.js' ],
};
