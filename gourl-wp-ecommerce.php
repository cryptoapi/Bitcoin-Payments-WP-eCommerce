<?php
/*
Plugin Name: 		GoUrl WP eCommerce - Bitcoin Altcoin Payment Gateway Addon
Plugin URI: 		https://gourl.io/bitcoin-payments-wp-ecommerce.html
Description: 		Provides a <a href="https://gourl.io">GoUrl.io</a> Bitcoin/Altcoins Payment Gateway for <a href="https://wordpress.org/plugins/wp-e-commerce/">WP eCommerce 3.8.10+</a>. Support product prices in USD/EUR/etc and in Bitcoin/Altcoins directly; sends the amount straight to your business Bitcoin/Altcoin wallet. Convert your USD/EUR/etc prices to cryptocoins using Google/Bitstamp/Cryptsy Live Exchange Rates. Accept Bitcoin, Litecoin, Speedcoin, Dogecoin, Paycoin, Darkcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Vericoin payments online. No Chargebacks, Global, Secure. All in automatic mode.
Version: 			1.0.0
Author: 			GoUrl.io
Author URI: 		https://gourl.io
License: 			GPLv2
License URI: 		http://www.gnu.org/licenses/gpl-2.0.html
GitHub Plugin URI: 	https://github.com/cryptoapi/Bitcoin-Payments-WP-eCommerce
*/


if (!defined( 'ABSPATH' )) exit; // Exit if accessed directly in wordpress


// WP eCommerce required
if (!in_array('wp-e-commerce/wp-shopping-cart.php', get_option('active_plugins')) || 
	!file_exists(WP_PLUGIN_DIR.'/wp-e-commerce/wpsc-includes/merchant.class.php')) return;


define( 'GOURLWPSC', 'wpsc-gourl');


add_filter( 'plugin_action_links', 				'gourl_wpsc_action_links', 10, 2 );
add_action('wpsc_transaction_result_cart_item', array('wpsc_gourl_gateway', 'cryptocoin_payment'));
add_action('wpsc_billing_details_bottom', 		array('wpsc_gourl_gateway', 'display_order_notes'));




/*
 *	1.
*/
require_once WP_PLUGIN_DIR.'/wp-e-commerce/wpsc-includes/merchant.class.php';

$nzshpcrt_gateways[$num] = array (
		'name'						=> __( 'GoUrl Bitcoin/Altcoins', GOURLWPSC ),
		'api_version'				=> 2.0,
		'image'						=> plugin_dir_url( __FILE__ ).'gourlpayments.png',
		'internalname'				=> 'wpsc_gourl_gateway',
		'class_name'				=> 'wpsc_gourl_gateway',
		'has_recurring_billing'		=> true,
		'wp_admin_cannot_cancel'	=> false,
		'display_name'				=> __( 'GoUrl Bitcoin/Altcoins', GOURLWPSC ),
		'form'						=> array('wpsc_gourl_gateway', 'display_config'),
		'submit_function'			=> array('wpsc_gourl_gateway', 'save_config'),
		'payment_type'				=> 'cryptocoin',
		'requirements'				=> array('php_version' => 5.2)
);



/*
 *	2.
*/
function gourl_wpsc_action_links($links, $file)
{
	static $this_plugin;

	if (false === isset($this_plugin) || true === empty($this_plugin)) {
		$this_plugin = plugin_basename(__FILE__);
	}

	if ($file == $this_plugin) {
		$settings_link = '<a href="'.admin_url('options-general.php?page=wpsc-settings&tab=gateway&payment_gateway_id=wpsc_gourl_gateway').'">'.__( 'Settings', GOURLWPSC ).'</a>';
		array_unshift($links, $settings_link);
			
		if (defined('GOURL'))
		{
			$unrecognised_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=unrecognised').'">'.__( 'Unrecognised', GOURLWPSC ).'</a>';
			array_unshift($links, $unrecognised_link);
			$payments_link = '<a href="'.admin_url('admin.php?page='.GOURL.'payments&s=gourlwpecommerce').'">'.__( 'Payments', GOURLWPSC ).'</a>';
			array_unshift($links, $payments_link);
		}
	}

	return $links;
}

	



/**
 *	3.
*/
class wpsc_gourl_gateway extends wpsc_merchant 
{
		
	/**
	 * 3.1
	 */
	public static function display_config()
	{
		global $gourl;
	
		$payments 		= array();
		$coin_names 	= array();
		$languages 		= array();
		$statuses 		= array(2 => 'Order Received', 3 => 'Accepted Payment', 4 => 'Job Dispatched', 5 => 'Closed Order');
		$mainplugin_url = admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
			
		
		$description   = "<a href='https://gourl.io'><img style='float:left; margin-right:15px' src='".plugin_dir_url( __FILE__ )."gourlpayments.png'></a>";
		$description  .= __( '<a target="_blank" href="https://gourl.io/bitcoin-payments-wp-ecommerce.html">Plugin Homepage &#187;</a>', GOURLWPSC ) . "<br>";
		$description  .= __( '<a target="_blank" href="https://github.com/cryptoapi/Bitcoin-Payments-WP-eCommerce">Plugin on Github - 100% Free Open Source &#187;</a>', GOURLWPSC ) . "<br><br>";
	
	
		if (class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl))
		{
			if (true === version_compare(GOURL_VERSION, '1.2.7', '<'))
			{
				$description .= '<div class="error"><p>' .sprintf(__( '<b>Your GoUrl Bitcoin Gateway <a href="%s">Main Plugin</a> version is too old. Requires 1.2.7 or higher version. Please <a href="%s">update</a> to latest version.</b>  &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a>', GOURLWPSC ), GOURL_ADMIN.GOURL, $mainplugin_url).'</p></div>';
			}
			elseif (true === version_compare(WPSC_VERSION, '3.8.10', '<'))
			{
				$description .= '<div class="error"><p>' .sprintf(__( '<b>Your WP eCommerce version %s is too old</b>. Requires 3.8.10 or higher version for GoUrl Bitcoin/Altcoins Payment Gateway', GOURLWPSC ), WPSC_VERSION).'</p></div>';
			}
			else
			{
				$payments 			= $gourl->payments(); 		// Activated Payments
				$coin_names			= $gourl->coin_names(); 	// All Coins
				$languages			= $gourl->languages(); 		// All Languages
			}
				
			$coins 	= implode(", ", $payments);
			$url	= GOURL_ADMIN.GOURL."settings";
			$url2	= GOURL_ADMIN.GOURL."payments&s=gourlwpecommerce";
			$url3	= GOURL_ADMIN.GOURL;
			$text 	= ($coins) ? $coins : __( '- Please setup -', GOURLWPSC );
		}
		else
		{
			$coins 	= "";
			$url	= $mainplugin_url;
			$url2	= $url;
			$url3	= $url;
			$text 	= __( '<b>Please install GoUrl Bitcoin Gateway WP Plugin &#187;</b>', GOURLWPSC );
	
			$description .= '<div class="error"><p>' .sprintf(__( '<b>You need to install GoUrl Bitcoin Gateway Main Plugin also. Go to - <a href="%s">Bitcoin Gateway plugin page</a></b> &#160; &#160; &#160; &#160; Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a> ', GOURLWPSC ), $mainplugin_url).'</p></div>';
		}
	
		$description .= __( 'If you use multiple stores/sites online, please create separate <a target="_blank" href="https://gourl.io/editrecord/coin_boxes/0">GoUrl Payment Box</a> (with unique payment box public/private keys) for each of your stores/websites. Do not use the same GoUrl Payment Box with the same public/private keys on your different websites/stores.', GOURLWPSC ).'<br/>';
		$description .= sprintf(__( 'Accept %s payments online in WP eCommerce.', GOURLWPSC), ($coin_names?ucwords(implode(", ", $coin_names)):"Bitcoin, Litecoin, Speedcoin, Dogecoin, Paycoin, Darkcoin, Reddcoin, Potcoin, Feathercoin, Vertcoin, Vericoin")).'<br/>';
	
		
		// a
		$tmp  = '<tr valign="top"><td colspan=2>';
		$tmp .= "<div style='font-size:13px; color:#888'>".$description."</div>";
		$tmp .= '</th></tr>';

		
		// b
		$defcoin = get_option(GOURLWPSC.'defcoin');
		if (!in_array($defcoin, array_keys($payments))) $defcoin = current(array_keys($payments));
		
		$tmp .= '<tr valign="top">
            	<th><label for="'.GOURLWPSC.'defcoin">'.__( 'PaymentBox Default Coin', GOURLWPSC ).'</label></th>
            	<td><select name="wpsc_options['.GOURLWPSC.'defcoin]" id="wpsc_options['.GOURLWPSC.'defcoin]">';
		foreach ($payments as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $defcoin).">".$v."</option>";
		$tmp .= "</select>";
		$tmp .= '<p class="description">'.sprintf(__( 'Default Coin in Crypto Payment Box. &#160; Activated Payments : <a href="%s">%s</a>', GOURLWPSC), $url, $text)."</p>";
		$tmp .= "</tr>";
	
		
		// c
		$deflang = get_option(GOURLWPSC.'deflang');
		if (!in_array($deflang, array_keys($languages))) $deflang = current(array_keys($languages));
			
		$tmp .= '<tr valign="top">
            <th><label for="'.GOURLWPSC.'deflang">'.__( 'PaymentBox Language', GOURLWPSC ).'</label></th>
            <td><select name="wpsc_options['.GOURLWPSC.'deflang]" id="wpsc_options['.GOURLWPSC.'deflang]">';
		foreach ($languages as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $deflang).">".$v."</option>";
		$tmp .= "</select>";
		$tmp .= '<p class="description">'.__("Default Crypto Payment Box Localisation", GOURLWPSC)."</p>";
		$tmp .= "</tr>";
	
		
		// d
		$emultiplier = str_replace("%", "", get_option(GOURLWPSC.'emultiplier'));
		if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier <= 0) $emultiplier = "1.00";
	
		$tmp .= '<tr valign="top">
            <th><label for="'.GOURLWPSC.'emultiplier">'.__( 'Exchange Rate Multiplier', GOURLWPSC ).'</label></th>
            <td><input type="text" value="'.$emultiplier.'" name="wpsc_options['.GOURLWPSC.'emultiplier]" id="wpsc_options['.GOURLWPSC.'emultiplier]">';
		$tmp .= '<p class="description">'.sprintf(__('The system uses the multiplier rate with today LIVE cryptocurrency exchange rates (which are updated every 30 minutes) when the transaction is calculating from a fiat currency (e.g. USD, EUR, etc) to %s. <br />Example: <b>1.05</b> - will add an extra 5%% to the total price in bitcoin/altcoins, <b>0.85</b> - will be a 15%% discount for the price in bitcoin/altcoins. Default: 1.00 ', GOURLWPSC ), $coins)."</p>";
		$tmp .= "</tr>";

		
		// e
		$ostatus = get_option(GOURLWPSC.'ostatus');
		if (!in_array($ostatus, array_keys($statuses))) $ostatus = 3; // Accepted Payment 
		
		$tmp .= '<tr valign="top">
            <th><label for="'.GOURLWPSC.'ostatus">'.__( 'Order Status - Cryptocoin Payment Received', GOURLWPSC ).'</label></th>
            <td><select name="wpsc_options['.GOURLWPSC.'ostatus]" id="wpsc_options['.GOURLWPSC.'ostatus]">';
		foreach ($statuses as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $ostatus).">".$v."</option>";
		$tmp .= "</select>";
		$tmp .= '<p class="description">'.sprintf(__("Payment is received successfully from the customer. You will see the bitcoin/altcoin payment statistics in one common table <a href='%s'>'All Payments'</a> with details of all received payments.<br/>If you sell digital products / software downloads you can use the status 'Completed' showing that particular customer already has instant access to your digital products", GOURLWPSC), $url2)."</p>";
		$tmp .= "</tr>";
			
		
		// f
		$ostatus2 = get_option(GOURLWPSC.'ostatus2');
		if (!in_array($ostatus2, array_keys($statuses))) $ostatus2 = 3; // Accepted Payment
		
		$tmp .= '<tr valign="top">
            <th><label for="'.GOURLWPSC.'ostatus2">'.__( 'Order Status - Previously Received Payment Confirmed', GOURLWPSC ).'</label></th>
            <td><select name="wpsc_options['.GOURLWPSC.'ostatus2]" id="wpsc_options['.GOURLWPSC.'ostatus2]">';
		foreach ($statuses as $k => $v) $tmp .= "<option value='".$k."'".self::sel($k, $ostatus2).">".$v."</option>";
		$tmp .= "</select>";
		$tmp .= '<p class="description">'.__("About one hour after the payment is received, the bitcoin transaction should get 6 confirmations (for transactions using other cryptocoins ~ 20-30min).<br>A transaction confirmation is needed to prevent double spending of the same money.", GOURLWPSC)."</p>";
		$tmp .= "</tr>";
			
		
		// g
		$iconwidth = str_replace("px", "", get_option(GOURLWPSC.'iconwidth'));
		if (!$iconwidth || !is_numeric($iconwidth) || $iconwidth < 30 || $iconwidth > 250) $iconwidth = 60;
		$iconwidth = $iconwidth . "px";
	
		$tmp .= '<tr valign="top">
            <th><label for="'.GOURLWPSC.'iconwidth">'.__( 'Icon Width', GOURLWPSC ).'</label></th>
            <td><input type="text" value="'.$iconwidth.'" name="wpsc_options['.GOURLWPSC.'iconwidth]" id="wpsc_options['.GOURLWPSC.'iconwidth]">';
		$tmp .= '<p class="description">'.__( 'Cryptocoin icons width in "Select Payment Method". Default 60px. Allowed: 30..250px', GOURLWPSC )."</p>";
		$tmp .= "</tr>";
	
		
		return $tmp;
	}
	

	/**
	 * 3.2 
	 */
	public static function save_config()
	{
		$arr = array('defcoin', 'deflang', 'emultiplier', 'ostatus', 'ostatus2', 'iconwidth');
	
		if ( isset( $_POST['wpsc_options'] ) )
		{
			foreach ($arr as $v)
				update_option( GOURLWPSC.$v, $_POST['wpsc_options'][GOURLWPSC.$v] );
		}
	
		self::gourl_upgrade();
		
		return true;
	}
	
	
	/**
	 * 3.3
	 */
	public function submit()
	{
		global $gourl;
	
		$mainplugin_url = admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
	
		// Re-test
		if (!(class_exists('gourlclass') && defined('GOURL') && defined('GOURL_ADMIN') && is_object($gourl) && true === version_compare(GOURL_VERSION, '1.2.7', '>=')))
		{
			$this->set_error_message('<div style="border:1px solid #eee;margin:20px 10px;padding:10px">'.sprintf(__( '<b>Error!</b> Please try a different payment method. Admin need to install and activate GoUrl Bitcoin Gateway Main Plugin 1.2.7+. <a href="%s">Bitcoin Gateway plugin page</a><br>Information: &#160; <a href="https://gourl.io/bitcoin-wordpress-plugin.html">Plugin Homepage</a> &#160; &#160; &#160; <a href="https://wordpress.org/plugins/gourl-bitcoin-payment-gateway-paid-downloads-membership/">WordPress.org Plugin Page</a> ', GOURLWPSC ), $mainplugin_url).'</p></div>');
			$this->set_purchase_processed_by_purchid(1); // WPSC_Purchase_Log::INCOMPLETE_SALE
			return;
		}
	
		// Checkout
		$user = get_current_user_id();
		$user = (!$user) ? __('Guest', GOURLWPSC) : "<a href='".htmlspecialchars("user-edit.php?user_id=".$user, ENT_QUOTES)."'>user".$user."</a>";
		$this->set_purchase_processed_by_purchid(1);
		$this->add_order_note($this->purchase_id, sprintf(__('Order Created by %s<br>Awaiting Cryptocurrency Payment ...', GOURLWPSC ), $user));
		$this->go_to_transaction_results($this->cart_data['session_id']);
	
		return true;
	}

	
	/**
	 * 3.4
	 */
	public static function cryptocoin_payment ($arr) 
	{
		global $gourl, $wpdb;
		static $flag = false;
		
		if ($flag) return false; 
		$flag = true;
		
		
		// Initialize
		// ------------------------
		if (class_exists('gourlclass') && defined('GOURL') && is_object($gourl))
		{
			$payments 		= $gourl->payments(); 		// Activated Payments
			$coin_names		= $gourl->coin_names(); 	// All Coins
			$languages		= $gourl->languages(); 		// All Languages
		}
		else
		{		
			$payments 		= array();
			$coin_names 	= array();
			$languages 		= array();
		}
		
		$statuses 			= array(2 => 'Order Received', 3 => 'Accepted Payment', 4 => 'Job Dispatched', 5 => 'Closed Order');
		$mainplugin_url 	= admin_url("plugin-install.php?tab=search&type=term&s=GoUrl+Bitcoin+Payment+Gateway+Downloads");
			
		$defcoin = get_option(GOURLWPSC.'defcoin');
		if (!in_array($defcoin, array_keys($payments))) $defcoin = current(array_keys($payments));
			
		$deflang = get_option(GOURLWPSC.'deflang');
		if (!in_array($deflang, array_keys($languages))) $deflang = current(array_keys($languages));
			
		$emultiplier = str_replace("%", "", get_option(GOURLWPSC.'emultiplier'));
		if (!$emultiplier || !is_numeric($emultiplier) || $emultiplier <= 0) $emultiplier = "1.00";
			
		$ostatus = get_option(GOURLWPSC.'ostatus');
		if (!in_array($ostatus, array_keys($statuses))) $ostatus = 3; // Accepted Payment
			
		$ostatus2 = get_option(GOURLWPSC.'ostatus2');
		if (!in_array($ostatus2, array_keys($statuses))) $ostatus2 = 3; // Accepted Payment
		
		$iconwidth = str_replace("px", "", get_option(GOURLWPSC.'iconwidth'));
		if (!$iconwidth || !is_numeric($iconwidth) || $iconwidth < 30 || $iconwidth > 250) $iconwidth = 60;
		$iconwidth = $iconwidth . "px";			
		
		
		
		
		
		// Current Order
		// -----------------
		$order_id 			= $arr["purchase_id"];
		$order_total		= $arr["purchase_log"]["totalprice"];
		$order_currency		= (version_compare(WPSC_VERSION, '3.8.14', '<')) ? current($wpdb->get_results("SELECT code FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE id = ".intval(get_option('currency_type'))." LIMIT 1", ARRAY_A))["code"] : WPSC_Countries::get_currency_code( get_option( 'currency_type' ) );
		$order_user_id		= $arr["purchase_log"]["user_ID"];

		if ($order_currency == "DOG") $order_currency = "DOGE"; // WP eCommerce allow max 3 symbols for coin symbol
		
		
		// Security
		// -------------
		if (!$order_id) throw new Exception('The GoUrl payment plugin was called to process a payment but could not retrieve the order details for order_id. Cannot continue!');
		
		if ($arr["purchase_log"]["gateway"] != "wpsc_gourl_gateway" || ($order_user_id && $order_user_id != get_current_user_id())) return false;
		
		if (!class_exists('gourlclass') || !defined('GOURL') || !is_object($gourl))
		{
			echo '<h2>' . __( 'Information', GOURLWPSC ) . '</h2>' . PHP_EOL;
			echo "<div style='border:1px solid #eee;margin:20px 10px;padding:10px'>".__( "Please try a different payment method. Admin need to install and activate wordpress plugin 'GoUrl Bitcoin Gateway' (https://gourl.io/bitcoin-wordpress-plugin.html) to accept Bitcoin/Altcoin Payments online", GOURLWPSC )."</div>";
		}
		elseif (!$payments || !$defcoin || true === version_compare(WPSC_VERSION, '3.8.10', '<') || true === version_compare(GOURL_VERSION, '1.2.7', '<') ||
				(array_key_exists($order_currency, $coin_names) && !array_key_exists($order_currency, $payments)))
		{
			echo '<h2>' . __( 'Information', GOURLWPSC ) . '</h2>' . PHP_EOL;
			echo  "<div style='border:1px solid #eee;margin:20px 10px;padding:10px'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try a different payment method or contact us if you need assistance. (GoUrl Bitcoin Plugin not configured - %s not activated)', GOURLWPSC ),(!$payments || !$defcoin?"Cryptocurrency":$coin_names[$order_currency]))."</div>";
		}
		else
		{
			$plugin			= "gourlwpecommerce";
			$amount 		= $order_total;
			$currency 		= $order_currency;
			$orderID		= "order" . $order_id;
			$userID			= $order_user_id;
			$period			= "NOEXPIRY";
			$language		= $deflang;
			$coin 			= $coin_names[$defcoin];
			$affiliate_key 	= "gourl";
			$crypto			= array_key_exists($currency, $coin_names);
				
			if (!$userID) $userID = "guest"; // allow guests to make checkout (payments)
				
		
				
			if (!$userID)
			{
				echo '<h2>' . __( 'Information', GOURLWPSC ) . '</h2>' . PHP_EOL;
				echo "<div align='center'><a href='".wp_login_url(get_permalink())."'>
					<img style='border:none;box-shadow:none;' title='".__('You need first to login or register on the website to make Bitcoin/Altcoin Payments', GOURLWPSC )."' vspace='10'
					src='".$gourl->box_image()."' border='0'></a></div>";
			}
			elseif ($amount <= 0)
			{
				echo '<h2>' . __( 'Information', GOURLWPSC ) . '</h2>' . PHP_EOL;
				echo "<div style='border:1px solid #eee;margin:20px 10px;padding:10px'>". sprintf(__( 'This order&rsquo;s amount is &ldquo;%s&rdquo; &mdash; it cannot be paid for. Please contact us if you need assistance.', GOURLWPSC ), $amount ." " . $currency)."</div>";
			}
			else
			{
		
				// Exchange (optional)
				// --------------------
				if ($currency != "USD" && !$crypto)
				{
					$amount = gourl_convert_currency($currency, "USD", $amount);
		
					if ($amount <= 0)
					{
						echo '<h2>' . __( 'Information', GOURLWPSC ) . '</h2>' . PHP_EOL;
						echo "<div style='border:1px solid #eee;margin:20px 10px;padding:10px'>".sprintf(__( 'Sorry, but there was an error processing your order. Please try later or use a different payment method. Cannot receive exchange rates for %s/USD from Google Finance', GOURLWPSC ), $currency)."</div>";
					}
					else $currency = "USD";
				}
					
				if (!$crypto) $amount = $amount * $emultiplier;
					
		
					
				// Payment Box
				// ------------------
				if ($amount > 0)
				{
					// crypto payment gateway
					$result = $gourl->cryptopayments ($plugin, $amount, $currency, $orderID, $period, $language, $coin, $affiliate_key, $userID, $iconwidth);
						
					if (!$result["is_paid"]) echo '<h2>' . __( 'Pay Now', GOURLWPSC ) . '</h2>' . PHP_EOL;
						
					if ($result["error"]) echo "<div style='border:1px solid #eee;margin:20px 10px;padding:10px'>".__( "Sorry, but there was an error processing your order. Please try a different payment method.", GOURLWPSC )."<br/>".$result["error"]."</div>";
					else
					{
						// display payment box or successful payment result
						echo $result["html_payment_box"];
		
						// payment received
						if ($result["is_paid"])
						{
							echo "<div align='center'>" . sprintf( __('%s payment ID: #%s, order ID: #%s', GOURLWPSC), ucfirst($result["coinname"]), $result["paymentID"], $order_id) . "</div><br>";
						}
					}
				}
			}
		}

		echo "<br><br>";
		
		return true;
	}

	
	/**
	 * 3.5
	 */
	public static function gourl_upgrade ()
	{
		global $wpdb;
	
		if (WPSC_TABLE_CURRENCY_LIST && !$wpdb->get_results("SELECT * FROM `".WPSC_TABLE_CURRENCY_LIST."` WHERE country = 'Cryptocurrency' LIMIT 1"))
		{
			$sql = "INSERT INTO `".WPSC_TABLE_CURRENCY_LIST."` VALUES
				(0, 'Cryptocurrency', 'C1', 'Bitcoin', '', '', 'BTC', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'C2', 'Litecoin', '', '', 'LTC', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'C3', 'Dogecoin', '', '', 'DOG', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'C5', 'Speedcoin', '', '', 'SPD', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'C6', 'Paycoin', '', '', 'XPY', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'C7', 'Darkcoin', '', '', 'DRK', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'C8', 'Vertcoin', '', '', 'VTC', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'C9', 'Reddcoin', '', '', 'RDD', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'D1', 'Feathercoin', '', '', 'FTC', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'D2', 'Vericoin', '', '', 'VRC', '0', '0','world','1'),
				(0, 'Cryptocurrency', 'D3', 'Potcoin', '', '', 'POT', '0', '0','world','1')";
	
			$wpdb->query($sql);
		}
	
		return true;
	}
	
	
	/**
	 * 3.6
	 */
	public static function add_order_note($purchase_id, $notes)
	{
		$id	= GOURLWPSC.$purchase_id."_log";
		$dt = current_time("mysql", 0);
			
		$arr = get_option($id);
		if (!$arr) $arr = array();
		$arr[] = "<table cellspacing='5' border='0'><tr><td valign='top'>" . $dt . " &#160;</td><td>" . $notes . "</td></tr></table>";
		update_option($id, $arr);
			
		return true;
	}
	

	/**
	 * 3.7
	 */
	public static function display_order_notes()
	{
		global $purchlogitem, $gourl, $wpdb;
	
		if (is_admin() && isset($_GET["id"]) && isset($_GET["page"]) && $_GET["page"] == "wpsc-purchase-logs" && isset($_GET["c"]) && $_GET["c"] == "item_details") 
		{
			$purchase_id = $_GET["id"];
			
			$tmp = get_option(GOURLWPSC.$purchase_id."_log"); 
			
			if ($tmp)
			{	
				echo "<br><h3>". __("Crypto Payment Log", GOURLWPSC)."</h3>";
				echo implode("\n", $tmp);
			}
		}
		
		return true;
		
	}

	
	/**
	 * 3.8
	 */
	public static function sel($val1, $val2)
	{
		$tmp = ((is_array($val1) && in_array($val2, $val1)) || strval($val1) == strval($val2)) ? ' selected="selected"' : '';
	
		return $tmp;
	}

	
	/**
	 * 3.9
	 */
	public function parse_gateway_notification() {
		return false;
	}
	
	
	/**
	 * 3.10
	 */
	public function process_gateway_notification() {
		return false;
	}
	
}	
// end class




/*
 *  4. Instant Payment Notification Function - pluginname."_gourlcallback"
*
*  This function will appear every time by GoUrl Bitcoin Gateway when a new payment from any user is received successfully.
*  Function gets user_ID - user who made payment, current order_ID (the same value as you provided to bitcoin payment gateway),
*  payment details as array and box status.
*
*  The function will automatically appear for each new payment usually two times :
*  a) when a new payment is received, with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 0
*  b) and a second time when existing payment is confirmed (6+ confirmations) with values: $box_status = cryptobox_updated, $payment_details[is_confirmed] = 1.
*
*  But sometimes if the payment notification is delayed for 20-30min, the payment/transaction will already be confirmed and the function will
*  appear once with values: $box_status = cryptobox_newrecord, $payment_details[is_confirmed] = 1
*
*  Payment_details example - https://gourl.io/images/plugin2.png
*  Read more - https://gourl.io/affiliates.html#wordpress
*/
function gourlwpecommerce_gourlcallback ($user_id, $order_id, $payment_details, $box_status)
{
	global $wpdb, $wpsc_merchant;
	
	if (!in_array($box_status, array("cryptobox_newrecord", "cryptobox_updated"))) return false;

	if (strpos($order_id, "order") === 0) $order_id = intval(substr($order_id, 5)); else return false;

	if (!$user_id || $payment_details["status"] != "payment_received") return false;

	$sql = 'SELECT * FROM `'.WPSC_TABLE_PURCHASE_LOGS.'` WHERE id = '.$order_id.' LIMIT 1';
	$arr = $wpdb->get_row($sql, ARRAY_A);
		
	if (!$arr) return false;

	
	// Initialize
	$statuses 	= array(2 => 'Order Received', 3 => 'Accepted Payment', 4 => 'Job Dispatched', 5 => 'Closed Order');
	$ostatus 	= get_option(GOURLWPSC.'ostatus');
	if (!in_array($ostatus, array_keys($statuses))) $ostatus = 3; // Accepted Payment
	$ostatus2 	= get_option(GOURLWPSC.'ostatus2');
	if (!in_array($ostatus2, array_keys($statuses))) $ostatus2 = 3; // Accepted Payment
			
	
	$coinName 	= ucfirst($payment_details["coinname"]);
	$amount		= $payment_details["amount"] . " " . $payment_details["coinlabel"] . "&#160; ( $" . $payment_details["amountusd"] . " )";
	$payID		= $payment_details["paymentID"];
	$trID		= $payment_details["tx"];
	$status		= ($payment_details["is_confirmed"]) ? $ostatus2 : $ostatus;
	$confirmed	= ($payment_details["is_confirmed"]) ? __('Yes', GOURLWPSC) : __('No', GOURLWPSC);


	// New Payment Received
	if ($box_status == "cryptobox_newrecord")
	{
		wpsc_gourl_gateway::add_order_note($order_id, sprintf(__('<b>%s</b> payment received<br>%s<br>Payment <a href="%s">id %s</a>. Awaiting network confirmation...'.($arr["processed"]!=$status?'<br>Order status changed to: %s':''), GOURLWPSC), $coinName, $amount, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, $statuses[$status]));
	}

	// Existing Payment confirmed (6+ confirmations)
	if ($payment_details["is_confirmed"])
	{
		wpsc_gourl_gateway::add_order_note($order_id, sprintf(__('%s Payment id <a href="%s">%s</a> Confirmed'.($arr["processed"]!=$status?'<br>Order status changed to: %s':''), GOURLWPSC), $coinName, GOURL_ADMIN.GOURL."payments&s=payment_".$payID, $payID, $statuses[$status]));
	}
	
	// Update Order Status
	wpsc_update_purchase_log_status($order_id, $status);
	wpsc_update_purchase_log_details($order_id, array('transactid' => $trID));

	
	// WP eCommerce not use new updated order status, therefore need to refresh page manually            
	if (in_array($status, array(3,4,5)) && !in_array($arr["processed"], array(3,4,5)) && !stripos($_SERVER["REQUEST_URI"], "cryptobox.callback.php")) { header('Location: '.$_SERVER["REQUEST_URI"]); echo "<script>window.location.href = '".$_SERVER["REQUEST_URI"]."';</script>"; die; }
	
	
	return true;
}


?>