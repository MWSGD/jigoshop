<?php

namespace Jigoshop\Admin\Migration;

use Jigoshop\Entity\Customer;
use Jigoshop\Entity\Order\Status;
use Jigoshop\Entity\Product;
use Jigoshop\Helper\Render;
use Jigoshop\Service\OrderServiceInterface;
use WPAL\Wordpress;

class Orders implements Tool
{
	const ID = 'jigoshop_orders_migration';

	/** @var Wordpress */
	private $wp;
	/** @var \Jigoshop\Core\Options */
	private $options;
	/** @var OrderServiceInterface */
	private $orderService;

	public function __construct(Wordpress $wp, \Jigoshop\Core\Options $options, OrderServiceInterface $orderService)
	{
		$this->wp = $wp;
		$this->options = $options;
		$this->orderService = $orderService;
	}

	/**
	 * @return string Tool ID.
	 */
	public function getId()
	{
		return self::ID;
	}

	/**
	 * Shows migration tool in Migration tab.
	 */
	public function display()
	{
		Render::output('admin/migration/orders', array());
	}

	/**
	 * Migrates data from old format to new one.
	 */
	public function migrate()
	{
		$wpdb = $this->wp->getWPDB();

		$query = $wpdb->prepare("
			SELECT DISTINCT p.ID, pm.* FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type IN (%s, %s) AND p.post_status <> %s",
			array('shop_order', 'auto-draft'));
		$orders = $wpdb->get_results($query);

		for ($i = 0, $endI = count($orders); $i < $endI;) {
			$order = $orders[$i];

			// Update central order data
			$status = $this->wp->getTheTerms($order['ID'], 'shop_order_status');

			if (!empty($status)) {
				$status = $this->_transformStatus($status);
				$query = $wpdb->prepare("UPDATE {$wpdb->posts} SET post_status = %s WHERE ID = %d", array($status, $order['ID']));
				$wpdb->query($query);
			}

			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)", array($order['ID'], 'number', $order['ID'])));
			$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)", array($order['ID'], 'updated_at', time())));

			// Update columns
			do {
				switch ($orders[$i]['meta_key']) {
					case '_js_completed_date':
						$wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_key = %s, meta_value = %d WHERE meta_id = %d", array('completed_at', strtotime($orders[$i]['meta_value']), $orders[$i]['meta_id'])));
						break;
					case 'order_key':
						$wpdb->query($wpdb->prepare("UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_id = %d", array('key', $orders[$i]['meta_id'])));
						break;
					case 'order_data':
						$data = unserialize($orders[$i]['meta_value']);

						// Migrate customer
						$customer = $this->_migrateCustomer($data);
						$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES (%d, %s, %s)", array($orders[$i]['ID'], 'customer', serialize($customer))));

						// TODO: Migrate other data
						break;
					case 'customer_user':
						// TODO: Update user data with customer ID
						break;
					case 'order_items':
						// TODO: Migrate order items
						break;
				}
				$i++;
			} while ($i < $endI && $orders[$i]['ID'] == $order['ID']);
		}
	}

	private function _transformStatus($status)
	{
		$status = reset($status);

		switch ($status) {
			case 'pending':
				return Status::PENDING;
			case 'processing':
				return Status::PROCESSING;
			case 'completed':
				return Status::COMPLETED;
			case 'cancelled':
				return Status::CANCELLED;
			case 'refunded':
				return Status::REFUNDED;
			case 'on-hold':
			default:
				return Status::ON_HOLD;
		}
	}

	private function _migrateCustomer($data)
	{
		$customer = new Customer();

		if (!empty($data['billing_company'])) {
			$address = new Customer\CompanyAddress();
			$address->setCompany($data['billing_company']);
			$address->setVatNumber($data['billing_euvatno']);
		} else {
			$address = new Customer\Address();
		}

		$address->setFirstName($data['billing_first_name']);
		$address->setLastName($data['billing_last_name']);
		$address->setAddress($data['billing_address_1'].' '.$data['billing_address_2']);
		$address->setCountry($data['billing_country']);
		$address->setState($data['billing_state']);
		$address->setPostcode($data['billing_postcode']);
		$address->setPhone($data['billing_phone']);
		$address->setEmail($data['billing_email']);
		$customer->setBillingAddress($address);

		if (!empty($data['shipping_company'])) {
			$address = new Customer\CompanyAddress();
			$address->setCompany($data['shipping_company']);
		} else {
			$address = new Customer\Address();
		}
		$address->setFirstName($data['shipping_first_name']);
		$address->setLastName($data['shipping_last_name']);
		$address->setAddress($data['shipping_address_1'].' '.$data['shipping_address_2']);
		$address->setCountry($data['shipping_country']);
		$address->setState($data['shipping_state']);
		$address->setPostcode($data['shipping_postcode']);

		$customer->setShippingAddress($address);

		return $customer;
	}
}