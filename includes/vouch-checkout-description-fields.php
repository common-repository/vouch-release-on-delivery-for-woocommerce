<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_gateway_description', 'vouch_woocommerce_description_fields', 20, 2 );

function vouch_woocommerce_description_fields( $description, $payment_id ) {

    if ( 'vouch' !== $payment_id ) {
        return $description;
    }
    
    ob_start();

    echo '<div style="display: block;height:auto;margin:10px 0px">';
    echo '<div style="display:flex;flex-direction:row;">';
    echo '<img src="' . plugins_url('../assets/vouch_icon.svg', __FILE__ ) . '">';
    echo '<div style="margin-left:10px">';
    echo '<p style="font-weight:bold;margin-bottom:0;color:#000">Escrow Protected</p>';
    echo '<p style="font-style:italic">Release on delivery</p>';
    echo '</div>';
    echo '</div>';
    echo '<img src="' . plugins_url('../assets/profileBadge.svg', __FILE__ ) . '" style="display: block;width: 55%;margin: 15px 10px;">';
    // echo '<p style="text-align:center">Hello</p>';
    echo '</div>';

    $description = ob_get_clean().$description;

    return $description;
}
