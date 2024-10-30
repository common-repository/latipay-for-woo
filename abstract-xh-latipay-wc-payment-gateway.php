<?php

defined( 'ABSPATH' ) || exit;

abstract class Abstract_XH_Latipay_Payment_Gateway extends WC_Payment_Gateway
{
    protected $instructions;

    public function __construct()
    {
        $this->id = strtolower($this->get_payment_method());

        $this->has_fields = false;

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');


        add_filter('woocommerce_payment_gateways', array($this, 'woocommerce_add_gateway'), 10, 1);
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        //add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
    }

    /**
     * Initialise Gateway Settings Form Fields
     *
     * @access public
     * @return void
     */
    function init_form_fields()
    {


        switch($this->get_payment_method()){
            case 'VM':
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', XH_LATIPAY),
                        'type' => 'checkbox',
                        'default' => 'yes',
                        'section' => 'default'
                    ),
                    'title' => array(
                        'title' => __('title', XH_LATIPAY),
                        'type' => 'text',
                        'default' => $this->get_payment_method(),
                        'desc_tip' => true,
                        'css' => 'width:400px',
                        'section' => 'default'
                    ),
                    'site_id' => array(
                        'title' => __('site_id', XH_LATIPAY),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                        'css' => 'width:400px',
                        'section' => 'default'
                    ),
                    'merchant_id' => array(
                        'title' => __('merchant_id', XH_LATIPAY),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                        'css' => 'width:400px',
                        'section' => 'default'
                    ),
                    'private_key' => array(
                        'title' => __('private_key', XH_LATIPAY),
                        'type' => 'text',
                        'default' => '',
                        'desc_tip' => true,
                        'css' => 'width:400px',
                        'section' => 'default'
                    ),

                    'description' => array(
                        'title' => __('description', XH_LATIPAY),
                        'type' => 'textarea',
                        'desc_tip' => true,
                        'css' => 'width:400px',
                        'section' => 'default'
                    ),
                    'instructions' => array(
                        'title' => __('Instructions', XH_LATIPAY),
                        'type' => 'textarea',
                        'css' => 'width:400px',
                        'description' => __('Instructions that will be added to the thank you page.', XH_LATIPAY),
                        'default' => '',
                        'section' => 'default'
                    )
                );
                break;
            default:
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Enable/Disable', XH_LATIPAY),
                        'type' => 'checkbox',
                        'default' => 'yes',
                        'section' => 'default'
                    ),
                    'title' => array(
                        'title' => __('title', XH_LATIPAY),
                        'type' => 'text',
                        'default' => $this->get_payment_method(),
                        'desc_tip' => true,
                        'css' => 'width:400px',
                        'section' => 'default'
                    ),

                    'description' => array(
                        'title' => __('description', XH_LATIPAY),
                        'type' => 'textarea',
                        'desc_tip' => true,
                        'css' => 'width:400px',
                        'section' => 'default'
                    ),
                    'instructions' => array(
                        'title' => __('Instructions', XH_LATIPAY),
                        'type' => 'textarea',
                        'css' => 'width:400px',
                        'description' => __('Instructions that will be added to the thank you page.', XH_LATIPAY),
                        'default' => '',
                        'section' => 'default'
                    )
                );
                break;
        }






    }

    public function woocommerce_add_gateway($methods)
    {
        $methods [] = $this;
        return $methods;
    }

    public function thankyou_page()
    {
        if ($this->instructions) {
            echo wpautop(wptexturize($this->instructions));
        }
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->payment_method) {
            echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
        }
    }

    abstract function get_payment_method();

    public function process_payment($order_id)
    {
        $order = new WC_Order ($order_id);
        if (!$order || !$order->needs_payment()) {
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        $total_amount = sprintf("%.2f",round($order->get_total(),2));
        $gateway = "https://api.latipay.net/v2";
        $url_return = $this->get_return_url($order);
        $url_notify = $this->get_return_url($order);
        $currency = get_woocommerce_currency();
        $supported_currencies = array('NZD', 'CNY', 'AUD');
        if (!in_array($currency, $supported_currencies)) {
            throw new Exception('Only currency:' . join(',', $supported_currencies) . ' can by allowed!');
        }

        $options = get_option('xh_latipay', array());
        $user_id = isset($options['user_id']) ? $options['user_id'] : null;
        $api_key = isset($options['api_key']) ? $options['api_key'] : null;
        $wallet_id = isset($options["wallet_id_" . strtolower($currency)]) ? $options["wallet_id_" . strtolower($currency)] : null;
        $payment_method = strtolower($this->get_payment_method());



        require_once 'includes/lib/Latipay.php';
        require_once 'includes/lib/Latipay_IP.php';
        $latipay = new Latipay($gateway);

        switch($payment_method){
            case 'nzbanks':
                $payment_method = 'polipay';
            break;
            case 'payid':
                $payment_method = 'azupay';
            break;
            case 'unionpay':
                $payment_method = 'upi_upop';
            break;
            default:
            //do nothing
            break;
        }

        $post_data = array(
            'user_id' => $user_id,
            'wallet_id' => $wallet_id,
            'amount' => $total_amount,
            'payment_method' => $payment_method,
            'return_url' => $url_return,
            'callback_url' => $url_notify,
            'backPage_url' => $order->get_cancel_order_url(),
            'merchant_reference' => $order_id . '_' . uniqid(),
            'ip' => Latipay_IP::clientIP(),
            'product_name' => $this->get_order_title($order),
            'version' => '2.0',
            'present_qr' => '1',
        );

        ksort($post_data);
        $item = array();
        foreach ($post_data as $key => $value) {
            $item[] = $key . "=" . $value;
        }
        $_prehash =  join("&", $item);

        $signature = hash_hmac('sha256', $_prehash . $api_key, $api_key);
        $post_data['signature'] = $signature;

        if (isset($options['IS_DEBUG']) && $options['IS_DEBUG'] == 1) {
            $logFile = dirname(__FILE__) . '/latipay-debug.log';
            $logStr = date('Y-m-d H:i:s') . ' process_payment : ' . json_encode($post_data) . PHP_EOL;
            file_put_contents($logFile, $logStr, FILE_APPEND);
        }

        try {


            if(strtolower($payment_method) == "vm") {

                $order_data = json_decode($order,true);

                $v2_post_data = [
                    "order_id" => $order_data['id'],
                    "amount" => $post_data['amount'],
                    "merchant_id" => $this->get_option('merchant_id'),
                    "site_id" => $this->get_option('site_id'),
                    "currency" => $order_data['currency'],
                    "firstname" => $order_data['billing']['first_name'],
                    "lastname" => $order_data['billing']['last_name'],
                    "address" => $order_data['billing']['address_1'].$order_data['billing']['address_2'],
                    "city" => $order_data['billing']['city'],
                    "state" => $order_data['billing']['state'],
                    "country" => $order_data['billing']['country'],
                    "postcode" => $order_data['billing']['postcode'],
                    "phone" => $order_data['billing']['phone'],
                    "email" => $order_data['billing']['email'],
                    "timestamp" => time(),
                    "product_name" => $post_data['product_name'],
                    "return_url" => $url_return,
                     "callback_url" => $url_return,
                   "cancel_order_url" => $order->get_cancel_order_url(),  //cancel url  
                ];


                $v2_post_data['signature'] = $this->getSignStr($v2_post_data, $this->get_option('private_key'));


                $payment = $latipay->createPaymentVm($v2_post_data);
                 return array(
                        'result' => 'success',
                        'redirect' => $payment['host_url'] . '/' . $payment['nonce']
                    );
   

            } else {
                $payment = $latipay->createPayment($post_data);
                if (isset($payment['code']) && $payment['code'] != '0') {
                    throw new Exception($payment['message']);
                }

                $response_signature = hash_hmac('sha256', $payment['nonce'] . $payment['host_url'], $api_key);
                if ($response_signature == $payment['signature']) {
                    return array(
                        'result' => 'success',
                        'redirect' => $payment['host_url'] . '/' . $payment['nonce']
                    );

                }
            }



        } catch (Exception $e) {
            throw new Exception('Payment failed : ' . $e->getMessage());
        }
    }

    /**
     * generate v2 sign
     * @param $data
     * @param $sk
     * @return string
     */
    public function getSignStr($data, $sk )
    {
        ksort($data);
        $msg = http_build_query($data);
        return  hash_hmac('sha256', $msg, $sk);

    }




    public function get_order_title($order, $limit = 32)
    {
        $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
        $title = "#{$order_id}";

        $order_items = $order->get_items();
        if ($order_items) {
            $qty = count($order_items);
            foreach ($order_items as $item_id => $item) {
                $title .= "|{$item['name']}";
                break;
            }
            if ($qty > 1) {
                $title .= '...';
            }
        }

        $title = mb_strimwidth($title, 0, $limit, '', 'utf-8');
        return apply_filters('xh-payment-get-order-title', $title, $order);
    }
}

?>
