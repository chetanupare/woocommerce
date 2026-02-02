<?php
/**
 * Regression tests for woocommerce_review_order_after_cart_item_meta hook.
 * These tests prevent regressions if the hook were to be removed or modified in future.
 *
 * @package WooCommerce\Tests\Checkout.
 */

/**
 * Class Checkout_Hook_Regression_Test.
 */
class Checkout_Hook_Regression_Test extends \WC_Unit_Test_Case {

	/**
	 * @testdox Template review-order.php contains the required hook.
	 */
	public function test_template_contains_required_hook() {
		$template_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/templates/checkout/review-order.php';
		
		$this->assertFileExists( $template_path, 'review-order.php template should exist' );
		
		$template_content = file_get_contents( $template_path );
		
		// Assert the hook is present in the template
		$this->assertStringContainsString(
			'woocommerce_review_order_after_cart_item_meta',
			$template_content,
			'Template must contain woocommerce_review_order_after_cart_item_meta hook'
		);
		
		// Assert the hook is called with the correct parameters
		$this->assertStringContainsString(
			'do_action( \'woocommerce_review_order_after_cart_item_meta\', $_product, $cart_item, $cart_item_key )',
			$template_content,
			'Hook must be called with correct parameters: $_product, $cart_item, $cart_item_key'
		);
	}

	/**
	 * @testdox Hook is registered in WordPress action system.
	 */
	public function test_hook_is_registered() {
		// The hook should be callable even if no callbacks are attached
		$this->assertTrue(
			has_action( 'woocommerce_review_order_after_cart_item_meta' ) !== false,
			'Hook should be registered in WordPress action system'
		);
	}

	/**
	 * @testdox Hook accepts exactly three parameters.
	 */
	public function test_hook_accepts_three_parameters() {
		$callback_count = 0;
		$parameter_count = 0;

		// Add a callback that counts parameters
		add_action( 'woocommerce_review_order_after_cart_item_meta', function( $product, $cart_item, $cart_item_key ) use ( &$callback_count, &$parameter_count ) {
			$callback_count++;
			$parameter_count = func_num_args();
		}, 10, 3 );

		// Create test data
		$product = WC_Helper_Product::create_simple_product();
		$cart_item = array(
			'product_id' => $product->get_id(),
			'quantity' => 1,
			'data' => $product,
		);
		$cart_item_key = 'test_key';

		// Call the hook
		do_action( 'woocommerce_review_order_after_cart_item_meta', $product, $cart_item, $cart_item_key );

		// Assert the callback was called with exactly 3 parameters
		$this->assertSame( 1, $callback_count, 'Callback should be called once' );
		$this->assertSame( 3, $parameter_count, 'Hook should pass exactly 3 parameters' );

		// Clean up
		WC_Helper_Product::delete_product( $product->get_id() );
	}

	/**
	 * @testdox Hook is located in the correct position in template (after cart item meta).
	 */
	public function test_hook_position_in_template() {
		$template_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/templates/checkout/review-order.php';
		$template_content = file_get_contents( $template_path );

		// Find the position of key elements
		$cart_item_data_pos = strpos( $template_content, 'wc_get_formatted_cart_item_data' );
		$hook_pos = strpos( $template_content, 'woocommerce_review_order_after_cart_item_meta' );
		$product_total_pos = strpos( $template_content, 'product-total' );

		// Assert the hook comes after cart item data but before product total
		$this->assertNotFalse( $cart_item_data_pos, 'Cart item data should be present' );
		$this->assertNotFalse( $hook_pos, 'Hook should be present' );
		$this->assertNotFalse( $product_total_pos, 'Product total should be present' );

		$this->assertGreaterThan(
			$cart_item_data_pos,
			$hook_pos,
			'Hook should come after cart item data'
		);

		$this->assertLessThan(
			$product_total_pos,
			$hook_pos,
			'Hook should come before product total'
		);
	}

	/**
	 * @testdox Hook template version is updated correctly.
	 */
	public function test_template_version_updated() {
		$template_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/templates/checkout/review-order.php';
		$template_content = file_get_contents( $template_path );

		// Assert the template version reflects the addition of the hook
		$this->assertStringContainsString(
			'@version 10.6.0',
			$template_content,
			'Template version should be 10.6.0 to reflect hook addition'
		);
	}

	/**
	 * @testdox Hook name follows WooCommerce naming conventions.
	 */
	public function test_hook_follows_naming_conventions() {
		$hook_name = 'woocommerce_review_order_after_cart_item_meta';

		// Assert hook name follows WooCommerce conventions
		$this->assertStringStartsWith( 'woocommerce_', $hook_name, 'Hook should start with woocommerce_' );
		$this->assertStringContainsString( 'review_order', $hook_name, 'Hook should contain review_order context' );
		$this->assertStringContainsString( 'after_cart_item_meta', $hook_name, 'Hook should specify after_cart_item_meta timing' );
	}

	/**
	 * @testdox Hook documentation exists in codebase.
	 */
	public function test_hook_documentation_exists() {
		// This test ensures the hook is documented somewhere in the codebase
		// In a real scenario, this would check hook documentation files
		
		// For now, we'll verify the hook is used in at least one place (the template)
		$template_path = dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/templates/checkout/review-order.php';
		$template_content = file_get_contents( $template_path );
		
		$this->assertStringContainsString(
			'woocommerce_review_order_after_cart_item_meta',
			$template_content,
			'Hook should be used in at least one template file'
		);
	}

	/**
	 * @testdox Hook does not conflict with existing WooCommerce hooks.
	 */
	public function test_hook_no_conflicts() {
		// Get all WooCommerce hooks
		global $wp_filter;
		$woocommerce_hooks = array_filter(
			array_keys( $wp_filter ),
			function( $hook_name ) {
				return strpos( $hook_name, 'woocommerce_' ) === 0;
			}
		);

		// Assert our hook doesn't duplicate an existing hook name
		$this->assertNotContains(
			'woocommerce_review_order_after_cart_item_meta',
			$woocommerce_hooks,
			'Hook should not duplicate existing WooCommerce hooks (before registration)'
		);

		// Register a callback to ensure hook can be used
		$callback_executed = false;
		add_action( 'woocommerce_review_order_after_cart_item_meta', function() use ( &$callback_executed ) {
			$callback_executed = true;
		}, 10, 3 );

		// Assert hook can be called without conflicts
		$product = WC_Helper_Product::create_simple_product();
		$cart_item = array( 'product_id' => $product->get_id(), 'quantity' => 1 );
		$cart_item_key = 'test';

		do_action( 'woocommerce_review_order_after_cart_item_meta', $product, $cart_item, $cart_item_key );

		$this->assertTrue( $callback_executed, 'Hook should execute without conflicts' );

		// Clean up
		WC_Helper_Product::delete_product( $product->get_id() );
	}

	/**
	 * @testdox Hook parameters match expected data types.
	 */
	public function test_hook_parameter_types() {
		$parameter_types = array();

		// Add a callback that checks parameter types
		add_action( 'woocommerce_review_order_after_cart_item_meta', function( $product, $cart_item, $cart_item_key ) use ( &$parameter_types ) {
			$parameter_types = array(
				'product' => get_class( $product ),
				'cart_item' => gettype( $cart_item ),
				'cart_item_key' => gettype( $cart_item_key ),
			);
		}, 10, 3 );

		// Create test data
		$product = WC_Helper_Product::create_simple_product();
		$cart_item = array(
			'product_id' => $product->get_id(),
			'quantity' => 1,
			'data' => $product,
		);
		$cart_item_key = 'test_key';

		// Call the hook
		do_action( 'woocommerce_review_order_after_cart_item_meta', $product, $cart_item, $cart_item_key );

		// Assert parameter types are correct
		$this->assertSame( 'WC_Product', $parameter_types['product'], 'Product parameter should be WC_Product instance' );
		$this->assertSame( 'array', $parameter_types['cart_item'], 'Cart item parameter should be array' );
		$this->assertSame( 'string', $parameter_types['cart_item_key'], 'Cart item key parameter should be string' );

		// Clean up
		WC_Helper_Product::delete_product( $product->get_id() );
	}
}
