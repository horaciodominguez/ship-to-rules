<?php
/**
 * STR_Rules unit tests (pure logic).
 *
 * @package ShipToRules
 */

use PHPUnit\Framework\TestCase;

/**
 * Class RulesTest
 */
class RulesTest extends TestCase {

	/**
	 * Allow mode includes only listed countries.
	 */
	public function test_evaluate_rule_allow_mode() {
		$rule = array(
			'mode'      => STR_Rules::MODE_ALLOW,
			'countries' => array( 'US', 'CA' ),
		);

		$this->assertTrue( STR_Rules::evaluate_rule( $rule, 'US' ) );
		$this->assertFalse( STR_Rules::evaluate_rule( $rule, 'DE' ) );
	}

	/**
	 * Deny mode excludes listed countries.
	 */
	public function test_evaluate_rule_deny_mode() {
		$rule = array(
			'mode'      => STR_Rules::MODE_DENY,
			'countries' => array( 'BR' ),
		);

		$this->assertFalse( STR_Rules::evaluate_rule( $rule, 'BR' ) );
		$this->assertTrue( STR_Rules::evaluate_rule( $rule, 'AR' ) );
	}

	/**
	 * Allowed countries from allow rule.
	 */
	public function test_countries_from_rule_allow() {
		$all  = array( 'US', 'CA', 'MX', 'DE' );
		$rule = array(
			'mode'      => STR_Rules::MODE_ALLOW,
			'countries' => array( 'US', 'MX' ),
		);

		$this->assertSame( array( 'US', 'MX' ), STR_Rules::countries_from_rule( $rule, $all ) );
	}

	/**
	 * Allowed countries from deny rule.
	 */
	public function test_countries_from_rule_deny() {
		$all  = array( 'US', 'CA', 'MX' );
		$rule = array(
			'mode'      => STR_Rules::MODE_DENY,
			'countries' => array( 'MX' ),
		);

		$this->assertSame( array( 'US', 'CA' ), STR_Rules::countries_from_rule( $rule, $all ) );
	}

	/**
	 * Resolve parent product ID from variation.
	 */
	public function test_resolve_product_id_returns_parent_for_variation() {
		$this->assertSame( 10, STR_Rules::resolve_product_id( 10, 0 ) );
	}
}
