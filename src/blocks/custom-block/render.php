<?php
/**
 * Block render template.
 *
 * @package ClawPress
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;
?>

<div
	<?php echo get_block_wrapper_attributes( [ 'class' => 'clawpress-toggle-block' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	data-wp-interactive="clawpress/toggle-content"
	data-wp-context='{ "isOpen": false }'
>
	<button
		class="clawpress-toggle-block__button"
		data-wp-on--click="actions.toggle"
		data-wp-text="state.buttonText"
	>
		<?php esc_html_e( 'Show Content', 'clawpress' ); ?>
	</button>

	<div
		class="clawpress-toggle-block__content"
		data-wp-bind--hidden="!context.isOpen"
	>
		<p><?php esc_html_e( 'This content is toggled by the Interactivity API. Click the button to hide it.', 'clawpress' ); ?></p>
	</div>
</div>
