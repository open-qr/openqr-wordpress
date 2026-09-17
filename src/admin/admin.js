/**
 * Admin screens' behaviour: create-screen tabs + static type switcher, codes list inline
 * editor toggle, copy-to-clipboard, pause/resume + delete via the REST proxy, and the
 * connect-form busy state. Plain DOM, no framework: these are server-rendered screens.
 *
 * Browser-only: alert/confirm are the deliberate UI for destructive actions, and `navigator`
 * is a browser global. wp-scripts' node env flags both, so they are disabled file-wide.
 */

/* eslint-disable no-alert */
/* global navigator */

( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function notify( message, isError ) {
		const el = document.createElement( 'div' );
		el.className =
			'notice ' +
			( isError ? 'notice-error' : 'notice-success' ) +
			' is-dismissible openqr-js-notice';
		const p = document.createElement( 'p' );
		p.textContent = message;
		el.appendChild( p );
		const wrap = document.querySelector( '.openqr-wrap' );
		if ( wrap && wrap.parentNode ) {
			wrap.parentNode.insertBefore( el, wrap );
			setTimeout( function () {
				el.remove();
			}, 6000 );
		} else {
			window.alert( message );
		}
	}

	function mapError( err ) {
		if ( err && err.message ) {
			return err.message;
		}
		return 'Something went wrong.';
	}

	ready( function () {
		// ── Create screen: tabs + static type switcher ──────────────────────────
		const tabs = document.querySelectorAll( '.openqr-tabs .nav-tab' );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				tabs.forEach( function ( t ) {
					t.classList.toggle( 'nav-tab-active', t === tab );
				} );
				document
					.querySelectorAll( '.openqr-tab-panel' )
					.forEach( function ( panel ) {
						panel.hidden = panel.dataset.form !== tab.dataset.tab;
					} );
			} );
		} );

		const typeSelect = document.getElementById( 'openqr-static-type' );
		if ( typeSelect ) {
			const syncType = function () {
				document
					.querySelectorAll( '.openqr-type-fields' )
					.forEach( function ( box ) {
						box.hidden = box.dataset.type !== typeSelect.value;
					} );
			};
			typeSelect.addEventListener( 'change', syncType );
			syncType();
			// Required flags only apply to the visible type's fields.
			document
				.querySelector( '#openqr-tab-static' )
				.addEventListener( 'submit', function ( e ) {
					const form = e.target;
					form.querySelectorAll( '.openqr-type-fields' ).forEach(
						function ( box ) {
							if ( box.dataset.type !== typeSelect.value ) {
								box.querySelectorAll( 'input' ).forEach(
									function ( input ) {
										input.disabled = true;
									}
								);
							}
						}
					);
					const required = boxRequiredMissing(
						form,
						typeSelect.value
					);
					if ( required ) {
						e.preventDefault();
						window.alert( required );
					}
				} );
			function boxRequiredMissing( form, type ) {
				const box = form.querySelector(
					'.openqr-type-fields[data-type="' + type + '"]'
				);
				if ( ! box ) {
					return null;
				}
				const missing = [];
				box.querySelectorAll( 'input[data-required="1"]' ).forEach(
					function ( input ) {
						if ( ! input.value.trim() ) {
							missing.push(
								input.closest( 'label' ).querySelector( 'span' )
									.textContent
							);
						}
					}
				);
				return missing.length
					? 'Please fill in: ' + missing.join( ', ' )
					: null;
			}
		}

		// ── Codes list: inline editor toggles ───────────────────────────────────
		document
			.querySelectorAll( '.openqr-toggle-editor' )
			.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					const target = document.getElementById(
						btn.dataset.target
					);
					if ( target ) {
						target.hidden = ! target.hidden;
					}
				} );
			} );

		// ── Copy short links ────────────────────────────────────────────────────
		document.querySelectorAll( '.openqr-copy' ).forEach( function ( el ) {
			el.title = 'Click to copy';
			el.style.cursor = 'pointer';
			el.addEventListener( 'click', function () {
				if ( typeof navigator !== 'undefined' && navigator.clipboard ) {
					navigator.clipboard.writeText(
						el.dataset.copy || el.textContent
					);
					const old = el.textContent;
					el.textContent = 'Copied';
					setTimeout( function () {
						el.textContent = old;
					}, 1200 );
				}
			} );
		} );

		// ── Pause / resume via the REST proxy ───────────────────────────────────
		document.querySelectorAll( '.openqr-pause' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				window.wp
					.apiFetch( {
						path:
							'/openqr/v1/codes/' +
							encodeURIComponent( btn.dataset.code ),
						method: 'PATCH',
						data: { status: btn.dataset.status },
					} )
					.then( function () {
						window.location.reload();
					} )
					.catch( function ( err ) {
						btn.disabled = false;
						notify( mapError( err ), true );
					} );
			} );
		} );

		// ── Delete (connection managers only) ───────────────────────────────────
		document
			.querySelectorAll( '.openqr-delete' )
			.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					const label = btn.dataset.label || btn.dataset.code;
					if (
						! window.confirm(
							'Delete "' +
								label +
								'" on OpenQR? Any printed copies will stop redirecting. This cannot be undone.'
						)
					) {
						return;
					}
					btn.disabled = true;
					window.wp
						.apiFetch( {
							path:
								'/openqr/v1/codes/' +
								encodeURIComponent( btn.dataset.code ),
							method: 'DELETE',
						} )
						.then( function () {
							window.location.reload();
						} )
						.catch( function ( err ) {
							btn.disabled = false;
							notify( mapError( err ), true );
						} );
				} );
			} );

		// ── Connect form: recommend a key name ─────────────────────────────────
		const keyInput = document.querySelector(
			'input[name="openqr_api_key"]'
		);
		if ( keyInput ) {
			keyInput.addEventListener( 'paste', function () {
				const form = keyInput.closest( 'form' );
				if ( form ) {
					form.querySelector( 'button[type="submit"]' ).classList.add(
						'openqr-busy'
					);
				}
			} );
		}
	} );
} )();
