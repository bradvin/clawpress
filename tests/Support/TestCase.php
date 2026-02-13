<?php
/**
 * Base test case utilities.
 *
 * @package ClawPress\Tests
 */

declare( strict_types=1 );

namespace ClawPress\Tests\Support;

abstract class TestCase extends \PHPUnit\Framework\TestCase {
	protected function setUp(): void {
		parent::setUp();
		WordPress_Stubs::reset();
	}
}
