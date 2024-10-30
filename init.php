<?php
/*
 * Plugin Name: Latipay For Woo
 * Plugin URI: https://www.latipay.net/
 * Description: A magic plugin that allows user pay by Card Payments, NZ Banks, Alipay, WechatPay, MoneyMore, PayID, UnionPay
 * Author: latipay
 * Version: 3.3.1
 * Author URI:  https://www.latipay.net/
 */
if (!defined('ABSPATH'))
    exit (); // Exit if accessed directly

if (!defined('XH_LATIPAY')) {
    define('XH_LATIPAY', 'latipay');
} else {
    return;
}

define('XH_LATIPAY_VERSION', '3.3.1');
define('XH_LATIPAY_FILE', __FILE__);
define('XH_LATIPAY_DIR', rtrim(plugin_dir_path(XH_LATIPAY_FILE), '/'));
define('XH_LATIPAY_URL', rtrim(plugin_dir_url(XH_LATIPAY_FILE), '/'));
load_plugin_textdomain(XH_LATIPAY, false, dirname(plugin_basename(XH_LATIPAY_FILE)) . '/lang/');
add_action('plugin_action_links_' . plugin_basename(XH_LATIPAY_FILE), function ($links) {
    return array_merge(array(
        'settings' => '<a href="' . admin_url('options-general.php?page=xh_latipay') . '">' . __('Settings', XH_LATIPAY_FILE) . '</a>'
    ), $links);
}, 10, 1);
add_action('admin_menu', function () {
    add_options_page(
        __('Latipay', XH_LATIPAY),
        __('Latipay', XH_LATIPAY),
        'administrator',
        'xh_latipay',
        function () {
            $options = get_option('xh_latipay', array());
            $user_id = isset($options['user_id']) ? $options['user_id'] : null;
            $api_key = isset($options['api_key']) ? $options['api_key'] : null;
            $error = null;
            if (!empty($user_id) && !empty($api_key)) {
                foreach (array('cny', 'aud', 'nzd') as $currency) {
                    $wallet_id = isset($options["wallet_id_{$currency}"]) ? $options["wallet_id_{$currency}"] : null;
                    if (!empty($wallet_id)) {
                        $sign = hash_hmac('sha256', $wallet_id . $user_id, $api_key);
                        $uri = "https://api.latipay.net/v2/detail/{$wallet_id}?user_id={$user_id}&signature={$sign}";

                        if (!class_exists('Latipay')) {
                            require_once 'includes/lib/Latipay.php';
                            require_once 'includes/lib/Latipay_IP.php';
                        }

                        $client = new Latipay_RestClient();
                        $response = $client->url($uri)->get();

                        if ( is_wp_error( $response ) ) {
                            $error = $response->get_error_message();
                        } else {
                            $response = json_decode( $response, true );
                            $response['payment_method'].= ",vm";


                            if ( $response['code'] == 0 ) {

                                $tmp = explode(',', $response['payment_method']);
                                foreach ($tmp as $k => $v) {
                                    if(strtolower($v) == 'polipay'){
                                        $tmp[$k]='NZBanks';
                                    }elseif(strtolower($v) == 'azupay'){
                                        $tmp[$k]='PayID';
                                    }elseif(strtolower($v) == 'upi_upop'){
                                        $tmp[$k]='UnionPay';
                                    }
                                }

                                $curOpt = [];
                                for($i=0;$i<count($tmp);$i++){
                                    $curOpt = get_option('woocommerce_'.$tmp[$i].'_settings');

                                    if(is_array($curOpt) && count($curOpt)){

                                        foreach ($curOpt as $k => $v) {
                                            if(strtolower($v) == 'polipay'){
                                                update_option('woocommerce_nzbanks_settings',
                                                    array(
                                                        'enabled' => 'yes',
                                                        'title'   => 'NZBanks',
                                                        'description' => $curOpt['description'],
                                                        'instructions' => $curOpt['instructions']
                                                    )
                                                );
                                            }elseif(strtolower($v) == 'azupay'){
                                                update_option('woocommerce_payid_settings',
                                                    array(
                                                        'enabled' => 'yes',
                                                        'title'   => 'PayID',
                                                        'description' => $curOpt['description'],
                                                        'instructions' => $curOpt['instructions']
                                                    )
                                                );
                                            } else {
												update_option('woocommerce_'.strtolower($v).'_settings',
												    array(
												        'enabled' => 'yes',
												        'title'   => $v,
												        'description' => $curOpt['description'],
												        'instructions' => $curOpt['instructions']
												    )
												);
											}
                                        }
                                    } else {
										update_option('woocommerce_'.strtolower($tmp[$i]).'_settings',
										    array(
										        'enabled' => 'yes',
										        'title'   => $tmp[$i],
										        'description' => $curOpt['description'],
										        'instructions' => $curOpt['instructions']
										    )
										);
									}
                                }

                                update_option( "xh_latipay_payment_$currency", $tmp);
                            } else {
                                $error = 'Error code: ' . $response['code'];
                                $error .= '<br>Error message: ' . $response['message'];
                            }
                        }
                    }
                }
            }

            ?>
            <?php if ( $response['code'] != 0 ): ?>
            <p>
                <?php echo $error;?>
            </p>
            <?php endif;?>
            <form method="post" id="mainform" action="options.php" enctype="multipart/form-data">
                <h3><?php echo __('Latipay settings', XH_LATIPAY) ?></h3>
                <a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout') ?>">Go woo settings</a>
                <table class="form-table">
                    <tbody>
                    <tr>
                        <th scope="row"><label>User ID</label></th>
                        <td>
                            <input type="text" style="width:400px;"
                                   value="<?php echo esc_attr(isset($options['user_id']) ? $options['user_id'] : null) ?>"
                                   name="xh_latipay[user_id]"/>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label>Api key</label></th>
                        <td>
                            <input type="text" style="width:400px;"
                                   value="<?php echo esc_attr(isset($options['api_key']) ? $options['api_key'] : null) ?>"
                                   name="xh_latipay[api_key]"/>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2">
                            <h5>Wallet ID for currency</h5>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label>CNY</label></th>
                        <td>
                            <input type="text" style="width:400px;"
                                   value="<?php echo esc_attr(isset($options['wallet_id_cny']) ? $options['wallet_id_cny'] : null) ?>"
                                   name="xh_latipay[wallet_id_cny]"/>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label>NZD</label></th>
                        <td>
                            <input type="text" style="width:400px;"
                                   value="<?php echo esc_attr(isset($options['wallet_id_nzd']) ? $options['wallet_id_nzd'] : null) ?>"
                                   name="xh_latipay[wallet_id_nzd]"/>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label>AUD</label></th>
                        <td>
                            <input type="text" style="width:400px;"
                                   value="<?php echo esc_attr(isset($options['wallet_id_aud']) ? $options['wallet_id_aud'] : null) ?>"
                                   name="xh_latipay[wallet_id_aud]"/>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label>is_spotpay</label></th>
                        <td>
                            <input type="text" style="width:400px;"
                                   value="<?php echo esc_attr(isset($options['is_spotpay']) ? $options['is_spotpay'] : null) ?>"
                                   name="xh_latipay[is_spotpay]"/>
                            <div style="color:gray">
                                Default is empty, do not change, unless you’ve been told to.
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row"><label>IS_DEBUG</label></th>
                        <td>
                            <input type="text" style="width:400px;"
                                   value="<?php echo esc_attr(isset($options['IS_DEBUG']) ? $options['IS_DEBUG'] : 0) ?>"
                                   name="xh_latipay[IS_DEBUG]"/>
                            <div style="color:gray">
                                Set 0 as debug mode and 1 as normal mode.
                            </div>
                        </td>
                    </tr>

                    </tbody>
                </table>
                <p class="submit">
                    <?php
                    wp_nonce_field('update-options')
                    ?>
                    <input type="hidden" name="action" value="update"/>
                    <input type="hidden" name="page_options" value="xh_latipay"/>
                    <input type="submit" value="<?php echo __('Save', XH_LATIPAY) ?>" class="button-primary"/>
                </p>
            </form>
            <?php
        });
});

add_action('init', 'initLatipay');
function initLatipay () {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    $currency = strtolower(get_woocommerce_currency());
    $methods = get_option("xh_latipay_payment_{$currency}", array());

    require_once 'abstract-xh-latipay-wc-payment-gateway.php';

    if ($methods && is_array($methods)) {
        foreach ($methods as $k => $method) {
            switch (strtolower(trim($method))) {
                case 'alipay':
                    require_once 'class-latipay-alipay-wc-payment-gateway.php';
                    new XHLatipayAlipayForWC();
                    break;

                case 'wechat':
                    require_once 'class-latipay-wechat-wc-payment-gateway.php';
                    new XHLatipayWechatForWC();
                    break;

                case 'moneymore':
                    require_once 'class-latipay-moneymore-wc-payment-gateway.php';
                    new XHLatipayMoneymoreForWC();
                    break;

                case 'nzbanks':
                    require_once 'class-latipay-polipay-wc-payment-gateway.php';
                    new XHLatipayPolipayForWC();
                    break;

                case 'payid':
                    require_once 'class-latipay-azupay-wc-payment-gateway.php';
                    new XHLatipayAzupayForWC();
                    break;
                case 'unionpay':
                    require_once 'class-latipay-unionpay-wc-payment-gateway.php';
                    new XHLatipayUnionpayForWC();
                    break;
                case 'vm':
                    require_once 'class-latipay-vm-wc-payment-gateway.php';
                    new XHLatipayVmForWC();
                    break;
            }
        }

    }
	

    $options = get_option('xh_latipay', array());

      //v2-callback
     if (isset($_REQUEST['notify_version']) && $_REQUEST['notify_version'] == 'v2') {
         
         $options = get_option('xh_latipay', array());
		
		//added by Edison Tsai on 14:16 2024/05/08 for fix class not found bug on some evn
		class_exists('XHLatipayVmForWC') or require_once 'class-latipay-vm-wc-payment-gateway.php';
		
         $handle = new XHLatipayVmForWC();

       //valid v2 params ,just enable  post
        if (
            !isset($_POST['order_id'])
            || !isset($_POST['currency'])
            || !isset($_POST['amount'])
            || !isset($_POST['pay_time'])
            || !isset($_POST['payment_method'])
            || !isset($_POST['status'])
            || !isset($_POST['signature'])
        ) {
			return;
		}

        //valid v2 sign
		$notifyPost = $_POST;
        unset($notifyPost['signature']);	

        $expect_sign  = $handle->getSignStr($notifyPost, $handle->get_option('private_key'));
		
        if($expect_sign != $_POST['signature']) {
      	  return;
        }

         $orderId = $_POST['out_trade_no'];
         $status = $_POST['status'];
         goto FINISH;
    }



   //v1 callback
    if (
        !isset($_REQUEST['merchant_reference'])
        || !isset($_REQUEST['payment_method'])
        || !isset($_REQUEST['status'])
        || !isset($_REQUEST['currency'])
        || !isset($_REQUEST['amount'])
        || !isset($_REQUEST['signature'])
    ) {
        return;
    }

    $options = get_option('xh_latipay', array());

    if (isset($options['IS_DEBUG']) && $options['IS_DEBUG'] == 1) {
        $logFile = dirname(__FILE__) . '/latipay-debug.log';
        $logStr = date('Y-m-d H:i:s') . ' payment_complete : ' . $_REQUEST['merchant_reference'] . ', ' . $_REQUEST['payment_method'] . $_REQUEST['status'] . PHP_EOL;
        file_put_contents($logFile, $logStr, FILE_APPEND);
    }

    $api_key = isset($options['api_key']) ? $options['api_key'] : null;
    $merchantOrderId = $_REQUEST['merchant_reference'];
    $payment_method = $_REQUEST['payment_method'];
    $status = $_REQUEST['status'];
    $currency = $_REQUEST['currency'];
    $amount = $_REQUEST['amount'];

    $signature_string = $merchantOrderId . $payment_method . $status . $currency . $amount;
    $signature = hash_hmac('sha256', $signature_string, $api_key);
    if ( $signature != $_REQUEST['signature'] ) {
        return;
    }

    $orderId = substr($merchantOrderId, 0, strripos($merchantOrderId, '_'));


    FINISH:
    $order = wc_get_order($orderId);
	
	
    if ($status == "paid") {

        $latipayOrderId =  isset($_REQUEST['order_id']) ? $_REQUEST['order_id'] : null;
        if ( $order->has_status( apply_filters( 'woocommerce_valid_order_statuses_for_payment_complete', array( 'on-hold', 'pending', 'failed', 'cancelled' ), $order ) ) ) {
            $order->payment_complete($latipayOrderId);
            //add order note
            $order->add_order_note(__(XH_LATIPAY . ' payment complete. order_id: ' . $latipayOrderId, XH_LATIPAY));

            //send email
            $mailer = WC()->mailer();
            $mailer->emails['WC_Email_New_Order']->trigger($orderId);
            $mailer->emails['WC_Email_Customer_Processing_Order']->trigger($orderId);
        }
    } else {
        wc_add_notice( __( 'Payment error', XH_LATIPAY ), 'error' );
    }
    return;
}

//added by Edison Tsai on 10:21 09/04/2023
add_action( 'woocommerce_thankyou', 'latipay_add_content_thankyou');
function latipay_add_content_thankyou($order_id)
{
	//modified by Edison Tsai on 23:55 2024/09/17 for replace get_status() with get_data()
	$status = '';
	try {
		$order = wc_get_order($order_id);
		
		$data = $order->get_data();
		$status = isset($data['status']) ? strtolower($data['status']) : '';
		
	} catch (Exception $e) {
		$status = '';
	}

	    if (empty($status)) {
			//Don't need to notice
			
		} elseif ($status == 'processing' || $status == 'completed') { //paid
			echo "<div style='color:green; padding:30px 0 30px 0;'>&#10004;&nbsp;Payment successful. Thank you!</div>";
	    } elseif(in_array($status, ['pending','on-hold'])) {
			echo "<div style='color:red; padding:30px 0 30px 0;'>&#10008;&nbsp;Payment is being processed, please wait.</div>";
		} elseif($status == 'cancelled') { //cancelled
			echo "<div style='color:red; padding:30px 0 30px 0;'>&#10008;&nbsp;Payment has been cancelled. Please attempt order payment again.</div>";
		} else { //failed or unpaid or others
			echo "<div style='color:red; padding:30px 0 30px 0;'>&#10008;&nbsp;Payment unsuccessful. Please attempt order payment again.</div>";
		}
		
}
