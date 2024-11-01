<?php
/**
 * Plugin Name: WooCommerce price rate
 * Plugin URI: https://github.com/abdallahnofal/woocommerce-price-rate/
 * Description: Change products' price based on specific rate.
 * Version: 1.0.1
 * Tested up to: 5.0.2
 * Author: Abdallah Nofal
 * Author URI: https://github.com/abdallahnofal/
 * Text Domain: abdallahnofal
 * 
 * WC requires at least: 2.2
 * WC tested up to: 3.5.3
 * 
 * License: GPLv3 or later License
 * URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly
}

/**
 * Class Woo_Price_Rate
 */
class Woo_Price_Rate {
  
  /**
   * Plugin init.
   */
  public function __construct() {

    if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
      add_filter( 'woocommerce_product_get_price', array($this, 'woo_price_rate_get_price'), 10, 2);
      add_filter( 'woocommerce_product_settings', array($this, 'woo_price_rate_product_settings'));
      add_filter( 'woocommerce_product_data_tabs', array($this, 'woo_price_rate_product_tabs'));
      add_filter( 'woocommerce_product_data_panels', array($this, 'woo_price_rate_product_data_panel')); 
      add_action( 'woocommerce_process_product_meta', array($this, 'woo_price_rate_save_fields'));
      add_action( 'woocommerce_process_product_meta_simple', array($this, 'woo_price_rate_save_fields'));
      add_action( 'woocommerce_process_product_meta_variable', array($this, 'woo_price_rate_save_fields')); 
    } else {
      add_action( 'admin_notices', array($this, 'woo_price_rate_woocommerce_active_error'));
    }

  }

  /**
   * woo_price_rate_woocommerce_active_error function
   * 
   * Returns an error at admin panel to activate woocommerce.
   */
  public function woo_price_rate_woocommerce_active_error() {

    ?>
    <div class="error notice is-dismissible">
        <p><?php _e( 'WooCommerce price rate will not work until WooCommerce is active.', 'woocommerce_price_rate' ); ?></p>
    </div>
    <?php

  }

  /**
   * woo_price_rate_get_price function
   * $price (float)
   * $product (Object)
   * 
   * Return the price with the applied rate.
   */
  public function woo_price_rate_get_price($price, $product) {

    $product_id = $product->get_id();

    // Chceck if applying rate is disabled or not available.
    $is_product_disabled = get_post_meta($product_id, '_woopr_disable_price_rate', true);
    $rate = get_option('woocommerce_price_rate');
    if(!$rate || $is_product_disabled == 'yes')  {
      return $price;
    }

    // Chceck if product has a custom price rate.
    $cp_status = get_post_meta($product_id, '_woopr_custom_price_rate_status', true);
    $cp_value = get_post_meta($product_id, '_woopr_custom_price_rate_value', true);
    if($cp_status == 'yes' && $cp_value) {
      return floatval($cp_value) * $price;
    } 

    return floatval($rate) * $price;
  }

  /**
   * woo_price_rate_product_settings function
   * $settings (Array)
   * 
   * Show the rate percentage field at woocommerce products settings.
   */
  public function woo_price_rate_product_settings($settings) {

    $new_settings = array();
    $key = 0;
    
    foreach( $settings as $values ){
      $new_settings[$key] = $values;
      $key++;
      if($values['id'] == 'woocommerce_review_rating_required'){
        $new_settings[$key] = array(
          'title'             => __('Price rate', 'woocommerce_price_rate'),
          'desc'              => __('The product price will be multiplied by this rate', 'woocommerce_price_rate'),
          'id'                => 'woocommerce_price_rate',
          'default'           => '',
          'type'              => 'number',
          'desc_tip'          => true, 
          'custom_attributes' => array('step' => 'any', 'min' => '0')
        );
        $key++;
      }
    }

    return $new_settings;
  }

  /**
   * woo_price_rate_product_tabs function
   * $tabs (Array)
   * 
   * Show price rate tab at edit product page.
   */
  public function woo_price_rate_product_tabs($tabs) {

    $tabs['woo_price_rate'] = array(
      'label'		=> __( 'Price rate', 'woocommerce' ),
      'target'	=> 'woo_price_rate',
      'class'		=> array('show_if_simple', 'show_if_variable'),
    );

    return $tabs;
  }

  /**
   * woo_price_rate_product_data_panel function
   * 
   * Add price rate panel at edit product page.
   */
  public function woo_price_rate_product_data_panel() {

    global $post;
    ?>
      <div id='woo_price_rate' class='panel woocommerce_options_panel'>
        <div class='options_group'>
          <p><?php printf(__('Default price rate is: %s', 'woocommerce_price_rate'), get_option('woocommerce_price_rate')); ?></p>
          <?php
            woocommerce_wp_checkbox( array(
              'id' => '_woopr_disable_price_rate',
              'label' => __('Disable price rate', 'woocommerce_price_rate'),
              'description' => __('If checked, will disable the default and the custom price rate.', 'woocommerce_price_rate')
            ) );

            woocommerce_wp_checkbox( array(
              'id' => '_woopr_custom_price_rate_status',
              'label' => __( 'Enable custom rate', 'woocommerce' ),
              'description' => __('If enabled, custom rate will overwrite the default rate.','woocommerce_price_rate')
            ) );

            woocommerce_wp_text_input( array(
              'id' => '_woopr_custom_price_rate_value',
              'label' => __('Custom price rate', 'woocommerce'),
              'desc_tip' => 'true',
              'type' => 'number',
              'custom_attributes'	=> array( 'min'	=> '0', 'step'	=> 'any'),
            ) );
          ?>
        </div>
      </div>
    <?php
  }

  /**
   * woo_price_rate_save_fields function
   * 
   * Save product price rate settings
   */
  public function woo_price_rate_save_fields($product_id) {

    // UPDATE _woopr_disable_price_rate
    $disable_price_rate = isset( $_POST['_woopr_disable_price_rate'] ) ? 'yes' : 'no';
    update_post_meta( $product_id, '_woopr_disable_price_rate', $disable_price_rate );
    
    // UPDATE _woopr_custom_price_rate_status
    $custom_price_rate_status = isset( $_POST['_woopr_custom_price_rate_status'] ) ? 'yes' : 'no';
    update_post_meta( $product_id, '_woopr_custom_price_rate_status', $custom_price_rate_status );
    
    // UPDATE _woopr_custom_price_rate_value
    if ( isset( $_POST['_woopr_custom_price_rate_value'] ) ) {
      update_post_meta( $product_id, '_woopr_custom_price_rate_value', abs( $_POST['_woopr_custom_price_rate_value'] ) );
    }
  }
}

new Woo_Price_Rate();
