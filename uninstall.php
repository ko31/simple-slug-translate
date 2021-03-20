<?php
// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

$option_name = 'simple-slug-translate';

delete_option( $option_name );

delete_site_option( $option_name );
