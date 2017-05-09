/**
 * Give - Stripe Gateway Add-on ADMIN JS
 */
jQuery.noConflict();
(function ($) {

	//On DOM Ready
	$(function () {

		$('body').on('click', '.give-wepay-connect-temp-dismiss', function (e) {
			e.preventDefault();

			$('.give-wepay-connect-message').slideUp();

			var postData = {
				give_action: 'wepay_connect_dismiss'
			};

			$.post(ajaxurl, postData, function (response) {

				// No need to do anything with response.

			}, 'json');

		});

	});

})(jQuery);