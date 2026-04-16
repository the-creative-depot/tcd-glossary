<?php
/**
 * Plugin Name: TCD Glossary
 * Description: A-Z glossary with Elementor widget, taxonomy filtering, and auto-updates from GitHub.
 * Version:     2.0.4
 * Author:      TCD
 * License:     GPL-2.0-or-later
 * Text Domain: tcd-glossary
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TCD_GLOSSARY_VERSION', '2.0.4' );
define( 'TCD_GLOSSARY_URL', plugin_dir_url( __FILE__ ) );
define( 'TCD_GLOSSARY_PATH', plugin_dir_path( __FILE__ ) );
define( 'TCD_GLOSSARY_GITHUB_REPO', 'the-creative-depot/tcd-glossary' );

require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-query.php';
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-glossary.php';
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-settings.php';
require_once TCD_GLOSSARY_PATH . 'includes/elementor/class-tcd-elementor.php';
require_once TCD_GLOSSARY_PATH . 'includes/class-tcd-updater.php';

add_action( 'plugins_loaded', function () {
	TCD_Glossary::instance();

	if ( is_admin() ) {
		new TCD_Settings();
		new TCD_Updater(
			plugin_basename( __FILE__ ),
			TCD_GLOSSARY_GITHUB_REPO,
			TCD_GLOSSARY_VERSION
		);
	}

	if ( did_action( 'elementor/loaded' ) ) {
		new TCD_Elementor();
	}
} );
