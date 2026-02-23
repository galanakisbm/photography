/**
 * Event Image Manager – Payment Handler
 *
 * Initialises Stripe.js, mounts the card element, handles card payment
 * submission, and renders the PayPal button.
 *
 * Expects localised object `eimPayment` with:
 *   - ajaxUrl
 *   - nonce
 *   - stripeKey
 *   - i18n  { processing, success, error, paypalReady }
 */
( function ( $ ) {
    'use strict';

    if ( typeof eimPayment === 'undefined' ) {
        return;
    }

    var stripe      = null;
    var cardElement = null;

    var $form         = $( '#eim-purchase-form' );
    var $submitBtn    = $( '#eim-purchase-submit' );
    var $status       = $( '.eim-purchase-status' );
    var $cardWrap     = $( '#eim-stripe-card-wrap' );
    var $paypalWrap   = $( '#eim-paypal-button-wrap' );
    var $cardErrors   = $( '#eim-card-errors' );
    var $paymentRadio = $( 'input[name="payment_method"]' );
    var $licenseRadio = $( 'input[name="license_type"]' );

    // ── Initialise Stripe ─────────────────────────────────────────────────────
    function initStripe() {
        if ( typeof Stripe === 'undefined' || ! eimPayment.stripeKey ) {
            return;
        }

        stripe      = Stripe( eimPayment.stripeKey );
        var elements = stripe.elements();

        cardElement = elements.create( 'card', {
            style: {
                base: {
                    fontSize:   '16px',
                    color:      '#32325d',
                    fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
                },
                invalid: { color: '#c00' },
            },
        } );

        cardElement.mount( '#eim-card-element' );

        cardElement.on( 'change', function ( event ) {
            $cardErrors.text( event.error ? event.error.message : '' );
        } );
    }

    // ── Payment method switch ─────────────────────────────────────────────────
    $paymentRadio.on( 'change', function () {
        if ( 'card' === this.value ) {
            $cardWrap.show();
            $paypalWrap.hide();
            $submitBtn.show();
        } else {
            $cardWrap.hide();
            $paypalWrap.show();
            $submitBtn.hide();
            initPayPal();
        }
    } );

    // ── Order summary update ──────────────────────────────────────────────────
    var prices = { personal: '9.99', commercial: '49.99' };

    $licenseRadio.on( 'change', function () {
        var license = this.value;
        var price   = prices[ license ] || '9.99';
        $( '#eim-summary-license' ).text( license.charAt( 0 ).toUpperCase() + license.slice( 1 ) );
        $( '#eim-summary-price' ).text( '$' + price );
        $( '#eim-summary-total' ).html( '<strong>$' + price + '</strong>' );
    } );

    // ── Card form submission ──────────────────────────────────────────────────
    $form.on( 'submit', function ( e ) {
        e.preventDefault();

        if ( 'card' !== $( 'input[name="payment_method"]:checked' ).val() ) {
            return;
        }

        if ( ! stripe || ! cardElement ) {
            showStatus( eimPayment.i18n.error, true );
            return;
        }

        $submitBtn.prop( 'disabled', true ).text( eimPayment.i18n.processing );
        $cardErrors.text( '' );

        var postId    = $form.find( 'input[name="post_id"]' ).val();
        var imgIndex  = $( '#eim-purchase-img-index' ).val();
        var license   = $( 'input[name="license_type"]:checked' ).val();

        // Step 1: Create payment intent on server.
        $.post(
            eimPayment.ajaxUrl,
            {
                action:    'eim_create_payment_intent',
                nonce:     eimPayment.nonce,
                post_id:   postId,
                img_index: imgIndex,
                license:   license,
            },
            function ( res ) {
                if ( ! res.success || ! res.data || ! res.data.client_secret ) {
                    resetSubmit();
                    showStatus( res.data && res.data.message ? res.data.message : eimPayment.i18n.error, true );
                    return;
                }

                // Step 2: Confirm payment with Stripe.js.
                stripe.confirmCardPayment( res.data.client_secret, {
                    payment_method: { card: cardElement },
                } ).then( function ( result ) {
                    resetSubmit();
                    if ( result.error ) {
                        $cardErrors.text( result.error.message );
                        showStatus( result.error.message, true );
                    } else if ( result.paymentIntent && 'succeeded' === result.paymentIntent.status ) {
                        showStatus( eimPayment.i18n.success );
                        $form[ 0 ].reset();
                        cardElement.clear();
                    }
                } );
            }
        ).fail( function () {
            resetSubmit();
            showStatus( eimPayment.i18n.error, true );
        } );
    } );

    // ── PayPal ────────────────────────────────────────────────────────────────
    function initPayPal() {
        if ( typeof paypal === 'undefined' || $( '#eim-paypal-button-container' ).children().length ) {
            return;
        }

        paypal.Buttons( {
            createOrder: function ( data, actions ) {
                var license = $( 'input[name="license_type"]:checked' ).val() || 'personal';
                var price   = prices[ license ] || '9.99';
                return actions.order.create( {
                    purchase_units: [ {
                        amount: { value: price },
                        description: 'Photo License – ' + license,
                    } ],
                } );
            },
            onApprove: function ( data, actions ) {
                return actions.order.capture().then( function ( details ) {
                    showStatus( eimPayment.i18n.success );
                } );
            },
            onError: function ( err ) {
                showStatus( eimPayment.i18n.error, true );
            },
        } ).render( '#eim-paypal-button-container' );
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    function showStatus( msg, isError ) {
        $status.css( 'color', isError ? '#c00' : '#0a0' ).text( msg );
    }

    function resetSubmit() {
        $submitBtn.prop( 'disabled', false ).text( eimPayment.i18n.purchase || 'Complete Purchase' );
    }

    // ── Init ──────────────────────────────────────────────────────────────────
    initStripe();

}( jQuery ) );
