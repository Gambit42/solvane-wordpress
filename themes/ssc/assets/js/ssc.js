/* Scroll reveal (same pattern as Evercrest): .reveal rises + fades in once; .reveal-group staggers its children.
   Editors can add either class under Block → Advanced → Additional CSS class. */
( () => {
	const io = new IntersectionObserver(
		( entries ) => {
			for ( const entry of entries ) {
				if ( ! entry.isIntersecting ) continue;
				entry.target.classList.add( 'is-in' );
				io.unobserve( entry.target );
			}
		},
		{ rootMargin: '0px 0px -12% 0px' }
	);

	// Site-wide defaults, so every page and template animates without per-block classes.
	const tag = ( selector, cls ) =>
		document.querySelectorAll( selector ).forEach( ( el ) => {
			if ( ! el.closest( '.reveal, .reveal-group' ) ) el.classList.add( cls );
		} );
	tag( '.card-grid, .steps-grid, .service-grid, .wp-block-post-template', 'reveal-group' );
	tag( '.band .section-head, .band .wp-block-columns, .band .wp-block-details, .cta-box, main .wp-block-post-featured-image.alignwide, .single main .wp-block-post-content > *', 'reveal' );

	document.querySelectorAll( '.reveal, .reveal-group' ).forEach( ( el ) => {
		if ( el.classList.contains( 'reveal-group' ) ) {
			[ ...el.children ].forEach( ( child, i ) => child.style.setProperty( '--i', i ) );
		}
		io.observe( el );
	} );
} )();
