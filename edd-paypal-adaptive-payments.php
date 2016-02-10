<?php
/*
Plugin Name: Easy Digital Downloads - PayPal Adaptive Payments
Plugin URL: http://easydigitaldownloads.com/extension/paypal-pro-express
Description: Adds a payment gateway for PayPal Adaptive Payments
Version: 1.3.1
Author: Benjamin Rojas
Author URI: http://benjaminrojas.net
Contributors: benjaminprojas
*/

if ( !defined( 'EPAP_PLUGIN_DIR' ) ) {
  define( 'EPAP_PLUGIN_DIR', dirname( __FILE__ ) );
}

function epap_plugin_data( $variable ) {
  if ( !function_exists( 'get_plugin_data' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
  }
  $plugin_data = get_plugin_data( EPAP_PLUGIN_DIR . '/edd-paypal-adaptive-payments.php' );
  return $plugin_data[ $variable ];
}

define( 'EDD_EPAP_STORE_API_URL', 'https://easydigitaldownloads.com' );
define( 'EDD_EPAP_PRODUCT_NAME', 'PayPal Adaptive Payments' );
define( 'EDD_EPAP_VERSION', epap_plugin_data( 'Version' ) );

if( class_exists( 'EDD_License' ) && is_admin() ) {
  $license = new EDD_License( __FILE__, EDD_EPAP_PRODUCT_NAME, EDD_EPAP_VERSION, 'Benjamin Rojas', 'epap_license_key' );
}

// Load the text domain
function epap_load_textdomain() {

  // Set filter for plugin's languages directory
  $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';


  // Traditional WordPress plugin locale filter
  $locale        = apply_filters( 'plugin_locale',  get_locale(), 'epap' );
  $mofile        = sprintf( '%1$s-%2$s.mo', 'epap', $locale );

  // Setup paths to current locale file
  $mofile_local  = $lang_dir . $mofile;
  $mofile_global = WP_LANG_DIR . '/epap/' . $mofile;

  if ( file_exists( $mofile_global ) ) {
    // Look in global /wp-content/languages/edd-paypal-adaptive-payments folder
    load_textdomain( 'epap', $mofile_global );
  } elseif ( file_exists( $mofile_local ) ) {
    // Look in local /wp-content/plugins/edd-paypal-adaptive-payments/languages/ folder
    load_textdomain( 'epap', $mofile_local );
  } else {
    // Load the default language files
    load_plugin_textdomain( 'epap', false, $lang_dir );
  }

}
add_action( 'init', 'epap_load_textdomain' );

function epap_load_class() {
  require_once( EPAP_PLUGIN_DIR . '/paypal/PayPalAdaptivePayments.php' );
}
add_action( 'plugins_loaded', 'epap_load_class' );

function epap_register_post_status() {
  register_post_status( 'preapproval' );
  register_post_status( 'cancelled' );
}
add_action( 'init',  'epap_register_post_status', 110 );

function epap_preapproval_status( $statuses ) {
  $statuses['preapproval'] = __( 'Preapproval Pending', 'epap' );
  $statuses['cancelled'] = __( 'Cancelled', 'epap' );
  return $statuses;
}
add_filter( 'edd_payment_statuses', 'epap_preapproval_status' );

function epap_payments_column( $columns ) {
  global $edd_options;
  if ( isset( $edd_options['epap_preapproval'] ) && $edd_options['epap_preapproval'] ) {
    $columns['preapproval'] = __( 'Preapproval', 'epap' );
  }
  return $columns;
}
add_filter( 'edd_payments_table_columns', 'epap_payments_column' );

function epap_payments_column_data( $value, $payment_id, $column_name ) {
  $status = isset( $_GET['status'] ) ? $_GET['status'] : get_post_status( $payment_id );
  if ( $column_name == 'preapproval' ) {
    $preapproval_elements = array(
      'preapproval_key' => get_post_meta( $payment_id, '_edd_epap_preapproval_key', true ),
      'payment_id'      => $payment_id,
      'epap_process'    => 'preapproval',
      'status'          => $status
    );
    $cancel_preapproval_elements = array(
      'preapproval_key' => get_post_meta( $payment_id, '_edd_epap_preapproval_key', true ),
      'payment_id'      => $payment_id,
      'epap_process'    => 'cancel_preapproval',
      'status'          => $status
    );
    if( !get_post_meta( $payment_id, '_edd_epap_preapproval_paid', true ) && get_post_status( $payment_id ) == 'preapproval') {
      $value = '<a href="' . add_query_arg( $preapproval_elements, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) . '" class="button-secondary button">' . __( 'Process Payment', 'epap' ) . '</a><br /><a href="' . add_query_arg( $cancel_preapproval_elements, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) . '" class="button-secondary button">' . __( 'Cancel Preapproval', 'epap' ) . '</a>';
    } else {
      $value = '';
    }
  }
  return $value;
}
add_filter( 'edd_payments_table_column', 'epap_payments_column_data', 10, 3 );

function epap_payment_view_data( $payment_id ) {
  global $edd_options;
  $paypal_adaptive = new PayPalAdaptivePaymentsGateway();
  $preapproval_key = get_post_meta( $payment_id, '_edd_epap_preapproval_key', true );
  $preapproval_details = $paypal_adaptive->get_preapproval_details( $preapproval_key );
  $data = '';
  $status = isset( $_GET['status'] ) ? $_GET['status'] : get_post_status( $payment_id );
  if ( get_post_meta( $payment_id, '_edd_epap_preapproval_key', true ) && get_post_status( $payment_id ) == 'preapproval') {
    $preapproval_elements = array(
      'preapproval_key' => $preapproval_key,
      'payment_id'      => $payment_id,
      'epap_process'    => 'preapproval',
      'status'          => $status
    );
    $cancel_preapproval_elements = array(
      'preapproval_key' => $preapproval_key,
      'payment_id'      => $payment_id,
      'epap_process'    => 'cancel_preapproval',
      'status'          => $status
    );
    ob_start(); ?>
    <div class="preapproval-wrap">
      <h4><?php _e( 'Preapproval', 'epap' ); ?></h4>
      <span class="preapproval-key"><?php echo get_post_meta( $payment_id, '_edd_epap_preapproval_key', true ); ?></span>
      <h4><?php _e('Sender Email', 'epap'); ?></h4>
      <span class="sender-email"><?php echo isset( $preapproval_details['senderEmail'] ) ? $preapproval_details['senderEmail'] : __('Sender Email is Missing', 'epap'); ?></span>
      <?php if( !get_post_meta( $payment_id, '_edd_epap_preapproval_paid', true ) ): ?><br />
        <a href="<?php echo add_query_arg( $preapproval_elements, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ); ?>" class="button-secondary button"><?php _e( 'Process Payment', 'epap' ); ?></a>
        <a href="<?php echo add_query_arg( $cancel_preapproval_elements, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ); ?>" class="button-secondary button"><?php _e( 'Cancel Preapproval', 'epap' ); ?></a>
      <?php endif; ?>
    </div>
    <?php
    $data = ob_get_clean();
  }
  echo $data;
}
add_action( 'edd_payment_view_details', 'epap_payment_view_data' );

function epap_payment_table_view( $views ) {
  $payment_count      = wp_count_posts( 'edd_payment' );
  $preapproval_count  = '&nbsp;<span class="count">(' . $payment_count->preapproval . ')</span>';
  $cancelled_count    = '&nbsp;<span class="count">(' . $payment_count->cancelled . ')</span>';
  $current            = isset( $_GET['status'] ) ? $_GET['status'] : '';
  $views['preapproval'] = sprintf( '<a href="%s"%s>%s</a>', add_query_arg( 'status', 'preapproval', admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ), $current === 'preapproval' ? ' class="current"' : '', __( 'Preapproval Pending', 'edd' ) . $preapproval_count );
  $views['cancelled'] = sprintf( '<a href="%s"%s>%s</a>', add_query_arg( 'status', 'cancelled', admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ), $current === 'cancelled' ? ' class="current"' : '', __( 'Cancelled', 'edd' ) . $cancelled_count );
  return $views;
}
add_filter( 'edd_payments_table_views', 'epap_payment_table_view' );
// registers the gateway
function epap_register_paypal_adaptive_payments_gateway( $gateways ) {
  // Format: ID => Name
  $gateways['paypal_adaptive_payments'] = array( 'admin_label' => __( 'PayPal Adaptive Payments', 'epap' ), 'checkout_label' => __( 'PayPal', 'epap' ) );
  return $gateways;
}
add_filter( 'edd_payment_gateways', 'epap_register_paypal_adaptive_payments_gateway' );

function edd_paypal_adaptive_payments_remove_cc_form() {
  // we only register the action so that the default CC form is not shown
}
add_action( 'edd_paypal_adaptive_payments_cc_form', 'edd_paypal_adaptive_payments_remove_cc_form' );

function epap_process_payment( $purchase_data ) {
  global $edd_options;
  $credentials = epap_api_credentials();
  foreach ( $credentials as $cred ) {
    if ( is_null( $cred ) ) {
      edd_set_error( 0, __( 'You must enter your API keys in settings', 'epap' ) );
      edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
    }
  }
  
  $paypal_adaptive = new PayPalAdaptivePaymentsGateway();
  
  $return_url = get_permalink( $edd_options['success_page'] ) . '?payment-confirmation=paypalexpress';
  $cancel_url = function_exists( 'edd_get_failed_transaction_uri' ) ? edd_get_failed_transaction_uri() : get_permalink( $edd_options['purchase_page'] );
  
  $payment_data = array(
    'price'        => $purchase_data['price'],
    'date'         => $purchase_data['date'],
    'user_email'   => $purchase_data['user_email'],
    'purchase_key' => $purchase_data['purchase_key'],
    'currency'     => edd_get_currency(),
    'downloads'    => $purchase_data['downloads'],
    'cart_details' => $purchase_data['cart_details'],
    'user_info'    => $purchase_data['user_info'],
    'status'       => 'pending'
  );
  
  // record the pending payment
  $payment   = edd_insert_payment( $payment_data );
  $receivers = $paypal_adaptive->divide_total( apply_filters( 'epap_adaptive_receivers', trim($edd_options['epap_receivers']), $payment ), $purchase_data['price'] );
  
  $type      = 'pay';
  $token = md5( $payment . $purchase_data['user_email'] );
  if( isset( $edd_options['epap_preapproval'] ) ) {
    $response = $paypal_adaptive->preapproval( $payment, $purchase_data['price'], $token );
    $type = 'preapproval';
  }
  else {
    $response = $paypal_adaptive->pay( $payment, $receivers, $token );
  }
  $responsecode = strtoupper( $response['responseEnvelope']['ack'] );
  if ( ( $responsecode == 'SUCCESS' || $responsecode == 'SUCCESSWITHWARNING' ) ) {
    
    if( isset( $response['preapprovalKey']) ) {
      $preapproval_key = $response['preapprovalKey'];
      $preapproval_details = $paypal_adaptive->get_preapproval_details($preapproval_key);
      add_post_meta( $payment, '_edd_epap_preapproval_key', $preapproval_key );
      add_post_meta( $payment, '_edd_epap_paid', 0 );
      edd_empty_cart();
      header( 'Location: ' . epap_api_credentials( 'preapproval_url' ) . $preapproval_key );
      exit;
    }
    else {
      $pay_key = $response['payKey'];
      add_post_meta( $payment, '_edd_epap_pay_key', $pay_key );
      edd_empty_cart();
      header( 'Location: ' . epap_api_credentials( 'checkout_url' ) . $pay_key );
      exit;
    }
    
  } else {
    $title = $type == 'pay' ? __( 'Payment Failed', 'epap' ) : __( 'Preapproval Failed', 'epap' );
    $message = $type == 'pay' ? __( 'A payment failed to process: %s', 'epap' ) : __( 'A preapproval failed to process: %s', 'epap' );
    edd_record_gateway_error( $title, sprintf( $message, json_encode( $response ) ), $payment );
    // get rid of the pending purchase
    edd_update_payment_status( $payment, 'failed' );
    if( isset( $response['error'] ) ) {
      foreach ( $response['error'] as $key => $value ) {
        edd_set_error( $value['errorId'], $value['message'] );
      }
    }
    else {
      edd_set_error( 'unknown', __('An Unknown Error Occured', 'eppe' ) );
    }
    edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
  }

}
add_action( 'edd_gateway_paypal_adaptive_payments', 'epap_process_payment' );

// listens for a IPN request and then processes the order information
function epap_listen_for_ipn() {
  // IPN is only kept in case a user does not return to the site and trigger the updates.
  if ( isset( $_GET['ipn'] ) && $_GET['ipn'] == 'epap' && isset( $_GET['payment_id'] ) ) {
    switch ( $_POST['transaction_type'] ) {
      case 'Adaptive Payment PAY':
      case 'Adaptive Payment Pay':
        $pay_key = get_post_meta( $_GET['payment_id'], '_edd_epap_pay_key', true );
        if( $pay_key == $_POST['pay_key'] && get_post_status( $_GET['payment_id'] ) != 'publish' ) {
          edd_insert_payment_note( $_GET['payment_id'], sprintf( __( 'PayPal Transaction ID: %s', 'epap' ) , $pay_key ) );
          edd_update_payment_status( $_GET['payment_id'], 'publish' );
        }
        break;
      case 'Adaptive Payment PREAPPROVAL':
      case 'Adaptive Payment Preapproval':
        $preapproval_key = get_post_meta( $_GET['payment_id'], '_edd_epap_preapproval_key', true );
        if ( $preapproval_key == $_POST['preapproval_key'] ) {
          switch( $_POST['status'] ) {
            case 'CANCELED':
              edd_update_payment_status( $_GET['payment_id'], 'cancelled' );
              break;
            case 'ACTIVE':
              if ( get_post_status( $_GET['payment_id'] ) != 'publish' ) {
                edd_update_payment_status( $_GET['payment_id'], 'preapproval' );
              }
              break;
          }
          update_post_meta( $_GET['payment_id'], '_edd_epap_paid', $_POST['current_total_amount_of_all_payments'] );
        }
        break;
      //default:
      //  edd_record_gateway_error( __( 'PayPal Adaptive IPN Response', 'epap' ), sprintf( __( 'IPN Response for an unknown type: %s', 'epap' ), json_encode( $_POST ), $_GET['payment_id'] ) );
      //  break;
    }
    return true;
  }
  if(function_exists('edd_get_purchase_session')) {
    // This is a failsafe for IPNs that do not work properly
    $session = edd_get_purchase_session();
    if ( isset( $_GET[ 'payment_key' ] ) ) {
      $payment_key = urldecode( $_GET[ 'payment_key' ] );
    } else if ( $session ) {
      $payment_key = $session[ 'purchase_key' ];
    }
  
    // No key found
    if ( ! isset( $payment_key ) )
      return false;
  
    $payment_id = edd_get_purchase_id_by_key( $payment_key );
    $payment_email = edd_get_payment_user_email( $payment_id );
  
    $payment_token = md5( $payment_id . $payment_email );
  
    if ( isset( $_GET['preapproval_token'] ) ) {
      $token = $_GET['preapproval_token'];
    
      if ( $payment_token == $token && get_post_status( $payment_id ) != 'publish' ) {
        edd_update_payment_status( $payment_id, 'preapproval' );
      }
    }
    elseif ( isset( $_GET['payment_token'] ) ) {
      $token = $_GET['payment_token'];
    
      if( $payment_token == $token ) {
        $pay_key = get_post_meta( $payment_id, '_edd_epap_pay_key', true );
        if( get_post_status( $_GET['payment_id'] ) != 'publish' ) {
          edd_insert_payment_note( $payment_id, sprintf( __( 'PayPal Transaction ID: %s', 'epap' ) , $pay_key ) );
          edd_update_payment_status( $payment_id, 'publish' );
        }
      }
    
    }
  }
}
add_action( 'init', 'epap_listen_for_ipn' );


function epap_process_payment_settings() {
  global $edd_options;
  
  if ( isset($edd_options['epap_preapproval']) && $edd_options['epap_preapproval'] && isset( $_GET['epap_process'] ) &&  isset( $_GET['payment_id'] ) && isset( $_GET['preapproval_key'] ) ) {
    $payment_id = $_GET['payment_id'];
    $status = isset( $_GET['status'] ) ? $_GET['status'] : get_post_status( $payment_id );
    // Process Preapproval
    if ( $_GET['epap_process'] == 'preapproval' ) {
      $paypal_adaptive = new PayPalAdaptivePaymentsGateway();
      $preapproval_key  = get_post_meta( $payment_id, '_edd_epap_preapproval_key', true );
      $preapproval_details = $paypal_adaptive->get_preapproval_details( $preapproval_key );
      if( $preapproval_details ) {
        $sender_email     = $preapproval_details[ 'senderEmail' ];
        edd_record_gateway_error( __( 'Preapproval Details', 'epap' ), sprintf( __( 'Preapproval Details: %s', 'epap' ), json_encode( $preapproval_details ) ), $payment_id );
        $amount           = $preapproval_details[ 'maxTotalAmountOfAllPayments' ];
        $paid             = get_post_meta( $payment_id, '_edd_epap_paid', true ) ? get_post_meta( $payment_id, '_edd_epap_paid', true ) : 0;
        if ( $amount > $paid ) {
          $payment = $paypal_adaptive->pay_preapprovals( $payment_id, $_GET['preapproval_key'], $sender_email, $amount );
          if ( $payment ) {
            $responsecode = strtoupper( $payment['responseEnvelope']['ack'] );
            $paymentStatus = ! empty( $payment[ 'paymentExecStatus' ] ) ? strtoupper( $payment[ 'paymentExecStatus' ] ) : '';
            if ( ( $responsecode == 'SUCCESS' || $responsecode == 'SUCCESSWITHWARNING' ) && ( $paymentStatus == 'COMPLETED' ) ) {
              $pay_key = ! empty( $payment['payKey'] ) ? $payment['payKey'] : '';
              add_post_meta( $payment_id, '_edd_epap_pay_key', $pay_key );
              add_post_meta( $payment_id, '_edd_epap_preapproval_paid', true );
              edd_insert_payment_note( $payment_id, sprintf( __( 'PayPal Transaction ID: %s', 'epap' ) , $pay_key ) );
              edd_update_payment_status( $payment_id, 'publish' );
              $query_args = array(
                'status' => $status,
                'epap-message' => 'preapproval_processed'
              );
              header( 'Location: ' . add_query_arg( $query_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) );
              exit;
            } else {
              edd_record_gateway_error( __( 'Preapproval Failed', 'epap' ), sprintf( __( 'A preapproval payment failed to process: %s', 'epap' ), json_encode( $payment ) ), $payment_id );
              $query_args = array(
                'status' => $status,
                'epap-message' => 'preapproval_failed'
              );
              header( 'Location: ' . add_query_arg( $query_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) );
              exit;
            }
          } else {
            edd_record_gateway_error( __( 'Preapproval Failed', 'epap' ), sprintf( __( 'A preapproval payment failed to process: %s', 'epap' ), json_encode( $payment ) ), $payment_id );
            $query_args = array(
              'status' => $status,
              'epap-message' => 'preapproval_failed'
            );
            header( 'Location: ' . add_query_arg( $query_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) );
            exit;
          }
        } else {
          $errors = array(
            'sender_email' => $sender_email,
            'amount' => $amount,
            'paid' => $paid,
            'preapproval_key' => $preapproval_key
          );
          edd_record_gateway_error( __( 'Preapproval Failed', 'epap' ), sprintf( __( 'A preapproval payment failed to process: %s', 'epap' ), json_encode( $errors ) ), $payment_id );
        }
      } else {
        edd_record_gateway_error( __( 'Preapproval Details Failed', 'epap' ), sprintf( __( 'An error occured while trying to grab the Preapproval Details, perhaps a missing preapproval key: %s', 'epap' ), json_encode( $preapproval_details ) ), $payment_id );
      }
    }
    // Process a cancelation of the Preapproval
    if ( $_GET['epap_process'] == 'cancel_preapproval' ) {
      $paypal_adaptive = new PayPalAdaptivePaymentsGateway();
      $cancellation = $paypal_adaptive->cancel_preapprovals( $_GET['preapproval_key'] );
      if ( $cancellation ) {
        $responsecode = strtoupper( $cancellation['responseEnvelope']['ack'] );
        if ( ( $responsecode == 'SUCCESS' || $responsecode == 'SUCCESSWITHWARNING' ) ) {
          edd_update_payment_status( $payment_id, 'cancelled' );
          $query_args = array(
            'status' => $status,
            'epap-message' => 'cancellation_processed'
          );
          header( 'Location: ' . add_query_arg( $query_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) );
          exit;
        } else {
          edd_record_gateway_error( __( 'Preapproval Cancellation Failed', 'epap' ), sprintf( __( 'A preapproval cancellation failed to process: %s', 'epap' ), json_encode( $cancellation ) ), $payment_id );
          $query_args = array(
            'status' => $status,
            'epap-message' => 'cancellation_failed'
          );
          header( 'Location: ' . add_query_arg( $query_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) );
          exit;
        }
      } else {
        edd_record_gateway_error( __( 'Preapproval Cancellation Failed', 'epap' ), sprintf( __( 'A preapproval cancellation failed to process: %s', 'epap' ), json_encode( $cancellation ) ), $payment_id );
        $query_args = array(
          'status' => $status,
          'epap-message' => 'cancellation_failed'
        );
        header( 'Location: ' . add_query_arg( $query_args, admin_url( 'edit.php?post_type=download&page=edd-payment-history' ) ) );
        exit;
      }
    }
  }
}
add_action( 'init', 'epap_process_payment_settings' );

// adds the settings to the Payment Gateways section
function epap_add_settings( $settings ) {

  $epap_settings = array(
    array(
      'id' => 'epap_settings_header',
      'name' => '<strong>' . __( 'PayPal Adaptive Payments API Keys', 'epap' ) . '</strong>',
      'desc' => __( 'Configure your PayPal Adaptive Payments settings', 'epap' ),
      'type' => 'header'
    ),
    array(
      'id' => 'epap_live_api_username',
      'name' => __( 'Live API Username', 'epap' ),
      'desc' => __( 'Enter your live API username', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_live_api_password',
      'name' => __( 'Live API Password', 'epap' ),
      'desc' => __( 'Enter your live API password', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_live_api_signature',
      'name' => __( 'Live API Signature', 'epap' ),
      'desc' => __( 'Enter your live API signature', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_live_app_id',
      'name' => __( 'Live APP ID', 'epap' ),
      'desc' => __( 'Enter your live APP ID (You can get this by creating an account at www.x.com and registering a new APP)', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_test_api_username',
      'name' => __( 'Test API Username', 'epap' ),
      'desc' => __( 'Enter your test API username', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_test_api_password',
      'name' => __( 'Test API Password', 'epap' ),
      'desc' => __( 'Enter your test API password', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_test_api_signature',
      'name' => __( 'Test API Signature', 'epap' ),
      'desc' => __( 'Enter your test API signature', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_test_app_id',
      'name' => __( 'Test APP ID', 'epap' ),
      'desc' => __( 'Enter your test APP ID (This should almost always be: APP-80W284485P519543T)', 'epap' ),
      'type' => 'text',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_receivers_header',
      'name' => '<strong>' . __( 'PayPal Adaptive Payment Receivers', 'epap' ) . '</strong>',
      'desc' => __( 'Add in email addresses for each receiver and the percentage they will receive.', 'epap' ),
      'type' => 'header'
    ),
    array(
      'id' => 'epap_receivers',
      'name' => __( 'PayPal Adaptive Receivers', 'epap' ),
      'desc' => __( 'Enter each receiver email on a new line and add a pipe bracket with the percentage of the payout afterwards. NOTE: The percentages MUST equal 100% and you can only have a maximum of 6 receivers. Example:<br /><br />test@test.com|50<br />test2@test.com|30<br />test3@test.com|20', 'epap' ),
      'type' => 'textarea',
      'size' => 'regular'
    ),
    array(
      'id' => 'epap_payment_type',
      'name' => __( 'Payment Type', 'epap' ),
      'desc' => __( 'Select which type of payment type you want to use with PayPal Adaptive Payments', 'epap' ),
      'type' => 'radio',
      'options' => array(
        'chained' => __( 'Chained Payments', 'epap' ),
        'parallel' => __( 'Parallel Payments', 'epap' )
      )
    ),
    array(
      'id' => 'epap_preapproval',
      'name' => __( 'Require Pre-Approval', 'epap' ),
      'desc' => __( 'Enable this option to require Pre-Approval before charging a customer', 'epap' ),
      'type' => 'checkbox'
    ),
    array(
      'id' => 'epap_fees',
      'name' => __( 'Fee Payment' , 'epap' ),
      'desc' => __( 'Please select the party(ies) responsible to pay the PayPal fees for each transaction', 'epap' ),
      'type' => 'select',
      'options' => array(
        'EACHRECEIVER' => __( 'Each Receiver', 'epap' ),
        'SENDER' => __( 'Sender (only the customer)', 'epap' ),
        'PRIMARYRECEIVER' => __( 'Primary Receiver (first person in receivers list)', 'epap' ),
        'SECONDARYONLY' => __( 'Secondary Only (excluding the first person in receivers list)', 'epap' ),
      )
    )
  );

  // If EDD is at version 2.5 or later...
  if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
    // Use the previously noted array key as an array key again and next your settings
    $epap_settings = array( 'epap_paypal_adaptive_payments' => $epap_settings );
  }

  return array_merge( $settings, $epap_settings );
}
add_filter( 'edd_settings_gateways', 'epap_add_settings' );

function epap_add_settings_section( $section ) {
  $section['epap_paypal_adaptive_payments'] = __( 'PayPal Adaptive Payments', 'eten' );
  return $section;
}
add_filter( 'edd_settings_sections_gateways', 'epap_add_settings_section' );

function epap_fees( $fees_payer, $receivers ) {
  if ( !empty( $receivers ) && ( $fees_payer == 'PRIMARYRECEIVER' || $fees_payer == 'SECONDARYONLY' ) ) {
    return count( $receivers ) > 1 ? $fees_payer : 'EACHRECEIVER';
  }
  return $fees_payer;
}
add_filter( 'epap_fees', 'epap_fees', 10, 2 );

function epap_api_credentials( $credential=false ) {
  global $edd_options;

  if ( edd_is_test_mode() ) {
    $app_id           = isset( $edd_options['epap_test_app_id'] ) ? $edd_options['epap_test_app_id'] : null;
    $api_username     = isset( $edd_options['epap_test_api_username'] ) ? $edd_options['epap_test_api_username'] : null;
    $api_password     = isset( $edd_options['epap_test_api_password'] ) ? $edd_options['epap_test_api_password'] : null;
    $api_signature    = isset( $edd_options['epap_test_api_signature'] ) ? $edd_options['epap_test_api_signature'] : null;
    $api_end_point    = 'https://svcs.sandbox.paypal.com/AdaptivePayments/';
    $checkout_url     = 'https://www.sandbox.paypal.com/webscr?cmd=_ap-payment&paykey=';
    $preapproval_url  = 'https://www.sandbox.paypal.com/webscr?cmd=_ap-preapproval&preapprovalkey=';
  } else {
    $app_id           = isset( $edd_options['epap_live_app_id'] ) ? $edd_options['epap_live_app_id'] : null;
    $api_username     = isset( $edd_options['epap_live_api_username'] ) ? $edd_options['epap_live_api_username'] : null;
    $api_password     = isset( $edd_options['epap_live_api_password'] ) ? $edd_options['epap_live_api_password'] : null;
    $api_signature    = isset( $edd_options['epap_live_api_signature'] ) ? $edd_options['epap_live_api_signature'] : null;
    $api_end_point    = 'https://svcs.paypal.com/AdaptivePayments/';
    $checkout_url     = 'https://www.paypal.com/webscr?cmd=_ap-payment&paykey=';
    $preapproval_url  = 'https://www.paypal.com/webscr?cmd=_ap-preapproval&preapprovalkey=';
  }
  $data = array(
    'app_id'          => $app_id,
    'api_username'    => $api_username,
    'api_password'    => $api_password,
    'api_signature'   => $api_signature,
    'api_end_point'   => $api_end_point,
    'checkout_url'    => $checkout_url,
    'preapproval_url' => $preapproval_url
  );
  if ( $credential ) {
    $data = $data[ $credential ];
  }
  return $data;
}

function epap_admin_messages() {
  global $typenow;

  if ( 'download' != $typenow )
    return;

  $edd_access_level = current_user_can('manage_shop_settings');
  
  if ( isset( $_GET['epap-message'] ) && $_GET['epap-message'] == 'cancellation_failed' && current_user_can( $edd_access_level ) ) {
    add_settings_error( 'epap-notices', 'epap-cancellation-failed', __( 'Preapproval cancellation failed. Please see the gateway log for more information.', 'epap' ), 'error' );
  }
  
  if ( isset( $_GET['epap-message'] ) && $_GET['epap-message'] == 'preapproval_failed' && current_user_can( $edd_access_level ) ) {
    add_settings_error( 'epap-notices', 'epap-preapproval-failed', __( 'Preapproval payment failed to process. Please see the gateway log for more information.', 'epap' ), 'error' );
  }
  
  if ( isset( $_GET['epap-message'] ) && $_GET['epap-message'] == 'preapproval_processed' && current_user_can( $edd_access_level ) ) {
    add_settings_error( 'epap-notices', 'epap-preapproval-processed', __( 'Payment processed successfully.', 'epap' ), 'updated' );
  }
  
  if ( isset( $_GET['epap-message'] ) && $_GET['epap-message'] == 'cancellation_processed' && current_user_can( $edd_access_level ) ) {
    add_settings_error( 'epap-notices', 'epap-cancellation-processed', __( 'Preapproval payment cancelled successfully.', 'epap' ), 'updated' );
  }
  
  settings_errors( 'epap-notices' );
}
add_action( 'admin_notices', 'epap_admin_messages' );

function epap_process_preapprovals( $payment_id, $receivers ) {
  $processed        = false;
  $paypal_adaptive  = new PayPalAdaptivePaymentsGateway();
  $preapproval_key  = get_post_meta( $payment_id, '_edd_epap_preapproval_key', true );
  $preapproval_details = $paypal_adaptive->get_preapproval_details( $preapproval_key );
  if( $preapproval_details ) {
    $sender_email     = $preapproval_details[ 'senderEmail' ];
    $amount           = $preapproval_details[ 'maxTotalAmountOfAllPayments' ];
    $paid             = get_post_meta( $payment_id, '_edd_epap_paid', true ) ? get_post_meta( $payment_id, '_edd_epap_paid', true ) : 0;
    
    if ( $paid < $amount ) {
      $payment = $paypal_adaptive->pay_preapprovals( $payment_id, $preapproval_key, $sender_email, $amount, $receivers );
      if ( $payment ) {
        $responsecode = strtoupper( $payment['responseEnvelope']['ack'] );
        $paymentStatus = isset( $payment[ 'paymentExecStatus' ] ) ? strtoupper( $payment[ 'paymentExecStatus' ] ) : false;
        if ( ( $responsecode == 'SUCCESS' || $responsecode == 'SUCCESSWITHWARNING' ) && ( $paymentStatus == 'COMPLETED' ) ) {
          $pay_key = $payment[ 'payKey' ];
        
          add_post_meta( $payment_id, '_edd_epap_pay_key', $pay_key );
          add_post_meta( $payment_id, '_edd_epap_preapproval_paid', true );
        
          edd_update_payment_status( $payment_id, 'publish' );
          $processed = true;
        } else {
          edd_record_gateway_error( __( 'Preapproval Failed', 'epap' ), sprintf( __( 'A preapproval payment failed to process: %s', 'epap' ), json_encode( $payment ) ), $payment_id );
        }
      } else {
        edd_record_gateway_error( __( 'Preapproval Failed', 'epap' ), sprintf( __( 'A preapproval payment failed to process: %s', 'epap' ), json_encode( $payment ) ), $payment_id );
      }
    }
    else {
      $errors = array(
        'sender_email' => $sender_email,
        'amount' => $amount,
        'paid' => $paid,
        'preapproval_key' => $preapproval_key
      );
      edd_record_gateway_error( __( 'Preapproval Failed', 'epap' ), sprintf( __( 'A preapproval payment failed to process: %s', 'epap' ), json_encode( $errors ) ), $payment_id );
      edd_record_gateway_error( __( 'Preapproval Details', 'epap' ), sprintf( __( 'Preapproval Details: %s', 'epap' ), json_encode( $preapproval_details ) ), $payment_id );
    }
  }
  return $processed;
}
