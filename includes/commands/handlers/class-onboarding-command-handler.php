<?php
/**
 * /onboarding command handler.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Commands\Handlers;

use ClawPress\Commands\Command_Handler;
use ClawPress\Commands\Command_Request;
use ClawPress\Commands\Command_Response;
use ClawPress\Helpers\Settings_Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Onboarding command.
 */
final class Onboarding_Command_Handler implements Command_Handler {
	/**
	 * Onboarding option key.
	 */
	private const ONBOARDING_STATE_OPTION = 'clawpress_onboarding_state';

	/**
	 * Default onboarding step.
	 */
	private const DEFAULT_STEP = 'welcome';

	/**
	 * Settings helper.
	 *
	 * @var Settings_Helper
	 */
	private Settings_Helper $settings_helper;

	/**
	 * Constructor.
	 *
	 * @param Settings_Helper $settings_helper Settings helper.
	 */
	public function __construct( Settings_Helper $settings_helper ) {
		$this->settings_helper = $settings_helper;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_command(): string {
		return '/onboarding';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return __( 'Manage onboarding state: start, resume, or reset.', 'clawpress' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_usage(): string {
		return '/onboarding start|resume|reset';
	}

	/**
	 * {@inheritDoc}
	 */
	public function is_destructive(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_default_suggestions(): array {
		return [ '/onboarding resume' ];
	}

	/**
	 * {@inheritDoc}
	 */
	public function handle( Command_Request $request ): Command_Response {
		$action = strtolower( $request->get_argument( 0 ) );
		if ( '' === $action ) {
			$action = 'resume';
		}

		switch ( $action ) {
			case 'start':
				return $this->start_onboarding();
			case 'resume':
				return $this->resume_onboarding();
			case 'reset':
				return $this->reset_onboarding();
			default:
				return Command_Response::error(
					sprintf(
							/* translators: %s: expected command usage */
						__( 'Invalid onboarding action. Expected: `%s`', 'clawpress' ),
						$this->get_usage()
					),
					$this->get_command(),
					false,
					false,
					[],
					[ '/onboarding resume', '/help', '/status' ]
				);
		}
	}

	/**
	 * Start onboarding flow.
	 */
	private function start_onboarding(): Command_Response {
		$this->persist_onboarding_step( self::DEFAULT_STEP );
		$this->settings_helper->update_settings( [ 'onboarding_completed' => false ] );

		return Command_Response::success(
			sprintf(
				/* translators: %s: onboarding step slug */
				__( 'Onboarding started. Current step: `%s`.', 'clawpress' ),
				self::DEFAULT_STEP
			),
			$this->get_command(),
			false,
			false,
			[],
			[ '/onboarding resume', '/status', '/help' ]
		);
	}

	/**
	 * Resume onboarding flow.
	 */
	private function resume_onboarding(): Command_Response {
		$settings = $this->settings_helper->get_settings();
		$step     = $this->resolve_onboarding_step( $settings );

		return Command_Response::success(
			sprintf(
				/* translators: %s: onboarding step slug */
				__( 'Onboarding resume point: `%s`.', 'clawpress' ),
				$step
			),
			$this->get_command(),
			false,
			false,
			[],
			[ '/onboarding start', '/onboarding reset', '/status' ]
		);
	}

	/**
	 * Reset onboarding flow.
	 */
	private function reset_onboarding(): Command_Response {
		$this->persist_onboarding_step( self::DEFAULT_STEP );
		$this->settings_helper->update_settings( [ 'onboarding_completed' => false ] );

		return Command_Response::success(
			sprintf(
				/* translators: %s: onboarding step slug */
				__( 'Onboarding reset. Current step: `%s`.', 'clawpress' ),
				self::DEFAULT_STEP
			),
			$this->get_command(),
			false,
			false,
			[],
			[ '/onboarding resume', '/status', '/help' ]
		);
	}

	/**
	 * Resolve current onboarding step.
	 *
	 * @param array<string,mixed> $settings Current settings.
	 */
	private function resolve_onboarding_step( array $settings ): string {
		if ( $this->settings_helper->get_onboarding_completed( $settings ) ) {
			return 'ready';
		}

		$state = get_option( self::ONBOARDING_STATE_OPTION, [] );
		if ( ! is_array( $state ) || empty( $state['step'] ) || ! is_string( $state['step'] ) ) {
			return self::DEFAULT_STEP;
		}

		return trim( $state['step'] );
	}

	/**
	 * Persist onboarding step.
	 *
	 * @param string $step Step identifier.
	 */
	private function persist_onboarding_step( string $step ): void {
		update_option(
			self::ONBOARDING_STATE_OPTION,
			[
				'step'       => $step,
				'updated_at' => time(),
			]
		);
	}
}
