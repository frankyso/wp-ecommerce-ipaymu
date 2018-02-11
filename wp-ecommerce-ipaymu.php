<?php
/*
 * Plugin Name: WP eCommerce Payment Gateway - iPaymu
 * Plugin URL: https://ipaymu.com
 * Description: iPaymu payment gateway plugin for WP-eCommerce
 * Version: 1.0
 * Author: Franky So
 */
$nzshpcrt_gateways[$num]['name'] = 'iPaymu Payment Gateway';
$nzshpcrt_gateways[$num]['internalname'] = 'iPaymuPaymentGateway';
$nzshpcrt_gateways[$num]['function'] = 'ipaymu_gateway';
$nzshpcrt_gateways[$num]['form'] = 'ipaymu_form';
$nzshpcrt_gateways[$num]['submit_function'] = 'ipaymu_form_submit';
$nzshpcrt_gateways[$num]['display_name'] = 'iPaymu';


// $result = $wpdb->get_results("SELECT * FROM ".WPSC_TABLE_PURCHASE_LOGS."where processed='2' AND gateway='iPaymuPaymentGateway'");

// $purchase_log = $wpdb->get_row($wpdb->prepare("SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `processed`= %s AND `gateway` = %s",  2, $nzshpcrt_gateways[$num]['internalname']), ARRAY_A);

// var_dump($purchase_log);
// exit;

if (! get_option('ipaymu_key') != '')
    update_option('ipaymu_key', ' ');

function ipaymu_form()
{
    $ipaymu_key = get_option('ipaymu_key');
    $logo = "https://ipaymu.com/wp-content/themes/ipaymu-themes/assets/img/logo-ipaymu-glow.png";
    
    $select = array(
        '0'=>'Produkcyjny',
        '1'=>'Testowy'
    );
    
    
    $output = '<tr><td colspan="2" style="text-align:center;"><br/><a href="http://cashbill.pl/" target="_blank"><img src="'.$logo.'"></a><br/><br/></td></tr>';
    
    $output .= '<tr><td colspan="2"><strong>Informasi Kredensial</strong></td></tr>';
    $output .= '<tr><td><label for="ipaymu_key">API Key</label></td>';
    $output .= '<td><input name="ipaymu_key" id="ipaymu_key" type="text" value="' . $ipaymu_key . '"/><br/>';
    $output .= '<p class="description">Belum memiliki kode API? <a target="_blank" href="https://ipaymu.com/dokumentasi-api-ipaymu-perkenalan">Pelajari cara Mendapatkan API Key</a></p>';
    $output .= '</td></tr>';

    return $output;  
}

function ipaymu_form_submit()
{
    if ($_POST['ipaymu_key'] != null) {
        update_option('ipaymu_key', $_POST['ipaymu_key']);
    }
    
    return true;
}


function ipaymu_get_cancelurl($transaction_id, $session_id)
{
    $cancelurl = get_option('shopping_cart_url');

    $params = array('ipaymu_cancel' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $cancelurl);
}

function ipaymu_get_accepturl($transaction_id, $session_id)
{
    $accepturl = get_option('transact_url');

    $params = array('ipaymu_accept' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $accepturl);
}

function ipaymu_get_callbackurl($transaction_id, $session_id)
{
    $callbackurl = get_option('siteurl');

    $params = array('ipaymu_callback' => '1', 'transaction_id' => $transaction_id, 'sessionid' => $session_id);
    return add_query_arg($params, $callbackurl);
}

function ipaymu_gateway($seperator, $sessionid)
{
    global $wpdb, $wpsc_cart;
       
    $restUrl = 'https://my.ipaymu.com/payment.htm';
    $ordernumber = 'WPEC' . $wpdb->get_var("SELECT id FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE sessionid = '$sessionid' LIMIT 1;");
    	
    if(strlen($ordernumber) > 20)
    {
        $ordernumber = time();
    }
    
    $amount	= $wpsc_cart->total_price;
    $transaction_id = uniqid(md5(rand(1, 666)), true); // Set the transaction id to a unique value for reference in the system.

    $wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET processed = '1', transactid = '" . $transaction_id . "', date = '" . time() . "' WHERE sessionid = " . $sessionid . " LIMIT 1");

    $purchase_log = $wpdb->get_row($wpdb->prepare("SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE `sessionid`= %s LIMIT 1",  $sessionid), ARRAY_A);
    
    $usersql = $wpdb->prepare("SELECT `" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.value,
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`name`,
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.`unique_name` FROM
	`" . WPSC_TABLE_CHECKOUT_FORMS . "` LEFT JOIN
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "` ON
	`" . WPSC_TABLE_CHECKOUT_FORMS . "`.id =
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`form_id` WHERE
	`" . WPSC_TABLE_SUBMITED_FORM_DATA . "`.`log_id`=%s
	ORDER BY `" . WPSC_TABLE_CHECKOUT_FORMS . "`.`checkout_order`", $purchase_log['id']);

    $userinfo = $wpdb->get_results($usersql, ARRAY_A);
    
    foreach ($userinfo as $key => $value) {   
        if (($value['unique_name'] == 'billingemail') && $value['value'] != '') {
            $email = $value['value'];
        }
    }

    $params = array(
            'key'      => get_option('ipaymu_key'),
            'action'   => 'payment',
            'product'  => "Pembayaran order #". $ordernumber,
            'price'    =>  $amount, 
            'quantity' => 1,
            'comments' => '', // Optional
            'ureturn'  => ipaymu_get_accepturl($ordernumber, $sessionid),
            'unotify'  => ipaymu_get_callbackurl($ordernumber, $sessionid),
            'ucancel'  => ipaymu_get_cancelurl($ordernumber, $sessionid),
 
            'format'   => 'json' // Format: xml / json. Default: xml
        );
    
    $params_string = http_build_query($params);

    //open connection
    $ch = curl_init();
     
    curl_setopt($ch, CURLOPT_URL, $restUrl);
    curl_setopt($ch, CURLOPT_POST, count($params));
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params_string);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
     
    //execute post
    $request = curl_exec($ch);
     
    if ( $request === false ) {
        echo 'Curl Error: ' . curl_error($ch);
    } else {
     
        $result = json_decode($request, true);
     
        if( isset($result['url']) )
            header('location: '. $result['url']);
        else {
            echo "Request Error ". $result['Status'] .": ". $result['Keterangan'];
        }
    }
     
    //close connection
    curl_close($ch);
    exit();
}


function ipaymu_callback() {
    global $wpdb;

    if(isset($_POST['trx_id']) && isset($_POST['status']))
    {
    	// echo file_put_contents("test.txt", json_encode($_POST));

    	$sessionid 	=	$_GET['sessionid'];
    	$purchase_log = new WPSC_Purchase_Log($sessionid, 'sessionid');

    	// var_dump($purchase_log);

    	if($_POST['status']=="berhasil")
    	{
    		$purchase_log->set('processed', WPSC_Purchase_Log::ACCEPTED_PAYMENT);
    		$purchase_log->set('transactid', $ordernumber);
    		$purchase_log->set('notes', 'Transaksi berhasil dengan kode transaksi iPaymu '.$_POST['trx_id']);
    		$purchase_log->save();

    		$purchase_log->set( 'ipaymu_trx_id', $_POST['trx_id'] )->save();
    		echo 'OK';
    	} elseif($_POST['status']=="pending") {
			$purchase_log->set('processed', WPSC_Purchase_Log::ORDER_RECEIVED);
    		$purchase_log->set('transactid', $ordernumber);
    		// $purchase_log->set('notes', 'Transaksi '.$_POST['trx_id']).' Pending menunggu pembayaran non-member dari iPaymu';
    		$purchase_log->save();

    		$purchase_log->set( 'ipaymu_trx_id', $_POST['trx_id'] )->save();
    		echo 'PENDING';
    	}

    	exit();
    }
}

add_action('init', 'ipaymu_callback');

function add_admin_menu(){
    add_menu_page( 'iPaymu', 'iPaymu', 'manage_options','options-general.php?page=wpsc-settings&tab=gateway&payment_gateway_id=CashBill', '', plugins_url( 'img/cashbill_50x50.png', __FILE__ ), 56 );
}

add_action( 'admin_menu', 'add_admin_menu' );


// Activate Plugin
function pluginprefix_setup_post_type() {
    // register the "book" custom post type
    register_post_type( 'book', ['public' => 'true'] );
}
add_action( 'init', 'pluginprefix_setup_post_type' );
 
function plugin_install() {
    /*Register WP CRON*/
    if ( ! wp_next_scheduled( 'ipaymu_wpecommerce_cronjob' ) ) {
      wp_schedule_event( time(), 'hourly', 'ipaymu_wpecommerce_cronjob' );
    }

    add_action( 'ipaymu_wpecommerce_cronjob', 'ipaymu_wpecommerce_cronjob' );
}
register_activation_hook( __FILE__, 'plugin_install' );


// Deactivate Plugin
function plugin_deactivation() {
    wp_clear_scheduled_hook("ipaymu_wpecommerce_cronjob");
}

register_deactivation_hook( __FILE__, 'plugin_deactivation' );

function ipaymu_wpecommerce_cronjob() {
	global $wpdb;
	$myrows =	$wpdb->get_results( "SELECT * FROM ".WPSC_TABLE_PURCHASE_LOGS." where processed='2' AND gateway='iPaymuPaymentGateway'");
	
	foreach ($myrows as $key => $value) {
		// Check data to iPaymu
		$purchase_log = new WPSC_Purchase_Log($value->sessionid, 'sessionid');

		// get iPaymu ID
		$ipaymu 	=	$purchase_log->get('ipaymu_trx_id');
		if ($ipaymu!=null) {
			// Do Check to Ipaymu
			// 
			file_put_contents("test.txt", file_get_contents("https://my.ipaymu.com/api/CekTransaksi.php?format=json&key=".get_option('ipaymu_key')."&id=".$ipaymu));
			$content 	=	json_decode(file_get_contents("https://my.ipaymu.com/api/CekTransaksi.php?format=json&key=".get_option('ipaymu_key')."&id=".$ipaymu), true);

			if($content['status']==1)
			{
				$purchase_log->set('notes', 'Transaksi berhasil dengan kode transaksi iPaymu '.$_POST['trx_id']);
    			$purchase_log->save();
			}
		}
	}

}