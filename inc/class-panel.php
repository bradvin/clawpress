<?php
/**
 * Floating admin panel assets and admin-bar toggle.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * Floating panel module.
 */
final class Panel {
	/**
	 * Register all hooks for the panel.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_bar_menu', [ self::class, 'register_admin_bar_toggle' ], 100 );
	}

	/**
	 * Enqueue floating panel assets on wp-admin screens.
	 *
	 * @param string $hook_suffix Current admin screen hook suffix.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		unset( $hook_suffix );

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$asset_file = CLAWPRESS_DIR . 'build/panel/panel.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset              = require $asset_file;
		$style_rel_path     = 'build/panel/panel.css';
		$rtl_style_rel_path = 'build/panel/panel-rtl.css';

		if ( ! file_exists( CLAWPRESS_DIR . $style_rel_path ) ) {
			$style_rel_path = 'build/panel/style-panel.css';
		}

		if ( ! file_exists( CLAWPRESS_DIR . $rtl_style_rel_path ) ) {
			$rtl_style_rel_path = 'build/panel/style-panel-rtl.css';
		}

		wp_enqueue_style(
			'clawpress-panel',
			CLAWPRESS_URL . $style_rel_path,
			[],
			$asset['version']
		);

		if ( is_rtl() && file_exists( CLAWPRESS_DIR . $rtl_style_rel_path ) ) {
			wp_enqueue_style(
				'clawpress-panel-rtl',
				CLAWPRESS_URL . $rtl_style_rel_path,
				[ 'clawpress-panel' ],
				$asset['version']
			);
		}

		wp_enqueue_script(
			'clawpress-panel',
			CLAWPRESS_URL . 'build/panel/panel.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_localize_script(
			'clawpress-panel',
			'CLAWPRESS_PANEL',
			[
				'restBase'     => esc_url_raw( rest_url( 'clawpress/v1' ) ),
				'nonce'        => wp_create_nonce( 'wp_rest' ),
				'streamNonce'  => wp_create_nonce( 'wp_rest' ),
				'userId'       => get_current_user_id(),
				'defaultWidth' => 420,
				'historyLimit' => 20,
				'mockEnabled'  => false,
			]
		);
	}

	/**
	 * Add a top-right admin bar toggle button for the panel.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar Admin bar object.
	 */
	public static function register_admin_bar_toggle( \WP_Admin_Bar $wp_admin_bar ): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$wp_admin_bar->add_node(
			[
				'id'     => 'clawpress-toggle',
				'parent' => 'top-secondary',
				'title'  => __( 'ClawPress', 'clawpress' ),
				'href'   => '#',
				'meta'   => [
					'class' => 'clawpress-adminbar-toggle',
					'title' => __( 'Toggle ClawPress panel', 'clawpress' ),
				],
			]
		);
	}
}
