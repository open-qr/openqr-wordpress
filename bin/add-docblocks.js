/**
 * One-shot docblock generator (line-based, replaces only machine-made blocks).
 * Recognises a pass-1 block by shape: every content line is `@param`/`@return` or a short
 * description equal to the function name. Hand-written blocks are never touched.
 */
const { readFileSync, writeFileSync, readdirSync } = require( 'node:fs' );
const path = require( 'node:path' );

const dir = path.resolve( __dirname, '../includes' );

const PHRASES = {
	code_id: 'OpenQR code ID.',
	post_id: 'Local post ID, 0 for none.',
	api_key: 'Raw oqr_ API key.',
	destination: 'Destination URL.',
	short_url: 'Short link URL.',
	label: 'Placement label.',
	theme: 'Saved theme name.',
	search: 'Search term.',
	cursor: 'Pagination cursor.',
	id: 'Identifier.',
	user_id: 'User ID.',
	size: 'Pixel size.',
	days: 'Window in days.',
	limit: 'Maximum rows.',
	offset: 'Rows to skip.',
	key: 'Key.',
	value: 'Value.',
	row: 'Registry row.',
	rows: 'Registry rows.',
	fields: 'Field values.',
	patch: 'Fields to change.',
	args: 'Arguments.',
	atts: 'Shortcode attributes.',
	attributes: 'Block attributes.',
	status: 'HTTP status.',
	headers: 'Lowercased headers.',
	raw: 'Raw body bytes.',
	body: 'JSON body.',
	request: 'REST request.',
	post: 'Post.',
	message: 'Message.',
	error: 'Error.',
	errors: 'Errors.',
	response: 'Upstream response.',
	path: 'API path beginning with /v1.',
	method: 'HTTP method.',
	dir: 'Directory path.',
	bytes: 'File bytes.',
	code: 'Code.',
	format: 'Format.',
	data: 'Payload to encode.',
	campaign: 'Campaign slug.',
	extra: 'Extra UTM parameters.',
	name: 'Name.',
	type: 'Payload type.',
	ttl: 'Seconds.',
	group: 'Cache group.',
	cb: 'Value producer.',
	user: 'User.',
	password: 'Password.',
	self: 'Instance.',
};

function describeParam( name ) {
	const clean = name.replace( /^\$/, '' );
	if ( PHRASES[ clean ] ) {
		return PHRASES[ clean ];
	}
	const human = clean
		.replace( /[_-]+/g, ' ' )
		.replace( /([a-z])([A-Z])/g, '$1 $2' )
		.toLowerCase();
	return human.charAt( 0 ).toUpperCase() + human.slice( 1 ) + '.';
}

function humanName( name ) {
	const spaced = name
		.replace( /([a-z0-9])([A-Z])/g, '$1 $2' )
		.replace( /_/g, ' ' );
	const sentence = spaced.charAt( 0 ).toUpperCase() + spaced.slice( 1 );
	return sentence + '.';
}

function buildBlock( indent, name, params, returnType ) {
	const lines = [ `${ indent }/**`, `${ indent } * ${ humanName( name ) }`, `${ indent } *` ];
	for ( const p of params ) {
		lines.push( `${ indent } * @param ${ p.variadic ? '...' : '' }${ p.type } ${ p.name } ${ describeParam( p.name ) }` );
	}
	if ( returnType && returnType !== 'void' ) {
		lines.push( `${ indent } * @return ${ returnType }` );
	}
	lines.push( `${ indent } */` );
	if ( lines.length === 4 ) {
		lines.splice( 2, 1 ); // description only
	}
	return lines;
}

function processSource( src ) {
	const lines = src.split( '\n' );
	const out = [];

	const stripTypes = ( t ) => ( t || '' ).trim().replace( /\s+/g, ' ' ) || 'mixed';

	function parseParams( raw ) {
		const params = [];
		for ( const part of raw.split( ',' ) ) {
			const trimmed = part.trim();
			if ( ! trimmed ) {
				continue;
			}
			const pm = trimmed.match( /^((?:[A-Za-z0-9_\\|?\[\]<>, ]+?)?)\s*(\.\.\.)?\s*&?\$(\w+)/ );
			if ( ! pm ) {
				continue;
			}
			params.push( { type: stripTypes( pm[ 1 ] ), name: '$' + pm[ 3 ], variadic: !! pm[ 2 ] } );
		}
		return params;
	}

	function isMachineBlock( blockLines ) {
		if ( ! blockLines.length ) {
			return false;
		}
		return blockLines.every( ( line ) => {
			const content = line.replace( /^\s*\/?\*+\s?/, '' ).replace( /\*\/\s*$/, '' ).trim();
			if ( content === '' || content === '/**' ) {
				return true;
			}
			return content.startsWith( '@param' ) || content.startsWith( '@return' );
		} );
	}

	for ( let i = 0; i < lines.length; i++ ) {
		const line = lines[ i ];

		// Function/method declaration?
		const fnMatch = line.match( /^(\t*)(?:public|protected|private)(?: static)? function ([A-Za-z0-9_]+)\(/ );
		if ( fnMatch ) {
			const indent = fnMatch[ 1 ];
			const name = fnMatch[ 2 ];

			// Collect the full signature (may span lines) up to the closing paren.
			let sig = line.slice( line.indexOf( '(' ) + 1 );
			let close = sig.lastIndexOf( ')' );
			while ( close === -1 || ! new RegExp( `\\)[^)]*$` ).test( sig ) ) {
				i++;
				if ( i >= lines.length ) {
					break;
				}
				sig += ' ' + lines[ i ].trim();
				close = sig.lastIndexOf( ')' );
			}
			const paramsRaw = close !== -1 ? sig.slice( 0, close ) : sig;
			const tail = close !== -1 ? sig.slice( close + 1 ) : '';
			const retMatch = tail.match( /[^A-Za-z0-9_]([A-Za-z0-9_\\|?\[\]]+)\s*\{/ );
			const returnType = retMatch ? retMatch[ 1 ] : '';

			// Inspect the block immediately above the declaration line.
			const above = [];
			let j = out.length - 1;
			while ( j >= 0 && ( /^\s*$/.test( out[ j ] ) || /\*\/\s*$/.test( out[ j ] ) || /^\s*(\/\*\*|\*)/.test( out[ j ] ) ) ) {
				above.unshift( out[ j ] );
				j--;
			}
			// Only treat as a docblock if the LAST line above is a comment closer.
			const hasBlock = above.length && /\*\//.test( above[ above.length - 1 ] );
			if ( hasBlock && isMachineBlock( above ) ) {
				out.length = j + 1;
			}

			const params = parseParams( paramsRaw );
			out.push( ...buildBlock( indent, name, params, returnType ) );
			continue;
		}

		// Class declaration without a docblock directly above?
		const cls = line.match( /^(\t*)((?:final |abstract )?class ([A-Za-z0-9_]+))/ );
		if ( cls ) {
			const indent = cls[ 1 ];
			const name = cls[ 3 ];
			const prev = out.length ? out[ out.length - 1 ] : '';
			if ( ! /\*\//.test( prev ) ) {
				out.push( `${ indent }/**` );
				out.push( `${ indent } * ${ humanName( name.replace( /^OpenQR_/, '' ) ) }` );
				out.push( `${ indent } */` );
			}
		}

		out.push( line );
	}
	return out.join( '\n' );
}

for ( const file of readdirSync( dir ) ) {
	if ( ! file.endsWith( '.php' ) ) {
		continue;
	}
	const full = path.join( dir, file );
	const src = readFileSync( full, 'utf8' );
	const out = processSource( src );
	if ( out !== src ) {
		writeFileSync( full, out );
		console.log( `documented ${ file }` );
	}
}
