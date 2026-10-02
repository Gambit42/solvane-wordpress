<?php
/** Solvane theme. Content model, forms and SEO live in the SSC Core plugin. */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	function () {
		add_editor_style( 'style.css' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'ssc', get_stylesheet_uri(), array(), filemtime( get_stylesheet_directory() . '/style.css' ) );
		wp_enqueue_script( 'ssc', get_theme_file_uri( 'assets/js/ssc.js' ), array(), filemtime( get_theme_file_path( 'assets/js/ssc.js' ) ), array( 'strategy' => 'defer' ) );
		// Runs in <head> so reveal targets are hidden before first paint (no flash, no jump).
		wp_add_inline_script( 'ssc', 'document.documentElement.classList.add("ssc-js");', 'before' );
	}
);

// Until real photos are added, projects and products show a neutral placeholder instead of an empty gap.
add_filter(
	'post_thumbnail_html',
	function ( $html, $post_id ) {
		if ( $html || ! in_array( get_post_type( $post_id ), array( 'project', 'product', 'post' ), true ) ) {
			return $html;
		}
		$img = array( 'project' => 'placeholder-solar', 'product' => 'placeholder-product', 'post' => 'placeholder-advice' )[ get_post_type( $post_id ) ];
		return sprintf(
			'<img src="%s" alt="" width="800" height="600" loading="lazy" decoding="async">',
			esc_url( get_theme_file_uri( "assets/img/$img.svg" ) )
		);
	},
	10,
	2
);

add_action( 'init', fn() => register_block_style( 'core/list', array( 'name' => 'checkmark', 'label' => __( 'Checkmark', 'ssc' ) ) ) );

// Fallback favicon until a Site Icon is uploaded in Settings → General.
add_action( 'wp_head', fn() => has_site_icon() || printf( '<link rel="icon" href="%s" type="image/svg+xml" />' . "\n", esc_url( get_theme_file_uri( 'assets/img/favicon.svg' ) ) ) );
