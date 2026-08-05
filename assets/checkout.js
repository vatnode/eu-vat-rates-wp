/**
 * Classic checkout only: WooCommerce refreshes the totals when its own address
 * fields change, but knows nothing about the VAT number field — without this,
 * a valid number would not remove VAT until the order was submitted.
 */
(function ($) {
  'use strict'

  var lastValue = null

  function refresh() {
    var value = $('#billing_vat_number').val()
    if (value === lastValue) {
      return
    }
    lastValue = value
    $(document.body).trigger('update_checkout')
  }

  $(function () {
    $(document.body).on('change blur', '#billing_vat_number', refresh)
  })
})(jQuery)
