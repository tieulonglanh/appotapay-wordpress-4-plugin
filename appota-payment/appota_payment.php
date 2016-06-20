<?php
/**
 * Plugin Name: Appota Payment Gateway
 * Plugin URI: appotapay.com
 * Description: Thanh toán với Appota Payment 
 * - Tích hợp thanh toán qua appotapay.com cho các website bán hàng có đăng ký API.
 * - Thực hiện lấy thông tin tài khoản người bán                             *
 *   danh sách các phương thức thanh toán ngân hàng qua email
 * - Gửi thông tin thanh toán tới appotapay.com để xử lý việc thanh toán.
 * - Xác thực tính chính xác của thông tin được gửi về từ appotapay.com
 * Version: 1.0
 * Author: tieulonglanh
 * Author URI: http://appotapay.com/
 * License: Appotapay.com
 */
if (!defined('ABSPATH'))
    exit; // Exit if accessed directly
include(WP_PLUGIN_DIR . '/appota-payment/call_api.php');


if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    //Create class after the plugins are loaded
    add_action('plugins_loaded', 'init_appota_payment_gateway_class');

    //Init payment gateway class
    function init_appota_payment_gateway_class() {

        //Defining class gateway
        function add_appota_payment_gateway_class($methods) {
            $methods[] = 'WC_Gateway_Appota_Payment';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'add_appota_payment_gateway_class');

        class WC_Gateway_Appota_Payment extends WC_Payment_Gateway {

            public function __construct() {

                // Đặt ID cho phương thức thanh toán (cần Unique)
                $this->id = 'appota_payment';
                // Đặt language cho phương thức thanh toán
                $this->lang = 'vi';
                // Đặt Icon trong cấu hình cho phương thức
                $this->icon = plugins_url('images/appota-plugin-icon.png', __FILE__);
                // Không hiện trường ngoài thanh toán người dùng
                $this->has_fields = false;
                // Tên phương thức thanh toán
                $this->method_title = __('Appota Payment', 'woocommerce');
                // Mô tả phương thức thanh toán
                $this->method_description = "Phương thức thanh toán an toàn với chi phí thấp qua cổng thanh toán Appotapay.com";
                // Có dùng SSL verify khi gọi API Appota hay không. True: có, False: không
                $this->ssl_verify = False;

                // Gọi init_form_fields theo chuẩn Woocommerce
                $this->init_form_fields();
                // Thực hiện chuyển cấu hình init_form_fields thành form cấu hình trong admin
                $this->init_settings();

                // Lấy thông tin tiêu đề phương thức thanh toán
                $this->title = $this->get_option('title');
                // Mô tả phương thức thanh toán
                $this->description = $this->get_option('description');
                // Lấy tên cửa hàng bán
                $this->appota_merchant_name = $this->get_option('appota_merchant_name');
                // Lấy api key được lưu trong cấu hình
                $this->appota_api_key = $this->get_option('appota_api_key');
                // Lấy api secret được lưu trong cấu hình
                $this->appota_api_secret = $this->get_option('appota_api_secret');
                // Lấy api secret được lưu trong cấu hình
                $this->appota_api_private_key = $this->get_option('appota_api_secret');
                // Lấy tên log file.
                $this->appota_log_file = $this->get_option('appota_log_file');

                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_api_wc_gateway_appota_payment', array($this, 'payment_complete'));
                if (!$this->is_valid_for_use()) {
                    $this->enabled = false;
                }
            }

            /**
             * Cấu hình các trường dữ liệu cần lưu trong quản trị
             */
            public function init_form_fields() {
                parent::init_form_fields();
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => __('Sử dụng phương thức', 'woocommerce'),
                        'type' => 'checkbox',
                        'label' => __('Đồng ý', 'woocommerce'),
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title' => __('Tiêu đề', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Tiêu đề của phương thức thanh toán bạn muốn hiển thị cho người dùng.', 'woocommerce'),
                        'default' => __('Appota Payment', 'woocommerce'),
                        'desc_tip' => true,
                    ),
                    'description' => array(
                        'title' => __('Mô tả phương thức thanh toán', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Mô tả của phương thức thanh toán bạn muốn hiển thị cho người dùng.', 'woocommerce'),
                        'default' => __('Thanh toán an toàn với Appota Payment. Thực hiện thanh toán với thẻ cào hoặc tài khoản ngân hàng trực tuyến', 'woocommerce')
                    ),
                    'account_config' => array(
                        'title' => __('Cấu hình tài khoản', 'woocommerce'),
                        'type' => 'title',
                        'description' => '',
                    ),
                    'appota_merchant_name' => array(
                        'title' => __('Tên cửa hàng', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('Tên cửa hàng của người bán hàng sử dụng cổng thanh toán Appota Pay.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_api_key' => array(
                        'title' => __('Appota API Key', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('API Key của tài khoản.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_api_secret' => array(
                        'title' => __('Appota API Secret', 'woocommerce'),
                        'type' => 'text',
                        'description' => __('API Secret của tài khoản.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_api_private_key' => array(
                        'title' => __('Appota API Private Key', 'woocommerce'),
                        'type' => 'textarea',
                        'description' => __('Private Key để verify khi gọi API lên hệ thống.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                    ),
                    'appota_log_file' => array(
                        'title' => __('Tên file lưu log', 'woocommerce'),
                        'type' => 'text',
                        'description' => sprintf(__('Tên file lưu trữ log trong quá trình thực hiện thanh toán bằng cổng Appota Payment, truy cập file log <code>woocommerce/logs/appota-payment-%s.log</code>', 'woocommerce'), date("d-m-Y")),
                        'default' => 'appota-payment',
                        'desc_tip' => true,
                    ),
                );
            }

            /**
             * Kiểm tra xem loại tiền tệ hệ thống dùng thanh toán có phù hợp với cổng thanh toán không
             *
             * @access public
             * @return bool
             */
            function is_valid_for_use() {
                if (!in_array(get_woocommerce_currency(), apply_filters('woocommerce_appota_supported_currencies', array('VND'))))
                    return false;
                return true;
            }

            /**
             * Admin Panel Options
             * - Hiển thị quản trị cấu hình cho plugins
             *
             * @since 1.0.0
             */
            public function admin_options() {
                ?>
                <h3><?php _e('Thanh toán Appota Pay', 'woocommerce'); ?></h3>
                <strong><?php _e('Đảm bảo an toàn tuyệt đối cho mọi giao dịch.', 'woocommerce'); ?></strong>
                <?php if ($this->is_valid_for_use()) : ?>
                    <table class="form-table">
                        <?php
                        // Generate the HTML For the settings form.
                        $this->generate_settings_html();
                        ?>
                    </table><!--/.form-table-->

                <?php else : ?>
                    <div class="inline error">
                        <p>
                            <strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('Phương thức thanh toán Appota Pay chỉ hỗ trợ tiền Việt Nam Đồng trên gian hàng của bạn. Xin hãy đổi loại tiền thanh toán thành Việt Nam Đồng', 'woocommerce'); ?>
                        </p>
                    </div>
                <?php
                endif;
            }

            public function process_payment($order_id) {
                include(WP_PLUGIN_DIR . '/appota-payment/appota_logger.php');
                $logger = new WC_Appota_Logger();

                $order = new WC_Order($order_id);
                // Request sang Appota Payment để lấy đường dẫn trang thanh toán
                $result = $this->receive_payment_url($order);
                if (empty($result)) {
                    $message = "Không nhận được thông tin trả về!";
                    $logger->writeLog("Failure: " . $message);
                    wc_add_notice(__('Payment error:', 'woothemes') . " " . $message, 'error');
                    return;
                }
                // Nếu có lỗi, hiện thông báo lỗi thanh toán
                if ($result['error'] != 0) {
                    $logger->writeLog("Failure: " . $result['message']);
                    wc_add_notice(__('Payment error:', 'woothemes') . " " . $result['message'], 'error');
                    return;
                }
                // Nếu không có lỗi, chuyển hướng url sang trang thanh toán của Appota
                $appota_payment_url = $result['redirect_url'];
                $logger->writeLog("Success: Redirect Payment Url -> " . $appota_payment_url);
                return array(
                    'result' => 'success',
                    'redirect' => $appota_payment_url
                );
            }

            /**
             * Lấy thông tin đơn hàng, gửi sang cổng thanh toán để nhận đường dẫn redirect
             * @param mixed $order
             * @internal param order_id             Mã đơn hàng
             * @internal param total_amount         Giá trị đơn hàng
             * @internal param shipping_fee         Phí vận chuyển
             * @internal param tax_fee              Thuế
             * @internal param currency_code        Mã tiền tệ
             * @internal param order_description    Mô tả đơn hàng
             * @internal param url_success          Url trả về khi thanh toán thành công
             * @internal param url_cancel           Url trả về khi hủy thanh toán
             * @internal param url_detail           Url chi tiết đơn hàng
             * @internal param payer_name           Thông tin thanh toán
             * @internal param payer_email
             * @internal param payer_phone_no
             * @internal param shipping_address
             * @access public
             * @return array
             */
            function receive_payment_url($order) {
                $order_id_by_time = time() . "-" . $order->id;
                // Tạo đường dẫn nhận kết quả trả về sau khi thanh toán thành công
                $url_success = get_bloginfo('wpurl') . "/?wc-api=WC_Gateway_Appota_Payment&order_id=$order->id";
                // Tạo đường dẫn nhận kết quả trả về sau khi thanh toán bị dừng
                $url_cancel = $order->get_cancel_order_url();

                // Tạo mảng thông tin sẽ chuyển sang Appota pay để nhận đường link thanh toán
                $params['order_id_by_time'] = strval($order_id_by_time);
                $params['merchant_order_id'] = $order->id;
                $params['total_amount'] = strval($order->order_total);
                $params['shipping_fee'] = strval($order->order_shipping); //isset($method->no_shipping) ? $method->no_shipping : 0,
                $params['tax_fee'] = strval($order->order_tax);
                $params['currency_code'] = strval(get_woocommerce_currency());
                $params['order_description'] = preg_replace('/[^a-zA-Z0-9\_-]/', '', $order->customer_note);

                $params['url_success'] = $url_success;
                $params['url_cancel'] = $url_cancel;

                $params['payer_name'] = strval($order->billing_first_name . " " . $order->billing_last_name);
                $params['payer_email'] = strval($order->billing_email);
                $params['payer_phone_no'] = strval($order->billing_phone);
                $params['payer_address'] = strval($order->shipping_address_1);

                $config = array();
                $config['api_key'] = $this->appota_api_key;
                $config['lang'] = $this->lang;
                $config['secret_key'] = $this->appota_api_secret;
                $config['ssl_verify'] = $this->ssl_verify;
                $config['private_key'] = $this->appota_api_private_key;

                // Gọi resful API của Appota Pay
                $call_api = new Appota_CallApi($config);
                $result = $call_api->getPaymentUrl($params);
                return $result;
            }

            function payment_complete() {
                global $woocommerce;
                include(WP_PLUGIN_DIR . '/appota-payment/appota_receiver.php');
                include(WP_PLUGIN_DIR . '/appota-payment/appota_logger.php');

                $receiver = new WC_Appota_Receiver();
                $logger = new WC_Appota_Logger();

                $check_valid_request = $receiver->checkValidRequest($_GET);
                if ($check_valid_request['error_code'] == 0) {
                    $check_valid_order = $receiver->checkValidOrder($_GET);
                    if ($check_valid_order['error_code'] == 0) {
                        $order_id = (int) $_GET['merchant_order_id'];
                        $transaction_id = (int) $_GET['transaction_id'];
                        $total_amount = floatval($_GET['total_amount']);
                        $order = new WC_Order($order_id);
                        $comment_status = 'Thực hiện thanh toán thành công với đơn hàng ' . $order_id . '. Giao dịch hoàn thành. Cập nhật trạng thái cho đơn hàng thành công';
                        $order->add_order_note(__($comment_status, 'woocommerce'));
                        $order->payment_complete();
                        $order->update_status('completed');
                        $woocommerce->cart->empty_cart();
                        $order_status = 'complete';

                        $message = "Appota Pay xác nhận đơn hàng: [Order ID: {$order_id}] - [Transaction ID: {$transaction_id}] - [Total: {$total_amount}] - [{$order_status}]";
                        $logger->writeLog($message);
                        
                        wp_redirect(add_query_arg('utm_nooverride', '1', $this->get_return_url($order)));
                    } else {
                        $message = "Mã Lỗi: {$check_valid_order['error_code']} - Message: {$check_valid_order['message']}";
                        $logger->writeLog($message);
                        
                        $redirect_url = add_query_arg( 'wc_error', urlencode($message . " Hãy thanh toán lại!"), '/thanh-toan/');
                        wp_redirect($redirect_url);
                    }
                } else {
                    $message = "Mã Lỗi: {$check_valid_request['error_code']} - Message: {$check_valid_request['message']}";
                    $logger->writeLog($message);
                    $redirect_url = add_query_arg( 'wc_error', urlencode($message . " Hãy thanh toán lại!"), '/thanh-toan/');
                    wp_redirect($redirect_url);
                }
            }

        }

    }

} else {

    /**
     * Thông báo cài đặt hoặc kích hoạt Woocommerce nếu plugin chưa được cài đặt hoặc kích hoạt
     */
    function appota_payment_missing_woocommerce_notice() {
        $class = 'notice notice-error';
        $message = __('Hệ thống chưa cài đặt hoặc kích hoạt plugin Woocommerce! Bạn cần cài đặt hoặc kích hoạt Woocommerce để sử dụng plugin Appota Payment', 'woocomerce-missing-plugin');

        printf('<div class="%1$s"><p>%2$s</p></div>', $class, $message);
    }

    add_action('admin_notices', 'appota_payment_missing_woocommerce_notice');
}
