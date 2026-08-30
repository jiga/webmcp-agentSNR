"use strict";

const { readFileSync } = require( "node:fs" );
const vm = require( "node:vm" );
const { webcrypto } = require( "node:crypto" );

class MockEventTarget {
	constructor() {
		this.listeners = new Map();
	}

	addEventListener( type, listener ) {
		const listeners = this.listeners.get( type ) || new Set();
		listeners.add( listener );
		this.listeners.set( type, listeners );
	}

	removeEventListener( type, listener ) {
		this.listeners.get( type )?.delete( listener );
	}

	dispatchEvent( event ) {
		event.target ||= this;
		event.currentTarget = this;
		for ( const listener of this.listeners.get( event.type ) || [] ) {
			listener.call( this, event );
		}
		return true;
	}
}

class MockCustomEvent {
	constructor( type, options = {} ) {
		this.type = type;
		this.detail = options.detail;
	}
}

class MockClassList {
	constructor( element ) {
		this.element = element;
	}

	add( ...tokens ) {
		tokens.forEach( ( token ) => this.element.classes.add( token ) );
	}

	contains( token ) {
		return this.element.classes.has( token );
	}

	remove( ...tokens ) {
		tokens.forEach( ( token ) => this.element.classes.delete( token ) );
	}

	toggle( token, force ) {
		const enabled = force === undefined ? ! this.contains( token ) : Boolean( force );
		if ( enabled ) {
			this.add( token );
		} else {
			this.remove( token );
		}
		return enabled;
	}
}

class MockElement extends MockEventTarget {
	constructor( ownerDocument, tagName ) {
		super();
		this.attributes = new Map();
		this.children = [];
		this.classes = new Set();
		this.classList = new MockClassList( this );
		this.dataset = {};
		this.disabled = false;
		this.download = "";
		this.hidden = false;
		this.href = "";
		this.id = "";
		this.ownerDocument = ownerDocument;
		this.parentNode = null;
		this.style = {};
		this.tagName = String( tagName ).toUpperCase();
		this.title = "";
		this.type = "";
		this.value = "";
		this._textContent = "";
	}

	get className() {
		return Array.from( this.classes ).join( " " );
	}

	set className( value ) {
		this.classes = new Set( String( value || "" ).split( /\s+/ ).filter( Boolean ) );
	}

	get innerHTML() {
		return this.textContent;
	}

	set innerHTML( value ) {
		this.ownerDocument.innerHTMLWrites.push( { element: this, value: String( value ) } );
		this.textContent = value;
	}

	get textContent() {
		return this._textContent + this.children.map( ( child ) => child.textContent ).join( "" );
	}

	set textContent( value ) {
		this._textContent = String( value ?? "" );
		this.children.forEach( ( child ) => {
			child.parentNode = null;
		} );
		this.children = [];
		this.ownerDocument.textContentWrites.push( { element: this, value: this._textContent } );
	}

	append( ...children ) {
		children.forEach( ( child ) => {
			if ( child === null || child === undefined ) {
				return;
			}
			if ( ! ( child instanceof MockElement ) ) {
				const textNode = new MockElement( this.ownerDocument, "#text" );
				textNode.textContent = String( child );
				child = textNode;
			}
			child.remove();
			child.parentNode = this;
			this.children.push( child );
		} );
	}

	appendChild( child ) {
		this.append( child );
		return child;
	}

	click() {
		this.dispatchEvent( new MockCustomEvent( "click" ) );
	}

	closest( selector ) {
		let candidate = this;
		while ( candidate ) {
			if ( candidate.matches( selector ) ) {
				return candidate;
			}
			candidate = candidate.parentNode;
		}
		return null;
	}

	getAttribute( name ) {
		if ( name === "class" ) {
			return this.className || null;
		}
		if ( name === "id" ) {
			return this.id || null;
		}
		if ( name.startsWith( "data-" ) ) {
			const key = dataKey( name );
			return Object.hasOwn( this.dataset, key ) ? String( this.dataset[ key ] ) : null;
		}
		return this.attributes.get( name ) ?? null;
	}

	matches( selector ) {
		return selector.split( "," ).some( ( alternative ) => matchesSelector( this, alternative.trim() ) );
	}

	querySelector( selector ) {
		return this.querySelectorAll( selector )[ 0 ] || null;
	}

	querySelectorAll( selector ) {
		const matches = [];
		for ( const child of descendants( this ) ) {
			if ( child.matches( selector ) ) {
				matches.push( child );
			}
		}
		return matches;
	}

	remove() {
		if ( ! this.parentNode ) {
			return;
		}
		this.parentNode.children = this.parentNode.children.filter( ( child ) => child !== this );
		this.parentNode = null;
	}

	removeAttribute( name ) {
		this.attributes.delete( name );
		if ( name === "href" ) {
			this.href = "";
		}
		if ( name.startsWith( "data-" ) ) {
			delete this.dataset[ dataKey( name ) ];
		}
	}

	replaceChildren( ...children ) {
		this.children.forEach( ( child ) => {
			child.parentNode = null;
		} );
		this.children = [];
		this._textContent = "";
		this.append( ...children );
	}

	select() {}

	setAttribute( name, value ) {
		const normalized = String( value );
		if ( name === "class" ) {
			this.className = normalized;
			return;
		}
		if ( name === "id" ) {
			this.id = normalized;
			return;
		}
		if ( name === "href" ) {
			this.href = normalized;
		}
		if ( name.startsWith( "data-" ) ) {
			this.dataset[ dataKey( name ) ] = normalized;
		}
		this.attributes.set( name, normalized );
	}
}

class MockDocument extends MockEventTarget {
	constructor() {
		super();
		this.innerHTMLWrites = [];
		this.textContentWrites = [];
		this.documentElement = new MockElement( this, "html" );
		this.body = new MockElement( this, "body" );
		this.documentElement.append( this.body );
	}

	createElement( tagName ) {
		return new MockElement( this, tagName );
	}

	execCommand() {
		return true;
	}

	querySelector( selector ) {
		if ( this.documentElement.matches( selector ) ) {
			return this.documentElement;
		}
		return this.documentElement.querySelector( selector );
	}

	querySelectorAll( selector ) {
		const matches = this.documentElement.matches( selector ) ? [ this.documentElement ] : [];
		return matches.concat( this.documentElement.querySelectorAll( selector ) );
	}
}

class MockWindow extends MockEventTarget {
	constructor( document, options = {} ) {
		super();
		this.document = document;
		this.fetch = options.fetch || ( async () => {
			throw new Error( "Unexpected fetch" );
		} );
		this.isSecureContext = options.isSecureContext ?? true;
		this.location = {
			origin: options.origin || "https://demo.test",
			reload: () => {
				this.reloads++;
			},
		};
		this.navigator = options.navigator || { clipboard: null };
		this.reloads = 0;
		this.self = this;
		this.top = options.top || this;
		this.parent = options.parent || this;
		this.wmcpConfig = options.wmcpConfig || {};
		this.wmcpRuntime = options.wmcpRuntime;
	}

	requestAnimationFrame( callback ) {
		callback( 0 );
		return 1;
	}

	setTimeout( callback ) {
		callback();
		return 1;
	}
}

function dataKey( attribute ) {
	return attribute.slice( 5 ).replace( /-([a-z])/g, ( _match, character ) => character.toUpperCase() );
}

function descendants( element ) {
	return element.children.flatMap( ( child ) => [ child, ...descendants( child ) ] );
}

function matchesSelector( element, selector ) {
	const parts = selector.split( /\s+/ ).filter( Boolean );
	if ( parts.length === 0 || ! matchesCompound( element, parts.at( -1 ) ) ) {
		return false;
	}

	let ancestor = element.parentNode;
	for ( let index = parts.length - 2; index >= 0; index-- ) {
		while ( ancestor && ! matchesCompound( ancestor, parts[ index ] ) ) {
			ancestor = ancestor.parentNode;
		}
		if ( ! ancestor ) {
			return false;
		}
		ancestor = ancestor.parentNode;
	}
	return true;
}

function matchesCompound( element, selector ) {
	const structuralSelector = selector.replace( /\[[^\]]+\]/g, "" );
	const tag = structuralSelector.match( /^[a-zA-Z][\w-]*/ )?.[ 0 ];
	if ( tag && element.tagName !== tag.toUpperCase() ) {
		return false;
	}

	const id = structuralSelector.match( /#([\w-]+)/ )?.[ 1 ];
	if ( id && element.id !== id ) {
		return false;
	}

	for ( const match of structuralSelector.matchAll( /\.([\w-]+)/g ) ) {
		if ( ! element.classList.contains( match[ 1 ] ) ) {
			return false;
		}
	}

	for ( const match of selector.matchAll( /\[([^\]=]+)(?:=(["'])(.*?)\2)?\]/g ) ) {
		const actual = element.getAttribute( match[ 1 ] );
		if ( actual === null || ( match[ 3 ] !== undefined && actual !== match[ 3 ] ) ) {
			return false;
		}
	}

	return true;
}

function element( document, tagName, options = {} ) {
	const target = document.createElement( tagName );
	if ( options.id ) {
		target.id = options.id;
	}
	if ( options.className ) {
		target.className = options.className;
	}
	Object.assign( target.dataset, options.dataset || {} );
	Object.entries( options.attributes || {} ).forEach( ( [ name, value ] ) => target.setAttribute( name, value ) );
	if ( options.text !== undefined ) {
		target.textContent = options.text;
	}
	if ( options.hidden !== undefined ) {
		target.hidden = options.hidden;
	}
	if ( options.children ) {
		target.append( ...options.children );
	}
	return target;
}

function jsonResponse( payload, options = {} ) {
	const status = options.status ?? 200;
	const body = options.body ?? JSON.stringify( payload );
	const headers = new Map( Object.entries( options.headers || {} ).map( ( [ key, value ] ) => [ key.toLowerCase(), String( value ) ] ) );
	return {
		headers: {
			get: ( name ) => headers.get( String( name ).toLowerCase() ) ?? null,
		},
		json: async () => payload,
		ok: status >= 200 && status < 300,
		status,
		text: async () => body,
	};
}

function queuedFetch( responses ) {
	const queue = Array.from( responses );
	const calls = [];
	const fetch = async ( url, options = {} ) => {
		calls.push( { options, url } );
		if ( queue.length === 0 ) {
			throw new Error( `Unexpected fetch: ${ options.method || "GET" } ${ url }` );
		}
		const response = queue.shift();
		return typeof response === "function" ? response( url, options ) : response;
	};
	fetch.calls = calls;
	return fetch;
}

function runBrowserScript( absolutePath, options ) {
	const { document, window } = options;
	document.defaultView = window;
	const sandbox = {
		Blob,
		CustomEvent: MockCustomEvent,
		Date,
		Intl,
		TextEncoder,
		URL,
		console,
		crypto: webcrypto,
		document,
		encodeURIComponent,
		fetch: window.fetch,
		navigator: window.navigator,
		window,
	};
	vm.runInNewContext( readFileSync( absolutePath, "utf8" ), sandbox, { filename: absolutePath } );
	return { document, window };
}

async function waitFor( predicate, message = "condition was not met" ) {
	for ( let attempt = 0; attempt < 50; attempt++ ) {
		if ( predicate() ) {
			return;
		}
		await new Promise( ( resolve ) => setImmediate( resolve ) );
	}
	throw new Error( message );
}

module.exports = {
	MockCustomEvent,
	MockDocument,
	MockWindow,
	element,
	jsonResponse,
	queuedFetch,
	runBrowserScript,
	waitFor,
};
