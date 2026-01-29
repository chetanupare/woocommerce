<?php
/**
 * Checkout item meta hooks.
 *
 * @package WooCommerce\Internal\Checkout
 */

declare( strict_types=1 );

namespace Automattic\WooCommerce\Internal\Checkout;

use Automattic\WooCommerce\Internal\RegisterHooksInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Adds checkout item meta hooks for block checkout.
 */
class CheckoutItemMetaHooks implements RegisterHooksInterface {

	/**
	 * Register hooks.
	 */
	public function register(): void {
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( $this, 'enqueue_block_support' ) );
	}

	/**
	 * Enqueue block checkout support.
	 */
	public function enqueue_block_support(): void {
		if ( ! wp_script_is( 'wc-blocks-checkout', 'enqueued' ) ) {
			return;
		}

		$script = <<<'JS'
( function() {
	var debounceTimeout;

	var initHooks = function() {
		var cartItems = document.querySelectorAll( '.wc-block-components-order-summary-item' );
		if ( ! cartItems.length ) {
			return;
		}

		cartItems.forEach( function( cartItem ) {
			if ( cartItem.querySelector( '.wc-block-cart-item-meta-hook' ) ) {
				return;
			}

			var description = cartItem.querySelector( '.wc-block-components-order-summary-item__description' );
			if ( ! description ) {
				return;
			}

			var hookContainer = document.createElement( 'div' );
			hookContainer.className = 'wc-block-cart-item-meta-hook';
			description.appendChild( hookContainer );

			if ( ! window.wc || ! window.wc.blocks || ! Array.isArray( window.wc.blocks.cartItemMetaHooks ) ) {
				return;
			}

			window.wc.blocks.cartItemMetaHooks.forEach( function( callback ) {
				if ( typeof callback !== 'function' ) {
					return;
				}
				try {
					callback( hookContainer, cartItem );
				} catch ( error ) {
					return;
				}
			} );
		} );
	};

	var scheduleInit = function() {
		clearTimeout( debounceTimeout );
		debounceTimeout = setTimeout( initHooks, 150 );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', initHooks );
	} else {
		initHooks();
	}

	var orderSummary = document.querySelector( '.wc-block-components-order-summary' );
	if ( orderSummary && window.MutationObserver ) {
		var observer = new MutationObserver( function( mutations ) {
			if ( ! mutations || ! mutations.length ) {
				return;
			}
			scheduleInit();
		} );
		observer.observe( orderSummary, { childList: true, subtree: true } );
	}
} )();
JS;

		wp_add_inline_script( 'wc-blocks-checkout', $script, 'after' );
	}
}
