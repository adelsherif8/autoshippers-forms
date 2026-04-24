<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class AS_Updater {

  private $repo;
  private $slug;
  private $file;
  private $version;
  private $transient_key;

  public function __construct( $repo, $plugin_file, $version ) {
    $this->repo          = $repo;
    $this->file          = $plugin_file;
    $this->slug          = plugin_basename( $plugin_file );
    $this->version       = $version;
    $this->transient_key = 'as_gh_release_' . md5( $repo );

    add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
    add_filter( 'plugins_api',                           [ $this, 'plugin_info'  ], 20, 3 );
    add_action( 'admin_init',                            [ $this, 'inject_update' ] );
  }

  /* ── Directly inject update into WP transient on plugins/updates screen ── */
  public function inject_update() {
    global $pagenow;
    if ( ! in_array( $pagenow, [ 'plugins.php', 'update-core.php' ], true ) ) return;

    delete_transient( $this->transient_key );
    $release = $this->get_release();
    if ( ! $release ) return;

    $latest = ltrim( $release->tag_name, 'v' );
    if ( ! version_compare( $this->version, $latest, '<' ) ) return;

    $package  = $this->get_zip_url( $release );
    if ( ! $package ) return;

    $raw_base = "https://raw.githubusercontent.com/{$this->repo}/master/assets/img";
    $update   = (object) [
      'slug'        => dirname( $this->slug ),
      'plugin'      => $this->slug,
      'new_version' => $latest,
      'url'         => "https://github.com/{$this->repo}",
      'package'     => $package,
      'icons'       => [
        'svg' => "{$raw_base}/icon.svg",
        '1x'  => "{$raw_base}/icon.svg",
      ],
    ];

    $current = get_site_transient( 'update_plugins' );
    if ( ! is_object( $current ) ) $current = new stdClass();
    if ( ! isset( $current->response ) ) $current->response = [];
    if ( ! isset( $current->checked ) )  $current->checked  = [];

    $current->response[ $this->slug ] = $update;
    $current->checked[ $this->slug ]  = $this->version;
    $current->last_checked            = time();

    set_site_transient( 'update_plugins', $current );
  }

  /* ── Fallback: hook into WP's normal check cycle ── */
  public function check_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $release = $this->get_release();
    if ( ! $release ) return $transient;

    $latest   = ltrim( $release->tag_name, 'v' );
    $raw_base = "https://raw.githubusercontent.com/{$this->repo}/master/assets/img";

    if ( version_compare( $this->version, $latest, '<' ) ) {
      $package = $this->get_zip_url( $release );
      if ( $package ) {
        $transient->response[ $this->slug ] = (object) [
          'slug'        => dirname( $this->slug ),
          'plugin'      => $this->slug,
          'new_version' => $latest,
          'url'         => "https://github.com/{$this->repo}",
          'package'     => $package,
          'icons'       => [
            'svg' => "{$raw_base}/icon.svg",
            '1x'  => "{$raw_base}/icon.svg",
          ],
        ];
      }
    }
    return $transient;
  }

  /* ── View details popup ── */
  public function plugin_info( $result, $action, $args ) {
    if ( $action !== 'plugin_information' ) return $result;
    if ( $args->slug !== dirname( $this->slug ) ) return $result;

    $release = $this->get_release();
    if ( ! $release ) return $result;

    $raw_base = "https://raw.githubusercontent.com/{$this->repo}/master/assets/img";
    return (object) [
      'name'          => 'AutoShippers Forms',
      'slug'          => dirname( $this->slug ),
      'version'       => ltrim( $release->tag_name, 'v' ),
      'author'        => 'Adel Emad',
      'download_link' => $this->get_zip_url( $release ),
      'icons'         => [
        'svg' => "{$raw_base}/icon.svg",
        '1x'  => "{$raw_base}/icon.svg",
      ],
      'sections' => [ 'changelog' => nl2br( esc_html( $release->body ?? 'Improvements and bug fixes.' ) ) ],
    ];
  }

  private function get_release() {
    $cached = get_transient( $this->transient_key );
    if ( $cached !== false ) return $cached;

    $response = wp_remote_get(
      "https://api.github.com/repos/{$this->repo}/releases/latest",
      [ 'headers' => [ 'User-Agent' => 'WordPress/AS-Updater' ], 'timeout' => 10 ]
    );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) return null;

    $release = json_decode( wp_remote_retrieve_body( $response ) );
    set_transient( $this->transient_key, $release, MINUTE_IN_SECONDS * 30 );
    return $release;
  }

  private function get_zip_url( $release ) {
    if ( ! empty( $release->assets ) ) {
      foreach ( $release->assets as $asset ) {
        if ( str_ends_with( $asset->name, '.zip' ) ) return $asset->browser_download_url;
      }
    }
    return null;
  }
}
