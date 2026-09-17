/**
 * Copy plain assets into build/ after wp-scripts builds the block bundle.
 * admin.js has no imports or JSX, so it ships as written.
 */
const { copyFileSync, mkdirSync } = require( 'node:fs' );
const path                        = require( 'node:path' );

const root = path.resolve( __dirname, '..' );
mkdirSync( path.join( root, 'build' ), { recursive: true } );
copyFileSync( path.join( root, 'src/admin/admin.js' ), path.join( root, 'build/admin.js' ) );
copyFileSync( path.join( root, 'src/styles/admin.css' ), path.join( root, 'build/admin.css' ) );
copyFileSync( path.join( root, 'src/styles/admin.css' ), path.join( root, 'build/admin-banner.css' ) );
copyFileSync( path.join( root, 'src/styles/frontend.css' ), path.join( root, 'build/frontend.css' ) );
console.log( 'build assets copied' );
