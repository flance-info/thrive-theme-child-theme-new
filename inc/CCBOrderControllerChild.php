<?php

namespace cBuilder\Classes;


use cBuilder\Classes\Database\Discounts;
use cBuilder\Classes\Database\Orders;
use cBuilder\Classes\Database\Payments;
use cBuilder\Classes\Database\Promocodes;
use cBuilder\Classes\Vendor\DataBaseModel;
use cBuilder\Helpers\CCBCleanHelper;
use cBuilder\Classes\CCBOrderController;

class CCBOrderControllerChild extends CCBOrderController{
	public static function init() {
		remove_all_actions('wp_ajax_create_cc_order');
		remove_all_actions('wp_ajax_nopriv_create_cc_order');
		CCBAjaxAction::addAction( 'create_cc_order', array( CCBOrderControllerChild::class, 'create' ), true );
		CCBAjaxAction::addAction( 'create_cc_order', array( CCBOrderControllerChild::class, 'create' ) );
		self::wpenqueu();
	}

	public static function wpenqueu(){
		wp_enqueue_script( 'calc-ajax',   get_stylesheet_directory_uri() .  '/assets/js/ajaxcall.js', array( 'jquery' ), time(), true );
	}
	public static function create() {
		check_ajax_referer( 'ccb_add_order', 'nonce' );

		/**  sanitize POST data  */
		$data = CCBCleanHelper::cleanData( (array) json_decode( stripslashes( $_POST['data'] ) ) );
		self::validate( $data );

		/**
		 *  if  order Id exist not create new one.
		 *  Used just for stripe if card error was found
		 */
		if ( ! empty( $data['orderId'] ) ) {
			$order = Orders::get( 'id', $data['orderId'] );
			if ( null !== $order ) {
				wp_send_json_success(
					array(
						'status'   => 'success',
						'order_id' => $data['orderId'],
					)
				);
				die();
			}
		}

		if ( ! empty( $data['promocodes'] ) ) {
			$promocodes = Discounts::get_promocodes_by_promocode( $data['id'], $data['promocodes'] );
			foreach ( $promocodes as $promocode ) {
				if ( ! empty( $promocode['promocode_count'] ) && $promocode['promocode_count'] > 0 ) {
					$promocode_count = intval( $promocode['promocode_count'] );
					$promocode_used  = ! empty( $promocode['promocode_used'] ) ? intval( $promocode['promocode_used'] ) : 0;
					if ( $promocode_count > $promocode_used ) {
						$update_data = array( 'promocode_used' => $promocode_used + 1 );
						Promocodes::update_discount_condition( $update_data, $promocode['promocode_id'] );
					} else {
						wp_send_json_error(
							array(
								'status'  => 'error',
								'message' => $promocode['promocode'] . ' is out of stock',
							)
						);
						die();
					}
				}
			}
		}

		if ( empty( self::$errors ) && 'POST' === $_SERVER['REQUEST_METHOD'] ) {

			$settings = CCBSettingsData::get_calc_single_settings( $data['id'] );
			if ( array_key_exists( 'num_after_integer', $settings['currency'] ) ) {
				self::$numAfterInteger = (int) $settings['currency']['num_after_integer'];
			}

			/** upload files if exist */
			if ( is_array( $_FILES ) ) {

				if ( ! function_exists( 'wp_handle_upload' ) ) {
					require_once ABSPATH . 'wp-admin/includes/file.php';
				}

				$order_details = $data['orderDetails'];
				$file_url      = array();

				/** upload all files, create array for fields */
				foreach ( $_FILES as $file_key => $file ) {
					$field_id    = preg_replace( '/_ccb_.*/', '', $file_key );
					$field_index = array_search( $field_id, array_column( $order_details, 'alias' ), true );

					/** if field not found continue */
					if ( false === $field_index ) {
						continue;
					}

					/** validate file by settings */
					$is_valid = self::validateFile( $file, $field_id, $data['id'] );

					if ( ! $is_valid ) {
						continue;
					}

					if ( ! array_key_exists( $field_id, $file_url ) ) {
						$file_url[ $field_id ] = array();
					}

					$file_info = wp_handle_upload( $file, array( 'test_form' => false ) );

					if ( ! empty( $file_info['file'] ) && str_contains( $file['type'], 'svg' ) ) {
						$svg_sanitizer = new \enshrined\svgSanitize\Sanitizer();
						$dirty_svg     = file_get_contents( $file_info['file'] ); //phpcs:ignore
						$clean_svg     = $svg_sanitizer->sanitize( $dirty_svg );
						file_put_contents( $file_info['file'], $clean_svg ); //phpcs:ignore
					}

					if ( $file_info && empty( $file_info['error'] ) ) {
						array_push( $file_url[ $field_id ], $file_info );
					}
				}

				foreach ( $order_details as $field_key => $field ) {
					if ( ! empty( $field['alias'] ) && isset( $file_url[ $field['alias'] ] ) && preg_replace( '/_field_id.*/', '', $field['alias'] ) === 'file_upload' ) {
						$order_details[ $field_key ]['options'] = wp_json_encode( $file_url[ $field['alias'] ] );
					}
				}
				$data['orderDetails'] = $order_details;
			}

			foreach ( $data['orderDetails'] as $key => $field ) {
				if ( isset( $field['alias'] ) && str_contains( $field['alias'], 'text_area' ) ) {
					$field[ $key ]['value'] = sanitize_text_field( $field[ $key ]['value'] );
				}
			}
			$orderDetailsString = '';
			foreach ( $data['orderDetails'] as $detail ) {
				$orderDetailsString .= "<span>" . $detail['title'] . ": ";
				$orderDetailsString .= $detail['extraView'] . "</span>";

			}

			$processedOrderDetails = preg_replace('/\n/', '<br>', $orderDetailsString);

			$order_data = array(
				'calc_id'       => $data['id'],
				'calc_title'    => get_post_meta( $data['id'], 'stm-name', true ),
				'status'        => ! empty( $data['status'] ) ? $data['status'] : Orders::$pending,
				'order_details' => wp_json_encode( $data['orderDetails'] ),
				'form_details'  => wp_json_encode( $data['formDetails'] ),
				'promocodes'    => wp_json_encode( $data['promocodes'] ?? array() ),
				'created_at'    => wp_date( 'Y-m-d H:i:s' ),
				'updated_at'    => wp_date( 'Y-m-d H:i:s' ),
			);

			$total = number_format( (float) $data['total'], self::$numAfterInteger, '.', '' );

			$payment_data = array(
				'type'       => ! empty( $data['paymentMethod'] ) ? $data['paymentMethod'] : Payments::$defaultType,
				'currency'   => array_key_exists( 'currency', $settings['currency'] ) ? $settings['currency']['currency'] : null,
				'status'     => Payments::$defaultStatus,
				'total'      => $total,
				'created_at' => wp_date( 'Y-m-d H:i:s' ),
				'updated_at' => wp_date( 'Y-m-d H:i:s' ),
			);

			$before_data = array(
				'payment_data' => $payment_data,
				'order_data'   => $order_data,
			);

			apply_filters( 'ccb_orders_before_create', $before_data );
			$unique_id = self::generate_unique_id();
			$order_data['id'] = $unique_id;

			$id = Orders::create_order( $order_data, $payment_data );

			self::send_order_confirmation_email($order_data, 	$id  );
			do_action( 'ccb_after_create_order', $order_data, $payment_data );
			$meta_data = array(
				'converted' => $data['converted'] ?? array(),
				'totals'    => isset( $data['totals'] ) ? wp_json_encode( $data['totals'] ) : array(),
			);

			update_option( 'calc_meta_data_order_' . $id, $meta_data );

			do_action( 'ccb_order_created', $order_data, $payment_data );


			wp_send_json_success(
				array(
					'status'   => 'success',
					'order_id' => $id,
					'processedOrderDetails' => wp_json_encode($processedOrderDetails),
				)
			);
		}
	}

	private static function generate_unique_id() {
		global $wpdb;
		$table = DataBaseModel::_table();
		$id    = rand( 100, 999999 );

		while ( $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE id = %d", $id ) ) > 0 ) {
			$id = rand( 100, 999999 );
		}

		return $id;
	}


    public static function send_order_confirmation_email($order_data, $order_id) {

        $email_to = '';
        $message = '';
		$fields = json_decode($order_data['form_details'], true);

        if (!empty(	$fields['fields'] )) {
            foreach ($fields['fields']  as $field) {

                if ($field['name'] == 'your-email') {
                    $email_to = $field['value'];
                }
                if ($field['name'] == 'your-message') {
					$message = 'Order Number: '.$order_id ." ";
                    $message .= $field['value'];
                }
            }
        }


        $subject = "New Order Created: Order ID " . $order_data['id'];
        $headers = array('Content-Type: text/html; charset=UTF-8');


        if (!empty($email_to) && !empty($message)) {
            wp_mail($email_to, $subject, nl2br($message), $headers);
        }
    }

}
