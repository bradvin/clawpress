<?php
/**
 * ClawPress abilities module.
 *
 * @package ClawPress
 */

declare( strict_types=1 );

namespace ClawPress\Abilities;

use ClawPress\Abilities\BuiltIn\File_Delete_Ability;
use ClawPress\Abilities\BuiltIn\File_List_Ability;
use ClawPress\Abilities\BuiltIn\File_Read_Ability;
use ClawPress\Abilities\BuiltIn\File_Write_Ability;
use ClawPress\Abilities\BuiltIn\Memory_Long_Term_Add_Ability;
use ClawPress\Abilities\BuiltIn\Memory_Long_Term_Delete_Ability;
use ClawPress\Abilities\BuiltIn\Memory_Long_Term_Update_Ability;
use ClawPress\Abilities\BuiltIn\Memory_Short_Term_Add_Ability;
use ClawPress\Abilities\BuiltIn\Memory_Short_Term_Delete_Ability;
use ClawPress\Abilities\BuiltIn\Memory_Short_Term_Update_Ability;

defined( 'ABSPATH' ) || exit;

/**
 * Registers all ClawPress abilities through the WordPress Abilities API.
 */
final class Abilities {
	/**
	 * Abilities category slug.
	 */
	public const CATEGORY_SLUG = 'clawpress';

	/**
	 * Register hooks.
	 */
	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Register the ClawPress ability category.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY_SLUG,
			[
				'label'       => __( 'ClawPress', 'clawpress' ),
				'description' => __( 'Abilities provided by the ClawPress plugin.', 'clawpress' ),
			]
		);
	}

	/**
	 * Register all built-in abilities.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		File_Read_Ability::register();
		File_Write_Ability::register();
		File_Delete_Ability::register();
		File_List_Ability::register();

		Memory_Short_Term_Add_Ability::register();
		Memory_Short_Term_Update_Ability::register();
		Memory_Short_Term_Delete_Ability::register();

		Memory_Long_Term_Add_Ability::register();
		Memory_Long_Term_Update_Ability::register();
		Memory_Long_Term_Delete_Ability::register();
	}
}
