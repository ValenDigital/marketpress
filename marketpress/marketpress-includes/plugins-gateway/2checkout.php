<?php
/*
MarketPress Authorize.net AIM Gateway Plugin
Author: S H Mohanjith (Incsub)
*/

class MP_Gateway_2Checkout extends MP_Gateway_API {

  //private gateway slug. Lowercase alpha (a-z) and dashes (-) only please!
  var $plugin_name = '2checkout';
  
  //name of your gateway, for the admin side.
  var $admin_name = '';
  
  //public name of your gateway, for lists and such.
  var $public_name = '';

  //url for an image for your checkout method. Displayed on checkout form if set
  var $method_img_url = '';
  
  //url for an submit button image for your checkout method. Displayed on checkout form if set
  var $method_button_img_url = '';

  //always contains the url to send payment notifications to if needed by your gateway. Populated by the parent class
  var $ipn_url;

  //credit card vars
  var $API_Username, $API_Password, $SandboxFlag, $returnURL, $cancelURL, $API_Endpoint, $version, $currencyCode, $locale;
    
  /****** Below are the public methods you may overwrite via a plugin ******/
  
  /**
   * Runs when your class is instantiated. Use to setup your plugin instead of __construct()
   */
  function on_creation() {
    global $mp;
    $settings = get_option('mp_settings');
    
    //set names here to be able to translate
    $this->admin_name = __('2Checkout (Beta)', 'mp');
    $this->public_name = __('2Checkout', 'mp');
    
    $this->method_img_url = $mp->plugin_url . 'images/2co_logo.png';
    $this->method_button_img_url = $mp->plugin_url . 'images/2co.png';
    
    $this->currencyCode = $settings['gateways']['2checkout']['currency'];
    
    
    $this->API_Username = $settings['gateways']['2checkout']['sid'];
    $this->API_Password = $settings['gateways']['2checkout']['secret_word'];
    $this->SandboxFlag = $settings['gateways']['2checkout']['mode'];
  }

  /**
   * Echo fields you need to add to the top of the payment screen, like your credit card info fields
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function payment_form($cart, $shipping_info) {
    global $mp;
    if (isset($_GET['2checkout_cancel'])) {
      echo '<div class="mp_checkout_error">' . __('Your 2Checkout transaction has been canceled.', 'mp') . '</div>';
    }
    $settings = get_option('mp_settings');
    ?>
    <?php
  }
  
  function _print_year_dropdown($sel='', $pfp = false)
  {
          $localDate=getdate();
          $minYear = $localDate["year"];
          $maxYear = $minYear + 15;
  
          $output =  "<option value=''>--</option>";
          for($i=$minYear; $i<$maxYear; $i++) {
                  if ($pfp) {
                          $output .= "<option value='". substr($i, 0, 4) ."'".($sel==(substr($i, 0, 4))?' selected':'').
                          ">". $i ."</option>";
                  } else {
                          $output .= "<option value='". substr($i, 2, 2) ."'".($sel==(substr($i, 2, 2))?' selected':'').
                  ">". $i ."</option>";
                  }
          }
          return($output);
  }
  
  function _print_month_dropdown($sel='')
  {
          $output =  "<option value=''>--</option>";
          $output .=  "<option " . ($sel==1?' selected':'') . " value='01'>01 - Jan</option>";
          $output .=  "<option " . ($sel==2?' selected':'') . "  value='02'>02 - Feb</option>";
          $output .=  "<option " . ($sel==3?' selected':'') . "  value='03'>03 - Mar</option>";
          $output .=  "<option " . ($sel==4?' selected':'') . "  value='04'>04 - Apr</option>";
          $output .=  "<option " . ($sel==5?' selected':'') . "  value='05'>05 - May</option>";
          $output .=  "<option " . ($sel==6?' selected':'') . "  value='06'>06 - Jun</option>";
          $output .=  "<option " . ($sel==7?' selected':'') . "  value='07'>07 - Jul</option>";
          $output .=  "<option " . ($sel==8?' selected':'') . "  value='08'>08 - Aug</option>";
          $output .=  "<option " . ($sel==9?' selected':'') . "  value='09'>09 - Sep</option>";
          $output .=  "<option " . ($sel==10?' selected':'') . "  value='10'>10 - Oct</option>";
          $output .=  "<option " . ($sel==11?' selected':'') . "  value='11'>11 - Nov</option>";
          $output .=  "<option " . ($sel==12?' selected':'') . "  value='12'>12 - Doc</option>";
  
          return($output);
  }
  
  /**
   * Use this to process any fields you added. Use the $_REQUEST global,
   *  and be sure to save it to both the $_SESSION and usermeta if logged in.
   *  DO NOT save credit card details to usermeta as it's not PCI compliant.
   *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
   *  it will redirect to the next step.
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function process_payment_form($cart, $shipping_info) {
    global $mp;
    
    $mp->generate_order_id();
  }
  
  /**
   * Echo the chosen payment details here for final confirmation. You probably don't need
   *  to post anything in the form as it should be in your $_SESSION var already.
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function confirm_payment_form($cart, $shipping_info) {
    global $mp;
  }

  /**
   * Use this to do the final payment. Create the order then process the payment. If
   *  you know the payment is successful right away go ahead and change the order status
   *  as well.
   *  Call $mp->cart_checkout_error($msg, $context); to handle errors. If no errors
   *  it will redirect to the next step.
   *
   * @param array $shipping_info. Contains shipping info and email in case you need it
   */
  function process_payment($cart, $shipping_info) {
    global $mp;
    
    $timestamp = time();
    $settings = get_option('mp_settings');
    
    $url = "https://www.2checkout.com/checkout/purchase";
    
    $params = array();
    
    $params['sid'] = $this->API_Username;
    $params['cart_order_id'] = $_SESSION['mp_order'];
    $params['x_receipt_link_url'] = mp_cart_link(false, true) . trailingslashit("confirmation");
    $params['skip_landing'] = '1';
    $params['currency_code'] = $this->currencyCode;
    
    if ($this->SandboxFlag == 'sandbox') {
      $params['demo'] = 'Y';
    }
    
    $totals = array();
    $counter = 1;
    
    $params["id_type"] = 1;
    
    foreach ($cart as $product_id => $data) {
      $totals[] = $data['price'] * $data['quantity'];
      
      $suffix = "_{$counter}";
      
      $sku = empty($data['SKU'])?$product_id:$data['SKU'];
      $params["c_prod{$suffix}"] = "{$sku},{$data['quantity']}";
      $params["c_name{$suffix}"] = $data['name'];
      $params["c_description{$suffix}"] = get_permalink($product_id);
      $params["c_price{$suffix}"] = $data['price'];
      
      $i++;
    }
    
    $total = array_sum($totals);
    
    if ( $coupon = $mp->coupon_value($mp->get_coupon_code(), $total) ) {
      $total = $coupon['new_total'];
    }

    //shipping line
    if ( ($shipping_price = $mp->shipping_price()) !== false ) {
      $total = $total + $shipping_price;
    }
    
    //tax line
    if ( ($tax_price = $mp->tax_price()) !== false ) {
      $total = $total + $tax_price;
    }
    
    $params['total'] = $total;
    
    $param_list = array();
    
    foreach ($params as $k => $v) {
      $param_list[] = "{$k}=".rawurlencode($v);
    }
    
    $param_str = implode('&', $param_list);
    
    $_SESSION['cart'] = $cart;
    $_SESSION['shipping_info'] = $shipping_info;
    
    wp_redirect("{$url}?{$param_str}");
    
    exit(0);
  }
  
  /**
   * Filters the order confirmation email message body. You may want to append something to
   *  the message. Optional
   *
   * Don't forget to return!
   */
  function order_confirmation_email($msg) {
    return $msg;
  }
  
  /**
   * Echo any html you want to show on the confirmation screen after checkout. This
   *  should be a payment details box and message.
   */
  function order_confirmation_msg($order) {
    global $mp;
    if ($order->post_status == 'order_received') {
      echo '<p>' . sprintf(__('Your payment via 2Checkout for this order totaling %s is not yet complete. Here is the latest status:', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total'])) . '</p>';
      $statuses = $order->mp_payment_info['status'];
      krsort($statuses); //sort with latest status at the top
      $status = reset($statuses);
      $timestamp = key($statuses);
      echo '<p><strong>' . date(get_option('date_format') . ' - ' . get_option('time_format'), $timestamp) . ':</strong>' . htmlentities($status) . '</p>';
    } else {
      echo '<p>' . sprintf(__('Your payment via 2Checkout for this order totaling %s is complete. The transaction number is <strong>%s</strong>.', 'mp'), $mp->format_currency($order->mp_payment_info['currency'], $order->mp_payment_info['total']), $order->mp_payment_info['transaction_id']) . '</p>';
    }
  }
  
  function order_confirmation($order) {
    global $mp;
    
    $timestamp = time();
    $total = $_REQUEST['total'];
    
    if ($this->SandboxFlag == 'sandbox') {
      $hash = strtoupper(md5($this->API_Password . $this->API_Username . 1 . $total));
    } else {
      $hash = strtoupper(md5($this->API_Password . $this->API_Username . $_REQUEST['order_number'] . $total));
    }
    
    if ($_REQUEST['key'] == $hash) {
      $status = __('The order has been received', 'mp');
      $paid = true;
      
      $payment_info['gateway_public_name'] = $this->public_name;
      $payment_info['gateway_private_name'] = $this->admin_name;
      $payment_info['status'][$timestamp] = "paid";
      $payment_info['total'] = $_REQUEST['total'];
      $payment_info['currency'] = $this->currencyCode;
      $payment_info['transaction_id'] = $_REQUEST['order_number'];  
      $payment_info['method'] = "Credit Card";
    
      $cart = $_SESSION['cart'];
      $shipping_info = $_SESSION['shipping_info'];
    
      $order = $mp->create_order($_SESSION['mp_order'], $cart, $shipping_info, $payment_info, $paid);
    }
  }
  
  /**
   * Echo a settings meta box with whatever settings you need for you gateway.
   *  Form field names should be prefixed with mp[gateways][plugin_name], like "mp[gateways][plugin_name][mysetting]".
   *  You can access saved settings via $settings array.
   */
  function gateway_settings_box($settings) {
    global $mp;
    
    $settings = get_option('mp_settings');
    
    ?>
    <div id="mp_2checkout" class="postbox">
      <h3 class='handle'><span><?php _e('2Checkout Settings', 'mp'); ?></span></h3>
      <div class="inside">
        <span class="description"><?php _e('Resell your inventory via 2Checkout.com.', 'mp') ?></span>
        <table class="form-table">
	  <tr>
	    <th scope="row"><?php _e('Mode', 'mp') ?></th>
	    <td>
              <p>
                <select name="mp[gateways][2checkout][mode]">
                  <option value="sandbox" <?php selected($settings['gateways']['2checkout']['mode'], 'sandbox') ?>><?php _e('Sandbox', 'mp') ?></option>
                  <option value="live" <?php selected($settings['gateways']['2checkout']['mode'], 'live') ?>><?php _e('Live', 'mp') ?></option>
                </select>
              </p>
	    </td>
	  </tr>
	  <tr>
	    <th scope="row"><?php _e('2Checkout Credentials', 'mp') ?></th>
	    <td>
              <span class="description"><?php print sprintf(__('You must login to 2Checkout vendor dashboard to obtain the seller ID and secret word. <a target="_blank" href="%s">Instructions &raquo;</a>', 'mp'), "http://www.2checkout.com/community/blog/knowledge-base/suppliers/tech-support/3rd-party-carts/md5-hash-checking/where-do-i-set-up-the-secret-word"); ?></span>
	      <p>
		<label><?php _e('Seller ID', 'mp') ?><br />
		  <input value="<?php echo esc_attr($settings['gateways']['2checkout']['sid']); ?>" size="30" name="mp[gateways][2checkout][sid]" type="text" />
		</label>
	      </p>
	      <p>
		<label><?php _e('Secret word', 'mp') ?><br />
		  <input value="<?php echo esc_attr($settings['gateways']['2checkout']['secret_word']); ?>" size="30" name="mp[gateways][2checkout][secret_word]" type="text" />
		</label>
	      </p>
	    </td>
	  </tr>
          <tr valign="top">
        <th scope="row"><?php _e('2Checkout Currency', 'mp') ?></th>
        <td>
          <select name="mp[gateways][2checkout][currency]">
          <?php
          $sel_currency = ($settings['gateways']['2checkout']['currency']) ? $settings['gateways']['2checkout']['currency'] : $settings['currency'];
          $currencies = array(
                "ARS" => 'ARS - Argentina Peso',
                "AUD" => 'AUD - Australian Dollar',
		"BRL" => 'BRL - Brazilian Real',
		"CAD" => 'CAD - Canadian Dollar',
		"CHF" => 'CHF - Swiss Franc',
		"DKK" => 'DKK - Danish Krone',
		"EUR" => 'EUR - Euro',
		"GBP" => 'GBP - British Pound',
		"HKD" => 'HKD - Hong Kong Dollar',
		"INR" => 'INR - Indian Rupee',
		"JPY" => 'JPY - Japanese Yen',
		"MXN" => 'MXN - Mexican Peso',
		"NOK" => 'NOK - Norwegian Krone',
		"NZD" => 'NZD - New Zealand Dollar',
		"SEK" => 'SEK - Swedish Krona',
		"USD" => 'USD - U.S. Dollar',
          );

          foreach ($currencies as $k => $v) {
              echo '		<option value="' . $k . '"' . ($k == $sel_currency ? ' selected' : '') . '>' . wp_specialchars($v, true) . '</option>' . "\n";
          }
          ?>
          </select>
        </td>
        </tr>
        </table>
      </div>
    </div>
    <?php
  }
  
  /**
   * Filters posted data from your settings form. Do anything you need to the $settings['gateways']['plugin_name']
   *  array. Don't forget to return!
   */
  function process_gateway_settings($settings) {
    return $settings;
  }
  
  /**
   * TODO: Test 2CO INS
   */
  function process_ipn_return() {
    global $mp;
    
    $settings = get_option('mp_settings');
    
    if (isset($_REQUEST['message_type']) && $_REQUEST['message_type'] == 'INVOICE_STATUS_CHANGED') {
      $sale_id = $_REQUEST['sale_id'];
      $tco_invoice_id = $_REQUEST['invoice_id'];
      $tco_vendor_order_id = $_REQUEST['vendor_order_id'];
      $tco_invoice_status = $_REQUEST['invoice_status'];
      $tco_hash = $_REQUEST['md5_hash'];
      $total = $_REQUEST['invoice_list_amount'];
      $payment_method = ucfirst($_REQUEST['payment_type']);
      
      $order = $mp->get_order($tco_vendor_order_id);
      
      if (!$order) {
	header('HTTP/1.0 404 Not Found');
	header('Content-type: text/plain; charset=UTF-8');
	print 'Invoice not found';
	exit(0);
      }
      
      $calc_key = md5($sale_id.$settings['gateways']['2checkout']['sid'].$_REQUEST['invoice_id'].$settings['gateways']['2checkout']['secret_word']);
      
      if (strtolower($tco_hash) != strtolower($calc_key)) {
	header('HTTP/1.0 403 Forbidden');
	header('Content-type: text/plain; charset=UTF-8');
	print 'We were unable to authenticate the request';
	exit(0);
      }
      
      if (strtolower($_REQUEST['invoice_status']) != "deposited") {
	header('HTTP/1.0 200 OK');
	header('Content-type: text/plain; charset=UTF-8');
	print 'Thank you very much for letting us know. REF: Not success';
	exit(0);
      }
      
      if ($this->SandboxFlag != 'sandbox') {
	if (intval($total) >= $order->mp_order_total) {
	  $payment_info = $order->mp_payment_info;
	  $payment_info['transaction_id'] = $tco_invoice_id;  
	  $payment_info['method'] = $payment_method;
	  
	  update_post_meta($order->ID, 'mp_payment_info', $payment_info);
	  
	  $mp->update_order_payment_status($tco_vendor_order_id, "paid", true);
	}
      }
      
      header('HTTP/1.0 200 OK');
      header('Content-type: text/plain; charset=UTF-8');
      print 'Thank you very much for letting us know';
      exit(0);
    }
  }
}

//register payment gateway plugin
mp_register_gateway_plugin( 'MP_Gateway_2Checkout', '2checkout', __('2Checkout (Beta)', 'mp') );
