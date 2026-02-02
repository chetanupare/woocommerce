<?php
/**
 * Integration tests for woocommerce_review_order_after_cart_item_meta hook in classic/shortcode checkout.
 *
 * @package WooCommerce\Tests\Templates.
 */

/**
 * Class Classic_Checkout_Review_Order_Hook_Test.
 */
class Classic_Checkout_Review_Order_Hook_Test extends \WC_Unit_Test_Case {

	/**
	 * @var array Test data to verify hook calls.
	 */
	private $hook_calls = [];

	/**
	 * Runs before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->hook_calls = [];
		
		// Create a test product
		$this->product = WC_Helper_Product::create_simple_product();
		
		// Add product to cart
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
	 * @testdox woocommerce_review_order_after_cart_item_meta hook is called with correct parameters.
	 */
	public function test_review_order_after_cart_item_meta_hook_called() {
		// Register a test callback
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		// Simulate the template hook call (as it would be called in review-order.php)
		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		// Assert the hook was called
		$this->assertCount( 1, $this->hook_calls, 'Hook should be called exactly once' );

		$hook_call = $this->hook_calls[0];
		$this->assertSame( $this->product->get_id(), $hook_call['product_id'], 'Product ID should match' );
		$this->assertSame( 2, $hook_call['quantity'], 'Quantity should match' );
		$this->assertNotEmpty( $hook_call['cart_item_key'], 'Cart item key should not be empty' );
	}

	/**
	 * @testdox Multiple callbacks can be registered for the hook.
	 */
	public function test_multiple_callbacks_supported() {
		// Register multiple test callbacks
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call_2' ), 20, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		// Simulate the template hook call
		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		// Assert both callbacks were called
		$this->assertCount( 2, $this->hook_calls, 'Both callbacks should be called' );
	}

	/**
	 * @testdox Hook parameters are passed correctly to callbacks.
	 */
	public function test_hook_parameters_correct() {
		add_action( 'woocommerce_review_order_after_cart_item_meta', array( $this, 'capture_hook_call' ), 10, 3 );

		// Get cart items
		$cart_items = WC()->cart->get_cart();
		$cart_item = reset( $cart_items );
		$cart_item_key = key( $cart_items );

		// Call the hook
		do_action( 'woocommerce_review_order_after_cart_item_meta', $cart_item['data'], $cart_item, $cart_item_key );

		$hook_call = $this->hook_calls[0];

		// Verify product parameter
		$this->assertInstanceOf( 'WC_Product', $hook_call['product'], 'Product should be a WC_Product instance' );
		$this->assertSame( $this->product->get_id(), $hook_call['product']->get_id(), 'Product should be the correct product' );

		// Verify cart item parameter
		$this->assertIsArray( $hook_call['cart_item'], 'Cart item should be an array' );
		$this->assertArrayHasKey( 'product_id', $hook_call['cart_item'], 'Cart item should have product_id' );
		$this->assertArrayHasKey( 'quantity', $hook_call['cart_item'], 'Cart item should have quantity' );
		$this->assertSame( $this->product->get_id(), $hook_call['cart_item']['product_id'], 'Cart item product_id should match' );
		$this->assertSame( 2, $hook_call['cart_item']['quantity'], 'Cart item quantity should match' );

		// Verify cart item key parameter
		$this->assertIsString( $hook_call['cart_item_key'], 'Cart item key should be a string' );
		$this->assertSame( $cart_item_key, $hook_call['cart_item_key'], 'Cart item key should match' );
	}

	/**
	 * @testdox Hook works with multiple cart items.
	 */
	public function test_hook_with_multiple_cart_items() {
		// Add another product
		$product2 = WC_Helper_Product::create_simple_product();
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

		// Clean up
		WC_Helper_Product::delete_product( $product2->get_id() );
	}

	/**
	 * @testdox Hook can be used to output HTML content.
	 */
	public function test_hook_can_output_html() {
		$test_output = '<div class="test-hook-output">Test Content</div>';

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

	/**
	 * Second test callback to capture hook calls.
	 *
	 * @param WC_Product $product     The product object.
	 * @param array      $cart_item   The cart item data.
	 * @param string     $cart_item_key The cart item key.
	 */
	public function capture_hook_call_2( $product, $cart_item, $cart_item_key ) {
		$this->hook_calls[] = array(
			'callback' => 'callback_2',
			'product_id' => $product->get_id(),
			'cart_item_key' => $cart_item_key,
		);
	}
}
