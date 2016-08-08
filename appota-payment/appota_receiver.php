<?php

/**
 * APPOTA RECEIVER
 * Class này có chức năng như sau:
 * - Nhận thông tin giao dịch từ cổng Appota Pay
 * - Xác minh dữ liệu nhận được
 * - Ghi log các dữ liệu và thông báo nhận được
 * - Nếu xác minh thông tin Appota Pay gửi về thành công, cập nhật (hoàn thành) đơn hàng
 *
 * Copy Right by Appota Pay
 * @author tieulonglanh
 */

/**
 * CẤU HÌNH HỆ THỐNG
 * @const DIR_LOG   Đường dẫn file log. Thư mục mặc định là appota_receiver
 * @const FILE_NAME Tên file log mặc định.
 *
 */
define('APPOTA_PAY_TRANSACTION_STATUS_COMPLETED', 1);
Class WC_Appota_Receiver extends WC_Gateway_Appota_Payment {

    public function __construct() {
        parent::__construct();
    }

    public function checkValidRequest($data) {

        if (!$this->hasCurl()) {
            return array(
                'error_code' => 102,
                'message' => 'Kiểm tra curl trên server'
            );
        }
        if (!$this->verifySignature($data, $this->appota_api_secret)) {
            return array(
                'error_code' => 103,
                'message' => 'Sai signature gửi đến. Không thể thực hiện thanh toán!'
            );
        }

        return array(
            'error_code' => 0,
            'message' => 'Thông tin request thành công!'
        );
    }


    public function checkValidOrder($data) {
        $order_id = (int) $data['order_id'];
        $transaction_status = (int) $data['status'];
        $total_amount = floatval($data['amount']);
        
        $confirm = '';

        //Kiểm tra trạng thái giao dịch
        if ($transaction_status == APPOTA_PAY_TRANSACTION_STATUS_COMPLETED) {

            //Lấy thông tin order
            if ($order_id === 0) {
                $confirm .= "\r\n" . ' Không nhận được mã đơn hàng nào : ' . $order_id;
				return array(
                    'error_code' => 106,
                    'message' => $confirm
                );
            }

            //Kiểm tra sự tồn tại của đơn hàng
            $order_info = new WC_Order($order_id);
            if (empty($order_info)) {
                $confirm .= "\r\n" . ' Đơn hàng với mã đơn : ' . $order_id . ' không tồn tại trên hệ thống';
				return array(
                    'error_code' => 107,
                    'message' => $confirm
                );
            }

            //Kiểm tra số tiền đã thanh toán phải >= giá trị đơn hàng
            //Lấy giá trị đơn hàng
            if ($total_amount < $order_info->order_total) {
                $confirm .= "\r\n" . ' Số tiền thanh toán: ' . $total_amount . ' cho đơn hàng có mã : ' . $order_id . ' nhỏ hơn giá trị của đơn hàng.';
                $order_info->update_status('on-hold', sprintf(__('Thanh toán tạm giữ: %s', 'woocommerce'), $confirm));
				return array(
                    'error_code' => 108,
                    'message' => $confirm
                );
            }
        } else {
            $confirm .= "\r\n" . ' Trạng thái giao dịch:' . $transaction_status . ' chưa thành công với mã đơn hàng : ' . $order_id;
			return array(
                'error_code' => 109,
                'message' => $confirm
            );
        }
        
        if ($confirm == '') {
            return array(
                'error_code' => 0,
                'message' => 'Đơn hàng hợp lệ'
            );
        }
        
        return array(
            'error_code' => 104,
            'message' => $confirm
        );
    }

    /**
     * Kiểm tra xem server có hỗ trợ curl hay không.
     * @return boolean
     */
    private function hasCurl() {
        return function_exists('curl_version');
    }

    private function verifySignature($data, $secret_key) {
        $signature = $data['signature'];
	unset($data['signature']);
        unset($data['wc-api']);
	ksort($data);
        $data_str = implode('', $data);
        $secret = pack('H*', strtoupper(md5($secret_key)));
        $new_signature = hash_hmac('sha256', $data_str, $secret);
        if($signature == $new_signature) {
            return true;
        } else {
            return false;
        }
    }

}
