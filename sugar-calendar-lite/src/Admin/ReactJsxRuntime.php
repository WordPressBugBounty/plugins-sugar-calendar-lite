<?php

namespace Sugar_Calendar\Admin;

/**
 * Compat shim for the `react-jsx-runtime` script handle on WP < 6.6.
 *
 * WP 6.2–6.5 ship React 18 but do not register the handle; the shim exposes
 * a ReactJSXRuntime global backed by React.createElement.
 *
 * @since 3.13.0
 */
class ReactJsxRuntime {

	/**
	 * Register the shim when the handle is missing.
	 *
	 * @since 3.13.0
	 */
	public static function ensure_registered() {

		if ( wp_script_is( 'react-jsx-runtime', 'registered' ) ) {
			return;
		}

		wp_register_script( 'react-jsx-runtime', false, [ 'react' ] );
		wp_add_inline_script(
			'react-jsx-runtime',
			'(function(R){' .
				'function jsx(t,c,k){' .
					'var p={},ch,i;' .
					'for(i in c){i==="children"?ch=c[i]:p[i]=c[i]}' .
					'if(k!==void 0)p.key=k;' .
					'var a=[t,p];' .
					'if(Array.isArray(ch))for(i=0;i<ch.length;i++)a.push(ch[i]);' .
					'else if(ch!==void 0)a.push(ch);' .
					'return R.createElement.apply(null,a)' .
				'}' .
				'window.ReactJSXRuntime={jsx:jsx,jsxs:jsx,Fragment:R.Fragment}' .
			'})(window.React);'
		);
	}
}
