(function ($) {
	'use strict';

	$('#pnp-payment-form').on('submit', function () {
		// Allow native submit to payment endpoint; basic empty checks handled by required attributes.
		return true;
	});
}(jQuery));
