/**
 * Minimal SVG line chart for the analytics screen. No dependencies.
 */
( () => {
	'use strict';

	const data = window.promoEngineChart;

	if ( ! data ) {
		return;
	}

	const NS = 'http://www.w3.org/2000/svg';
	const COLORS = [ '#2271b1', '#d63638', '#dba617', '#00a32a' ];
	const WIDTH = 960;
	const HEIGHT = 260;
	const PAD = { top: 16, right: 16, bottom: 28, left: 64 };
	const TICKS = 4;
	const days = Object.keys( data.series );

	const svgNode = ( name, attrs, parent ) => {
		const node = document.createElementNS( NS, name );
		Object.entries( attrs ).forEach( ( [ key, value ] ) => node.setAttribute( key, value ) );
		parent.appendChild( node );
		return node;
	};

	const niceStep = ( raw ) => {
		if ( raw <= 0 ) {
			return 1;
		}
		const pow = 10 ** Math.floor( Math.log10( raw ) );
		return [ 1, 2, 2.5, 5, 10 ].find( ( m ) => m * pow >= raw ) * pow;
	};

	function render( container ) {
		const keys = container.dataset.peChart.split( ',' );
		const money = container.hasAttribute( 'data-pe-money' );
		const format = ( value ) =>
			( money ? data.currency : '' ) + value.toLocaleString( undefined, { maximumFractionDigits: money ? 2 : 0 } );

		const values = keys.map( ( key ) => days.map( ( day ) => Number( data.series[ day ][ key ] ) || 0 ) );
		const step = niceStep( Math.max( 0, ...values.flat() ) / TICKS );
		const max = step * TICKS;
		const innerWidth = WIDTH - PAD.left - PAD.right;
		const innerHeight = HEIGHT - PAD.top - PAD.bottom;
		const x = ( i ) => PAD.left + ( days.length > 1 ? ( i * innerWidth ) / ( days.length - 1 ) : innerWidth / 2 );
		const y = ( value ) => PAD.top + innerHeight - ( value / max ) * innerHeight;

		const svg = document.createElementNS( NS, 'svg' );
		svg.setAttribute( 'viewBox', `0 0 ${ WIDTH } ${ HEIGHT }` );
		svg.setAttribute( 'class', 'pe-chart__svg' );

		for ( let i = 0; i <= TICKS; i++ ) {
			const value = step * i;
			svgNode( 'line', { x1: PAD.left, x2: WIDTH - PAD.right, y1: y( value ), y2: y( value ), class: 'pe-chart__grid' }, svg );
			svgNode( 'text', { x: PAD.left - 8, y: y( value ) + 4, 'text-anchor': 'end', class: 'pe-chart__tick' }, svg ).textContent = format( value );
		}

		const every = Math.ceil( days.length / 8 );

		days.forEach( ( day, i ) => {
			if ( i % every === 0 || i === days.length - 1 ) {
				svgNode( 'text', { x: x( i ), y: HEIGHT - 8, 'text-anchor': 'middle', class: 'pe-chart__tick' }, svg ).textContent = day.slice( 5 );
			}
		} );

		values.forEach( ( series, s ) => {
			svgNode(
				'polyline',
				{
					points: series.map( ( value, i ) => `${ x( i ) },${ y( value ) }` ).join( ' ' ),
					fill: 'none',
					stroke: COLORS[ s % COLORS.length ],
					'stroke-width': 2,
					'stroke-linejoin': 'round',
				},
				svg
			);
		} );

		const cursor = svgNode( 'line', { y1: PAD.top, y2: PAD.top + innerHeight, class: 'pe-chart__cursor', visibility: 'hidden' }, svg );

		const legend = document.createElement( 'ul' );
		legend.className = 'pe-chart__legend';
		keys.forEach( ( key, s ) => {
			const item = document.createElement( 'li' );
			item.style.setProperty( '--pe-series', COLORS[ s % COLORS.length ] );
			item.textContent = data.labels[ key ] || key;
			legend.appendChild( item );
		} );

		const tooltip = document.createElement( 'div' );
		tooltip.className = 'pe-chart__tooltip';
		tooltip.hidden = true;

		svg.addEventListener( 'mousemove', ( event ) => {
			const rect = svg.getBoundingClientRect();
			const px = ( ( event.clientX - rect.left ) * WIDTH ) / rect.width;
			const i = Math.min( days.length - 1, Math.max( 0, Math.round( ( ( px - PAD.left ) / innerWidth ) * ( days.length - 1 ) ) ) );

			cursor.setAttribute( 'x1', x( i ) );
			cursor.setAttribute( 'x2', x( i ) );
			cursor.setAttribute( 'visibility', 'visible' );

			tooltip.replaceChildren();
			const title = document.createElement( 'strong' );
			title.textContent = days[ i ];
			tooltip.appendChild( title );

			keys.forEach( ( key, s ) => {
				const row = document.createElement( 'span' );
				row.style.setProperty( '--pe-series', COLORS[ s % COLORS.length ] );
				row.textContent = `${ data.labels[ key ] || key }: ${ format( values[ s ][ i ] ) }`;
				tooltip.appendChild( row );
			} );

			tooltip.hidden = false;
			tooltip.style.left = `${ ( x( i ) / WIDTH ) * rect.width }px`;
		} );

		svg.addEventListener( 'mouseleave', () => {
			cursor.setAttribute( 'visibility', 'hidden' );
			tooltip.hidden = true;
		} );

		container.append( legend, svg, tooltip );
	}

	document.querySelectorAll( '[data-pe-chart]' ).forEach( render );
} )();
