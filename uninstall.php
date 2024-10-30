<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}
 
delete_option('iute_notice');
delete_option('woocommerce_iutepay_settings');

delete_option('enabled');
delete_option('description');
delete_option('showPromoOnCategoryPage');
delete_option('enableWebhook');
delete_option('testmode');
delete_option('test_iute_api_key');
delete_option('test_iute_admin_key');
delete_option('iute_api_key');
delete_option('iute_admin_key');
delete_option('country');
delete_option('emailNotificationAboutNewLoanApplication');
delete_option('emailNotificationAboutLoanApplicationStatusChange');