<?php
/*
 * Plugin Name: BaniPay Payment Gateway
 * Plugin URI: https://banipay.me/portal/home#pay
 * Description: BaniPay payment gateway, plugin for Woocommerce
 * Author: Vulcan
 * Author URI: https://banipay.me/portal/home
 * Version: 1.2.1

/*
 * This action hook registers our PHP class as a Woocommerce payment gateway
 */
include_once('banipay.php');

add_filter( 'woocommerce_payment_gateways', 'banipay_add_gateway_class' );
function banipay_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_BaniPay_Gateway'; // Class name BaniPay
	return $gateways;
}
 
/*
 * Payment gateway plugin registration
 */
 
add_action( 'plugins_loaded', 'banipay_init_gateway_class', 11 );

function banipay_init_gateway_class() {

    
    class WC_BaniPay_Gateway extends WC_Payment_Gateway {
        
        public $details = array();
        public $transaction = array();
 		/**
 		 * Class constructor
 		 */
 		public function __construct() {            
 
            $this->id = 'banipay'; // payment gateway plugin ID
            $this->icon = 'https://banipay.s3.us-east-2.amazonaws.com/FondoTransparente.png'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'BaniPay Gateway';
            $this->method_description = 'BaniPay payment gateway'; // will be displayed on the options page
            $this->affiliate_code_demo = '141581ae-fb1f-4cfb-b21e-040a8851c265';

            $this->supports = array(
                'products'
            );
         
            // Start of form
            $this->init_form_fields();
         
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );

            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->private_key = $this->testmode ? $this->get_option( 'test_private_key' ) : $this->get_option( 'private_key' );
	        $this->publishable_key = $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
            
            // Custom the thank-you page 
            add_action( 'woocommerce_thankyou', array($this, 'thank_you_page') );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
         
 		}
 
		/**
 		 * Settings
 		 */
 		public function init_form_fields(){            

            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable BaniPay Gateway',
                    'type'        => 'checkbox',
                    'description' => 'BaniPay payment',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'BaniPay',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'BaniPay allows you to collect your products or services through the internet. Boost your business simply, quickly and safely, with or without a website',
                ),
                'affiliate_code'  => array(
                    'title'       => 'Affiliate Code',
                    'default'     => $this->affiliate_code_demo,
                    'type'        => 'text',
                    'description' => 'Example Code (test): '.$this->affiliate_code_demo,
                ),
                'expire_minutes' => array(
                    'title'       => 'Expire Minutes',
                    'type'        => 'number',
                    'default'     => 20,
                    'description' => 'Only int',
                ),
                'failed_url' => array(
                    'title'       => 'Failed URL',
                    'type'        => 'text',
                    'default'        => get_site_url().'/error',
                    'description' => 'Example Notification URL: '.get_site_url().'/error'
                ),
                /*
                'notification_url' => array(
                    'title'       => 'Notification URL',
                    'type'        => 'text',
                    'default'        => get_site_url().'/notification',
                    'description' => 'Example Notification URL: '.get_site_url().'/notification'
                ),
                */
                'billing' => array(
                    'title'       => 'Facturación',
                    'label'       => 'Habilitar Captura de NIT y Razón Social',
                    'type'        => 'checkbox',
                    'default'     => 'no'
                ),
                'logs' => array(
                    'title'       => 'Logs',
                    'label'       => 'Enable Logs',
                    'type'        => 'checkbox',
                    'default'     => 'no'
                ),
            );
 
         }
         
        
        public function get_title() {
            return apply_filters( 'woocommerce_gateway_title', $this->title, $this->id );
        }
        
        public function get_icon() {

            // $icon = $this->icon ? '<img " src="' . WC_HTTPS::force_https_url( $this->icon ) . '" alt="' . esc_attr( $this->get_title() ) . '" />' : '';
            // return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
        }
      
		/**
		 * BaniPay transaction registration process
		 */          
		public function payment_fields() {
                        
            if ( $this->description ) {
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
            // see banipay payment method
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-banipay wc-payment-form fieldset-custom">';

            do_action( 'woocommerce_credit_card_form_start', $this->id );

            if ($this->get_option( 'billing' ) == 'yes') {
                echo '
                    <div class="form-row">
                        <label>Con factura: <input id="billing" name="billing" type="checkbox" autocomplete="off" onclick="withBilling(this)"></label>
                        
                    </div>
                    <div id="form-billing" class="animate bform-hide">
                        <div class="form-row">
                            <label>Nombre o Razón Social: <span class="required">*</span></label>
                            <input id="socialreason" name="nameOrSocialReason" type="text" autocomplete="off" placeholder="">
                        </div>
                        <div class="form-row">
                            <label>NIT: <span class="required">*</span></label>
                            <input id="nit" name="nit" type="number" autocomplete="off" placeholder="">
                        </div>
                    </div>
                ';
            }
            echo '
                <div class="form-row form-row-wide"><label>Pagar con: </label>
                    <div class="text-center">
                        <img title="'. $this->method_title .'" class="banipay-logo" src=" '. $this->icon .'" />
                    </div>
                </div>                    
                <div class="clear"></div>
            ';
        
            do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';

        }
        

        public function payment_scripts() {
             
            wp_register_style( 'woocommerce_banipay', plugins_url( 'banipay-gateway/assets/css/banipay.css', dirname( __FILE__ ) ) );
            wp_enqueue_style( 'woocommerce_banipay' );

            wp_register_script( 'woocommerce_banipay', plugins_url( 'banipay-gateway/assets/js/banipay.js', dirname( __FILE__ ) ) );
            wp_enqueue_script( 'woocommerce_banipay' );
        }
        
        
		public function validate_fields() {

            if( (isset($_POST['billing'])) && $_POST['billing'] == 'on' ) {

                if( empty( $_POST['nameOrSocialReason']) ) {
                    wc_add_notice(  'Debe ingresar un Nombre o Razón Social', 'error' );
                    return false;
                }

                if( empty( $_POST['nit']) ) {
                    wc_add_notice(  'Debe ingresar un NIT', 'error' );
                    return false;
                }

            }

            return true;

        }

        public function get_return_url( $order = null ) {
            if ( $order ) {
                $return_url = $order->get_checkout_order_received_url();
            } else {
                $return_url = wc_get_endpoint_url( 'order-received', '', wc_get_page_permalink( 'checkout' ) );
            }
    
            if ( is_ssl() || get_option( 'woocommerce_force_ssl_checkout' ) == 'yes' ) {
                $return_url = str_replace( 'http:', 'https:', $return_url );
            }
    
            return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
        }
        

        // Payment process
		public function process_payment( $order_id ) {
            
            global $woocommerce;
            
            // Current order
            $order = wc_get_order( $order_id );

            $this->add_shipping_method( $woocommerce->cart->get_shipping_total(), $this->get_shipping_name_by_id(WC()->session->get( 'chosen_shipping_methods' )[0]) );
            
            $return_url = $this->get_return_url( $order );
            wc_setcookie('order_id', $order_id);
            $items = $woocommerce->cart->get_cart();
            
            // Product loading
            foreach($items as $key => $item) { 
                $data = array(
                    "concept"         => $item['data']->get_title(),
                    "productImageUrl" => get_the_post_thumbnail_url ($item['product_id'], 'medium'),
                    "quantity"        => $item['quantity'],
                    "unitPrice"       => $item['data']->get_price(),
                );
                array_push($this->details, $data);   
            }
            
            // Products services to register
            $data = array(
                "withInvoice"        => false,
                "externalCode"       => $_COOKIE["woocommerce_cart_hash"],
                "paymentDescription" =>  get_bloginfo('name'),
                
                "address"            => $order->get_billing_address_1(), 
                "administrativeArea" => $order->get_billing_state(), 
                "country"            => $order->get_billing_country(), 
                "firstName"          => $order->get_billing_first_name(), 
                "identifierCode"     => $order_id, 
                "identifierName"     => $order->get_billing_first_name(), 
                "lastName"           => $order->get_billing_last_name(), 
                "locality"           => $order->get_billing_city(), 
                "email"              => $order->get_billing_email(), 
                "nit"                => $this->checkBilling( isset($_POST['billing']), isset($_POST['nit']) ), 
                "nameOrSocialReason" => $this->checkBilling( isset($_POST['billing']), isset($_POST['nameOrSocialReason']) ),  
                "phoneNumber"        => $order->get_billing_phone(), 
                "postalCode"         => $order->get_billing_postcode(),
                
                "details"            => $this->details
            );

            // Settings needed to record transaction
            $params = array(
                "affiliateCode"   => $this->get_option( 'affiliate_code' ),
                "expireMinutes"   => $this->get_option( 'expire_minutes' ),
                "failedUrl"       => $this->get_option( 'failed_url' ),
                "successUrl"      =>  $return_url,
                // "notificationUrl" => $this->get_option( 'notification_url' )
            );

            $this->logs("Details", $data);
            $this->logs("URL Return", $return_url);
            $this->logs("Params", $params);

            // return;

            // Current cart hash
            $hash = wp_hash(print_r($data, true).$order);

            if( (isset($_COOKIE["code_internal"])) && ($_COOKIE["code_internal"] == $hash) ) {
                $this->logs("Hash", $_COOKIE["code_internal"]); 

                $this->emptyCart($woocommerce);
                // redirect to banipay url transaction
                return array(
                    'result' => 'success',
                    'redirect' => $_COOKIE["url_transaction"]
                );

            } else {

                // Cookie cart hash
                wc_setcookie('code_internal', $hash);
                
                // Start class Transaction
                $temp = new Transaction();

                $affiliate = $temp->getAffiliate($this->get_option( 'affiliate_code' ));

                if ( ($this->affiliate_code_demo != $this->get_option( 'affiliate_code' ) ) && isset( $affiliate->withInvoice ) ) {
                    $data["withInvoice"] = $affiliate->withInvoice;
                }

                // Registration a transaction
                $transaction = $temp->register($data, $params);
                $this->logs("Transaction", $transaction); 

                if( isset($transaction) && !isset($transaction->status) ){

                    wc_setcookie('external_code', $transaction->externalCode);
                    wc_setcookie('transaction_generated', $transaction->transactionGenerated);
                    wc_setcookie('url_transaction', $transaction->urlTransaction);
                    wc_setcookie('payment_id', $transaction->paymentId);
                    
                    $this->emptyCart($woocommerce);

                    // redirect to banipay url transaction
                    return array(
                        'result' => 'success',
                        'redirect' => $transaction->urlTransaction
                    );

                } else {
                    wc_add_notice(  'El Código de Afiliado de BaniPay no es correcto.', 'error' );
                    return;
                }
            }
 
        }
        

        function thank_you_page( ) {            

            $order = wc_get_order( $_COOKIE['order_id'] );
            
            $newData = new Transaction();

            // Payment status verification
            $response = $newData->getTransaction($_COOKIE["payment_id"], $_COOKIE["transaction_generated"]);
         
            if( !is_wp_error( $response ) ) {
                                
                if ( (isset($response->paymentStatus) && $response->paymentStatus == 'PROCESSED') || (isset($response->paymentStatus) && $response->paymentStatus == 'PROCESSING') ) {
                // if ( isset($response->paymentStatus) ) {
                    
                    $this->logs("Transaction response", $response);
             
                    $order->payment_complete();
                    // $order->reduce_order_stock();
                    
                    unset($_COOKIE['code_internal']);
                    unset($_COOKIE['external_code']);
                    unset($_COOKIE['transaction_generated']);
                    unset($_COOKIE['url_transaction']);
                    unset($_COOKIE['payment_id']);
                    unset($_COOKIE['order_id']);                    
         
                } else {
                    return;
                }
         
            } else {
                wc_add_notice( 'Error en la transacción.', 'error' );
                return;
            }

        }

        function emptyCart($woocommerce) {
            $woocommerce->cart->empty_cart();
        }

        function checkBilling($billing, $data) {
            if ( ($this->get_option( 'billing' ) == 'yes') && isset($billing) && ($billing == 'on') ) {
                return sanitize_text_field($data);
            }
            return '';
        }

        function logs($detail = null, $data = null) {
            if($this->get_option( 'logs' ) == 'yes') {
                error_log( "{$detail} : ". print_r($data, true) );
            }
        }

        function get_shipping_name_by_id( $shipping_id ) {
            $packages = WC()->shipping->get_packages();
        
            foreach ( $packages as $i => $package ) {
                if ( isset( $package['rates'] ) && isset( $package['rates'][ $shipping_id ] ) ) {
                    $rate = $package['rates'][ $shipping_id ];
                    /* @var $rate WC_Shipping_Rate */
                    return $rate->get_label();
                }
            }

            return '';
        }

        function add_shipping_method( $shipping_total, $shipping_name) {

            if ($shipping_total > 0) {
                $data = array(
                    "concept"         => $shipping_name,
                    "productImageUrl" => $this->icon,
                    "quantity"        => 1,
                    "unitPrice"       => $shipping_total,
                );
                array_push($this->details, $data);   
            }
            return;

        }
 	}
}