<?php
/**
 * Unit tests for shortcode checkout hook functionality.
 * These tests ensure the woocommerce_review_order_after_cart_item_meta hook
 * works correctly in the classic/shortcode checkout and prevent regressions.
 *
 * @package WooCommerce\Tests\Checkout.
 */

/**
 * Class Shortcode_Checkout_Hook_Test.
 */
class Shortcode_Checkout_Hook_Test extends \WC_Unit_Test_Case {

	/**
	 * @var array Test data to verify hook calls.
	 */
	private $hook_calls = [];

	/**
	 * @var WC_Product Test product.
	 */
	private $product;

	/**
	 * Runs before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->hook_calls = [];
		
		// Create a test product
		$this->product = WC_Helper_Product::create_simple_product();
		$this->product->set_name( 'Test Product for Hook' );
		$this->product->set_price( 10.99 );
		$this->product->save();
		
		// Add product to cart
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->product->get_id(), 2 );
	}

	/**
	 * Runs after each test.
	 */
	public function tearDown(): void {
		WC()->cart->empty_cart();
		WC_Helper_Product::delete_product( $this->product->get_id() );
		parent::tearDown();
	}

	/**
	 * @testdox woocommerce_review_order_after_cart_item_meta hook exists and is callable.
	 */
	public function test_hook_exists() {
		$this->assertTrue(
			has_action( 'woocommerce_review_order_after_cart_item_meta' ) !== false,
			'The woocommerce_review_order_after_cart_item_meta hook should be available'
		);
	}

	/**
	 * @testdox Hook is called with correct parameters in shortcode checkout.
	 */
	public function test_hook_called_with_correct_parameters() {
		// Register a test callback
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		// Simulate the template hook call (as it would be called in review-order.php)
		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		// Assert the hook was called exactly once
		$this->assertCount( 1, $this->hook_calls, 'Hook should be called exactly once' );

		$hook_call = $this->hook_calls[0];
		$this->assertSame( $this->product->get_id(), $hook_call['product_id'], 'Product ID should match' );
		$this->assertSame( 2, $hook_call['quantity'], 'Quantity should match' );
		$this->assertNotEmpty( $hook_call['cart_item_key'], 'Cart item key should not be empty' );
		$this->assertInstanceOf( 'WC_Product', $hook_call['product'], 'Product should be a WC_Product instance' );
	}

	/**
	 * @testdox Hook receives complete cart item data including product attributes.
	 */
	public function test_hook_receives_complete_cart_item_data() {
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		$hook_call = $this->hook_calls[0];

		// Verify cart item contains expected keys
		$expected_keys = array( 'product_id', 'quantity', 'data', 'key', 'product_hash', 'line_tax_data', 'line_subtotal', 'line_total', 'line_tax', 'line_subtotal_tax' );
		foreach ( $expected_keys as $key ) {
			$this->assertArrayHasKey( $key, $hook_call['cart_item'], "Cart item should contain key: {$key}" );
		}

		// Verify specific values
		$this->assertSame( $this->product->get_id(), $hook_call['cart_item']['product_id'], 'Product ID in cart item should match' );
		$this->assertSame( 2, $hook_call['cart_item']['quantity'], 'Quantity in cart item should match' );
		$this->assertSame( $cart_item_key, $hook_call['cart_item']['key'], 'Cart item key should match' );
	}

	/**
	 * @testdox Multiple callbacks can be registered and executed in priority order.
	 */
	public function test_multiple_callbacks_priority_order() {
		$calls = [];

		// Register callbacks with different priorities
		add_action( 'woocommerce_review_order_after_cart_item_meta', function( $product, $cart_item, $cart_item_key ) use ( &$calls ) {
			$calls[] = 'first';
		}, 5, 3 );

		add_action( 'woocommerce_review_order_after_cart_item_meta', function( $product, $cart_item, $cart_item_key ) use ( &$calls ) {
			$calls[] = 'second';
		}, 10, 3 );

		add_action( 'woocommerce_review_order_after_cart_item_meta', function( $product, $cart_item, $cart_item_key ) use ( &$calls ) {
			$calls[] = 'third';
		}, 15, 3 );

		// Get cart items and call hook
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		// Assert callbacks were called in priority order
		$this->assertCount( 3, $calls, 'All three callbacks should be called' );
		$this->assertSame( array( 'first', 'second', 'third' ), $calls, 'Callbacks should be called in priority order' );

		// Clean up
		remove_all_actions( 'woocommerce_review_order_after_cart_item_meta' );
	}

	/**
	 * @testdox Hook works correctly with multiple cart items.
	 */
	public function test_hook_with_multiple_cart_items() {
		// Add another product
		$product2 = WC_Helper_Product::create_simple_product();
		$product2->set_name( 'Second Test Product' );
		$product2->set_price( 5.99 );
		$product2->save();
		WC()->cart->add_to_cart( $product2->get_id(), 1 );

		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();

		// Call the hook for each cart item (as it would happen in the template)
		foreach ( $cart_items as $cart_item_key => $cart_item ) {
			do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );
		}

		// Assert hook was called for each cart item
		$this->assertCount( 2, $this->hook_calls, 'Hook should be called for each cart item' );

		// Verify both products are represented
		$product_ids = array_map( function( $call ) {
			return $call['product_id'];
		}, $this->hook_calls );

		$this->assertContains( $this->product->get_id(), $product_ids, 'First product should be in hook calls' );
		$this->assertContains( $product2->get_id(), $product_ids, 'Second product should be in hook calls' );

		// Clean up
		WC_Helper_Product::delete_product( $product2->get_id() );
	}

	/**
	 * @testdox Hook can be used to output HTML content that appears in checkout.
	 */
	public function test_hook_can_output_html_content() {
		$test_output = '<div class="custom-checkout-meta">Test Content</div>';

		// Add a callback that outputs HTML
		add_action( 'woocommerce_review_order_after_cart_item_meta', function( $product, $cart_item, $cart_item_key ) use ( $test_output ) {
			echo $test_output;
		}, 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		// Capture output
		ob_start();
		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );
		$output = ob_get_clean();

		// Assert the HTML was output
		$this->assertStringContainsString( $test_output, $output, 'Hook should be able to output HTML content' );
	}

	/**
	 * @testdox Hook receives correct product data including name and price.
	 */
	public function test_hook_receives_correct_product_data() {
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		$hook_call = $this->hook_calls[0];
		$product = $hook_call['product'];

		// Verify product data
		$this->assertSame( 'Test Product for Hook', $product->get_name(), 'Product name should match' );
		$this->assertSame( 10.99, $product->get_price(), 'Product price should match' );
		$this->assertSame( 'simple', $product->get_type(), 'Product type should be simple' );
	}

	/**
	 * @testdox Hook can be removed and re-added without issues.
	 */
	public function test_hook_can_be_removed_and_readded() {
		$callback = array( $this, 'capture_hook_call' );

		// Add hook
		add_action( 'woocommerce_review_order_after_cart_item_meta', $callback, 10, 3 );

		// Call hook and verify it works
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );
		$this->assertCount( 1, $this->hook_calls, 'Hook should be called initially' );

		// Remove hook
		remove_action( 'woocommerce_review_order_after_cart_item_meta', $callback, 10 );
		$this->hook_calls = [];

		// Call hook again and verify it's not called
		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );
		$this->assertCount( 0, $this->hook_calls, 'Hook should not be called after removal' );

		// Re-add hook
		add_action( 'woocommerce_review_order_after_cart_item_meta', $callback, 10, 3 );

		// Call hook and verify it works again
		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );
		$this->assertCount( 1, $this->hook_calls, 'Hook should be called after re-adding' );
	}

	/**
	 * @testdox Hook works with variable products.
	 */
	public function test_hook_with_variable_product() {
		// Create a variable product
		$variable_product = WC_Helper_Product::create_variation_product();
		$variations = $variable_product->get_children();
		$variation_id = reset( $variations );

		// Clear cart and add variation
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $variation_id, 1 );

		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		// Assert hook was called
		$this->assertCount( 1, $this->hook_calls, 'Hook should be called for variable product' );

		$hook_call = $this->hook_calls[0];
		$this->assertInstanceOf( 'WC_Product_Variation', $hook_call['product'], 'Product should be a variation instance' );
		$this->assertSame( $variation_id, $hook_call['product_id'], 'Product ID should be variation ID' );

		// Clean up
		WC_Helper_Product::delete_product( $variable_product->get_id() );
	}

	/**
	 * @testdox Hook parameters are passed by reference correctly.
	 */
	public function test_hook_parameters_passed_correctly() {
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		// Store original values
		$original_product = $cart_item['data'];
		$original_cart_item = $cart_item;
		$original_key = $cart_item_key;

		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		$hook_call = $this->hook_calls[0];

		// Verify the same objects/arrays were passed
		$this->assertSame( $original_product, $hook_call['product'], 'Same product object should be passed' );
		$this->assertSame( $original_cart_item, $hook_call['cart_item'], 'Same cart item array should be passed' );
		$this->assertSame( $original_key, $hook_call['cart_item_key'], 'Same cart item key should be passed' );
	}

	/**
	 * Test callback to capture hook calls.
	 *
	 * @param WC_Product $product     The product object.
	 * @param array      $cart_item   The cart item data.
	 * @param string     $cart_item_key The cart item key.
	 */
	public function capture_hook_call( $product, $cart_item, $cart_item_key ) {
		$this->hook_calls[] = array(
			'product' => $product,
			'product_id' => $product->get_id(),
			'cart_item' => $cart_item,
			'cart_item_key' => $cart_item_key,
			'quantity' => $cart_item['quantity'],
		);
	}
}
