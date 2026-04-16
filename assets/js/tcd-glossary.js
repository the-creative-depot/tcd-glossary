(function () {
	'use strict';

	/**
	 * Safely parse an HTML string and return a DocumentFragment.
	 * Uses DOMParser so no raw string-to-DOM assignment occurs.
	 *
	 * @param {string} htmlString Server-rendered HTML.
	 * @returns {DocumentFragment}
	 */
	function parseSafeHTML( htmlString ) {
		var doc      = new DOMParser().parseFromString( htmlString, 'text/html' );
		var fragment = document.createDocumentFragment();
		while ( doc.body.firstChild ) {
			fragment.appendChild( doc.body.firstChild );
		}
		return fragment;
	}

	function initGlossary( container ) {
		var postType = container.getAttribute( 'data-post-type' );
		var taxonomy = container.getAttribute( 'data-taxonomy' );
		var widgetId = container.getAttribute( 'data-widget-id' );
		var nav      = container.querySelector( '.tcd-glossary__nav' );

		// Pill click handlers
		var pills = container.querySelectorAll( '.tcd-glossary__filter-pill' );
		pills.forEach( function ( pill ) {
			pill.addEventListener( 'click', function () {
				var termSlug = this.getAttribute( 'data-term' );
				setActivePill( container, this );
				fetchFiltered( container, postType, taxonomy, termSlug, widgetId, nav );
			} );
		} );

		// Dropdown change handler
		var dropdown = container.querySelector( '.tcd-glossary__filter-dropdown' );
		if ( dropdown ) {
			dropdown.addEventListener( 'change', function () {
				fetchFiltered( container, postType, taxonomy, this.value, widgetId, nav );
			} );
		}
	}

	function setActivePill( container, activePill ) {
		var pills = container.querySelectorAll( '.tcd-glossary__filter-pill' );
		pills.forEach( function ( pill ) {
			pill.classList.remove( 'is-active' );
		} );
		activePill.classList.add( 'is-active' );
	}

	function fetchFiltered( container, postType, taxonomy, termSlug, widgetId, nav ) {
		var body = new FormData();
		body.append( 'action', 'tcd_glossary_filter' );
		body.append( 'nonce', tcdGlossary.nonce );
		body.append( 'post_type', postType );
		body.append( 'taxonomy', taxonomy );
		body.append( 'term_slug', termSlug );
		body.append( 'widget_id', widgetId );

		container.classList.add( 'is-loading' );

		fetch( tcdGlossary.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: body,
		} )
			.then( function ( response ) {
				return response.json();
			} )
			.then( function ( result ) {
				if ( result.success ) {
					// Replace nav list using safe DOM parsing
					if ( nav ) {
						while ( nav.firstChild ) {
							nav.removeChild( nav.firstChild );
						}
						nav.appendChild( parseSafeHTML( result.data.nav ) );
					}
					// Replace sections (remove old sections + empty message, insert new)
					var oldSections = container.querySelector( '.tcd-glossary__sections' );
					var oldEmpty    = container.querySelector( '.tcd-glossary__empty' );
					if ( oldSections ) {
						oldSections.remove();
					}
					if ( oldEmpty ) {
						oldEmpty.remove();
					}
					container.appendChild( parseSafeHTML( result.data.sections ) );
				}
			} )
			.finally( function () {
				container.classList.remove( 'is-loading' );
			} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var glossaries = document.querySelectorAll( '.tcd-glossary' );
		glossaries.forEach( initGlossary );
	} );
})();
