jQuery(document).ready(function($) {
    // Handle bid form submission
    $(document).on('submit', 'form.auction_form', function(e) {
        e.preventDefault();

        var form = $(this);
        var formData = form.serialize();
        var bidButton = form.find('button.place-bid-button');
        var bidAmountInput = form.find('input[name="bid_amount"]');
        var productId = form.find('input[name="product_id"]').val();
        var messagesContainer = form.closest('.auction-details').find('.auction-messages');
        if (!messagesContainer.length) {
            messagesContainer = $('<div class="auction-messages woocommerce-notices-wrapper"></div>').insertBefore(form);
        }
        messagesContainer.html(''); // Clear previous messages

        // Basic client-side validation (more robust validation is on server)
        var bidAmount = parseFloat(bidAmountInput.val());
        var minBid = parseFloat(bidAmountInput.attr('min'));

        if (isNaN(bidAmount) || bidAmount < minBid) {
            messagesContainer.html('<div class="woocommerce-error">' + wc_auctions_params.i18n_invalid_bid_amount + ' (' + wc_auctions_params.currency_symbol + minBid.toFixed(2) + ')</div>');
            return false;
        }

        bidButton.prop('disabled', true).text(wc_auctions_params.i18n_placing_bid);

        $.ajax({
            type: 'POST',
            url: wc_auctions_params.ajax_url,
            data: formData + '&action=place_auction_bid&nonce=' + wc_auctions_params.place_bid_nonce,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    messagesContainer.html('<div class="woocommerce-message">' + response.data.message + '</div>');
                    // Update current bid display
                    $('.auction-current-bid .price.current-bid-value').html(response.data.current_bid_html);
                    // Update minimum bid for next bid
                    if (response.data.next_min_bid) {
                       bidAmountInput.attr('min', response.data.next_min_bid_raw);
                       bidAmountInput.attr('placeholder', response.data.next_min_bid);
                       // Update label for minimum bid
                       $('label[for="bid_amount_' + productId + '"]').text(wc_auctions_params.i18n_min_bid + response.data.next_min_bid);
                    }
                    bidAmountInput.val(''); // Clear input

                    // Potentially update bid history here if implemented
                    // $(document).trigger('woocommerce_auction_bid_placed', [response.data]);

                } else {
                    messagesContainer.html('<div class="woocommerce-error">' + response.data.message + '</div>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                messagesContainer.html('<div class="woocommerce-error">' + wc_auctions_params.i18n_bid_error + ' (' + errorThrown + ')</div>');
            },
            complete: function() {
                bidButton.prop('disabled', false).text(wc_auctions_params.i18n_place_bid);
            }
        });

        return false;
    });

    // Handle Buy it Now form submission (basic, could be enhanced)
    $(document).on('submit', 'form.buy_now_form', function() {
        // This typically submits to the standard add to cart, but we might want AJAX later
        // For now, let it submit normally.
        // To make it AJAX, you'd preventDefault and handle similarly to bidding.
        $(this).find('button.buy-now-button').prop('disabled', true).text(wc_auctions_params.i18n_processing);
    });

    // Countdown timer (from previous step, ensuring it's here)
	function updateCountdown() {
		$('.auction-time-left .countdown').each(function() {
			var $this = $(this);
			var endTimeStr = $this.data('time');
			if (!endTimeStr) return;

			var endTime = new Date(endTimeStr).getTime();

			if (isNaN(endTime)) {
				$this.text(wc_auctions_params.i18n_countdown_ended); // Or N/A
				return;
			}

			var now = new Date().getTime();
			var distance = endTime - now;

			if (distance < 0) {
				$this.text(wc_auctions_params.i18n_countdown_ended);
				// Optionally trigger a page refresh or AJAX update for auction status
				// For example: $(document.body).trigger('wc_auction_ended', [productId]);
				// location.reload(); // simple refresh
				return;
			}

			var days = Math.floor(distance / (1000 * 60 * 60 * 24));
			var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
			var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
			var seconds = Math.floor((distance % (1000 * 60)) / 1000);

			$this.text(days + "d " + hours + "h " + minutes + "m " + seconds + "s ");
		});
	}

    if ($('.auction-time-left .countdown').length) {
        // Initialize countdowns
        $('.auction-time-left .countdown').each(function() {
            $(this).data('time', $(this).closest('.auction-details').find('.auction-end-date-meta').val());
        });
        updateCountdown();
	    setInterval(updateCountdown, 1000);
    }

});
