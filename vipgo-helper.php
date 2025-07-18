<?php

// VIP Go-specific code can go here...

// Temporary workaround for sitemaps returning 404 (https://core.trac.wordpress.org/ticket/51136)
add_action(
	'init',
	function() {
		global $wp_sitemaps;
		remove_action( 'template_redirect', array( $wp_sitemaps, 'render_sitemaps' ) );
	},
	100 
);
