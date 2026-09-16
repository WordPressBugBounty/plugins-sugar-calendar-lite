/* globals jQuery, wp */
( function ( $ ) {

	'use strict';

	let SugarCalendar = window.SugarCalendar || {};
	SugarCalendar.Admin = SugarCalendar.Admin || {};

	// The block editor's save controls. Update/Publish carries aria-disabled
	// rather than disabled while saving is locked, so it still emits clicks.
	const SAVE_CONTROLS = '.editor-post-publish-button, .editor-post-publish-panel__toggle, .editor-post-save-draft';

	/**
	 * Block a save on an invalid metabox field, and show the user which one.
	 *
	 * Each feature owns its own rule and its own copy; this owns the wiring that
	 * is identical everywhere — the two classic hooks, the block-editor lock and
	 * notice, the tab switch, the message element, and the submit-button unlock.
	 *
	 * @since 3.14.0
	 */
	SugarCalendar.Admin.FieldError = {

		errorClass: 'sugar-calendar-field-error',
		invalidClass: 'sugar-calendar-field-invalid',

		// Set for a tick so the first caller keeps focus when two guards fail on
		// the same save click.
		claimed: false,

		// Serial for errorKey()'s fallback on fields with no id.
		generated: 0,

		// Guards registered via guard(). Internal only: it exists so the document
		// listeners below are bound once rather than once per guard.
		guards: [],

		documentListenersBound: false,

		/* -----------------------------------------------------------------
		 * Public: declare a guard
		 * ----------------------------------------------------------------- */

		/**
		 * Block the save while a field is invalid.
		 *
		 * The feature supplies its field, its rule and its copy. Everything
		 * identical between guards — the two classic hooks, the block-editor lock
		 * and notice, the tab switch, the message element, the submit unlock — is
		 * handled here.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object}   config          Guard definition.
		 * @param {jQuery}   config.field    The control the error belongs to: it takes
		 *                                   the red border, the focus, and the message
		 *                                   below it. Watched for changes automatically.
		 * @param {Function} config.validate ( $field, watched ) => '' when the save may
		 *                                   proceed, otherwise a key from `message`.
		 * @param {Object}   config.message  { key: string } keyed by what validate returns.
		 * @param {Object}   [config.watch]  { name: jQuery } of other controls feeding the
		 *                                   rule. Passed to validate as its second
		 *                                   argument, and changes to them re-run it.
		 * @param {string|Object} [config.notice] Block-editor banner text. A string covers
		 *                                   every result; an object keyed like `message`
		 *                                   gives each result its own banner.
		 * @param {boolean}  [config.native] Also hook the browser's own `invalid` event,
		 *                                   for inputs with native constraints such as
		 *                                   type=url. Without it a value the browser
		 *                                   rejects aborts the submit before the guard
		 *                                   ever runs.
		 * @param {string[]} [config.immediate] Keys from `message` painted the moment a
		 *                                   watched control changes, before any save.
		 *                                   Border and message only: no focus, no tab
		 *                                   switch. The block editor also posts the banner.
		 *
		 * @return {void}
		 */
		guard: function ( config ) {

			if ( ! config || ! config.field || ! config.field.length || ! config.validate ) {
				return;
			}

			config.watch = config.watch || {};
			config.message = config.message || {};

			this.guards.push( config );

			// Classic: an empty value reaches the submit event. A value the browser
			// itself rejects does NOT — native validation runs first and aborts the
			// submit — so constrained fields need the `invalid` hook as well.
			$( '#post' ).on( 'submit', ( e ) => {

				if ( ! this.evaluate( config ) ) {
					return;
				}

				e.preventDefault();
				this.revealGuard( config );
			} );

			if ( config.native ) {

				config.field.on( 'invalid', ( e ) => {

					// Suppress the browser bubble: it cannot render on a field behind
					// another tab, which leaves the click looking dead.
					e.preventDefault();

					// The browser aborts the submit whether or not this guard's rule
					// agrees, and the submit event never fires, so nothing else will
					// put the submit box back. A stale hidden value the rule ignores
					// would otherwise leave Update disabled and spinning for good.
					this.unlockClassicSubmit();

					this.revealGuard( config );
				} );
			}

			// The field is always an input to its own rule, so it is watched without
			// the caller having to say so.
			let $watched = config.field;

			Object.keys( config.watch ).forEach( ( name ) => {
				$watched = $watched.add( config.watch[ name ] );
			} );

			$watched.on( 'input change', () => this.syncGuard( config ) );

			this.bindDocumentListeners();
			this.syncGuard( config );
		},

		/**
		 * Run one guard's rule.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 *
		 * @return {string} '' when valid, otherwise the message key.
		 */
		evaluate: function ( config ) {

			return config.validate( config.field, config.watch ) || '';
		},

		/**
		 * Whether a rule result is one the guard paints without waiting for a save.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 * @param {string} reason What validate() returned.
		 *
		 * @return {boolean}
		 */
		isImmediate: function ( config, reason ) {

			return !! reason && Array.isArray( config.immediate ) && config.immediate.indexOf( reason ) !== -1;
		},

		/**
		 * The copy for a rule result.
		 *
		 * An unmapped key would otherwise paint a red border and focus with nothing
		 * to read — the "looks like it worked" failure. Say so instead.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 * @param {string} key    What validate() returned.
		 *
		 * @return {string} The message to show.
		 */
		messageFor: function ( config, key ) {

			if ( config.message[ key ] !== undefined ) {
				return config.message[ key ];
			}

			const fallback = Object.keys( config.message )[0];

			window.console.warn(
				'SugarCalendar FieldError: validate() returned "' + key + '", which has no entry in `message`.'
			);

			return fallback !== undefined ? config.message[ fallback ] : '';
		},

		/**
		 * The block-editor banner for a rule result.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 * @param {string} key    What validate() returned.
		 *
		 * @return {string} The banner text, or '' for no banner.
		 */
		noticeFor: function ( config, key ) {

			if ( ! config.notice ) {
				return '';
			}

			if ( typeof config.notice === 'string' ) {
				return config.notice;
			}

			return config.notice[ key ] !== undefined ? config.notice[ key ] : '';
		},

		/**
		 * Put up the block-editor banner for a rule result.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 * @param {string} reason What validate() returned.
		 *
		 * @return {void}
		 */
		createNotice: function ( config, reason ) {

			const text = this.noticeFor( config, reason );

			if ( ! text || ! this.canUseEditorStore() ) {
				return;
			}

			wp.data.dispatch( 'core/notices' ).createNotice(
				'error',
				text,
				{
					id: this.errorKey( config.field ),
					isDismissible: true,
					actions: [
						{
							label: wp.i18n.__( 'Review', 'sugar-calendar-lite' ),
							// Read the reason at click time: the notice outlives every
							// edit that leaves the field invalid, so a bound snapshot
							// would keep replaying the first failure.
							onClick: () => this.revealGuard( config ),
						},
					],
				}
			);
		},

		/**
		 * Re-evaluate one guard: clear when fine, hold the block-editor lock when not.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 *
		 * @return {void}
		 */
		syncGuard: function ( config ) {

			const reason = this.evaluate( config );
			const immediate = this.isImmediate( config, reason );

			if ( immediate ) {
				this.mark( config.field, this.messageFor( config, reason ) );
			} else if ( reason ) {
				this.refresh( config, reason );
			} else {
				this.clear( config.field );
			}

			if ( ! this.canUseEditorStore() ) {
				return;
			}

			const editor = wp.data.dispatch( 'core/editor' );
			const key = this.errorKey( config.field );

			if ( reason ) {
				editor.lockPostSaving( key );

				if ( immediate ) {
					this.createNotice( config, reason );
				}

				return;
			}

			editor.unlockPostSaving( key );
			wp.data.dispatch( 'core/notices' ).removeNotice( key );
		},

		/**
		 * Show one guard's message on its field.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 *
		 * @return {void}
		 */
		revealGuard: function ( config ) {

			const reason = this.evaluate( config );

			if ( ! reason ) {
				return;
			}

			this.show( config.field, this.messageFor( config, reason ) );
		},

		/**
		 * Block editor: report a blocked save, and make sure it really is blocked.
		 *
		 * lockPostSaving emits nothing to listen for, so the save ATTEMPT is the
		 * only available signal. WordPress only started disabling Save draft and
		 * Publish under that lock in 7.1; on older versions the buttons stay live
		 * and the save goes through under the banners, so the click has to be
		 * stopped here before it reaches the editor. Bound once for every guard.
		 *
		 * @since 3.14.0
		 *
		 * @return {void}
		 */
		bindDocumentListeners: function () {

			if ( this.documentListenersBound || ! this.canUseEditorStore() ) {
				return;
			}

			this.documentListenersBound = true;

			const report = () => this.guards.forEach( ( config ) => this.reportGuard( config ) );

			document.addEventListener( 'click', ( e ) => {

				if ( ! e.target.closest || ! e.target.closest( SAVE_CONTROLS ) ) {
					return;
				}

				report();
				this.holdSave( e );
			}, true );

			document.addEventListener( 'keydown', ( e ) => {

				if ( e.key === 's' && ( e.metaKey || e.ctrlKey ) ) {
					report();
					this.holdSave( e );
				}
			}, true );
		},

		/**
		 * Stop a save attempt reaching the editor while a guard is unhappy.
		 *
		 * Capture phase on `document` runs ahead of the editor's own handler, so
		 * halting propagation here is what actually prevents the save — the lock
		 * alone does not on WordPress below 7.1.
		 *
		 * @since 3.14.0
		 *
		 * @param {Event} e The save click or shortcut being intercepted.
		 *
		 * @return {void}
		 */
		holdSave: function ( e ) {

			if ( ! this.guards.some( ( config ) => this.evaluate( config ) ) ) {
				return;
			}

			e.preventDefault();
			e.stopImmediatePropagation();
		},

		/**
		 * Block editor: paint the blocked-save notice and reveal the field.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 *
		 * @return {void}
		 */
		reportGuard: function ( config ) {

			// Re-run the lock from the live value first. syncGuard only fires for
			// controls named in `watch`, so a rule reading anything else can reach
			// the save click unlocked, and would otherwise show a notice over a save
			// that goes through anyway.
			this.syncGuard( config );

			const reason = this.evaluate( config );

			if ( ! reason ) {
				return;
			}

			this.createNotice( config, reason );
			this.revealGuard( config );
		},

		/* -----------------------------------------------------------------
		 * Public: reveal / clear directly
		 * ----------------------------------------------------------------- */

		/**
		 * Reveal a field's error.
		 *
		 * Marking is unconditional so a save with several broken fields shows all of
		 * them; only the first caller of the tick takes the tab switch and the focus.
		 *
		 * @since 3.14.0
		 *
		 * @param {jQuery} $field  The offending control.
		 * @param {string} message Message to show under it. Optional.
		 *
		 * @return {boolean} Whether this call took the focus.
		 */
		show: function ( $field, message ) {

			if ( ! $field || ! $field.length ) {
				return false;
			}

			this.mark( $field, message );

			this.unlockClassicSubmit();

			// A second guard failing on the same click keeps its border and message,
			// but must not drag the tab and the caret away from the first one.
			if ( this.claimed ) {
				return false;
			}

			this.claim();
			this.revealTab( $field );

			// Let a widget that opens on focus stand down for this one call: a
			// datepicker panel would drop open directly over the message we just
			// wrote. Set and cleared around a synchronous focus(), so a missed
			// clear is impossible and the worst case is the panel opening as before.
			$field.data( 'scFieldErrorRevealing', true );

			// Scroll first, then focus with preventScroll so the focus call does not
			// jump-cut past the smooth scroll.
			$field[0].scrollIntoView( { behavior: 'smooth', block: 'center' } );
			$field[0].focus( { preventScroll: true } );

			$field.removeData( 'scFieldErrorRevealing' );

			return true;
		},

		/**
		 * Paint a field's error without taking the focus or switching tabs.
		 *
		 * @since 3.14.0
		 *
		 * @param {jQuery} $field  The offending control.
		 * @param {string} message Message to show under it. Optional.
		 *
		 * @return {void}
		 */
		mark: function ( $field, message ) {

			const $error = this.resolveErrorElement( $field );

			if ( $error.length ) {
				$error.text( message || '' ).toggle( !! message );

				// A feature-rendered element may carry no id; jQuery drops an
				// undefined value silently, so check rather than let the screen
				// reader link go missing unnoticed.
				if ( $error.attr( 'id' ) ) {
					$field.attr( 'aria-describedby', $error.attr( 'id' ) );
				}
			}

			$field.attr( 'aria-invalid', 'true' ).addClass( this.invalidClass );
		},

		/**
		 * Rewrite a message that is already on screen, when the reason changed under it.
		 *
		 * Without this the copy is whatever the failing save wrote. Toggling All Day
		 * changes which fields the rule asks for, so the visible text would go on
		 * naming a time field the user can no longer see.
		 *
		 * Silent by design: no focus, no tab switch, and nothing appears on a field
		 * that was not already showing an error.
		 *
		 * @since 3.14.0
		 *
		 * @param {Object} config Guard definition.
		 * @param {string} reason What validate() returned.
		 *
		 * @return {void}
		 */
		refresh: function ( config, reason ) {

			if ( ! config.field.hasClass( this.invalidClass ) ) {
				return;
			}

			const $error = this.resolveErrorElement( config.field, false );

			if ( ! $error.length ) {
				return;
			}

			const message = this.messageFor( config, reason );

			$error.text( message || '' ).toggle( !! message );
		},

		/**
		 * Drop a field's error state.
		 *
		 * Called from syncGuard() once the rule passes, and directly when something
		 * else made the error moot — a field gated by a sibling control never fires
		 * an event of its own. Editing the field is not enough on its own: a value
		 * that is still wrong keeps its error and gets refresh()'d instead.
		 *
		 * @since 3.14.0
		 *
		 * @param {jQuery} $field The control to clear.
		 *
		 * @return {void}
		 */
		clear: function ( $field ) {

			if ( ! $field || ! $field.length ) {
				return;
			}

			this.resolveErrorElement( $field, false ).text( '' ).hide();

			$field
				.removeAttr( 'aria-invalid' )
				.removeAttr( 'aria-describedby' )
				.removeClass( this.invalidClass );
		},

		/* -----------------------------------------------------------------
		 * Internals
		 * ----------------------------------------------------------------- */

		/**
		 * Hold the focus for the rest of this tick.
		 *
		 * @since 3.14.0
		 *
		 * @return {void}
		 */
		claim: function () {

			this.claimed = true;

			setTimeout( function () {
				SugarCalendar.Admin.FieldError.claimed = false;
			}, 0 );
		},

		/**
		 * Switch the metabox to the tab the field lives on.
		 *
		 * Every section carries its own id, so the tab is derivable from the field
		 * and no registration is needed.
		 *
		 * @since 3.14.0
		 *
		 * @param {jQuery} $field The offending control.
		 *
		 * @return {void}
		 */
		revealTab: function ( $field ) {

			const id = $field.closest( '.sugar-calendar-metabox__section' ).data( 'id' );

			if ( ! id ) {
				return;
			}

			$( '.sugar-calendar-metabox__navigation__button' ).filter( '[data-id="' + id + '"]' ).trigger( 'click' );
		},

		/**
		 * The value tying a field to its message element.
		 *
		 * Prefers the field's id so a feature can pre-render the element in PHP.
		 * Falls back to a generated key rather than giving up: without one the
		 * field would get a red border and focus but no message, which reads as
		 * the helper having worked.
		 *
		 * @since 3.14.0
		 *
		 * @param {jQuery} $field The offending control.
		 *
		 * @return {string} Lookup key for `data-sc-field-error`.
		 */
		errorKey: function ( $field ) {

			const id = $field.attr( 'id' );

			if ( id ) {
				return id;
			}

			let key = $field.data( 'scFieldErrorKey' );

			if ( ! key ) {
				this.generated += 1;
				key = 'sc-field-' + this.generated;

				$field.data( 'scFieldErrorKey', key );
			}

			return key;
		},

		/**
		 * Find the field's message element, creating one when the feature did not
		 * render its own.
		 *
		 * @since 3.14.0
		 *
		 * @param {jQuery}  $field The offending control.
		 * @param {boolean} create Whether to insert a fallback element. Default true.
		 *
		 * @return {jQuery} The message element, possibly empty.
		 */
		resolveErrorElement: function ( $field, create ) {

			const key = this.errorKey( $field );
			const $existing = $( '[data-sc-field-error="' + key + '"]' );

			if ( $existing.length || create === false ) {
				return $existing;
			}

			// .first(): appendTo() against several targets CLONES, so a multi-field
			// set would grow one message per field.
			const $holder = $field.closest( '.sugar-calendar-metabox__field' ).first();

			if ( ! $holder.length ) {
				return $();
			}

			return $( '<p></p>' )
				.attr( 'id', key + '-error' )
				.attr( 'data-sc-field-error', key )
				.attr( 'role', 'alert' )
				.addClass( this.errorClass )
				.hide()
				.appendTo( $holder );
		},

		/**
		 * Whether `core/editor` can be dispatched to.
		 *
		 * Asks the store rather than inferring it from the editor type, which is an
		 * SC option and so states intent, not outcome: a plugin filtering
		 * `use_block_editor_for_post_type` renders classic without `wp-editor` while
		 * that option still reads `block`.
		 *
		 * @since 3.14.0
		 *
		 * @return {boolean} True when the editor-store branches may run.
		 */
		canUseEditorStore: function () {

			return !! window.wp && !! window.wp.data && !! window.wp.data.select( 'core/editor' );
		},

		/**
		 * Undo the submit lock core applies on its way out of the form.
		 *
		 * wp-admin/js/post.js disables the submit controls and spins the spinner as
		 * the form submits, counting on the navigation to discard that state.
		 * Cancelling the submit keeps the page, so nothing restores it: Update sits
		 * spinning and refuses clicks forever. Runs on a 0ms timeout so it lands
		 * after every handler in the same submit dispatch, whatever the binding
		 * order. No-ops in the block editor, which has no #submitpost.
		 *
		 * @since 3.14.0
		 *
		 * @return {void}
		 */
		unlockClassicSubmit: function () {

			setTimeout( function () {

				const $box = $( '#submitpost' );

				if ( ! $box.length ) {
					return;
				}

				$box.find( '.disabled' ).removeClass( 'disabled' );
				$box.find( '.spinner.is-active' ).removeClass( 'is-active' );
			}, 0 );
		},
	};

	window.SugarCalendar = SugarCalendar;

} )( jQuery );
