<?php
/**
 * Plugin Name: Iute e-commerce
 * Description: Iute checkout plugin for Woocommerce
 * Author:      IuteCredit Europe AS
 * Author URI:  https://www.iutecredit.com/
 * Text Domain: iutepay
 * Domain Path: /i18n
 * Version:     1.0.48
 */

class Constants
{

    public static $set_constants = array();


    public static function is_defined($name)
    {
        return array_key_exists($name, self::$set_constants)
            ? true
            : defined($name);
    }

    public static function set_constant($name, $value)
    {
        self::$set_constants[$name] = $value;
    }

}

/**
 * Iute manager class in Admin UI
 *
 */
class Iute_Ecom_Rest_Client
{
    private $_url;
    private $_admin_key;

    public function __construct()
    {
        $payment_gateways = WC()->payment_gateways->payment_gateways();
        if (isset($payment_gateways['iutepay'])) {
            $iutepay = $payment_gateways['iutepay'];
            $this->_url = $iutepay->url;
            $this->_admin_key = $iutepay->iute_admin_key;
        }
    }

    private function send_request($path, $method, $body)
    {

        if (!$method) {
            $method = 'GET';
        }

        if (!$body) {
            $body = null;
        }

        $args = array(
            'headers' => array(
                'x-iute-admin-key' => $this->_admin_key,
                'content-Type' => 'application/json',
                'charset' => 'utf-8'),
            'timeout' => 5
        );
        error_log("Iute. send_request " . $path . " method = " . $method);
        if ($body) {
            $args['body'] = json_encode($body);
            $args['data_format'] = 'body';//https://wordpress.stackexchange.com/questions/237224/sending-json-string-through-wp-remote-post
            if ($method == 'POST') {
                $response = wp_remote_post($this->_url . $path, $args);
            } elseif ($method == 'DELETE' || $method == 'PUT') {
                $args['method'] = $method;
                $response = wp_remote_request($this->_url . $path, $args);
            }
        } else {
            $response = wp_remote_get($this->_url . $path, $args);
        }

        if (isset($response->errors)) {
            error_log("Iute. send_request errors " . $path . " errors=" . json_encode($response->errors));
            return null;
        } else {
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            error_log("Iute. Log send_request path " . $path . " code= " . $response_code . " response=" . $response_body);

            if ($response_code >= 200 && $response_code < 300) {
                $response = json_decode($response_body, false);
                if (!$response) {
                    return true;
                }
            } else {
                error_log("Iute. send_request path " . $path . " code= " . $response_code . " error response=" . $response_body);
                return null;
            }
        }
        return $response;
    }

    /**
     * Optional operation, if it fails not a issue, we can continue with order and payment processing and redirect to woocomerce thank you page
     * @param $checkout_session_id
     * @param $order_id
     * @param $order_number
     */
    public function setOrderId($checkout_session_id, $order_id, $order_number)
    {
        try {
            $result = $this->send_request('/api/v1/eshop/management/checkout/' . $checkout_session_id . '/order-id', 'PUT',
                array(
                    'orderId' => $order_id,
                    'orderNumber' => $order_number,
                )
            );

            if (!$result) {
                $order = wc_get_order($order_id);
                $order->add_order_note("Unable to set order number=" . $order_number . " to checkout session id=" . $checkout_session_id, 0, false);
                error_log("Iute. Unable to set order number=" . $order_number . " to checkout session id=" . $checkout_session_id);
            } else {
                $order = wc_get_order($order_id);
                $order->add_order_note("Successfully set order id to checkout session " . $checkout_session_id, 0, false);
            }
        } catch (Exception $e) {
            $order = wc_get_order($order_id);
            $order->add_order_note("Unable to set order number=" . $order_number . " to checkout session id=" . $checkout_session_id . " e=" . $e, 0, false);
            error_log("Iute. Unable to set order number=" . $order_number . " to checkout session id=" . $checkout_session_id . " e=" . $e);
        }
    }

}


if (!function_exists('getallheaders')) {
    function getallheaders()
    {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

/**
 * Adds a privacy policy statement.
 */
function iute_add_privacy_policy_content()
{
    if (!function_exists('wp_add_privacy_policy_content')) {
        return;
    }
    $content = '<h2>' . __('What we collect and store', 'iutepay') . '</h2><p>'
        . sprintf(
            __('When you submit a loan application choosing iute as a checkout option for your order, we send  to Iute Group AS the details of your order, including your shopping cart content, your personal details and delivery/billing addresses. This information is a required part of loan application, you submit. The Iute Group AS privacy policy is <a href="%1$s" target="_blank">here</a>.', 'iutepay'),
            'https://iutecredit.com/privacy-policy/'
        ) . '</p>';
    wp_add_privacy_policy_content('Iute e-commerce', wp_kses_post(wpautop($content, false)));
}

add_action('admin_init', 'iute_add_privacy_policy_content');


add_filter('woocommerce_payment_gateways', 'iutepay_add_gateway_class');
function iutepay_add_gateway_class($gateways)
{
    $gateways[] = 'WC_Iutepay_Gateway';
    return $gateways;
}

//add_action( 'woocommerce_after_shop_loop_item', 'iute_insert_as_low_as_placeholder_category_page', 6 );
// 11 woocommerce_template_single_price - 10
add_action('woocommerce_single_product_summary', 'iute_insert_as_low_as_placeholder_single_product_page', 11);

/*function iute_insert_as_low_as_placeholder_category_page() {
    global $product;
    echo "<div class='iute-as-low-as' data-amount='".esc_attr($this->convert_to_country_currency($product))."' data-sku='".esc_attr(empty($product->get_sku()) ? $product->get_id(): $product->get_sku())."' data-page-type='category'></div>";
}*/

function iute_insert_as_low_as_placeholder_single_product_page()
{
    global $product;
    echo get_promo_message_html($product);
}

add_filter('woocommerce_get_price_html', 'iute_insert_as_low_as_woocommerce_get_price_html', 10, 2);

// put promo message inside <span class="price></span>
function iute_insert_as_low_as_woocommerce_get_price_html($price, $product)
{
    global $wp_query;
    if ($wp_query->is_single) {
        // we can use this as alternative iute_insert_as_low_as_placeholder_single_product_page
    } else {
        $price = $price . get_promo_message_html($product, true);
    }

    return $price;
}

function get_promo_message_html($product, $categoryPage = false)
{
    $payment_gateways = WC()->payment_gateways->payment_gateways();
    if (isset($payment_gateways['iutepay'])) {
        $iutepay = $payment_gateways['iutepay'];
        $showPromoOnCategoryPage = $iutepay->showPromoOnCategoryPage;

        if ($categoryPage && !$showPromoOnCategoryPage) {
            return "";
        }

        $pageType = "product";
        if ($categoryPage) {
            $pageType = "category";
        }

        $dataAttrs = "";

        if ($iutepay->promoSettings[$pageType]['logoColor']) {
            // data-logo-color
            $dataAttrs = $dataAttrs . " data-logo-color='" . $iutepay->promoSettings[$pageType]['logoColor'] . "' ";
        }

        if ($iutepay->promoSettings[$pageType]['logoType']) {
            // data-logo-type
            $dataAttrs = $dataAttrs . " data-logo-type='" . $iutepay->promoSettings[$pageType]['logoType'] . "' ";
        }

        if (!$iutepay->promoSettings[$pageType]['showReadMore']) {
            // data-learnmore-show

            $dataAttrs = $dataAttrs . " data-learnmore-show='false' ";
        }

        $price = $iutepay->convert_to_country_currency($product);


        $variations = NULL;
        if (!($product instanceof WC_Product_Variation)) {
            $variations = iute_get_product_variations_by_product_encoded($product);
            if ($variations) {
                $dataAttrs = $dataAttrs . " data-variations='" . $variations . "' ";
            }
        }
        return "<div class='" . $iutepay->promoSettings[$pageType]['class'] . "' data-amount='" . esc_attr($price) . "' data-id='" . esc_attr($product->get_id()) . "' data-sku='" . esc_attr(empty($product->get_sku()) ? $product->get_id() : $product->get_sku()) . "' data-page-type='" . $pageType . "' " . $dataAttrs . "></div>";
    }

    return "";
}

add_filter( 'elementor/widget/render_content', function ( $widget_content, $widget ) {
    global $product;
    $widget_name = $widget->get_name();
    if ( ! in_array( $widget_name, ['woocommerce-product-price' ] ) ) {
        return $widget_content;
    }

    return $widget_content."".get_promo_message_html($product);
}, 10 ,2 );

function custom_gateway_icon($icon, $id)
{
    if ($id === 'iutepay') {
        return '<img src="' . plugin_dir_url(__FILE__) . 'public/images/iutepay.svg">';
    } else {
        return $icon;
    }
}

add_filter('woocommerce_gateway_icon', 'custom_gateway_icon', 10, 2);


function iute_add_fake_error_after_validation_trick($posted)
{
    if ($_POST["payment_method"] == "iutepay" && $_POST['confirm-order-flag'] == "1") {
        wc_add_notice(__("custom_notice", 'fake_error'), 'error');
    }
}

add_action('woocommerce_after_checkout_validation', 'iute_add_fake_error_after_validation_trick');

function iute_after_add_to_cart_button()
{
    global $product;

    $payment_gateways = WC()->payment_gateways->payment_gateways();
    if (!isset($payment_gateways['iutepay'])) {
        return;
    }

    $iutepay = $payment_gateways['iutepay'];
    if (!$iutepay->fastCheckoutButtonOnProductPage) {
        return;
    }

    $button_html = $iutepay->fastCheckoutButtonOnProductPageHTML;
    $button_selector = $iutepay->fastCheckoutButtonOnProductPageSelector;

    $checkout_url = wc_get_checkout_url();
    $iutepay_fastcheckout_button_js = <<<IUTEPAYJS3
        {$button_html}
        <script>
        jQuery(document).ready(function(){
            jQuery('{$button_selector}').click(function(data) {
                var thisbutton = jQuery(this);
                var form = thisbutton.closest('form.cart');
                var product_qty = form.find('input[name=quantity]').val() || 1;
                var product_id = form.find('input[name=product_id]').val();
                if (!product_id) {
                    product_id = form.find('button[name=add-to-cart]').val();
                }
                var variation_id = form.find('input[name=variation_id]').val() || 0;
                
                var data = {
                    action: 'woocommerce_ajax_add_to_cart',
                    product_id: product_id,
                    product_sku: '',
                    quantity: product_qty,
                    variation_id: variation_id,
                };
          
                jQuery(function ($) {
                    $.ajax({
                        type: 'post',
                        url:  wc_add_to_cart_params.ajax_url,
                        data: data,
                        success: function (response) { 
                             console.log('added to cart ' + data.product_id + ' response : ' + response);
                             window.location = '{$checkout_url}';
                        }, 
                    });
                }); 
                
            });
        });
        </script>
IUTEPAYJS3;


    echo $iutepay_fastcheckout_button_js;

}

add_action('woocommerce_after_add_to_cart_button', 'iute_after_add_to_cart_button');

add_action('wp_ajax_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart');
add_action('wp_ajax_nopriv_woocommerce_ajax_add_to_cart', 'woocommerce_ajax_add_to_cart');

function woocommerce_ajax_add_to_cart()
{

    $product_id = apply_filters('woocommerce_add_to_cart_product_id', absint($_POST['product_id']));
    $quantity = empty($_POST['quantity']) ? 1 : wc_stock_amount($_POST['quantity']);
    $variation_id = absint($_POST['variation_id']);
    $passed_validation = apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity);
    $product_status = get_post_status($product_id);

    if ($passed_validation && WC()->cart->add_to_cart($product_id, $quantity, $variation_id) && 'publish' === $product_status) {

        do_action('woocommerce_ajax_added_to_cart', $product_id);

        if ('yes' === get_option('woocommerce_cart_redirect_after_add')) {
            wc_add_to_cart_message(array($product_id => $quantity), true);
        }

        WC_AJAX:: get_refreshed_fragments();
    } else {

        $data = array(
            'error' => true,
            'product_url' => apply_filters('woocommerce_cart_redirect_after_error', get_permalink($product_id), $product_id));

        echo wp_send_json($data);
    }

    wp_die();
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action('plugins_loaded', 'iute_init_gateway_class');
function iute_init_gateway_class()
{

    class WC_Iutepay_Gateway extends WC_Payment_Gateway
    {

        /**
         * Class constructor
         */
        public function __construct()
        {

            $plugin_dir = plugin_dir_url(__FILE__);

            $this->version = '1.0.48';
            $this->id = 'iutepay'; // payment gateway plugin ID
            $this->icon;
            $this->has_fields = false; // in case you need a custom credit card form
            $this->chosen = false; // chosen by default
            $this->method_title = 'Iute Checkout';
            $this->method_description = 'Iute checkout by Iute Group AS.'; // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );


            // Load the settings.
            $this->init_settings();
            $this->title = __('Pay in parts', 'iutepay');
            $this->description = $this->get_option('description', ' ');
            $this->country = $this->get_option('country');
            $this->enabled = $this->get_option('enabled');
            $this->testmode = 'yes' === $this->get_option('testmode');
            $this->env = $this->get_option('env');
            $this->showPromoOnCategoryPage = 'yes' === $this->get_option('showPromoOnCategoryPage');
            $this->enableWebhook = 'yes' === $this->get_option('enableWebhook');
            $this->iute_admin_key = $this->testmode ? $this->get_option('test_iute_admin_key') : $this->get_option('iute_admin_key');
            $this->iute_api_key = $this->testmode ? $this->get_option('test_iute_api_key') : $this->get_option('iute_api_key');
            // tmp disabled, we waiting feedback about this
            $this->emailNotificationAllowed = false;
            $this->emailNotificationAboutNewLoanApplication = 'yes' === $this->get_option('emailNotificationAboutNewLoanApplication');
            $this->emailNotificationAboutLoanApplicationStatusChange = 'yes' === $this->get_option('emailNotificationAboutLoanApplicationStatusChange');

            $this->emailNotificationNewOrder = 'yes' === $this->get_option('emailNotificationNewOrder');
            $this->emailNotificationCancelledOrder = 'yes' === $this->get_option('emailNotificationCancelledOrder');
            $this->emailNotificationOnHoldOrder = 'yes' === $this->get_option('emailNotificationOnHoldOrder');
            $this->emailNotificationProcessingOrder = 'yes' === $this->get_option('emailNotificationProcessingOrder');

            $this->antifraudEnabled = 'yes' === $this->get_option('iutepay_antifraud_enabled');
            $this->thankYouModalEnabled = 'yes' === $this->get_option('iutepay_thank_you_enabled');
            $this->fastCheckoutFormPromoModal = 'yes' === $this->get_option('fastCheckoutFormPromoModal');
            $this->fastCheckoutButtonOnProductPage = 'yes' === $this->get_option('productPage_fast_checkout_button_visible');
            $this->fastCheckoutButtonOnProductPageHTML = $this->get_option('productPage_fast_checkout_button_html');
            $this->fastCheckoutButtonOnProductPageSelector = $this->get_option('productPage_fast_checkout_button_selector', "div.iute-add-to-cart-button");

            $this->promoSettings = array(
                'product' => array(
                    'class' => $this->get_option('productPage_promo_class', 'iute-as-low-as'),
                    'logoColor' => $this->get_option('productPage_promo_logoColor', ''),
                    'logoType' => $this->get_option('productPage_promo_logoType', ''),
                    'showReadMore' => 'yes' === $this->get_option('productPage_promo_showReadMore'),
                ),
                'category' => array(
                    'class' => $this->get_option('categoryPage_promo_class', 'iute-as-low-as'),
                    'logoColor' => $this->get_option('categoryPage_promo_logoColor', ''),
                    'logoType' => $this->get_option('categoryPage_promo_logoType', ''),
                    'showReadMore' => 'yes' === $this->get_option('categoryPage_promo_showReadMore'),
                ),
            );

            //$this->isPatronymicRequired = false;

            if ($this->country == 'en') { // Albania (EN)
                $this->envDomainName = 'al';
                $this->locale = 'en';
                $this->countryIso3Code = 'alb';
                $this->currency = 'lek';
                //$this->isPatronymicRequired = true;
            } else if ($this->country == 'al') { // Albania
                $this->envDomainName = 'al';
                $this->locale = 'sq';
                $this->countryIso3Code = 'alb';
                $this->currency = 'lek';
                //$this->isPatronymicRequired = true;
            } else if ($this->country == 'bs') { // Bosnia
                $this->envDomainName = 'ba';
                $this->locale = 'bs';
                $this->countryIso3Code = 'bih';
                $this->currency = 'bam';
            } else if ($this->country == 'bg') { // Bulgaria
                $this->envDomainName = 'bg';
                $this->locale = 'bg';
                $this->countryIso3Code = 'bgr';
                $this->currency = 'bgn';
            } else if ($this->country == 'mk') {  //North Macedonia
                $this->envDomainName = 'mk';
                $this->locale = 'mk';
                $this->countryIso3Code = 'mkd';
                $this->currency = 'mkd';
            } else if ($this->country == 'md') {  //Moldova
                $this->envDomainName = 'md';
                $this->locale = 'ro';
                $this->countryIso3Code = 'mda';
                $this->currency = 'mdl';
            }

            if ($this->testmode) {
                $this->envDomainSuffix = "-stage";
                if ($this->env && ($this->env == "stage" || $this->env == "dev")) {
                    $this->envDomainSuffix = "-".$this->env;
                }
            } else {
                $this->envDomainSuffix = '';
            }

            $this->url = 'https://ecom' . $this->envDomainSuffix . '.iutecredit.' . $this->envDomainName;

            // Method with all the options fields
            $this->init_form_fields();

            // This action hook saves the settings, calling a method from parent class
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

            // We need custom JavaScript to obtain a token
            add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

            if ($this->enableWebhook) {
                add_action('woocommerce_api_wc_gateway_iutepay_confirmation', array($this, 'user_confirmation_webhook'));
                add_action('woocommerce_api_wc_gateway_iutepay_cancel', array($this, 'user_cancel_webhook'));
            }

            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_custom_checkout_field'));
            add_filter('woocommerce_checkout_fields', array($this, 'change_custom_fields_priority'), 9999);

        }

        public function change_custom_fields_priority($fields)
        {
            /*$fields["billing"]["billing_patronymic"]["type"] = 'text';
            $fields["billing"]["billing_patronymic"]["required"] = $this->isPatronymicRequired;
            $fields["billing"]["billing_patronymic"]["label"] = __('Patronymic', 'iutepay');
            $fields["billing"]["billing_patronymic"]["priority"] = 25;*/
            return $fields;
        }

        public function save_custom_checkout_field($order_id)
        {
            /* if (!empty($_POST['billing_patronymic'])) {
                 update_post_meta($order_id, 'billing_patronymic', wc_clean($_POST['billing_patronymic']));
             }*/
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'general_settings_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('General', 'iutepay'),
                    'description' => __('General settings', 'iutepay'),
                    'class' => ''
                ),
                'enabled' => array(
                    'title' => __('Enable/Disable', 'iutepay'),
                    'label' => __('Enable iute checkout', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'yes'
                ),
                'enableWebhook' => array(
                    'title' => __('Enable webhook notifications', 'iutepay'),
                    'label' => __('Enable iute plugin to update eshop order status, depending on loan application decision.', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => __('Allow iute plugin to update eshop order status, depending on loan application decision (approval or rejection).', 'iutepay'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'testmode' => array(
                    'title' => 'Test mode',
                    'label' => 'Enable Test Mode',
                    'type' => 'checkbox',
                    'description' => 'Plugin will work against iute sandbox environment. NB! Enter your TEST API keys!',
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                'test_iute_api_key' => array(
                    'title' => 'Test API Key',
                    'type' => 'text'
                ),
                'test_iute_admin_key' => array(
                    'title' => 'Test Admin API Key',
                    'type' => 'password',
                ),
                'iute_api_key' => array(
                    'title' => 'Live API Key',
                    'type' => 'text',
                ),
                'iute_admin_key' => array(
                    'title' => 'Live Admin API Key',
                    'type' => 'password'
                ),
                'country' => array(
                    'title' => 'Country',
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => 'en',
                    'options' => array(
                        'en' => 'Albania (EN)',
                        'al' => 'Albania',
                        'bs' => 'Bosnia',
                        'bg' => 'Bulgaria',
                        'mk' => 'North Macedonia',
                        'md' => 'Moldova',
                    ),
                ),
                'checkout_settings_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('Checkout', 'iutepay'),
                    'description' => __('Checkout page settings', 'iutepay'),
                    'class' => ''
                ),
                'description' => array(
                    'title' => 'Description', 'woocommerce',
                    'type' => 'textarea',
                    'description' => __('Buy now, pay later with iute.', 'iutepay'),
                    'default' => __('Buy now, pay later with iute.', 'iutepay'),
                    'desc_tip' => true,
                ),
                'iutepay_antifraud_enabled' => array(
                    'title' => __('Antifraud', 'iutepay'),
                    'label' => __('Enable iute antifraud on checkout', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'iutepay_thank_you_enabled' => array(
                    'title' => __('Thank You modal', 'iutepay'),
                    'label' => __('Show thank you modal on Order Received page, instead of into checkout modal', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'email_settings_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('Email Notifications', 'iutepay'),
                    'description' => __('Customer notification settings', 'iutepay'),
                    'class' => ''
                ),
                'emailNotificationAboutNewLoanApplication' => array(
                    'title' => __('Email about new loan application', 'iutepay'),
                    'label' => __('Send email to customer about new order.', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'emailNotificationAboutLoanApplicationStatusChange' => array(
                    'title' => __('Email about loan application status change', 'iutepay'),
                    'label' => __('Send email to customer about loan application status change.', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'order_email_settings_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('Order emails', 'iutepay'),
                    'description' => __('Email notifications about orders made with iute payment method. Read more on the ', 'iutepay').' <a href="/wp-admin/admin.php?page=wc-settings&tab=email" target="_blank">'.__('emails page', 'iutepay').'</a>',
                    'class' => ''
                ),
                'emailNotificationNewOrder' => array(
                    'title' => __('New Order', 'iutepay'),
                    'label' => __('When loan application submitted to iute and new order is created.', 'iutepay').' <a href="/wp-admin/admin.php?page=wc-settings&tab=email&section=wc_email_new_order">'.__('Email template', 'iutepay').'</a>',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'emailNotificationCancelledOrder' => array(
                    'title' => __('Cancelled Order', 'iutepay'),
                    'label' => __('When submitted loan application rejected.', 'iutepay').' <a href="/wp-admin/admin.php?page=wc-settings&tab=email&section=wc_email_cancelled_order">'.__('Email template', 'iutepay').'</a>',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'emailNotificationOnHoldOrder' => array(
                    'title' => __('Order on-hold', 'iutepay'),
                    'label' => __('When submitted loan application amount not the same as order total amount.', 'iutepay').' <a href="/wp-admin/admin.php?page=wc-settings&tab=email&section=wc_email_customer_on_hold_order">'.__('Email template', 'iutepay').'</a>',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'emailNotificationProcessingOrder' => array(
                    'title' => __('Processing or Completed order', 'iutepay'),
                    'label' => __('When submitted loan application approved.', 'iutepay').' <a href="/wp-admin/admin.php?page=wc-settings&tab=email&section=wc_email_customer_processing_order">'.__('Email template', 'iutepay').'</a>, '.' <a href="/wp-admin/admin.php?page=wc-settings&tab=email&section=wc_email_customer_completed_order">'.__('Email template', 'iutepay').'</a>',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'promo_modal_settings_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('Promo modal', 'iutepay'),
                    'description' => __('Promo modal settings', 'iutepay'),
                    'class' => ''
                ),
                'fastCheckoutFormPromoModal' => array(
                    'title' => __('Fast checkout from Promo modal', 'iutepay'),
                    'label' => __('Show Start checkout button in the Promo modal', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'yes'
                ),
                'category_pages_settings_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('Category page', 'iutepay'),
                    'description' => __('Category page settings', 'iutepay'),
                    'class' => ''
                ),
                'showPromoOnCategoryPage' => array(
                    'title' => __('Show promo widget on products category pages', 'iutepay'),
                    'label' => __('Show promo widget on products category pages', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => __('Show promo widget on products category pages', 'iutepay'),
                    'default' => 'yes',
                    'desc_tip' => true,
                ),
                // class
                'categoryPage_promo_class' => array(
                    'title' => __('Start text', 'iutepay'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => 'iute-as-low-as',
                    'options' => array(
                        'iute-as-low-as' => 'As low as (default)',
                        'iute-from' => 'From',
                    ),
                ),
                // data-logo-color
                'categoryPage_promo_logoColor' => array(
                    'title' => __('Iute logo color', 'iutepay'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => '',
                    'options' => array(
                        '' => 'Colored (default)',
                        'white' => 'White',
                        'black' => 'Black',
                    ),
                ),
                // data-logo-type
                'categoryPage_promo_logoType' => array(
                    'title' => __('Iute logo type', 'iutepay'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => '',
                    'options' => array(
                        '' => 'Image (default)',
                        'symbol' => 'Symbol',
                        'text' => 'Text',
                    ),
                ),
                // data-learnmore-show
                'categoryPage_promo_showReadMore' => array(
                    'title' => __('Read more', 'iutepay'),
                    'label' => __('Show read more link otherwise whole promo message will be clickable.', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'yes'
                ),
                'product_page_settings_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('Product page', 'iutepay'),
                    'description' => __('Product page settings', 'iutepay'),
                    'class' => ''
                ),
                // class
                'productPage_promo_class' => array(
                    'title' => __('Start text', 'iutepay'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => 'iute-as-low-as',
                    'options' => array(
                        'iute-as-low-as' => 'As low as (default)',
                        'iute-from' => 'From',
                    ),
                ),
                // data-logo-color
                'productPage_promo_logoColor' => array(
                    'title' => __('Iute logo color', 'iutepay'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => '',
                    'options' => array(
                        '' => 'Colored (default)',
                        'white' => 'White',
                        'black' => 'Black',
                    ),
                ),
                // data-logo-type
                'productPage_promo_logoType' => array(
                    'title' => __('Iute logo type', 'iutepay'),
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' => '',
                    'options' => array(
                        '' => 'Image (default)',
                        'symbol' => 'Symbol',
                        'text' => 'Text',
                    ),
                ),
                // data-learnmore-show
                'productPage_promo_showReadMore' => array(
                    'title' => __('Read more', 'iutepay'),
                    'label' => __('Show read more link otherwise whole promo message will be clickable.', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'yes'
                ),

                'product_page_fast_checkout_title' => array(
                    'type' => 'title',
                    'title' => '<hr/>' . __('Fast checkout button', 'iutepay'),
                    'description' => __('Fast checkout button on Product page', 'iutepay'),
                    'class' => ''
                ),

                'productPage_fast_checkout_button_visible' => array(
                    'title' => __('Visible', 'iutepay'),
                    'label' => __('Show fast checkout on product page', 'iutepay'),
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),

                'productPage_fast_checkout_button_html' => array(
                    'title' => 'HTML layout', 'woocommerce',
                    'type' => 'textarea',
                    'description' => __('HTML layout of the checkout button.', 'iutepay'),
                    'desc_tip' => true,
                    'default' => <<<HTML
<p></p>
<div class="iute-add-to-cart-button iute-add-to-cart-button-woo button">
    <button class="iute-symbol-logo-primary">&nbsp;</button> Start checkout with iute
</div>
HTML,

                ),
                'productPage_fast_checkout_button_selector' => array(
                    'title' => 'click jQuery selector',
                    'type' => 'text',
                    'description' => __('jQuery selector for onclick handler', 'iutepay'),
                    'desc_tip' => true,
                    'default' => 'div.iute-add-to-cart-button'
                ),
            );

            if(strpos($_SERVER['SERVER_NAME'], 'localhost') !== false ||
                strpos($_SERVER['SERVER_NAME'], 'woo-ica-stage.iute.eu') !== false
            ) {

                $new_fields = array('env' => array(
                    'title' => 'Environment',
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'default' =>  $this->get_option('testmode') == 'yes' ? 'stage' : 'prod',
                    'description' => 'This field visible only on localhost and woo-ica-stage.iute.eu hosts<br/>Current API host: <b>'.$this->url.'</b>',
                    'options' => array(
                        'prod' => 'Prod',
                        'stage' => 'Stage',
                        'dev' => 'Dev',
                    ),
                )
                );

                $this->form_fields = array_merge($new_fields, $this->form_fields);
            }
        }

        public function get_default_country_currency()
        {
            if ($this->currency == 'lek') {
                return 'ALL';
            }
            return strtoupper($this->currency);
        }

        public function get_currency_modifier()
        {
            $defaultCountryCurrency = $this->get_default_country_currency();
            $currentCurrency = get_woocommerce_currency();
            $defaultWooCommerceCurrency = get_option('woocommerce_currency');

            // EUR, ALL, BGN, MKD, BAM, MDL
            if ($defaultCountryCurrency != $currentCurrency) {
                if (class_exists('BeRocket_CE', false)) {
                    // BeRocket_CE plugin support
                    $class = new ReflectionClass('BeRocket_CE');
                    $inst = $class->getStaticPropertyValue('instance');
                    $options = $inst->get_option();
                    return floatval($options['currency'][$defaultCountryCurrency]) * floatval($options['multiplier']);
                }
            }
            return 1;
        }

        public function get_default_variation($product, $context = 'view')
        {
            $default_attributes = $product->get_default_attributes($context);
            $vars = $product->get_available_variations();
            $default_variation = NULL;
            foreach ($vars as $variation) {
                $found = true;
                // Loop through variation attributes
                foreach ($variation['attributes'] as $key => $value) {
                    $taxonomy = str_replace('attribute_', '', $key);
                    // Searching for a matching variation as default
                    if (isset($default_attributes[$taxonomy]) && $default_attributes[$taxonomy] != $value) {
                        $found = false;
                        break;
                    }
                }
                if ($found) {
                    $default_variation = $variation;
                    break;
                } // If not we continue
                else {
                    continue;
                }
            }
            if ($default_variation) {
                return $product->is_on_sale() ? $default_variation['display_regular_price'] : $default_variation['display_price'];
            } else {
                return $product->is_on_sale() ? $product->get_variation_sale_price('max', true) : $product->get_variation_regular_price('max', true);
            }
        }

        public function convert_to_country_currency($product = NULL, $price_amount = NULL)
        {
            $defaultCountryCurrency = $this->get_default_country_currency();
            $currentCurrency = get_woocommerce_currency();
            $defaultWooCommerceCurrency = get_option('woocommerce_currency');

            // EUR, ALL, BGN, MKD, BAM, MDL

            $price = $price_amount;
            $price_edit = NULL;
            if ($product) {
                if ($product->get_type() == 'variable') {
                    $price = $this->get_default_variation($product);
                    $price_edit = $this->get_default_variation($product, 'edit');
                } else {
                    $price = $product->get_sale_price() ? $product->get_sale_price() : $product->get_price();
                    $price_edit = $product->get_sale_price('edit') ? $product->get_sale_price('edit') : $product->get_price('edit');
                }
            }

            $resultPrice = $price;
            if ($defaultCountryCurrency != $currentCurrency) {

                $currency_modifier_default_country = $this->get_currency_modifier();

                if ($defaultWooCommerceCurrency == $currentCurrency) {
                    // EUR -> ALL
                    $resultPrice = floatval($price) * floatval($currency_modifier_default_country);
                } else if ($price_edit) {
                    // LEV -> EUR -> ALL
                    // we expecting here product has price in EUR in catalog
                    $resultPrice = floatval($price_edit) * floatval($currency_modifier_default_country);
                } else {
                    $resultPrice = -1;
                }


            }

            if (!$resultPrice) {
                return $resultPrice;
            }

            return number_format($resultPrice, 2, ".", "");
        }

        /**
         * @param $total - shopping cart total
         * @param $orderId - real order id or temporary order id
         * @param $signatureTimestampMillis - signature timestamp with millis
         * @param $itemPriceQuantityArray - shopping cart items array of array where price is first element and quantity is second, ex: [ [1000, 1], [2000, 3] ]
         * @param $adminKey - admin key
         * @return string - hex representation if signature
         */
        public function signature($total, $orderId, $signatureTimestampMillis, $itemPriceQuantityArray, $adminKey): array
        {
            $items = array();
            foreach ($itemPriceQuantityArray as $item) {
                array_push($items, array("a" => strval($item[0]), 'b' => strval($item[1])));
            }
            $payloadObject = array(
                "a" => strval($total),
                "b" => strval($orderId),
                "c" => strval($signatureTimestampMillis),
                "d" => $items
            );

            $payloadString = json_encode($payloadObject);
            $binarySignature = hash_hmac("sha256", $payloadString, $adminKey, true);
            $base64Signature = base64_encode($binarySignature);

            return array(
                "payload" => base64_encode($payloadString),
                "rawSignature" => $binarySignature,
                "signature" => $base64Signature
            );
        }

        /**
         * Defines the way, how payment method looks like on checkout page
         */
        public function payment_fields()
        {
            if ($this->description) {
                // display the description with <p> tags etc.
                echo wpautop(wp_kses_post($this->description));
            }

            $currentTimestamp = $this->get_current_timestamp();

            $orderId = "ESHOP_ORDER_" . $currentTimestamp;

            $cart = WC()->cart;

            echo '<input id="iutepay_error_message" name="iutepay_error_message" type="hidden">';
            echo '<input id="iutepay_checkout_session_id" name="iutepay_checkout_session_id" type="hidden" value="X!">';
            echo '<input id="iutepay_checkout_cart_total" name="iutepay_checkout_cart_total" type="hidden" value="' . $cart->get_total("edit") . '">';
            echo '<input id="iutepay_checkout_cart_discount" name="iutepay_checkout_cart_discount" type="hidden" value="' . $cart->get_discount_total() . '">';
            echo '<input id="iutepay_checkout_order_id" name="iutepay_checkout_order_id" type="hidden" value="' . $orderId . '">';
            if ($this->antifraudEnabled) {
                $items = array();
                foreach ($cart->get_cart() as $cart_item_key => $values) {
                    $product = $values['data'];
                    array_push($items, array(
                        $this->convert_to_country_currency($product),
                        $values['quantity'],
                    ));
                }

                $signature = $this->signature($cart->get_total("edit"), $orderId, $currentTimestamp, $items, $this->iute_admin_key);

                echo '<input id="iutepay_checkout_session_signature" name="iutepay_checkout_session_signature" type="hidden" value="' . $signature['signature'] . '">';
                if ($this->testmode) {
                    echo '<input id="iutepay_checkout_session_signature_payload_base64" name="iutepay_checkout_session_signature_payload_base64" type="hidden" value="' . $signature['payload'] . '">';
                }
                echo '<input id="iutepay_checkout_session_signature_timestamp" name="iutepay_checkout_session_signature_timestamp" type="hidden" value="' . $currentTimestamp . '">';
            }
            //TODO: maybe here is the place for some IuteCredit legal links
        }

        /*
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts()
        {
            if (Constants::is_defined("IUTEPAY_PAYMENT_SCRIPTS_ADDED")) {
                return;
            }
            Constants::set_constant("IUTEPAY_PAYMENT_SCRIPTS_ADDED", true);
            add_action('wp_footer', array($this, 'checkout_js'));
        }

        public function get_current_timestamp()
        {
            return date_create()->format('Uv');
        }

        public function checkout_js()
        {
            wp_enqueue_style('iutepay', $this->url . "/iutepay.css", array(), $this->version . "_" . round(time() / 300) * 300);
            wp_enqueue_script('iutepay', $this->url . "/iutepay.js", array(), $this->version . "_" . round(time() / 300) * 300);

            $iutepay_checkout_js = '';
            if (is_checkout()) {

                $cart = WC()->cart;

                $items = array();
                foreach ($cart->get_cart() as $cart_item_key => $values) {
                    $product = $values['data'];

                    $item = new stdClass;
                    $item->displayName = $product->get_title();
                    $item->id = $product->get_id();
                    $item->sku = $product->get_sku();
                    $item->unitPrice = $this->convert_to_country_currency($product);
                    $item->qty = $values['quantity'];
                    if ($product->get_image_id()) {
                        $item->itemImageUrl = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail', false)[0];
                    }
                    $item->itemUrl = $product->get_permalink();

                    array_push($items, $item);
                }
                $iute_json_encoded_cart_items = wp_json_encode($items);
                $iute_blog_name = get_bloginfo('name');

                $antifraud_json_part = "";
                if ($this->antifraudEnabled) {
                    $antifraud_json_part = <<<JSON
                    "signature": jQuery('#iutepay_checkout_session_signature').val(),
                                    "signatureTimestamp": jQuery('#iutepay_checkout_session_signature_timestamp').val(),  
                    JSON;
                }

                $confirmation_json_part = "";
                if ($this->enableWebhook) {
                    $confirmationUrl = get_site_url() . "/?wc-api=wc_gateway_iutepay_confirmation";
                    $cancelUrl = get_site_url() . "/?wc-api=wc_gateway_iutepay_cancel";
                    $confirmation_json_part = <<<JSON
                    "userConfirmationUrl": "{$confirmationUrl}",
                                    "userCancelUrl": "{$cancelUrl}",
                                    "userConfirmationUrlAction": "POST",    
                    JSON;
                }

                $ty = $this->thankYouModalEnabled ? 'false' : 'true';

                $iutepay_checkout_js = <<<IUTEPAYJS1
                
                var checkout_form = jQuery('form.woocommerce-checkout');
                
                const scroll_to_notices = function() {
                    var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout' );
        
                    if ( ! scrollElement.length ) {
                        scrollElement = $( 'form.checkout' );
                    }
                    $.scroll_to_notices( scrollElement );
                };
                
                const submit_error = function( error_message ) {
                    $( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
                    checkout_form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' );
                    checkout_form.removeClass( 'processing' ).unblock();
                    checkout_form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).trigger( 'blur' );
                    scroll_to_notices();
                    //$( document.body ).trigger( 'checkout_error' , [ error_message ] );
                };

                
                var successCallback = function (data) {

                    // deactivate the tokenRequest function event
                    checkout_form.off('checkout_place_order', tokenRequest);

                    if (data.checkoutSessionId) {
                        jQuery('#iutepay_checkout_session_id').val(data.checkoutSessionId);
                    }

                    // submit the form now
                    jQuery('#confirm-order-flag').val('');
                    jQuery('#place_order').trigger('click');

                };

                var errorCallback = function (message) {
                    if (!message) {
                        return
                    }
                    console.log(message);
                    //TODO: replace alert with more gentle notice
                    //alert(message);
                    // send error messsage to woocomerce
                    jQuery('#iutepay_error_message').val(message);
                    
                    submit_error('<div class="woocommerce-error">' + message + '</div>');
                 
                };

                var tokenRequest = function () {

                    if (jQuery('#payment_method_iutepay:checked').length === 0) {
                        return;
                    }

                    jQuery('#iutepay_error_message').val("");

                    let shippingAddressPrefix = "#billing";
                    if (jQuery('#ship-to-different-address-checkbox:checked').length === 1) {
                        shippingAddressPrefix = '#shipping';
                    }
                    
                    var discount = jQuery('#iutepay_checkout_cart_discount').val() * {$this->get_currency_modifier()};
                    var total = jQuery('#iutepay_checkout_cart_total').val() * {$this->get_currency_modifier()};
                    
                    iute.setThankYouScreenEnabled({$ty});             
                    
                    iute.checkout({
                            "merchant": {
                                {$confirmation_json_part}
                                "name": "{$iute_blog_name}"
                            },
                            {$antifraud_json_part}
                            "shipping": {
                                "name": {
                                    "first": jQuery(shippingAddressPrefix + '_first_name').val(),
                                    "last": jQuery(shippingAddressPrefix + '_last_name').val(),
                                },
                                "address": {
                                    "line1": jQuery(shippingAddressPrefix + '_address_1').val(),
                                    "line2": jQuery(shippingAddressPrefix + '_address_2').val(),
                                    "city": jQuery(shippingAddressPrefix + '_city').val(),
                                    "state": jQuery(shippingAddressPrefix + '_state').val(),
                                    "zipcode": jQuery(shippingAddressPrefix + '_postcode').val(),
                                    "country": iute.getCountryISO3(jQuery(shippingAddressPrefix + '_country').val())
                                },
                                "phoneNumber": jQuery('#billing_phone').val(),
                                "email": jQuery('#billing_email').val()
                            },
                            "billing": {
                                "name": {
                                    "first": jQuery('#billing_first_name').val(),
                                    "last": jQuery('#billing_last_name').val(),
                                    //"patronymic": jQuery('#billing_patronymic').val(),
                                },
                                "address": {
                                    "line1": jQuery('#billing_address_1').val(),
                                    "line2": jQuery('#billing_address_2').val(),
                                    "city": jQuery('#billing_city').val(),
                                    "state": jQuery('#billing_state').val(),
                                    "zipcode": jQuery('#billing_postcode').val(),
                                    "country": iute.getCountryISO3(jQuery('#billing_country').val())
                                },
                                "phoneNumber": jQuery('#billing_phone').val(),
                                "email": jQuery('#billing_email').val()
                            },
                            "items": {$iute_json_encoded_cart_items},
                            "discounts": {
                                "COMMON_DISCOUNT": {
                                    "discount_amount": jQuery('#iutepay_checkout_cart_discount').val(),
                                    "discount_display_name": "WC discount"
                                },
                            },
                            "metadata": {
                                "mode": "modal"
                            },
                            "orderId": jQuery('#iutepay_checkout_order_id').val(),
                            "currency": "{$this->currency}",
                            "shippingAmount": {$this->convert_to_country_currency(NULL, $cart->get_shipping_total())},
                            "taxAmount": {$this->convert_to_country_currency(NULL, $cart->get_fee_tax())},
                            "subtotal": {$this->convert_to_country_currency(NULL, $cart->get_subtotal())},
                            "discount": discount,
                            "total":  total
                        },
                        {
                            onSuccess: function (result) {
                                console.log("Success", JSON.stringify(result));
                                successCallback(result);
                            },
                            onFailure: function (result) {
                                console.log("Failure", JSON.stringify(result));
                                errorCallback(result.message);
                            }
                        })
                    return false;

                };
                
                var externalCheckoutErrorHandlers = [];

                var iuteValidateCheckoutFormBeforeSubmitOrderHandler = function (a, b, c) {
                    console.log("checkout_error");
                    let hasOnlyCustomNotice = true;
                    jQuery('.woocommerce-error li').each(function() {
                        var error_text = $(this).text();
                        if (error_text.indexOf('custom_notice') > -1) {
                            jQuery(this).css('display', 'none');
                        } else {
                            hasOnlyCustomNotice = false;
                        }
                    });
                    
                     var handlers = jQuery._data(jQuery(document.body).get(0), "events").checkout_error;
                     if (externalCheckoutErrorHandlers.length + 1 > handlers.length) {
                        // restore external handlers
                         for(let i = 0; i < externalCheckoutErrorHandlers.length; i++) {
                            jQuery(document.body).on('checkout_error', externalCheckoutErrorHandlers[i]);
                         }
                     }
                                         
                    if (hasOnlyCustomNotice) {
                        // do not trigger checkout_error to other handlers
                        jQuery('.woocommerce-error').css('display', 'none');
                        tokenRequest();
                    } else {
                        // trigger checkout_error to other handlers from externalCheckoutErrorHandlers
                        jQuery(document.body).trigger('checkout_error');
                    }
                };
                
                checkout_form.on('checkout_place_order', function () {
                    jQuery('#iutepay_error_message').val("");
                    if ($('#confirm-order-flag').length == 0) {
                        checkout_form.append('<input type="hidden" id="confirm-order-flag" name="confirm-order-flag" value="1">');
                        
                        // we need disable any checkout_error handlers except iuteValidateCheckoutFormBeforeSubmitOrderHandler
                        var handlers = jQuery._data(jQuery(document.body).get(0), "events").checkout_error;
                        for(let i = 0; i < handlers.length; i++) {
                            if (handlers[i].handler != iuteValidateCheckoutFormBeforeSubmitOrderHandler) {
                                externalCheckoutErrorHandlers.push(handlers[i].handler);
                                jQuery(document.body).off('checkout_error');
                            }   
                        }
                        if (externalCheckoutErrorHandlers.length > 0) { 
                            jQuery(document.body).on('checkout_error', iuteValidateCheckoutFormBeforeSubmitOrderHandler);
                        }
                    }
                    return true;
                });
                
                jQuery(document.body).on('checkout_error', iuteValidateCheckoutFormBeforeSubmitOrderHandler);
                
                IUTEPAYJS1;

            }

            $iutepay_fastcheckout_js = "";

            if ($this->fastCheckoutFormPromoModal) {
                $checkout_url = wc_get_checkout_url();
                $iutepay_fastcheckout_js = <<<IUTEPAYJS3
        iute.onFastCheckout(function(data) {
            var addCartData = {
                action: 'ql_woocommerce_ajax_add_to_cart',
                product_id: data.product.id,
                product_sku: data.product.sku,
                quantity: 1,
            };
            if (data.product.variantId) {
                addCartData.variation_id = data.product.variantId
            }
            jQuery(function ($) {
                $.ajax({
                    type: 'post',
                    url: wc_add_to_cart_params.wc_ajax_url.toString().replace( '%%endpoint%%', 'add_to_cart' ),
                    data: addCartData,
                    success: function (response) { 
                         console.log('added to cart ' + data.product.id + ' response : ' + response);
                         window.location = '{$checkout_url}';
                    }, 
                });
            }); 
            
        });
IUTEPAYJS3;
            }


            $thank_you_modal_js = '';
            if (is_order_received_page() && $this->thankYouModalEnabled) {
                // check is payment method was iute
                global $wp;
                $order_id = absint($wp->query_vars['order-received']);
                $order = wc_get_order($order_id);
                if ($order->get_payment_method() == 'iutepay') {
                    $thank_you_modal_js = <<<IUTEPAYJS
iute.openThankYouModal();
IUTEPAYJS;
                }

            }

            $iutepay_script_js = <<<IUTEPAYJS2

            var locale = '{$this->locale}';
            var lang = document.getElementsByTagName('html')[0].getAttribute('lang');
            if (lang) {
                if (lang.indexOf('-') > 0) {
                    locale = lang.split('-')[0]
                }
                if (lang.length == 2) {
                    locale = lang.toLowerCase();
                }
            }

            var configuration = function() {
                iute.configure('{$this->iute_api_key}', locale, {
                pluginVersion: '{$this->version}',
                platformName: 'woocommerce'
                });
                {$iutepay_fastcheckout_js}
                 jQuery("form[name='checkout']").on("submit", function (e) {
                    if (jQuery('#payment_method_iutepay:checked').length === 0) {
                        return;
                    }
                    if (!iute.checkoutStarted) {
                        e.preventDefault();
                    }
                 });              
            }

            if (window['iute']) {
                configuration();
            }
            jQuery(function ($) {
                configuration();
                {$iutepay_checkout_js}
                {$thank_you_modal_js}
            });
            IUTEPAYJS2;

            wp_add_inline_script('iutepay', $iutepay_script_js);
        }

        /*
          * Fields validation. In our case can be used to validate phone number, for example.
         */
        public function validate_fields()
        {
            if (!empty($_POST['iutepay_error_message'])) {
                wc_add_notice($_POST['iutepay_error_message'], 'error');
                return false;
            }

            return true;
        }

        /*
         * We're processing the payments here
         */
        public function process_payment($order_id)
        {

            $order = wc_get_order($order_id);

            $ecom_client = new Iute_Ecom_Rest_Client();

            $ecom_client->setOrderId($_POST['iutepay_checkout_session_id'], $order_id, $order->get_order_number());

            $this->email_notification_new_order($order_id);
            $this->woocommerce_email_notification($order_id, 'WC_Email_New_Order');

            // Redirect to the thank you page
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );

        }

        public function get_public_key()
        {
            $public_key_ttl_seconds = 3600;// 1 hour ttl

            $pub_key_option_name = "iute_notification_public_key";
            $pub_key_ts_option_name = "iute_notification_public_key_timestamp";
            $public_key = $this->get_option($pub_key_option_name);
            $public_key_timestamp = $this->get_option($pub_key_ts_option_name);

            // if  we have public key and timestamp in options and it not expired yet
            if ($public_key && $public_key_timestamp && intval($public_key_timestamp) + $public_key_ttl_seconds > time()) {
                error_log("Return public key from cache: " . $public_key);
                return $public_key;
            }

            $url = $this->url . "/public-key.pem";
            error_log("trying to download public key from: " . $url);
            $public_key = file_get_contents($url);
            error_log("Downloaded public key: " . $public_key);
            $this->update_option($pub_key_option_name, $public_key);
            $this->update_option($pub_key_ts_option_name, time());

            $public_key = $this->get_option($pub_key_option_name);
            error_log("Got public key from cache again: " . $public_key);
            return $public_key;
        }

        public function get_timestamp_header($headers)
        {
            $h = $headers['x-iute-timestamp'];
            if (!$h) {
                return $headers['X-Iute-Timestamp'];
            }
            return $h;
        }

        public function get_signature_header($headers)
        {
            $h = $headers['x-iute-signature'];
            if (!$h) {
                return $headers['X-Iute-Signature'];
            }
            return $h;
        }

        public function verify_webhoook_request($data)
        {
            $headers = getallheaders();

            $timestamp = $this->get_timestamp_header($headers);
            if (!$timestamp) {
                error_log("Iute Confirmation Request Failure. Wrong timestamp. " . $data . ". " . $timestamp);
                wp_die('Iute Confirmation Request Failure. Wrong timestamp', 'Iute Webhook', array('response' => 409));
            }

            $signature = $this->get_signature_header($headers);
            if (!$signature) {
                error_log("Iute Confirmation Request Failure. Wrong signature. " . $data . ". " . $signature);
                wp_die('Iute Confirmation Request Failure. Wrong signature', 'Iute Webhook', array('response' => 409));
            }
            $signature = base64_decode($signature);

            $public_Key = $this->get_public_key();
            if (!$public_Key) {
                error_log("Iute Confirmation Request Failure. Wrong public key. " . $data . ". " . $public_Key);
                wp_die('Iute Confirmation Request Failure. Wrong public key', 'Iute Webhook', array('response' => 409));
            }

            $ok = openssl_verify($data . "" . $timestamp, $signature, $public_Key, "sha256WithRSAEncryption");
            if ($ok != 1) {
                error_log("Iute Confirmation Request Failure. Signature verification failed. " . $data . ". " . $ok);
                wp_die('Iute Confirmation Request Failure. Signature verification failed.' . $ok, 'Iute Webhook', array('response' => 409));
            }

        }

        /*
         * webhook handler to get update from ecom
         */
        public function user_confirmation_webhook()
        {
            $data = file_get_contents('php://input');

            error_log("user_confirmation_webhook: " . $data);

            if ($data) {
                $this->verify_webhoook_request($data);

                $posted = json_decode(wp_unslash($data), true);
                $order = wc_get_order($posted['orderId']);
                if (!$order) {
                    error_log("Iute Confirmation Request Failure. Order not found. " . $data . ". " . $posted['orderId']);
                    wp_die('Iute Confirmation Request Failure. Order not found', 'Iute Webhook', array('response' => 409));
                }

                $total_order_amount = (float)$order->get_total();
                $total_loan_amount = (float)$posted['loanAmount'];
                if ($total_order_amount == $total_loan_amount) {
                    error_log("user_confirmation_webhook. payment_complete. " . $posted['orderId']);
                    $order->payment_complete();
                    $this->email_notification_order_status_changed($posted['orderId'], __('Payment Completed', 'iutepay'));
                    if ($order->needs_processing()) {
                        $this->woocommerce_email_notification($posted['orderId'], 'WC_Email_Customer_Processing_Order');
                    } else {
                        $this->woocommerce_email_notification($posted['orderId'], 'WC_Email_Customer_Completed_Order');
                    }
                } else {
                    $order->update_status('on-hold', "Loan amount " . $total_loan_amount . " and order total amount " . $total_order_amount . " is different");
                    $this->email_notification_order_status_changed($posted['orderId'], __('On-hold', 'woocommerce'));
                    $this->woocommerce_email_notification($posted['orderId'], 'WC_Email_Customer_On_Hold_Order');
                }

            }
        }

        public function user_cancel_webhook()
        {
            $data = file_get_contents('php://input');

            error_log("user_cancel_webhook: " . $data);

            if ($data) {
                $this->verify_webhoook_request($data);

                $posted = json_decode(wp_unslash($data), true);
                $order = wc_get_order($posted['orderId']);
                if (!$order) {
                    error_log("Iute Confirmation Request Failure. Order not found. " . $data . ". " . $posted['orderId']);
                    wp_die('Iute Confirmation Request Failure. Order not found', 'Iute Webhook', array('response' => 409));
                }

                $order->update_status('cancelled', $posted['description']);

                $this->email_notification_order_status_changed($posted['orderId'], 'Cancelled', $posted['description']);
                $this->woocommerce_email_notification($posted['orderId'], 'WC_Email_Cancelled_Order');
            }
        }

        public function woocommerce_email_notification($order_id, $email_id) {
            if ($email_id == 'WC_Email_New_Order' && !$this->emailNotificationNewOrder) {
                return;
            }

            if ($email_id == 'WC_Email_Cancelled_Order' && !$this->emailNotificationCancelledOrder) {
                return;
            }

            if ($email_id == 'WC_Email_Customer_On_Hold_Order' && !$this->emailNotificationOnHoldOrder) {
                return;
            }

            if ($email_id == 'WC_Email_Customer_Processing_Order' && !$this->emailNotificationProcessingOrder) {
                return;
            }

            global $woocommerce;
            $mailer = $woocommerce->mailer();
            $mailer->emails[$email_id]->trigger($order_id);
        }

        public function email_notification_new_order($order_id)
        {
            if (!$this->emailNotificationAllowed) {
                return;
            }
            if (!$this->emailNotificationAboutNewLoanApplication) {
                return;
            }

            global $woocommerce;
            $mailer = $woocommerce->mailer();

            $order = new WC_Order($order_id);
            $item_names = "";
            $items = $order->get_items();
            foreach ($items as $item) {
                $item_names = $item_names . $item->get_name() . " ";
            }

            $message_body = sprintf(
                __('You have new order from Iute. Item: %s Price: %s %s. Client info: %s %s with phone %s.', 'iutepay'),
                $item_names,
                $order->get_total(),
                $order->get_currency(),
                $order->get_billing_first_name(),
                $order->get_billing_last_name(),
                $order->get_billing_phone()
            );

            $head = sprintf(__('New order %s received', 'iutepay'), $order->get_order_number());

            // Message head and message body.
            $message = $mailer->wrap_message($head, $message_body);
            // Client email, email subject and message.
            $mailer->send($order->get_billing_email(), $head, $message);
        }

        public function email_notification_order_status_changed($order_id, $status, $description = "")
        {
            if (!$this->emailNotificationAllowed) {
                return;
            }
            if (!$this->emailNotificationAboutLoanApplicationStatusChange) {
                return;
            }

            global $woocommerce;
            $order = new WC_Order($order_id);

            $mailer = $woocommerce->mailer();

            $message_body = sprintf(
                __('Order status: %s %s', 'iutepay'),
                $status,
                $description
            );

            $head = sprintf(__('Order %s status changed', 'iutepay'), $order->get_order_number());

            // Message head and message body.
            $message = $mailer->wrap_message($head, $message_body);
            // Client email, email subject and message.
            $mailer->send($order->get_billing_email(), $head, $message);
        }

    }

    $gw = new WC_Iutepay_Gateway();
    $gw->payment_scripts();
}

add_action('rest_api_init', 'iute_register_routes');

function iute_register_routes()
{
    // http://localhost:8000/?rest_route=/iute/v1/product/11/variations
    register_rest_route('iute/v1', 'product/(?P<id>\d+)/variations', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iute_get_product_variations',
        'permission_callback' => '__return_true' // Allow any user to access
    ]);
}

function iute_get_product_variations($request)
{
    $id = $request->get_param('id');
    $product = wc_get_product($id);
    return iute_get_product_variations_by_product($product);
}

function iute_get_product_variations_by_product($product)
{
    if ($product instanceof WC_Product_Variable) {
        $result = array();
        $default_attributes = $product->get_default_attributes('view');
        $vars = $product->get_available_variations();
        foreach ($vars as $variation) {
            $variation_product = new WC_Product_Variation($variation['variation_id']);

            $result["name"] = $variation_product->get_title();

            if ($variation_product->get_image_id()) {
                $result["img"] = wp_get_attachment_image_src($variation_product->get_image_id(), 'thumbnail', false)[0];
            } else if ($product->get_image_id())   {
                $result["img"] = wp_get_attachment_image_src($product->get_image_id(), 'thumbnail', false)[0];
            }

            $attrs_result = array();
            // Loop through variation attributes
            foreach ($variation['attributes'] as $key => $value) {
                $taxonomy = str_replace('attribute_', '', $key);
                // Searching for a matching variation as default
                $attr_props = array();
                if ($value) {
                    $attr_props = array_merge($attr_props, array("v" => $value));
                }
                if (isset($default_attributes[$taxonomy]) && $default_attributes[$taxonomy] == $value) {
                    // if default
                    $attr_props = array_merge($attr_props, array("d" => 1));
                }

                $attrs_result = array_merge($attrs_result, array($taxonomy => count($attr_props) > 0 ? $attr_props : new stdClass()));

                if (count($attr_props) == 0 && !$result[$key]) {
                    // loading all possible values for attribute with name eq $taxonomy

                    $result[$key] = $product->get_attributes()[$taxonomy]["options"];
                }
            }
            $result[$variation['variation_id']] = $attrs_result;
        }
        return $result;
    }
    return NULL;
}

function iute_get_product_variations_by_product_encoded($product)
{
    $result = iute_get_product_variations_by_product($product);
    if ($result == NULL) {
        return NULL;
    }
    return base64_encode(json_encode($result));
}


if (is_admin()) {

    /**
     * Alert class in Admin UI
     *
     * @param string $message Text to be displayed in alert
     * @param string $type Type of alert (notice-error, notice-success, notice-warning, notice-info)
     */
    class Iute_Admin_Alert
    {
        private $_type;
        private $_message;

        public function __construct($type, $message, $after_redirect = false)
        {
            if ($after_redirect)
                update_option('iute_notice', sprintf('<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr($type), esc_html('Iute: ' . $message)));
            else {
                $this->_type = $type;
                $this->_message = $message;
                add_action('admin_notices', array($this, 'render'));
            }
        }

        public function render()
        {
            printf('<div class="notice %1$s is-dismissible"><p>%2$s</p></div>', esc_attr($this->_type), esc_html('Iute: ' . $this->_message));
        }
    }

    add_action('admin_notices', function () {
        $message = get_option('iute_notice');
        if ($message) {
            printf($message);
            delete_option('iute_notice');
        }
    });

    /**
     * Iute manager class in Admin UI
     *
     */
    class Iute_Ecom_Manager
    {
        private $_url;
        private $_admin_key;

        public function __construct()
        {
            $payment_gateways = WC()->payment_gateways->payment_gateways();
            if (isset($payment_gateways['iutepay'])) {
                $iutepay = $payment_gateways['iutepay'];
                $this->_url = $iutepay->url;
                $this->_admin_key = $iutepay->iute_admin_key;
            }
        }

        private function send_request($path, $method, $body, $success_message)
        {

            if (!$method) {
                $method = 'GET';
            }

            if (!$body) {
                $body = null;
            }

            if (!$success_message) {
                $success_message = null;
            }

            $args = array(
                'headers' => array(
                    'x-iute-admin-key' => $this->_admin_key,
                    'content-Type' => 'application/json',
                    'charset' => 'utf-8'),
                'timeout' => 5
            );
            if ($body) {
                $args['body'] = json_encode($body);
                $args['data_format'] = 'body';//https://wordpress.stackexchange.com/questions/237224/sending-json-string-through-wp-remote-post
                if ($method == 'POST') {
                    $response = wp_remote_post($this->_url . $path, $args);
                } elseif ($method == 'DELETE') {
                    $args['method'] = $method;
                    $response = wp_remote_request($this->_url . $path, $args);
                }
            } else
                $response = wp_remote_get($this->_url . $path, $args);
            if (isset($response->errors)) {
                $error_message = "";
                if (is_wp_error($response)) {
                    $error_message = $response->get_error_message();
                }

                new Iute_Admin_Alert('notice-error',
                    'Something is wrong. ' . $method . ' ' . $this->_url . $path . ' Error message:' . $error_message . ' ' . print_r($response->errors, true));
                return null;
            } else {
                $response_code = wp_remote_retrieve_response_code($response);
                if ($response_code >= 200 && $response_code < 300) {
                    $response = json_decode(wp_remote_retrieve_body($response), false);
                    if ($success_message)
                        new Iute_Admin_Alert('notice-success', $success_message, true);
                } else {
                    $error_message = "";
                    if (is_wp_error($response)) {
                        $error_message = $response->get_error_message();
                    }
                    new Iute_Admin_Alert('notice-error',
                        'Something is wrong. Req: ' . $method . ' ' . $this->_url . $path . ' Message:' . $error_message . ' ' . $response_code . ', ' . wp_remote_retrieve_response_message($response), true);
                    return null;
                }
            }
            return $response;
        }

        public function get_iute_products()
        {
            return $this->send_request('/api/v1/eshop/management/loan-product', 'GET');
        }

        public function get_iute_mappings()
        {
            $response = $this->send_request('/api/v1/eshop/management/product-mapping?size=500', 'GET');
            if ($response) {
                return $response->content;
            }
            return array();
        }

        public function submit_iute_mappings($body)
        {
            return $this->send_request('/api/v2/eshop/management/product-mapping?batch=true', 'POST',
                $body,
                __('Mappings were succesfully created.', 'iutepay'));
        }

        public function delete_iute_mappings($body)
        {
            return $this->send_request('/api/v1/eshop/management/product-mapping?batch=true', 'DELETE',
                $body,
                __('Mappings were succesfully deleted.', 'iutepay'));
        }

    }

    add_action('woocommerce_product_bulk_edit_start', 'iute_add_product_custom_field');

    function iute_add_product_custom_field()
    {
        global $iute_products;

        if ($iute_products) {
            ?>
            <div class="inline-edit-group wp-clearfix">
                <label class="alignleft">
                    <span class="title"><?php esc_html_e('Iute product', 'iutepay'); ?></span>
                    <select name="iutepay_product">
                        <option value="">— <?php esc_html_e('No Change', 'iutepay'); ?> —</option>
                        <option value="__remove"><?php esc_html_e('Remove all mappings for this product(s)', 'iutepay'); ?></option>
                        <?php
                        if (is_array($iute_products)) {
                            foreach ($iute_products as $iute_product) { ?>
                                <option value="<?php echo esc_attr('__remove' . $iute_product->id) ?>"><?php echo esc_html(__('Remove', 'iutepay') . ' ' . $iute_product->name) ?></option>
                                <?php
                            }
                            foreach ($iute_products as $iute_product) { ?>
                                <option value="<?php echo esc_attr($iute_product->id) ?>"><?php echo esc_html(__('Add', 'iutepay') . ' ' . $iute_product->name) ?></option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                </label>
            </div>
            <?php
        }
    }


    add_action('woocommerce_product_bulk_edit_save', 'iute_submit_mapping_from_bulk_edit');

    function iute_submit_mapping_from_bulk_edit($product)
    {
        global $iute_mappings_to_submit;
        $post_id = $product->get_id();
        $iute_product = sanitize_key($_REQUEST['iutepay_product']);
        if ($iute_product != '') {
            $iute_delete = str_starts_with($iute_product, '__remove');
            if ($iute_delete)
                $iute_product = str_replace('__remove', '', $iute_product);
            //iutepay product ID may be a number in case of LES, or a UUID in case of NC
            wc_get_logger()->log('info', 'iute_product: : ' . $iute_product);
            if (is_numeric($iute_product) || wp_is_uuid($iute_product) || $iute_product == '') {
                if (!$iute_mappings_to_submit)
                    $iute_mappings_to_submit = array();
                $mapping = array('sku' => (empty($product->sku) ? $product->id : $product->sku));
                if ($iute_product != '') {
                    $mapping['productId'] = $iute_product;
                }
                array_push($iute_mappings_to_submit, $mapping);
                wc_get_logger()->log('info', 'complete mapping: ' . $mapping);

            } else {
                wc_get_logger()->log('warn', 'iutepay_product is invalid: ' . $iute_product);
            }
            //submit mappings, if latest product is being processed
            if ($iute_mappings_to_submit && is_array($iute_mappings_to_submit) && count($iute_mappings_to_submit) == count($_REQUEST['post'])) {
                $ecom_manager = new Iute_Ecom_Manager();

                wc_get_logger()->log('info', 'mapppings to be submitted: ' . json_encode($iute_mappings_to_submit));
                if ($iute_delete) {
                    $response = $ecom_manager->delete_iute_mappings($iute_mappings_to_submit);
                } else {
                    $response = $ecom_manager->submit_iute_mappings($iute_mappings_to_submit);
                }
                $iute_mappings_to_submit = null; //we do not want submitted mappings to be processed next time this function is evaluated.
            }

        }

    }

    add_filter('manage_edit-product_columns', 'iute_show_column', 15);

    function iute_show_column($columns)
    {
        //add column
        if (!isset($columns['iute_product'])) {
            $columns['iute_product'] = esc_html(__('Iute mappings', 'iutepay'));
        }

        return $columns;
    }

    add_action('manage_product_posts_custom_column', 'iute_get_iute_product', 10, 2);

    function iute_get_iute_product($column, $postid)
    {
        global $iute_products;
        global $iute_mappings;

        if ($column == 'iute_product') {

            if (!is_array($iute_mappings)) {
                $iute_mappings = array();

                $ecom_manager = new Iute_Ecom_Manager();

                try {
                    //get list of products to display them in drop down list of bulk edit form
                    $iute_products = $ecom_manager->get_iute_products();

                    //prepares existing mappings to be displayed in eshop products list
                    foreach ($ecom_manager->get_iute_mappings() as $iute_mapping) {
                        $sku = $iute_mapping->sku;
                        if (!isset($iute_mappings[$sku]))
                            $iute_mappings[$sku] = array();
                        array_push($iute_mappings[$sku], $iute_mapping->productName);
                    }
                } catch (Throwable $e) {
                    error_log("Iute. mapping loading error " . $e->getMessage());
                } catch (Exception $e) {
                    error_log("Iute. mapping loading error " . $e->getMessage());
                }

            }

            $sku = get_post_meta($postid, '_sku', true);
            if (isset($iute_mappings[$sku])) {
                echo esc_html(implode(', ', $iute_mappings[$sku]));
            } elseif (isset($iute_mappings[$postid])) {
                echo esc_html(implode(', ', $iute_mappings[$postid]));
            }
        }
    }
}


