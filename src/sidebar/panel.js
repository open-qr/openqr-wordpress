/**
 * Post-editor sidebar panel: codes pointing at this page + one-click create.
 * The classic-editor metabox (class-post-editor.php) mirrors this surface.
 */
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import { useEffect, useState, createElement } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

function Panel() {
	const postId = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostId(), [] );
	const postType = useSelect( ( select ) => select( 'core/editor' ).getCurrentPostType(), [] );
	const isPublished = useSelect( ( select ) => select( 'core/editor' ).isCurrentPostPublished(), [] );
	const permalink = useSelect( ( select ) => select( 'core/editor' ).getPermalink(), [] );
	const title = useSelect( ( select ) => select( 'core/editor' ).getEditedPostAttribute( 'title' ) || '', [] );
	const { createNotice } = useDispatch( 'core/notices' );

	const supported = [ 'post', 'page', 'product' ].includes( postType );
	const [ codes, setCodes ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		if ( ! supported || ! postId ) {
			return;
		}
		apiFetch( { path: `/openqr/v1/codes?post_id=${ postId }` } )
			.then( ( res ) => setCodes( res.codes || [] ) )
			.catch( () => setCodes( [] ) );
	}, [ supported, postId ] );

	if ( ! supported ) {
		return null;
	}

	const create = () => {
		setBusy( true );
		apiFetch( { path: '/openqr/v1/codes', method: 'POST', data: { destination: permalink, label: title, post_id: postId } } )
			.then( ( res ) => {
				createNotice( 'success', __( 'QR code created. Download it for printing from OpenQR > QR Codes.', 'openqr' ), { type: 'snackbar' } );
				setCodes( ( prev ) => [ res.row, ...( prev || [] ) ] );
			} )
			.catch( ( err ) => {
				const message = err?.message || __( 'Could not create the QR code.', 'openqr' );
				createNotice( 'error', message, { type: 'snackbar' } );
			} )
			.finally( () => setBusy( false ) );
	};

	return createElement(
		PluginDocumentSettingPanel,
		{ name: 'openqr-panel', title: 'OpenQR', icon: 'grid-view' },
		window.openqrEditor?.isConnected
			? [
					codes === null ? createElement( Spinner, { key: 's' } ) : null,
					codes && codes.length
						? createElement(
								'ul',
								{ className: 'openqr-panel-list', key: 'list' },
								codes.map( ( code ) =>
									createElement(
										'li',
										{ key: code.code_id },
										createElement( 'strong', null, code.label || code.code_id ),
										code.short_url ? createElement( 'div', { className: 'openqr-panel-link' }, code.short_url ) : null
									)
								)
						  )
						: createElement( 'p', { className: 'openqr-panel-empty', key: 'empty' }, __( 'No QR codes point at this page yet.', 'openqr' ) ),
					isPublished && permalink
						? createElement(
								Button,
								{ key: 'create', variant: 'primary', onClick: create, disabled: busy, isBusy: busy },
								__( 'Create QR for this page', 'openqr' )
						  )
						: createElement( 'p', { className: 'openqr-panel-empty', key: 'hint' }, __( 'Publish this page first — the QR needs its final address.', 'openqr' ) ),
			  ]
			: [
					createElement(
						'p',
						{ key: 'connect' },
						__( 'Connect your OpenQR account to create QR codes for this page.', 'openqr' )
					),
					createElement(
						Button,
						{ key: 'settings', variant: 'secondary', href: window.openqrEditor?.keysUrl, target: '_blank', rel: 'noopener' },
						__( 'Set up OpenQR', 'openqr' )
					),
			  ]
	);
}

registerPlugin( 'openqr-sidebar-panel', { render: Panel } );
