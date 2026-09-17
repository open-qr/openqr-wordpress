/**
 * OpenQR block registration (uses JSX via wp-scripts).
 *
 * While the plugin is active, PHP renders the block fresh. `save` also emits minimal
 * fallback markup so pages survive deactivation (the QR image is a durable file in
 * uploads/). Attribute changes after 1.0 require a block.json `deprecated` entry.
 */
import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Placeholder, Spinner, ToggleControl, TextControl, RangeControl, Button } from '@wordpress/components';
import { useBlockProps, AlignmentControl, BlockControls } from '@wordpress/block-editor';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';
import './editor.scss';

function Edit( props ) {
	const { attributes, setAttributes } = props;
	const blockProps = useBlockProps();
	const [ codes, setCodes ] = useState( null );
	const [ creating, setCreating ] = useState( false );

	useEffect( () => {
		if ( codes !== null ) {
			return;
		}
		if ( ! window.openqrEditor?.isConnected ) {
			setCodes( [] );
			return;
		}
		apiFetch( { path: '/openqr/v1/codes' } )
			.then( ( res ) => setCodes( res.codes || [] ) )
			.catch( () => setCodes( [] ) );
	}, [ codes ] );

	const pickCode = ( code ) => {
		setAttributes( {
			codeId: code.code_id,
			url: '',
			assetUrl: code.asset_url || '',
			label: code.label || '',
			shortLink: code.short_url || '',
		} );
	};

	const createForUrl = () => {
		if ( ! attributes.url ) {
			return;
		}
		setCreating( true );
		apiFetch( {
			path: '/openqr/v1/codes',
			method: 'POST',
			data: { destination: attributes.url, label: attributes.label || '' },
		} )
			.then( ( res ) => {
				setAttributes( { codeId: res.row.code_id, assetUrl: res.row.asset_url || '', shortLink: res.row.short_url || '' } );
			} )
			.catch( () => {} )
			.finally( () => setCreating( false ) );
	};

	const previewUrl = attributes.assetUrl;

	return (
		<div { ...blockProps }>
			<BlockControls>
				<AlignmentControl
					value={ attributes.align }
					onChange={ ( next ) => setAttributes( { align: next || '' } ) }
				/>
			</BlockControls>
			{ previewUrl ? (
				<figure className="openqr-figure openqr-block-preview">
					<img src={ previewUrl } width={ attributes.size } height={ attributes.size } alt="" />
					{ ( attributes.showLink && attributes.shortLink ) || attributes.label ? (
						<figcaption className="openqr-caption">
							{ attributes.showLink && attributes.shortLink ? <code>{ attributes.shortLink }</code> : attributes.label }
						</figcaption>
					) : null }
				</figure>
			) : (
				<Placeholder
					icon="grid-view"
					label={ __( 'OpenQR code', 'openqr' ) }
					instructions={ window.openqrEditor?.isConnected
						? __( 'Pick one of your codes, or create one from a URL.', 'openqr' )
						: __( 'Connect OpenQR in Settings first.', 'openqr' ) }
				>
					{ creating ? (
						<Spinner />
					) : (
						<>
							{ codes === null ? (
								<Spinner />
							) : (
								<select
									aria-label={ __( 'Your codes', 'openqr' ) }
									value={ attributes.codeId }
									onChange={ ( e ) => {
										const code = ( codes || [] ).find( ( c ) => c.code_id === e.target.value );
										if ( code ) {
											pickCode( code );
										}
									} }
								>
									<option value="">{ __( '— choose a code —', 'openqr' ) }</option>
									{ ( codes || [] ).map( ( code ) => (
										<option key={ code.code_id } value={ code.code_id }>
											{ code.label || code.code_id }
										</option>
									) ) }
								</select>
							) }
							<TextControl
								placeholder={ __( '…or any URL', 'openqr' ) }
								value={ attributes.url }
								onChange={ ( url ) => setAttributes( { url, codeId: '', assetUrl: '' } ) }
							/>
							<Button variant="primary" onClick={ createForUrl } disabled={ ! attributes.url }>
								{ __( 'Create editable code', 'openqr' ) }
							</Button>
						</>
					) }
				</Placeholder>
			) }
			<div className="openqr-block-controls">
				<RangeControl
					label={ __( 'Size (px)', 'openqr' ) }
					value={ attributes.size }
					onChange={ ( size ) => setAttributes( { size } ) }
					min={ 96 }
					max={ 1024 }
				/>
				<TextControl
					label={ __( 'Caption', 'openqr' ) }
					value={ attributes.label }
					onChange={ ( label ) => setAttributes( { label } ) }
				/>
				<ToggleControl
					label={ __( 'Show the short link', 'openqr' ) }
					checked={ attributes.showLink }
					onChange={ ( showLink ) => setAttributes( { showLink } ) }
				/>
			</div>
		</div>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: ( { attributes } ) => {
		// Minimal fallback so deactivation never blanks a published figure: the QR image is a
		// durable file in uploads/. Real rendering is the PHP render_callback while active.
		if ( ! attributes.assetUrl ) {
			return null;
		}
		const children = [
			createElement( 'img', {
				className: 'openqr-qr',
				src: attributes.assetUrl,
				width: attributes.size,
				height: attributes.size,
				alt: attributes.label || attributes.shortLink || '',
				decoding: 'async',
			} ),
		];
		if ( attributes.showLink && attributes.shortLink ) {
			children.push( createElement( 'figcaption', { className: 'openqr-caption' }, createElement( 'code', null, attributes.shortLink ) ) );
		} else if ( attributes.label ) {
			children.push( createElement( 'figcaption', { className: 'openqr-caption' }, attributes.label ) );
		}
		return createElement( 'figure', { className: 'openqr-figure' }, children );
	},
} );

// Sidebar panel rides the same bundle: one entry, one asset.php.
import '../../sidebar/panel';

