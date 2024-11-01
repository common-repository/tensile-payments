(function( $ ) {

	'use strict';

	/**

	 * All of the code for your public-facing JavaScript source
	 * should reside in this file.

	 * Note: It has been assumed you will write jQuery code here, so the
	 * $ function reference has been prepared for usage within the scope
	 * of this function.

	 * This enables you to define handlers, for when the DOM is ready:

	 * $(function() {
	 * });

	 * When the window is loaded:
	 * $( window ).load(function() {
	 * });

	 * ...and/or other possibilities.

	 * Ideally, it is not considered best practise to attach more than a
	 * single DOM-ready or window-load handler for a particular page.
	 * Although scripts in the WordPress core, Plugins and Themes may be
	 * practising this, we should strive to set a better example in our own work.

	 */

})( jQuery );

jQuery( document ).ready(function() {
    jQuery('form.checkout').on('change', 'input[name="payment_method"]', function() {
        jQuery(document.body).trigger('update_checkout');
    });

	jQuery('body').on('click', '.tensileplaceorderbtn', function(event) {
		jQuery('.tensileplaceorderbtn').addClass('tensile_btn_after_click');
		jQuery('#tensile-popup-background').css("display", "flex");
		jQuery('.tensile_btn_loader').show();

		var $inputs = jQuery('.woocommerce-checkout :input');
		// get an associative array of just the values.

		var values = {};
		var alertErrorMsg = "Please fill out the required fields:\n";

		/* New Validation */
		$inputs.each(function () {
			if (!jQuery(this).val()) {
				switch (this.name) {
					case 'billing_first_name':
						alertErrorMsg += " - First Name\n";
						break;
					case 'billing_last_name':
						alertErrorMsg += " - Last Name\n";
						break;
					case 'billing_email':
						alertErrorMsg += " - Email\n";
						break;
					case 'billing_phone':
						alertErrorMsg += " - Phone\n";
						break;
					case 'billing_address_1':
						alertErrorMsg += " - Street address\n";
						break;
					case 'billing_city':
						alertErrorMsg += " - Town / City\n";
						break;
					case 'billing_state':
						alertErrorMsg += " - State\n";
						break;
					case 'billing_postcode':
						alertErrorMsg += " - ZIP Code\n";
						break;
					case 'billing_country':
						alertErrorMsg += " - Country\n";
						break;
				}
				if (jQuery('#ship-to-different-address-checkbox').is(':checked')) {
					switch (this.name) {
						case 'shipping_first_name':
							alertErrorMsg += " - Shipping First Name\n";
							break;
						case 'shipping_last_name':
							alertErrorMsg += " - Shipping Last Name\n";
							break;
						case 'shipping_address_1':
							alertErrorMsg += " - Shipping Street address\n";
							break;
						case 'shipping_city':
							alertErrorMsg += " - Shipping Town / City\n";
							break;
						case 'shipping_state':
							alertErrorMsg += " - Shipping State\n";
							break;
						case 'shipping_postcode':
							alertErrorMsg += " - Shipping ZIP Code\n";
							break;
						case 'shipping_country':
							alertErrorMsg += " - Shipping Country\n";
							break;
					}
				}
			} else {
				values[this.name] = jQuery(this).val();
			}
		});
    if (alertErrorMsg !== "Please fill out the required fields:\n") {
			jQuery('.tensileplaceorderbtn').removeClass('tensile_btn_after_click');
			jQuery('#tensile-popup-background').css("display", "none");
			jQuery('.tensile_btn_loader').hide();
			alert(alertErrorMsg);
			event.preventDefault();
			return false;
    }
		/* End of New Validation */

		if(jQuery('#ship-to-different-address-checkbox').is(':checked')) {
			var ship_to_diff_add_checked = 'checked';
		} else {
			var ship_to_diff_add_checked = 'notchecked';
		}

		var ajaxurl = jQuery(".customajaxurl").val();

		jQuery.ajax({
			type: "POST",
			url: ajaxurl,
			//async: false,
			beforeSend: function () { },
			data: { action: 'tensile_create_woo_order', form_data: values, ship_to_diff_add_checked: ship_to_diff_add_checked },
			success: function(res) {
				jQuery('.tensile_btn_loader').hide();

				newdata = JSON.parse(res);

				var urlstatus = newdata.status;
				var message = newdata.message;

				if( urlstatus == 'ok') {
					var loaderimg_url = newdata.loaderimg
					var newTarget = newdata.url;
					var orderid = newdata.orderid;
					show_model_iframe(newTarget,orderid, loaderimg_url);
				} else {
					alert(message);
				}
				jQuery('.tensileplaceorderbtn').removeClass('tensile_btn_after_click');
				jQuery('#tensile-popup-background').css("display", "none");
				jQuery('.tensile_btn_loader').hide();
			}
		});
	}) /* tensileplaceorderbtn on click */

	/* cancel the order after click outside of model */
	jQuery('#tensilemodel').on('hidden.bs.modal', function (e) {
		var this_order_id = jQuery('.tensile_model_order_id').val();
	  	var ajaxurl = jQuery(".customajaxurl").val();
        jQuery.ajax({
          type: "POST",
          url: ajaxurl,
          beforeSend: function () { },
          data:{ action: 'tensile_cncl_woo_order', this_order_id: this_order_id },
          success: function(res) { }
        });
        jQuery('#tensile-popup-background').css("display", "none");
	});
	/* cancel the order after click outside of model */

});  // document ready 

function show_model_iframe(src, orderid, loaderimg_url) {
	jQuery('.tensile_model_order_id').val(orderid);
	jQuery('#tensile-popup').html('<img src="' + loaderimg_url + '" class="loaderimg_url_inpopup" height="450" width="830" style="border:0">');
	jQuery('#tensile-popup').html('<iframe id="tensile-popup-iframe" src="' + src + '" height="450" width="830" style="border:0">');
	jQuery('.modal-dialog').removeClass('modal-lg');
	jQuery('#tensilemodel').modal({backdrop: 'static'});
}