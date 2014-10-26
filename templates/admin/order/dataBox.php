<?php
use Jigoshop\Admin\Helper\Forms;
use Jigoshop\Entity\Order\Status;

/**
 * @var $order \Jigoshop\Entity\Order The order to display.
 * @var $billingFields array List of billing fields to display.
 * @var $shippingFields array List of shipping fields to display.
 * @var $customers array List of available customers.
 */
?>
<style type="text/css">
	#post-body-content, #minor-publishing { display:none }
</style>
<div class="panels jigoshop jigoshop-data" data-order="<?php echo $order->getId(); ?>">
	<input name="post_title" type="hidden" value="<?php echo $order->getTitle(); ?>" />

	<ul class="nav nav-tabs nav-justified" role="tablist">
		<li class="active"><a href="#order" role="tab" data-toggle="tab"><?php _e('Order', 'jigoshop'); ?></a></li>
		<li><a href="#billing-address" role="tab" data-toggle="tab"><?php _e('Billing address', 'jigoshop'); ?></a></li>
		<li><a href="#shipping-address" role="tab" data-toggle="tab"><?php _e('Shipping address', 'jigoshop'); ?></a></li>
		<!-- TODO: Maybe a filter to show/hide insignificant data? -->
	</ul>
	<noscript>
		<div class="alert alert-danger" role="alert"><?php _e('<strong>Warning</strong> Order panel will not work properly without JavaScript.', 'jigoshop'); ?></div>
	</noscript>
	<div class="tab-content form-horizontal">
		<div class="tab-pane active" id="order">
			<?php echo Forms::select(array(
				'name' => 'order[status]',
				'label' => __('Order status', 'jigoshop'),
				'value' => $order->getStatus(),
				'options' => Status::getStatuses(),
			)); ?>
			<?php echo Forms::select(array(
				'name' => 'order[customer]',
				'label' => __('Customer', 'jigoshop'),
				'value' => $order->getCustomer() ? $order->getCustomer()->getId() : '',
				'options' => $customers,
			)); ?>
			<?php echo Forms::textarea(array(
				'name' => 'post_excerpt',
				'label' => __("Customer's note", 'jigoshop'),
				'value' => $order->getCustomerNote(),
			)); ?>
		</div>
		<div class="tab-pane" id="billing-address">
			<?php $address = $order->getBillingAddress(); ?>
			<?php
			foreach ($billingFields as $field => $definition) {
				$definition['name'] = "order[billing][{$field}]";
				$definition['value'] = $address->get($field);
				echo Forms::field($definition['type'], $definition);
			}
			?>
		</div>
		<div class="tab-pane" id="shipping-address">
			<?php $address = $order->getShippingAddress(); ?>
			<?php
			foreach ($shippingFields as $field => $definition) {
				$definition['name'] = "order[shipping][{$field}]";
				$definition['value'] = $address->get($field);
				echo Forms::field($definition['type'], $definition);
			}
			?>
		</div>
	</div>
</div>
