<?php
/*
 * Plugin Name: Woocommerce BaniPay Payment Gateway
 * Plugin URI: https://vulcan.com
 * Description: Plugin Woocommerce BaniPay
 * Author: Vulcan
 * Author URI: https://vulcan.com
 * Version: 1.0.2


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
            $this->icon = 'https://banipay.me/assets/img/banipay.png'; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = false; // in case you need a custom credit card form
            $this->method_title = 'BaniPay Gateway';
            $this->method_description = 'BaniPay payment gateway'; // will be displayed on the options page

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
            // add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
         
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
                'url_logo_banipay' => array(
                    'title'       => 'Logo BaniPay',
                    'type'        => 'text',
                    'default'     => 'https://banipay.me/assets/img/banipay.png',
                    'desc_tip'    => true,
                ),
                'affiliate_code'  => array(
                    'title'       => 'Affiliate Code',
                    'default'     => '141581ae-fb1f-4cfb-b21e-040a8851c265',
                    'type'        => 'text',
                    'description' => 'Example Code (test): 141581ae-fb1f-4cfb-b21e-040a8851c265',
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
                'notification_url' => array(
                    'title'       => 'Notification URL',
                    'type'        => 'text',
                    'default'        => get_site_url().'/notification',
                    'description' => 'Example Notification URL: '.get_site_url().'/notification'
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
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-banipay wc-payment-form" style="background:transparent;">';

            do_action( 'woocommerce_credit_card_form_start', $this->id );
            
            echo '<div class="form-row form-row-wide"><label>Pay with: </label>
                    <div class="text-center">
                        <img style="width: 60%;" src=" '. $this->get_option('url_logo_banipay') .'" />
                    </div>
                </div>                    
                <div class="clear"></div>';
        
            do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';

        }
        

	 	public function payment_scripts() {
             
            wp_register_script( 'woocommerce_banipay', plugins_url( '/banipay-gateway/banipay.js' ) );
            wp_enqueue_script( 'woocommerce_banipay' );
        }
        
        
		public function validate_fields() {
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
                // "reserved1"          => "string_1",
                "details"            => $this->details
            );

            // Settings needed to record transaction
            $params = array(
                "affiliateCode"   => $this->get_option( 'affiliate_code' ),
                "expireMinutes"   => $this->get_option( 'expire_minutes' ),
                "failedUrl"       => $this->get_option( 'failed_url' ),
                // "successUrl"      => "https://diebian.dev/",
                "successUrl"      =>  $return_url,
                "notificationUrl" => $this->get_option( 'notification_url' )
            );

            $this->logs("Details", $data);
            $this->logs("URL Return", $return_url);
            $this->logs("Params", $params);

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
                                
                //if ( (isset($response->paymentStatus) && $response->paymentStatus == 'PROCESSED') || (isset($response->paymentStatus) && $response->paymentStatus == 'PROCESSING') ) {
                if ( isset($response->paymentStatus) ) {
                    
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
                wc_add_notice(  'Error en la transacción.', 'error' );
                return;
            }

        }

        function emptyCart($woocommerce) {
            $woocommerce->cart->empty_cart();
        }

        function logs($detail = null, $data = null) {
            if($this->get_option( 'logs' ) == 'yes') {
                error_log( "{$detail} : ". print_r($data, true) );
            }
        }
 	}
}