/**
 * OpenQR block registration (uses JSX via wp-scripts).
 *
 * While the plugin is active, PHP renders the block fresh through the same OpenQR_Embed
 * implementation the page builders use. `save` also emits minimal fallback markup so existing
 * codes survive deactivation (the QR image is a durable file in uploads/); the current-page and
 * custom sources are server-rendered and honestly render nothing without the plugin.
 *
 * Legacy 1.0 attribute shapes (codeId/url + label/showLink) keep their exact old output in PHP.
 */
import { registerBlockType } from '@wordpress/blocks';
import { createElement, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Spinner,
	ToggleControl,
	TextControl,
	TextareaControl,
	RangeControl,
	SelectControl,
	Button,
	PanelBody,
	ColorPalette,
} from '@wordpress/components';
import {
	useBlockProps,
	InspectorControls,
	AlignmentControl,
	BlockControls,
} from '@wordpress/block-editor';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';
import metadata from './block.json';
import './editor.scss';

const BLOCK_NAME = 'openqr/qr';

const PRESENTATIONS = [
	{ value: 'qr_caption', label: __( 'QR with instruction', 'openqr' ) },
	{ value: 'qr', label: __( 'QR only', 'openqr' ) },
	{ value: 'qr_button', label: __( 'QR with button', 'openqr' ) },
];

const CUSTOM_TYPES = [
	{ value: 'url', label: __( 'Website', 'openqr' ) },
	{ value: 'text', label: __( 'Text', 'openqr' ) },
	{ value: 'email', label: __( 'Email', 'openqr' ) },
	{ value: 'phone', label: __( 'Phone', 'openqr' ) },
	{ value: 'sms', label: __( 'SMS', 'openqr' ) },
	{ value: 'whatsapp', label: __( 'WhatsApp', 'openqr' ) },
	{ value: 'wifi', label: __( 'Wi-Fi', 'openqr' ) },
	{ value: 'geo', label: __( 'Location', 'openqr' ) },
	{ value: 'vcard', label: __( 'Contact card (vCard)', 'openqr' ) },
];

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

	const source = attributes.source || ( attributes.codeId ? 'code' : 'url' );
	const set = ( patch ) => setAttributes( patch );

	const pickCode = ( code ) => {
		setAttributes( {
			source: 'code',
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
			data: {
				destination: attributes.url,
				label: attributes.label || '',
			},
		} )
			.then( ( res ) => {
				setAttributes( {
					source: 'code',
					codeId: res.row.code_id,
					assetUrl: res.row.asset_url || '',
					shortLink: res.row.short_url || '',
				} );
			} )
			.catch( () => {} )
			.finally( () => setCreating( false ) );
	};

	return (
		<div { ...blockProps }>
			<BlockControls>
				<AlignmentControl
					value={ attributes.align }
					onChange={ ( next ) =>
						setAttributes( { align: next || '' } )
					}
				/>
			</BlockControls>

			<InspectorControls>
				<PanelBody title={ __( 'Content', 'openqr' ) } initialOpen>
					<SelectControl
						label={ __( 'What should the code carry?', 'openqr' ) }
						value={ source === 'code' ? 'code' : source }
						options={ [
							{
								value: 'current',
								label: __(
									'This page or product (updates itself)',
									'openqr'
								),
							},
							{
								value: 'code',
								label: __(
									'An existing OpenQR code',
									'openqr'
								),
							},
							{
								value: 'url',
								label: __( 'A fixed URL', 'openqr' ),
							},
							{
								value: 'custom',
								label: __( 'Other fixed content', 'openqr' ),
							},
						] }
						onChange={ ( v ) =>
							set(
								v === 'code'
									? { source: 'code' }
									: {
											source: v,
											codeId: '',
											assetUrl: '',
											shortLink: '',
									  }
							)
						}
					/>

					{ source === 'code' && (
						<>
							{ codes === null ? (
								<Spinner />
							) : (
								<SelectControl
									label={ __( 'Your codes', 'openqr' ) }
									value={ attributes.codeId }
									options={ [
										{
											value: '',
											label: __(
												'— choose a code —',
												'openqr'
											),
										},
										...( codes || [] ).map( ( c ) => ( {
											value: c.code_id,
											label: c.label || c.code_id,
										} ) ),
									] }
									onChange={ ( id ) => {
										const code = ( codes || [] ).find(
											( c ) => c.code_id === id
										);
										if ( code ) {
											pickCode( code );
										}
									} }
								/>
							) }
						</>
					) }

					{ source === 'url' && (
						<>
							<TextControl
								label={ __( 'URL', 'openqr' ) }
								placeholder={ __(
									'…or pick one of your codes instead',
									'openqr'
								) }
								value={ attributes.url }
								onChange={ ( url ) =>
									set( { url, codeId: '', assetUrl: '' } )
								}
							/>
							<Button
								variant="secondary"
								onClick={ createForUrl }
								disabled={ ! attributes.url || creating }
								isBusy={ creating }
							>
								{ __(
									'Create editable code from this URL',
									'openqr'
								) }
							</Button>
						</>
					) }

					{ source === 'custom' && (
						<>
							<SelectControl
								label={ __( 'Type', 'openqr' ) }
								value={ attributes.cfType }
								options={ CUSTOM_TYPES }
								onChange={ ( cfType ) => set( { cfType } ) }
							/>
							{ attributes.cfType === 'url' && (
								<TextControl
									label={ __( 'URL', 'openqr' ) }
									placeholder="example.com"
									value={ attributes.cfUrl }
									onChange={ ( cfUrl ) => set( { cfUrl } ) }
								/>
							) }
							{ attributes.cfType === 'text' && (
								<TextareaControl
									label={ __( 'Text', 'openqr' ) }
									value={ attributes.cfText }
									onChange={ ( cfText ) => set( { cfText } ) }
								/>
							) }
							{ attributes.cfType === 'email' && (
								<>
									<TextControl
										label={ __(
											'Email address',
											'openqr'
										) }
										placeholder="hello@example.com"
										value={ attributes.cfEmail }
										onChange={ ( cfEmail ) =>
											set( { cfEmail } )
										}
									/>
									<TextControl
										label={ __(
											'Subject (optional)',
											'openqr'
										) }
										value={ attributes.cfSubject }
										onChange={ ( cfSubject ) =>
											set( { cfSubject } )
										}
									/>
									<TextareaControl
										label={ __(
											'Message (optional)',
											'openqr'
										) }
										value={ attributes.cfBody }
										onChange={ ( cfBody ) =>
											set( { cfBody } )
										}
									/>
								</>
							) }
							{ ( attributes.cfType === 'phone' ||
								attributes.cfType === 'sms' ||
								attributes.cfType === 'whatsapp' ) && (
								<TextControl
									label={
										attributes.cfType === 'whatsapp'
											? __(
													'Phone number (with country code)',
													'openqr'
											  )
											: __( 'Phone number', 'openqr' )
									}
									placeholder="+44 7000 000000"
									value={ attributes.cfPhone }
									onChange={ ( cfPhone ) =>
										set( { cfPhone } )
									}
								/>
							) }
							{ ( attributes.cfType === 'sms' ||
								attributes.cfType === 'whatsapp' ) && (
								<TextareaControl
									label={ __(
										'Message (optional)',
										'openqr'
									) }
									value={ attributes.cfMessage }
									onChange={ ( cfMessage ) =>
										set( { cfMessage } )
									}
								/>
							) }
							{ attributes.cfType === 'wifi' && (
								<>
									<TextControl
										label={ __(
											'Network name (SSID)',
											'openqr'
										) }
										placeholder="My Wi-Fi"
										value={ attributes.cfSsid }
										onChange={ ( cfSsid ) =>
											set( { cfSsid } )
										}
									/>
									<SelectControl
										label={ __( 'Security', 'openqr' ) }
										value={ attributes.cfEncryption }
										options={ [
											{
												value: 'WPA',
												label: __(
													'WPA / WPA2 / WPA3',
													'openqr'
												),
											},
											{
												value: 'WEP',
												label: __( 'WEP', 'openqr' ),
											},
											{
												value: 'nopass',
												label: __(
													'No password',
													'openqr'
												),
											},
										] }
										onChange={ ( cfEncryption ) =>
											set( { cfEncryption } )
										}
									/>
									{ attributes.cfEncryption !== 'nopass' && (
										<TextControl
											label={ __( 'Password', 'openqr' ) }
											value={ attributes.cfPassword }
											onChange={ ( cfPassword ) =>
												set( { cfPassword } )
											}
										/>
									) }
									<ToggleControl
										label={ __(
											'Hidden network',
											'openqr'
										) }
										checked={ attributes.cfHidden }
										onChange={ ( cfHidden ) =>
											set( { cfHidden } )
										}
									/>
								</>
							) }
							{ attributes.cfType === 'geo' && (
								<>
									<TextControl
										label={ __( 'Latitude', 'openqr' ) }
										placeholder="51.5074"
										value={ attributes.cfLat }
										onChange={ ( cfLat ) =>
											set( { cfLat } )
										}
									/>
									<TextControl
										label={ __( 'Longitude', 'openqr' ) }
										placeholder="-0.1278"
										value={ attributes.cfLng }
										onChange={ ( cfLng ) =>
											set( { cfLng } )
										}
									/>
								</>
							) }
							{ attributes.cfType === 'vcard' && (
								<>
									<TextControl
										label={ __( 'First name', 'openqr' ) }
										placeholder="Jane"
										value={ attributes.cfFirstName }
										onChange={ ( cfFirstName ) =>
											set( { cfFirstName } )
										}
									/>
									<TextControl
										label={ __( 'Last name', 'openqr' ) }
										placeholder="Doe"
										value={ attributes.cfLastName }
										onChange={ ( cfLastName ) =>
											set( { cfLastName } )
										}
									/>
									<TextControl
										label={ __( 'Phone number', 'openqr' ) }
										placeholder="+44 7000 000000"
										value={ attributes.cfPhone }
										onChange={ ( cfPhone ) =>
											set( { cfPhone } )
										}
									/>
									<TextControl
										label={ __( 'Email', 'openqr' ) }
										placeholder="jane@example.com"
										value={ attributes.cfEmail }
										onChange={ ( cfEmail ) =>
											set( { cfEmail } )
										}
									/>
									<TextControl
										label={ __(
											'Company (optional)',
											'openqr'
										) }
										placeholder="Acme Ltd"
										value={ attributes.cfOrg }
										onChange={ ( cfOrg ) =>
											set( { cfOrg } )
										}
									/>
									<TextControl
										label={ __(
											'Job title (optional)',
											'openqr'
										) }
										placeholder="Designer"
										value={ attributes.cfTitle }
										onChange={ ( cfTitle ) =>
											set( { cfTitle } )
										}
									/>
									<TextControl
										label={ __(
											'Website (optional)',
											'openqr'
										) }
										placeholder="example.com"
										value={ attributes.cfWebsite }
										onChange={ ( cfWebsite ) =>
											set( { cfWebsite } )
										}
									/>
									<TextControl
										label={ __(
											'Address (optional)',
											'openqr'
										) }
										placeholder="123 High St, London"
										value={ attributes.cfAddress }
										onChange={ ( cfAddress ) =>
											set( { cfAddress } )
										}
									/>
								</>
							) }
						</>
					) }
				</PanelBody>

				<PanelBody
					title={ __( 'Layout', 'openqr' ) }
					initialOpen={ false }
				>
					<SelectControl
						label={ __( 'Presentation', 'openqr' ) }
						value={ attributes.presentation }
						options={ PRESENTATIONS }
						onChange={ ( presentation ) => set( { presentation } ) }
					/>
					{ attributes.presentation !== 'qr' && (
						<TextControl
							label={ __( 'Instruction', 'openqr' ) }
							default={ __(
								'Scan to open on your phone',
								'openqr'
							) }
							value={ attributes.instruction }
							onChange={ ( instruction ) =>
								set( { instruction } )
							}
						/>
					) }
					<TextControl
						label={ __( 'Heading', 'openqr' ) }
						placeholder={ __(
							'e.g. Take this property with you',
							'openqr'
						) }
						value={ attributes.heading }
						onChange={ ( heading ) => set( { heading } ) }
					/>
					<RangeControl
						label={ __( 'QR size (px)', 'openqr' ) }
						value={ attributes.size }
						onChange={ ( size ) => set( { size } ) }
						min={ 96 }
						max={ 1024 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'QR appearance', 'openqr' ) }
					initialOpen={ false }
				>
					<p>
						{ __(
							'Leave both empty for the OpenQR default (ink on white). Dark, high-contrast colours scan most reliably.',
							'openqr'
						) }
					</p>
					<ColorPalette
						label={ __( 'Code colour', 'openqr' ) }
						clearable
						colors={ [] }
						value={ attributes.qrDark }
						onChange={ ( qrDark ) =>
							set( { qrDark: qrDark || '' } )
						}
					/>
					<ColorPalette
						label={ __( 'Background colour', 'openqr' ) }
						clearable
						colors={ [] }
						value={ attributes.qrLight }
						onChange={ ( qrLight ) =>
							set( { qrLight: qrLight || '' } )
						}
					/>
				</PanelBody>

				{ attributes.presentation === 'qr_button' && (
					<PanelBody
						title={ __( 'Button', 'openqr' ) }
						initialOpen={ false }
					>
						<TextControl
							label={ __( 'Button text', 'openqr' ) }
							value={ attributes.buttonText }
							onChange={ ( buttonText ) => set( { buttonText } ) }
						/>
						<TextControl
							label={ __( 'Button link (optional)', 'openqr' ) }
							placeholder={ __(
								'Empty: the code’s destination',
								'openqr'
							) }
							value={ attributes.buttonUrl }
							onChange={ ( buttonUrl ) => set( { buttonUrl } ) }
						/>
						<ToggleControl
							label={ __( 'Add a Download QR link', 'openqr' ) }
							checked={ attributes.download }
							onChange={ ( download ) => set( { download } ) }
						/>
					</PanelBody>
				) }
			</InspectorControls>

			<ServerSideRender block={ BLOCK_NAME } attributes={ attributes } />
		</div>
	);
}

registerBlockType( metadata, {
	edit: Edit,
	save: ( { attributes } ) => {
		// Minimal fallback so deactivation never blanks a published figure: the QR image is a
		// durable file in uploads/. Real rendering is the PHP render_callback while active.
		// Current-page and custom-content sources are server-rendered and honestly render
		// nothing without the plugin.
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
			children.push(
				createElement(
					'figcaption',
					{ className: 'openqr-caption' },
					createElement( 'code', null, attributes.shortLink )
				)
			);
		} else if ( attributes.label ) {
			children.push(
				createElement(
					'figcaption',
					{ className: 'openqr-caption' },
					attributes.label
				)
			);
		}
		return createElement(
			'figure',
			{ className: 'openqr-figure' },
			children
		);
	},
} );

// Sidebar panel rides the same bundle: one entry, one asset.php.
import '../../sidebar/panel';
