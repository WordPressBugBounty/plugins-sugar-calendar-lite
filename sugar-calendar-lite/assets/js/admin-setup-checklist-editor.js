/* globals wp */

'use strict';

wp.domReady( function() {
	const config = window.sugar_calendar_admin_setup_checklist_editor;
	const pageId = Number( config.page_id );
	const blockName = config.block_name;
	const errorText = config.error_text;
	const recoveryText = config.recovery_text;
	const noticeId = 'sugar-calendar-setup-checklist-editor';
	const timeoutDuration = 10000;
	let unsubscribe = null;
	let timeoutId = null;
	let pending = false;
	let settled = false;

	/**
	 * Return the current URL without checklist-only arguments.
	 *
	 * @since 3.15.0
	 *
	 * @return {string} Ordinary edit URL.
	 */
	function getOrdinaryEditUrl() {
		const url = new window.URL( window.location.href );

		url.searchParams.delete( 'sc_setup_checklist_action' );
		url.searchParams.delete( 'sc_setup_checklist_nonce' );

		return url.toString();
	}

	/**
	 * Stop all callbacks after one terminal result.
	 *
	 * @since 3.15.0
	 */
	function cleanup() {
		settled = true;

		if ( unsubscribe ) {
			const stop = unsubscribe;

			unsubscribe = null;
			stop();
		}

		if ( timeoutId ) {
			window.clearTimeout( timeoutId );
			timeoutId = null;
		}
	}

	/**
	 * Display a plain error with an ordinary edit-link recovery.
	 *
	 * @since 3.15.0
	 */
	function fail() {
		if ( settled ) {
			return;
		}

		cleanup();

		const notices = wp.data.dispatch( 'core/notices' );

		if ( notices && typeof notices.createErrorNotice === 'function' ) {
			notices.createErrorNotice( errorText, {
				actions: [
					{
						label: recoveryText,
						url: getOrdinaryEditUrl(),
					},
				],
				id: noticeId,
				isDismissible: true,
			} );
			return;
		}

		const notice = window.document.createElement( 'div' );
		const link = window.document.createElement( 'a' );

		notice.className = 'notice notice-error';
		link.href = getOrdinaryEditUrl();
		link.textContent = recoveryText;
		notice.appendChild( window.document.createTextNode( errorText + ' ' ) );
		notice.appendChild( link );
		window.document.body.prepend( notice );
	}

	/**
	 * Find the first exact block recursively.
	 *
	 * @since 3.15.0
	 *
	 * @param {Array} blocks Current block tree.
	 *
	 * @return {string} Matching client ID, or an empty string.
	 */
	function findBlockClientId( blocks ) {
		for ( const block of blocks ) {
			if ( block.name === blockName ) {
				return block.clientId;
			}

			const nestedClientId = findBlockClientId( block.innerBlocks || [] );

			if ( nestedClientId ) {
				return nestedClientId;
			}
		}

		return '';
	}

	/**
	 * Return the exact selected native editor stores when ready.
	 *
	 * @since 3.15.0
	 *
	 * @return {Object|null} Current editor selectors.
	 */
	function getReadyEditor() {
		const postEditor = wp.data.select( 'core/editor' );
		const blockEditor = wp.data.select( 'core/block-editor' );

		if ( ! postEditor || ! blockEditor ) {
			return null;
		}

		const currentPost = postEditor.getCurrentPost();
		const settings = blockEditor.getSettings();

		// BlockEditorProvider sets the private `__internalIsInitialized` once the post's
		// blocks have synced; inserting earlier loses the block to its first reset.
		if (
			Number( postEditor.getCurrentPostId() ) !== pageId ||
			postEditor.getCurrentPostType() !== 'page' ||
			! currentPost ||
			Number( currentPost.id ) !== pageId ||
			currentPost.type !== 'page' ||
			! settings ||
			settings.__internalIsInitialized !== true ||
			! wp.blocks.getBlockType( blockName )
		) {
			return null;
		}

		return { blockEditor, postEditor };
	}

	/**
	 * Whether the loaded page is locked against this handoff.
	 *
	 * @since 3.15.0
	 *
	 * @param {Object} postEditor Current post editor selector.
	 *
	 * @return {boolean} Whether insertion must stop.
	 */
	function isLocked( postEditor ) {
		return postEditor.isPostLocked() || postEditor.isPostSavingLocked();
	}

	/**
	 * Remove launch arguments only after the client ID is confirmed.
	 *
	 * @since 3.15.0
	 */
	function finish() {
		cleanup();
		window.history.replaceState( window.history.state, '', getOrdinaryEditUrl() );
	}

	/**
	 * Select an existing calendar or insert one into the live root tree.
	 *
	 * @since 3.15.0
	 *
	 * @param {Object} ready Current editor selectors.
	 */
	function selectOrInsert( ready ) {
		if ( isLocked( ready.postEditor ) ) {
			fail();
			return;
		}

		const existingClientId = findBlockClientId( ready.blockEditor.getBlocks() );
		const editor = wp.data.dispatch( 'core/block-editor' );

		cleanup();

		if ( existingClientId ) {
			editor.selectBlock( existingClientId );

			if ( ready.blockEditor.getSelectedBlockClientId() === existingClientId ) {
				finish();
				return;
			}

			settled = false;
			fail();
			return;
		}

		if ( ! ready.blockEditor.canInsertBlockType( blockName ) ) {
			settled = false;
			fail();
			return;
		}

		const block = wp.blocks.createBlock( blockName );

		editor.insertBlocks( block );

		const inserted = ready.blockEditor.getBlock( block.clientId );

		if ( inserted && inserted.clientId === block.clientId && inserted.name === blockName ) {
			finish();
			return;
		}

		settled = false;
		fail();
	}

	/**
	 * Queue one fresh read after native provider effects settle.
	 *
	 * @since 3.15.0
	 */
	function maybeRun() {
		if ( settled || pending ) {
			return;
		}

		const ready = getReadyEditor();

		if ( ! ready ) {
			return;
		}

		if ( isLocked( ready.postEditor ) ) {
			fail();
			return;
		}

		pending = true;

		window.Promise.resolve().then( function() {
			pending = false;

			if ( settled ) {
				return;
			}

			const fresh = getReadyEditor();

			if ( ! fresh ) {
				fail();
				return;
			}

			selectOrInsert( fresh );
		} );
	}

	if ( ! pageId || ! blockName || ! errorText || ! recoveryText ) {
		fail();
		return;
	}

	unsubscribe = wp.data.subscribe( maybeRun );
	timeoutId = window.setTimeout( fail, timeoutDuration );
	maybeRun();
} );
