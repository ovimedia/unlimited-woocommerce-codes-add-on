<?php
/*
Plugin Name: Unlimited WooCommerce Codes Add on  
Description: Include different code types in your WooCommerce pages.
Author: Ovi García - ovimedia.es
Author URI: http://www.ovimedia.es/
Text Domain: unlimited-woocommerce-codes
Version: 0.1
*/

if ( ! defined( 'ABSPATH' ) ) exit; 

if ( ! class_exists( 'unlimited_woocommerce_codes' ) ) 
{
	class unlimited_woocommerce_codes 
    {

        function __construct() 
        {   
            add_action( 'init', array( $this, 'uwc_load_languages') );
            add_action( 'init', array( $this, 'uwc_load_codes') );           
            add_action( 'admin_print_scripts', array( $this, 'uwc_admin_js_css') );
            add_action( 'add_meta_boxes', array( $this, 'uwc_init_metabox') ); 
            add_action( 'save_post', array( $this, 'uwc_save_data_codes') );
            add_filter( 'manage_edit-code_columns', array( $this, 'uwc_edit_code_columns' )) ;
            add_action( 'manage_code_posts_custom_column', array( $this, 'uwc_manage_code_columns'), 10, 2 );
        }

        public function uwc_load_codes()
        {
            $args = array(
                'posts_per_page'   => -1,
                'meta_key'         => 'uc_order_code',
                'orderby'          => 'meta_value_num',
                'order'            => 'ASC',
                'post_type'        => 'code',
                'post_status'      => 'publish',
                'meta_query' => array(
                    array(
                        'key' => 'uwc_type',
                        'value'  =>  'woocommerce'
                    )
                )
            );

            $codes = get_posts( $args );

            foreach($codes as $woocode)
            {
                $pagezones = get_post_meta($woocode->ID, "uwc_zone_page_names", true);
                $product_cats = get_post_meta($woocode->ID, "uwc_product_categories", true);
                $posts_id = get_post_meta( $woocode->ID, 'uc_post_code_id', true);
                $exclude_post_id = get_post_meta( $woocode->ID, 'uc_exclude_post_code_id', true); 
                $codeid = $woocode->ID;

                $content = $woocode->post_content;

                foreach($pagezones as $zone)
                {
                    add_action($zone, function() use ($content, $product_cats, $posts_id, $exclude_post_id, $codeid)
                    {
                        global $post;
                        $terms = wp_get_post_terms( $post->ID, 'product_cat' );

                        $load = false;

                        foreach ( $terms as $term ) 
                        {
                            if(in_array($term->term_id, $product_cats)) $load = true; 
                        }

                        if($this->check_wpml_languages($codeid))
                            if( ($load || in_array($post->ID, $posts_id) || in_array(-1, $posts_id) 
                            || $product_cats == "")  && !in_array($post->ID, $exclude_post_id ))
                                echo do_shortcode($content);     
                        
                    });
                }
            }
        }

        public function check_wpml_languages($code_id)
        {
            if ( function_exists('icl_object_id') )  
            {
                $wpml_languages = get_post_meta( $code_id, 'uc_wpml_languages_load', true );
                
                if(in_array("all", $wpml_languages) || in_array(ICL_LANGUAGE_CODE, $wpml_languages) )
                    return true;
                else
                    return false;
            }
            
            return true; 
        }
        
        public function uwc_load_languages() 
        {
            load_plugin_textdomain( 'unlimited-woocommerce-codes', false, '/'.basename( dirname( __FILE__ ) ) . '/languages/' ); 
        }

        public function uwc_edit_code_columns( $columns ) 
        {
            unset($columns["order"]);
            unset($columns["date"]);
            unset($columns["shortcode"]);
            $columns["woosections"] = translate( 'Woocommerce sections', 'unlimited-woocommerce-codes' );
            $columns["woocategories"] = translate( 'Product categories', 'unlimited-woocommerce-codes' );
            $columns["date"]  = __( 'Date' );
            
            return $columns;
        }
        
        public function uwc_manage_code_columns( $column, $post_id ) 
        {
            $column_values = "";

            switch( $column ) 
            {         
                case 'woosections':
                    
                    $values = get_post_meta( $post_id, 'uwc_zone_page_names', true);
                    
                    foreach ($values as $value)
                    {
                        $column_values .= $value.", ";
                    }

                    echo substr($column_values, 0, -2); 

                break;    

                case 'woocategories':
                    
                    $values = get_post_meta( $post_id, 'uwc_product_categories', true);
                    
                    foreach ($values as $value)
                    {
                        $term = get_term( $value, "product_cat" );

                        $column_values .= $term->name.", ";
                    }

                    echo substr($column_values, 0, -2); 

                break;  
            }
        }

        public function uwc_admin_js_css() 
        {
            if(get_post_type(get_the_ID()) == "code")
            {
                wp_register_style( 'uwc_codes_admin_css', WP_PLUGIN_URL. '/'.basename( dirname( __FILE__ ) ).'/css/uwc_admin.css', false, '1.0.0' );

                wp_enqueue_style( 'uwc_codes_admin_css' );

                wp_enqueue_script( 'uwc_codes_script', WP_PLUGIN_URL. '/'.basename( dirname( __FILE__ ) ).'/js/uwc_admin.js', array('jquery') );
            }
        }

        public function uwc_init_metabox()
        {
            add_meta_box( 'zone-woocode', translate( 'Woocommerce code options', 'unlimited-woocommerce-codes' ), 
                         array( $this, 'uwc_meta_options'), 'code', 'side', 'default' );
        }
        

        public function uwc_meta_options( $post )
        {
            global $wpdb;
            
            ?>
            <div class="meta_div_codes">         
            
                <p>
                    <label for="uwc_type">
                        <?php echo translate( 'Load woocommerce codes:', 'unlimited-woocommerce-codes' ) ?>
                    </label>
                </p>
                <p> 
                    <select id="uwc_type" name="uwc_type">
                        <option value="neither" <?php if(get_post_meta( get_the_ID(), 'uwc_type', true) == "neither") echo ' selected="selected" '; ?> >
                            <?php echo translate( 'No', 'unlimited-woocommerce-codes' ) ?>
                        </option>
                        <option value="woocommerce" <?php if(get_post_meta( get_the_ID(), 'uwc_type', true) == "woocommerce") echo ' selected="selected" '; ?> >
                            <?php echo translate( 'Yes', 'unlimited-woocommerce-codes' ) ?>
                        </option>
                    </select>
                <p>
                    <label for="uwc_zone_page_names">
                        <?php echo translate( 'Woocommerce sections', 'unlimited-woocommerce-codes' ) ?>:
                    </label>
                </p>
                <p>
                    <select multiple="multiple" id="uwc_zone_page_names" name="uwc_zone_page_names[]">
                        <?php

                            $woopages = array(
                                "Shop/Archive/Product category" => array(
                                    "Before main content" => "woocommerce_before_main_content",
                                    "Archive description" => "woocommerce_archive_description",
                                    "Before shop loop" => "woocommerce_before_shop_loop",
                                    "Before shop loop item" => "woocommerce_before_shop_loop_item",
                                    "Before shop loop item title" => "woocommerce_before_shop_loop_item_title",
                                    "Shop loop item title" => "woocommerce_shop_loop_item_title",
                                    "After shop loop item title" => "woocommerce_after_shop_loop_item_title",
                                    "After shop loop item" => "woocommerce_after_shop_loop_item",
                                    "After shop loop" => "woocommerce_after_shop_loop",
                                    "After main content" => "woocommerce_after_main_content"
                                ),
                                "Product" => array(
                                    "Before single product summary" => "woocommerce_before_single_product_summary",
                                    "Single product summary" => "woocommerce_single_product_summary",
                                    "Before add to cart form" => "woocommerce_before_add_to_cart_form",
                                    "Before variations form" => "woocommerce_before_variations_form",
                                    "Before add to cart button" => "woocommerce_before_add_to_cart_button",
                                    "Before single variation" => "woocommerce_before_single_variation",
                                    "Single variation" => "woocommerce_single_variation",
                                    "After single variation" => "woocommerce_after_single_variation",
                                    "After add to cart button" => "woocommerce_after_add_to_cart_button",
                                    "After variations form" => "woocommerce_after_variations_form",
                                    "After add to cart form" => "woocommerce_after_add_to_cart_form",
                                    "Product meta start" => "woocommerce_product_meta_start",
                                    "Product meta end" => "woocommerce_product_meta_end",
                                    "Share" => "woocommerce_share",
                                    "After single product summary" => "woocommerce_after_single_product_summary"
                                ),
                                "Cart" => array(
                                    "Before cart" => "woocommerce_before_cart",
                                    "Before cart table" => "woocommerce_before_cart_table",
                                    "Before cart contents" => "woocommerce_before_cart_contents",
                                    "Cart contents" => "woocommerce_cart_contents",
                                    "Cart coupon" => "	woocommerce_cart_coupon	",
                                    "After cart contents" => "woocommerce_after_cart_contents",
                                    "After cart table" => "woocommerce_after_cart_table",
                                    "Cart collaterals" => "woocommerce_cart_collaterals",
                                    "Before cart totals" => "woocommerce_before_cart_totals",
                                    "Cart totals before shipping" => "woocommerce_cart_totals_before_shipping",
                                    "Before shipping calculator" => "woocommerce_before_shipping_calculator",
                                    "After shipping calculator" => "woocommerce_after_shipping_calculator	",
                                    "Cart totals after shipping" => "woocommerce_cart_totals_after_shipping",
                                    "Cart totals before order total" => "woocommerce_cart_totals_before_order_total",
                                    "Cart totals after order total" => "woocommerce_cart_totals_after_order_total",
                                    "Proceed to checkout" => "woocommerce_proceed_to_checkout",
                                    "After cart totals" => "woocommerce_after_cart_totals",
                                    "After cart" => "woocommerce_after_cart"
                                ),
                                "Checkout" => array(
                                    "Before checkout form" => "woocommerce_before_checkout_form",
                                    "Before customer details" => "woocommerce_checkout_before_customer_details",
                                    "Before checkout billing form" => "woocommerce_before_checkout_billing_form",
                                    "After checkout billing form" => "woocommerce_after_checkout_billing_form",
                                    "Before checkout shipping form" => "woocommerce_before_checkout_shipping_form",
                                    "After checkout shipping form" => "woocommerce_after_checkout_shipping_form",
                                    "Before order notes" => "woocommerce_before_order_notes",
                                    "After order notes" => "woocommerce_after_order_notes",
                                    "Checkout after customer details" => "woocommerce_checkout_after_customer_details",
                                    "Checkout before order review" => "woocommerce_checkout_before_order_review",
                                    "Review order before cart contents" => "woocommerce_review_order_before_cart_contents",
                                    "Review order after cart contents" => "woocommerce_review_order_after_cart_contents",
                                    "Review order before shipping" => "woocommerce_review_order_before_shipping",
                                    "Review order after shipping" => "woocommerce_review_order_after_shipping",
                                    "Review order before order total" => "woocommerce_review_order_before_order_total",
                                    "Review order after order total" => "woocommerce_review_order_after_order_total",
                                    "Review order before payment" => "woocommerce_review_order_before_payment",
                                    "Review order before submit" => "woocommerce_review_order_before_submit", 
                                    "Review order after submit" => "woocommerce_review_order_after_submit",
                                    "Review order after payment" => "woocommerce_review_order_after_payment",
                                    "Checkout after order review" => "woocommerce_checkout_after_order_review",
                                    "After checkout form" => "woocommerce_after_checkout_form"
                                ),
                                "My Account" => array(
                                    "Before customer login form" => "woocommerce_before_customer_login_form",
                                    "Login form start" => "woocommerce_login_form_start",
                                    "Login form" => "woocommerce_login_form",
                                    "Login form end" => "woocommerce_login_form_end",
                                    "Register form start" => "woocommerce_register_form_start",
                                    "Register form" => "woocommerce_register_form",
                                    "Register form" => "register_form",
                                    "Register form end" => "woocommerce_register_form_end",
                                    "After customer login form" => "woocommerce_after_customer_login_form",
                                    "Account content" => "woocommerce_account_content",
                                    "Account dashboard" => "woocommerce_account_dashboard",
                                    "Before account orders" => "woocommerce_before_account_orders",
                                    "Before account orders pagination" => "woocommerce_before_account_orders_pagination",
                                    "After account orders" => "woocommerce_after_account_orders",
                                    "Before account downloads" => "woocommerce_before_account_downloads",
                                    "Before available downloads" => "woocommerce_before_available_downloads",
                                    "After available downloads" => "woocommerce_after_available_downloads",
                                    "After account downloads" => "woocommerce_after_account_downloads",
                                    "Before edit account address form" => "woocommerce_before_edit_account_address_form",
                                    "After edit account address form" => "woocommerce_after_edit_account_address_form",
                                    "Before edit acount address form" => "woocommerce_before_edit_account_address_form",
                                    "After edit account adress form" => "woocommerce_after_edit_account_address_form",
                                    "Before account payment methods" => "woocommerce_before_account_payment_methods",
                                    "After account payment methods" => "woocommerce_after_account_payment_methods",
                                    "Before edit account form" => "woocommerce_before_edit_account_form",
                                    "Edit account form start" => "woocommerce_edit_account_form_start",
                                    "Edit account form" => "woocommerce_edit_account_form",
                                    "Edit account form end" => "woocommerce_edit_account_form_end",
                                    "After edit account form" => "woocommerce_after_edit_account_form"
                                )
                            );

                            $pagezones = get_post_meta(get_the_ID(), "uwc_zone_page_names", true);

                            foreach($woopages as $woopage => $zones)
                            { 
                                echo "<optgroup label='".translate( $woopage, 'unlimited-woocommerce-codes' )."'>";

                                foreach($zones as $name => $zone )
                                {
                                    echo "<option ";

                                    if(in_array($zone, $pagezones))
                                        echo " selected ";

                                    echo " value='".$zone."' >".$name."</option>";
                                }

                                echo "</optgroup>";
                            } 

                            ?>
                    </select>
                </p>
                <p>
                    <label for="uwc_product_categories">
                        <?php echo translate( 'Product categories', 'unlimited-woocommerce-codes' ) ?>:
                    </label>
                </p>
                <p>
                    <select multiple="multiple" id="uwc_product_categories" name="uwc_product_categories[]">

                    <?php

                        $cats = get_post_meta(get_the_ID(), "uwc_product_categories", true);

                          $taxonomy     = 'product_cat';
                            $orderby      = 'name';  
                            $show_count   = 0;
                            $pad_counts   = 0;      
                            $hierarchical = 1;      
                            $title        = '';  
                            $empty        = 0;

                            $args = array(
                                    'taxonomy'     => $taxonomy,
                                    'orderby'      => $orderby,
                                    'show_count'   => $show_count,
                                    'pad_counts'   => $pad_counts,
                                    'hierarchical' => $hierarchical,
                                    'title_li'     => $title,
                                    'hide_empty'   => $empty
                            );

                            $product_cats = get_categories($args);
                   
                            foreach($product_cats as $cat)
                            {
                                echo "<option ";

                                if(in_array($cat->term_id, $cats))
                                    echo " selected ";

                                echo " value='".$cat->term_id."' >".$cat->name."</option>";
                            }
                    ?>
                    </select>
                </p>     
                  
            </div>
        <?php 
        }

        public function uwc_save_data_codes( $post_id )
        {
            if ( "code" != get_post_type($post_id) || current_user_can("administrator") != 1 || !isset($_REQUEST['uc_validate_data'])) return;
            
            $zone_pages = $product_categories = array();

            $validate_zone_pages = $validate_product_categories  = true;

            update_post_meta( $post_id, 'uwc_type',sanitize_text_field( $_REQUEST["uwc_type"]));

            foreach( $_REQUEST['uwc_zone_page_names'] as $zones)
            {
                if(wp_check_invalid_utf8( $zones, true ) != "")
                    $zone_pages[] = sanitize_text_field($zones);
                else
                    $validate_zone_pages = false;
            }

            foreach( $_REQUEST['uwc_product_categories'] as $categories)
            {
                if(intval($categories))
                    $product_categories[] = intval($categories);
                else
                    $validate_product_categories = false;
            }

            if($validate_zone_pages )
                update_post_meta( $post_id, 'uwc_zone_page_names', $zone_pages);

            if($validate_product_categories )    
                update_post_meta( $post_id, 'uwc_product_categories',  $product_categories);
        }            
    }
}

$GLOBALS['unlimited_woocommerce_codes'] = new unlimited_woocommerce_codes();   
    
?>
