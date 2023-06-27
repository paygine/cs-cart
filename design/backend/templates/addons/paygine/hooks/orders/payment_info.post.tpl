<div class="control-group">
{if fn_paygine_order_can_be_complete($order_info)}
	<a class="btn"
		href="{"paygine.complete?order_id=`$order_info.order_id`"|fn_url}"
		data-ca-dialog-title="Complete"
	>{__("paygine.complete")}</a>
{/if}

{if fn_paygine_order_can_be_refund($order_info)}
	<a class="btn"
		href="{"paygine.refund?order_id=`$order_info.order_id`"|fn_url}"
		data-ca-dialog-title="Refund"
	>{__("paygine.refund")}</a>
{/if}
</div>