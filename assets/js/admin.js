/**
 * Promo Engine admin: conditional form rows, threshold repeater, confirmations.
 */
( () => {
	'use strict';

	document.addEventListener( 'click', ( event ) => {
		const link = event.target.closest( '[data-pe-confirm]' );

		if ( link && ! window.confirm( link.dataset.peConfirm ) ) {
			event.preventDefault();
		}
	} );

	const form = document.querySelector( '[data-pe-form]' );

	if ( ! form ) {
		return;
	}

	const type = form.querySelector( '[data-pe-type]' );
	const scope = form.querySelector( '[data-pe-scope]' );

	const toggleRows = ( attribute, value ) => {
		form.querySelectorAll( `[${ attribute }]` ).forEach( ( row ) => {
			row.hidden = ! row.getAttribute( attribute ).split( ' ' ).includes( value );
		} );
	};

	const sync = () => {
		toggleRows( 'data-pe-types', type.value );
		toggleRows( 'data-pe-scopes', scope.value );
	};

	const abToggle = form.querySelector( '[data-pe-ab-toggle]' );
	const syncAb = () => {
		form.querySelectorAll( '[data-pe-ab-row]' ).forEach( ( row ) => {
			row.hidden = ! abToggle.checked;
		} );
	};

	type.addEventListener( 'change', sync );
	scope.addEventListener( 'change', sync );
	abToggle.addEventListener( 'change', syncAb );
	sync();
	syncAb();

	const tiers = form.querySelector( '[data-pe-tiers] tbody' );
	const template = document.querySelector( '[data-pe-tier-template]' );
	let nextIndex = tiers.querySelectorAll( '[data-pe-tier]' ).length;

	form.addEventListener( 'click', ( event ) => {
		if ( event.target.closest( '[data-pe-add-tier]' ) ) {
			const row = template.content.firstElementChild.cloneNode( true );

			row.querySelectorAll( 'input' ).forEach( ( input ) => {
				input.name = input.name.replace( '__i__', String( nextIndex ) );
			} );
			nextIndex++;
			tiers.appendChild( row );
			row.querySelector( 'input' ).focus();
			return;
		}

		const remove = event.target.closest( '[data-pe-remove-tier]' );

		if ( remove ) {
			remove.closest( '[data-pe-tier]' ).remove();
		}
	} );
} )();
