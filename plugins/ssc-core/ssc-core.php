<?php
/**
 * Plugin Name: SSC Core
 * Description: Projects, products, enquiry form and SEO tags for Solvane.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Text Domain: ssc-core
 */

defined( 'ABSPATH' ) || exit;

// ponytail: business details live here; move to a settings page if the client needs to edit them without a developer.
const SSC_BUSINESS = array(
	'name'      => 'Solvane',
	'phone'     => '+61 3 9000 0000',
	'email'     => 'hello@solvane.com.au',
	'area'      => 'Melbourne, Victoria',
	'locality'  => 'Melbourne',
	'region'    => 'VIC',
	'country'   => 'AU',
);

/* -------------------------------------------------------------------------
 * Content types
 * ---------------------------------------------------------------------- */

add_action( 'init', 'ssc_register_content_types' );

function ssc_register_content_types() {
	// Taxonomies first, so /projects/service/x/ isn't swallowed by the post type's rewrite rules.
	register_taxonomy(
		'project_service',
		'project',
		array(
			'label'             => 'Services',
			'labels'            => array( 'singular_name' => 'Service' ),
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'projects/service' ),
		)
	);
	register_taxonomy(
		'product_cat',
		'product',
		array(
			'label'             => 'Product types',
			'labels'            => array( 'singular_name' => 'Product type' ),
			'hierarchical'      => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'rewrite'           => array( 'slug' => 'products/type' ),
		)
	);

	register_post_type(
		'project',
		array(
			'label'        => 'Projects',
			'labels'       => array( 'singular_name' => 'Project', 'add_new_item' => 'Add project' ),
			'public'       => true,
			'show_in_rest' => true,
			'has_archive'  => 'projects',
			'rewrite'      => array( 'slug' => 'projects' ),
			'menu_icon'    => 'dashicons-admin-home',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields' ),
		)
	);

	register_post_type(
		'product',
		array(
			'label'        => 'Products',
			'labels'       => array( 'singular_name' => 'Product', 'add_new_item' => 'Add product' ),
			'public'       => true,
			'show_in_rest' => true,
			'has_archive'  => 'products',
			'rewrite'      => array( 'slug' => 'products' ),
			'menu_icon'    => 'dashicons-products',
			'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes', 'custom-fields' ),
		)
	);

	// Every form submission is kept here, so an enquiry is never lost if email delivery fails.
	register_post_type(
		'enquiry',
		array(
			'label'           => 'Enquiries',
			'labels'          => array( 'singular_name' => 'Enquiry' ),
			'public'          => false,
			'show_ui'         => true,
			'menu_icon'       => 'dashicons-email',
			'supports'        => array( 'title', 'editor' ),
			'capability_type' => 'post',
			'capabilities'    => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'    => true,
		)
	);

	add_post_type_support( 'page', 'excerpt' );

	foreach ( SSC_SEO_TYPES as $type ) {
		register_post_meta(
			$type,
			'_ssc_seo_title',
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => true,
				'auth_callback' => fn( $allowed, $key, $post_id ) => current_user_can( 'edit_post', $post_id ),
			)
		);
	}
}

register_activation_hook(
	__FILE__,
	function () {
		ssc_register_content_types();
		flush_rewrite_rules();
	}
);
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/* -------------------------------------------------------------------------
 * Details fields (shown as a fact strip at the top of projects and products)
 * ---------------------------------------------------------------------- */

function ssc_fields( $post_type ) {
	return array(
		'project' => array(
			'suburb'    => 'Suburb',
			'solar'     => 'Solar system (e.g. 10.4 kW, 24 panels)',
			'battery'   => 'Battery (e.g. 13.5 kWh Tesla Powerwall 3)',
			'charger'   => 'EV charger (e.g. 7 kW Zappi)',
			'completed' => 'Completed (e.g. March 2026)',
		),
		'product' => array(
			'brand'    => 'Brand',
			'warranty' => 'Warranty (e.g. 25 years product)',
			'best_for' => 'Best for (e.g. Larger homes with high evening use)',
		),
	)[ $post_type ] ?? array();
}

/** SEO fields apply to everything a visitor can land on. */
const SSC_SEO_TYPES = array( 'page', 'post', 'project', 'product' );

add_action(
	'add_meta_boxes',
	function () {
		foreach ( array( 'project', 'product' ) as $type ) {
			add_meta_box( 'ssc-details', 'Details', 'ssc_details_box', $type, 'normal', 'high' );
		}
		foreach ( SSC_SEO_TYPES as $type ) {
			add_meta_box( 'ssc-seo', 'Search engines', 'ssc_seo_box', $type, 'normal', 'low' );
		}
	}
);

function ssc_details_box( $post ) {
	wp_nonce_field( 'ssc_meta', 'ssc_meta_nonce' );
	foreach ( ssc_fields( $post->post_type ) as $key => $label ) {
		printf(
			'<p><label>%s<br><input class="widefat" name="ssc[%s]" value="%s"></label></p>',
			esc_html( $label ),
			esc_attr( $key ),
			esc_attr( get_post_meta( $post->ID, "_ssc_$key", true ) )
		);
	}
}

function ssc_seo_box( $post ) {
	if ( ! ssc_fields( $post->post_type ) ) { // Details box already printed the nonce.
		wp_nonce_field( 'ssc_meta', 'ssc_meta_nonce' );
	}
	printf(
		'<p><label>Page title in Google (leave blank to use the title above)<br><input class="widefat" name="ssc[seo_title]" value="%s" maxlength="70"></label></p>
		<p class="description">The meta description is the <strong>Excerpt</strong> (Page panel → Excerpt). Aim for 140–160 characters.</p>',
		esc_attr( get_post_meta( $post->ID, '_ssc_seo_title', true ) )
	);
}

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['ssc_meta_nonce'] )
			|| ! wp_verify_nonce( sanitize_key( $_POST['ssc_meta_nonce'] ), 'ssc_meta' )
			|| ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['ssc'] ) ? wp_unslash( (array) $_POST['ssc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized below.
		$keys  = array_keys( ssc_fields( $post->post_type ) );
		if ( in_array( $post->post_type, SSC_SEO_TYPES, true ) ) {
			$keys[] = 'seo_title';
		}
		foreach ( $keys as $key ) {
			$value = sanitize_text_field( $input[ $key ] ?? '' );
			'' === $value ? delete_post_meta( $post_id, "_ssc_$key" ) : update_post_meta( $post_id, "_ssc_$key", $value );
		}
	},
	10,
	2
);

add_filter(
	'the_content',
	function ( $content ) {
		$id = get_the_ID();
		if ( ! is_singular( array( 'project', 'product' ) ) || get_queried_object_id() !== $id ) {
			return $content;
		}
		$rows = '';
		foreach ( ssc_fields( get_post_type( $id ) ) as $key => $label ) {
			$value = get_post_meta( $id, "_ssc_$key", true );
			if ( '' !== $value ) {
				$rows .= sprintf( '<div><dt>%s</dt><dd>%s</dd></div>', esc_html( preg_replace( '/ \(.*\)$/', '', $label ) ), esc_html( $value ) );
			}
		}
		return ( $rows ? "<dl class=\"facts\">$rows</dl>" : '' ) . $content;
	},
	5
);

/* -------------------------------------------------------------------------
 * Enquiry form: [ssc_enquiry]
 * Spam protection: hidden honeypot field + signed timestamp (rejects bots that submit in under 4s
 * or replay an old form). No nonce, because nonces break on cached pages for logged-out visitors.
 * ---------------------------------------------------------------------- */

const SSC_INTERESTS = array( 'Solar', 'Battery', 'EV charger', 'Electrical work' );

add_shortcode(
	'ssc_enquiry',
	function () {
		$status = sanitize_key( $_GET['enquiry'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( 'sent' === $status ) {
			return '<div class="ssc-notice" role="status"><strong>Thanks, your enquiry has been sent.</strong> We reply within one business day.</div>';
		}
		$notice = 'invalid' === $status
			? '<div class="ssc-notice is-error" role="alert">Please add your name, a valid email address and a short message, then send again.</div>'
			: '';
		$time   = time();
		$checks = '';
		foreach ( SSC_INTERESTS as $interest ) {
			$checks .= sprintf( '<label><input type="checkbox" name="interest[]" value="%1$s"> %1$s</label>', esc_attr( $interest ) );
		}
		ob_start();
		?>
		<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput -- static markup. ?>
		<form class="ssc-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="ssc_enquiry">
			<input type="hidden" name="ts" value="<?php echo esc_attr( $time . '.' . wp_hash( 'ssc' . $time ) ); ?>">
			<input type="hidden" name="back" value="<?php echo esc_url( get_permalink() ); ?>">
			<div class="hp" aria-hidden="true"><label>Leave this empty <input name="website" tabindex="-1" autocomplete="off"></label></div>
			<p><label for="ssc-name">Name</label><input id="ssc-name" name="name" required autocomplete="name"></p>
			<p><label for="ssc-phone">Phone</label><input id="ssc-phone" name="phone" type="tel" autocomplete="tel"></p>
			<p><label for="ssc-email">Email</label><input id="ssc-email" name="email" type="email" required autocomplete="email"></p>
			<p><label for="ssc-suburb">Suburb</label><input id="ssc-suburb" name="suburb" autocomplete="address-level2"></p>
			<fieldset class="full"><legend>I'm interested in</legend><div class="checks"><?php echo $checks; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above. ?></div></fieldset>
			<p class="full"><label for="ssc-message">Tell us about your home</label><textarea id="ssc-message" name="message" required placeholder="Roof type, storeys, average quarterly bill, current solar or switchboard age"></textarea></p>
			<p class="full"><button type="submit">Send enquiry</button></p>
		</form>
		<?php
		return ob_get_clean();
	}
);

add_action( 'admin_post_nopriv_ssc_enquiry', 'ssc_handle_enquiry' );
add_action( 'admin_post_ssc_enquiry', 'ssc_handle_enquiry' );

function ssc_handle_enquiry() {
	// phpcs:disable WordPress.Security.NonceVerification -- see spam note above.
	$back = wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['back'] ?? '' ) ), home_url( '/contact/' ) );
	$f    = array(
		'name'    => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
		'email'   => sanitize_email( wp_unslash( $_POST['email'] ?? '' ) ),
		'phone'   => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
		'suburb'  => sanitize_text_field( wp_unslash( $_POST['suburb'] ?? '' ) ),
		'message' => sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) ),
	);
	$interest = array_values( array_intersect( SSC_INTERESTS, (array) wp_unslash( $_POST['interest'] ?? array() ) ) );
	[ $time, $sig ] = array_pad( explode( '.', sanitize_text_field( wp_unslash( $_POST['ts'] ?? '' ) ), 2 ), 2, '' );
	$is_spam = ! empty( $_POST['website'] ) || ! hash_equals( wp_hash( 'ssc' . $time ), $sig )
		|| time() - (int) $time < 4 || time() - (int) $time > DAY_IN_SECONDS;
	// phpcs:enable

	if ( $is_spam ) {
		wp_safe_redirect( add_query_arg( 'enquiry', 'sent', $back ) ); // Don't tell bots they failed.
		exit;
	}
	if ( ! $f['name'] || ! is_email( $f['email'] ) || ! $f['message'] ) {
		wp_safe_redirect( add_query_arg( 'enquiry', 'invalid', $back ) . '#enquiry' );
		exit;
	}

	$body = sprintf(
		"Name: %s\nEmail: %s\nPhone: %s\nSuburb: %s\nInterested in: %s\n\n%s",
		$f['name'],
		$f['email'],
		$f['phone'],
		$f['suburb'],
		implode( ', ', $interest ) ?: '-',
		$f['message']
	);
	wp_insert_post(
		array(
			'post_type'    => 'enquiry',
			'post_status'  => 'private',
			'post_title'   => $f['name'] . ( $f['suburb'] ? " ({$f['suburb']})" : '' ),
			'post_content' => $body,
		)
	);
	wp_mail(
		get_option( 'admin_email' ),
		'New website enquiry: ' . $f['name'],
		$body,
		array( 'Reply-To: ' . $f['name'] . ' <' . $f['email'] . '>' )
	);
	wp_safe_redirect( add_query_arg( 'enquiry', 'sent', $back ) . '#enquiry' );
	exit;
}

/* -------------------------------------------------------------------------
 * SEO: custom title, meta description (excerpt or term description), archive canonicals, Open Graph, schema.
 * Sitemap is WordPress core's /wp-sitemap.xml.
 * ---------------------------------------------------------------------- */

/** Post type archives have no page to edit, so their title and description live here. */
const SSC_ARCHIVE_SEO = array(
	'project' => array( 'Solar, Battery & EV Charger Projects in Melbourne | Solvane', 'Recent solar, home battery and EV charger installs across Melbourne, with the system size, brands and the brief behind each job.' ),
	'product' => array( 'Solar Panels, Batteries & EV Chargers We Install | Solvane', 'The solar panels, inverters, home batteries and EV chargers we install across Melbourne, and the kind of home each one suits best.' ),
);

/** Title override, meta description and canonical URL for the current view. */
function ssc_seo() {
	$obj   = get_queried_object();
	$title = '';
	$desc  = '';
	$url   = '';
	if ( $obj instanceof WP_Post ) { // Singular views, plus the Advice page that lists posts.
		$title = get_post_meta( $obj->ID, '_ssc_seo_title', true );
		$desc  = $obj->post_excerpt;
		$url   = get_permalink( $obj );
	} elseif ( $obj instanceof WP_Term ) {
		$desc = $obj->description;
		$url  = get_term_link( $obj );
	} elseif ( $obj instanceof WP_Post_Type ) {
		[ $title, $desc ] = SSC_ARCHIVE_SEO[ $obj->name ] ?? array( '', '' );
		$url              = get_post_type_archive_link( $obj->name );
	}
	$paged = (int) get_query_var( 'paged' );
	if ( $url && $paged > 1 ) {
		$url = trailingslashit( $url ) . user_trailingslashit( "page/$paged", 'paged' );
	}
	return array(
		'title'       => $title,
		'description' => wp_strip_all_tags( $desc ) ?: get_bloginfo( 'description' ),
		'url'         => $url,
	);
}

add_filter( 'pre_get_document_title', fn( $title ) => ssc_seo()['title'] ?: $title );

// Enquiries and admin-only types stay out of the sitemap.
add_filter( 'wp_sitemaps_post_types', fn( $types ) => array_intersect_key( $types, array_flip( SSC_SEO_TYPES ) ) );
add_filter( 'wp_sitemaps_add_provider', fn( $provider, $name ) => 'users' === $name ? false : $provider, 10, 2 );
// Term archives list one or two items each; the service pages and posts are what should rank.
add_filter( 'wp_sitemaps_taxonomies', '__return_empty_array' );

add_action(
	'wp_head',
	function () {
		$seo   = ssc_seo();
		$obj   = get_queried_object();
		$image = $obj instanceof WP_Post && has_post_thumbnail( $obj )
			? get_the_post_thumbnail_url( $obj, 'large' )
			: plugins_url( 'og-default.jpg', __FILE__ );
		$tags  = array(
			'description'    => $seo['description'],
			'og:type'        => is_single() ? 'article' : 'website',
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:title'       => wp_get_document_title(),
			'og:description' => $seo['description'],
			'og:url'         => $seo['url'],
			'og:image'       => $image,
			'og:locale'      => 'en_AU',
			'twitter:card'   => 'summary_large_image',
		);
		foreach ( array_filter( $tags ) as $key => $value ) {
			printf( '<meta %s="%s" content="%s" />' . "\n", str_starts_with( $key, 'og:' ) ? 'property' : 'name', esc_attr( $key ), esc_attr( wp_strip_all_tags( $value ) ) );
		}
		if ( $seo['url'] && ! is_singular() ) { // Core prints the canonical for singular views only.
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $seo['url'] ) );
		}

		$org = array(
			'@type' => 'Organization',
			'name'  => SSC_BUSINESS['name'],
			'url'   => home_url( '/' ),
		);
		if ( is_front_page() ) {
			$schema = array(
				'@context' => 'https://schema.org',
				'@graph'   => array(
					array(
						'@type' => 'WebSite',
						'name'  => SSC_BUSINESS['name'],
						'url'   => home_url( '/' ),
					),
					array(
						'@type'      => 'Electrician',
						'name'       => SSC_BUSINESS['name'],
						'url'        => home_url( '/' ),
						'image'      => $image,
						'telephone'  => SSC_BUSINESS['phone'],
						'email'      => SSC_BUSINESS['email'],
						'areaServed' => SSC_BUSINESS['area'],
						'address'    => array(
							'@type'           => 'PostalAddress',
							'addressLocality' => SSC_BUSINESS['locality'],
							'addressRegion'   => SSC_BUSINESS['region'],
							'addressCountry'  => SSC_BUSINESS['country'],
						),
						'knowsAbout' => array( 'Solar panel installation', 'Home battery storage', 'EV charger installation', 'Residential electrical' ),
					),
				),
			);
		} elseif ( is_singular( 'post' ) ) {
			$schema = array(
				'@context'         => 'https://schema.org',
				'@type'            => 'BlogPosting',
				'headline'         => get_the_title( $obj ),
				'description'      => $seo['description'],
				'image'            => $image,
				'datePublished'    => get_the_date( 'c', $obj ),
				'dateModified'     => get_the_modified_date( 'c', $obj ),
				'mainEntityOfPage' => $seo['url'],
				'author'           => $org,
				'publisher'        => $org,
			);
		}
		if ( isset( $schema ) ) {
			echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES ) . "</script>\n";
		}
	},
	1
);
