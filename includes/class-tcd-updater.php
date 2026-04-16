<?php
/**
 * GitHub release auto-updater.
 *
 * Checks the GitHub releases API for new versions and injects update data
 * into WordPress's plugin update transient.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TCD_Updater {

	private $plugin_slug;
	private $plugin_basename;
	private $github_repo;
	private $current_version;
	private $transient_key = 'tcd_glossary_update_check';
	private $cache_hours   = 12;

	/**
	 * @param string $plugin_basename Plugin basename (e.g. 'tcd-glossary/tcd-glossary.php').
	 * @param string $github_repo     GitHub owner/repo (e.g. 'tcd/tcd-glossary').
	 * @param string $current_version Current plugin version.
	 */
	public function __construct( $plugin_basename, $github_repo, $current_version ) {
		$this->plugin_basename = $plugin_basename;
		$this->plugin_slug     = dirname( $plugin_basename );
		$this->github_repo     = $github_repo;
		$this->current_version = $current_version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'upgrader_post_install', array( $this, 'after_install' ), 10, 3 );
	}

	/**
	 * Fetch latest release data from GitHub (cached).
	 *
	 * @return object|false Release data or false on failure.
	 */
	private function get_release_data() {
		$cached = get_transient( $this->transient_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$url      = 'https://api.github.com/repos/' . $this->github_repo . '/releases/latest';
		$response = wp_remote_get( $url, array(
			'headers' => array(
				'Accept' => 'application/vnd.github.v3+json',
			),
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );
		if ( empty( $body->tag_name ) ) {
			return false;
		}

		set_transient( $this->transient_key, $body, $this->cache_hours * HOUR_IN_SECONDS );

		return $body;
	}

	/**
	 * Get the download URL for the zip asset from a release.
	 *
	 * @param object $release GitHub release object.
	 * @return string|false Download URL or false.
	 */
	private function get_zip_url( $release ) {
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( substr( $asset->name, -4 ) === '.zip' ) {
					return $asset->browser_download_url;
				}
			}
		}

		// Fallback to zipball URL.
		if ( ! empty( $release->zipball_url ) ) {
			return $release->zipball_url;
		}

		return false;
	}

	/**
	 * Normalize a version tag (strip leading 'v').
	 *
	 * @param string $tag Version tag.
	 * @return string
	 */
	private function normalize_version( $tag ) {
		return ltrim( $tag, 'vV' );
	}

	/**
	 * Inject update data into the update_plugins transient.
	 *
	 * @param object $transient The update_plugins transient data.
	 * @return object
	 */
	public function check_for_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_release_data();
		if ( ! $release ) {
			return $transient;
		}

		$remote_version = $this->normalize_version( $release->tag_name );
		$zip_url        = $this->get_zip_url( $release );

		if ( ! $zip_url ) {
			return $transient;
		}

		if ( version_compare( $remote_version, $this->current_version, '>' ) ) {
			$transient->response[ $this->plugin_basename ] = (object) array(
				'slug'        => $this->plugin_slug,
				'plugin'      => $this->plugin_basename,
				'new_version' => $remote_version,
				'url'         => 'https://github.com/' . $this->github_repo,
				'package'     => $zip_url,
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View Details" modal.
	 *
	 * @param false|object|array $result
	 * @param string             $action
	 * @param object             $args
	 * @return false|object
	 */
	public function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		$release = $this->get_release_data();
		if ( ! $release ) {
			return $result;
		}

		$info              = new stdClass();
		$info->name        = 'TCD Glossary';
		$info->slug        = $this->plugin_slug;
		$info->version     = $this->normalize_version( $release->tag_name );
		$info->author      = 'TCD';
		$info->homepage    = 'https://github.com/' . $this->github_repo;
		$info->sections    = array(
			'description' => 'Displays glossary terms in an A-Z grouped layout with Elementor widget support.',
			'changelog'   => nl2br( esc_html( $release->body ) ),
		);
		$info->download_link = $this->get_zip_url( $release );
		$info->requires      = '5.8';
		$info->tested        = '6.5';

		return $info;
	}

	/**
	 * Ensure the installed directory is named correctly after update.
	 *
	 * @param bool  $response
	 * @param array $hook_extra
	 * @param array $result
	 * @return array
	 */
	public function after_install( $response, $hook_extra, $result ) {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== $this->plugin_basename ) {
			return $result;
		}

		$proper_destination = WP_PLUGIN_DIR . '/' . $this->plugin_slug;

		if ( $result['destination'] !== $proper_destination ) {
			$wp_filesystem->move( $result['destination'], $proper_destination );
			$result['destination'] = $proper_destination;
		}

		activate_plugin( $this->plugin_basename );

		return $result;
	}
}
