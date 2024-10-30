<?php
if (!defined('ABSPATH'))
    exit (); // Exit if accessed directly
class XHLatipayAzupayForWC extends Abstract_XH_LATIPAY_Payment_Gateway
{

    public function __construct()
    {
        parent::__construct();

        $this->icon = XH_LATIPAY_URL . '/images/payid.png';
        $this->method_title = __('Latipay - PayID', XH_LATIPAY);
    }

    public function get_payment_method()
    {
        return 'PayID';
    }
}

?>
