/**
 * Block checkout only.
 *
 * WooCommerce sends contact fields to the Store API when the order is placed,
 * not while the shopper types — so a valid VAT number would not remove VAT from
 * the visible total until after checkout. Pushing the value to
 * cart/update-customer keeps what the shopper sees in step with what they pay.
 */
(function () {
  'use strict'

  var FIELD_ID = 'contact-vatnode-vat-number'
  var KEY = 'vatnode/vat-number'
  var lastSent = null

  function apiFetch() {
    return window.wp && window.wp.apiFetch ? window.wp.apiFetch : null
  }

  function receiveCart(cart) {
    var data = window.wp && window.wp.data
    if (!data || !cart) {
      return
    }
    var store = data.dispatch('wc/store/cart')
    if (store && typeof store.receiveCart === 'function') {
      store.receiveCart(cart)
    }
  }

  function push(value) {
    var fetcher = apiFetch()
    if (!fetcher || value === lastSent) {
      return
    }
    lastSent = value

    var body = { additional_fields: {} }
    body.additional_fields[KEY] = value

    fetcher({
      path: '/wc/store/v1/cart/update-customer',
      method: 'POST',
      data: body,
    })
      .then(receiveCart)
      // A rejected update means the totals stay as they are; the order itself
      // is validated server-side either way, so there is nothing to recover.
      .catch(function () {})
  }

  function bind(field) {
    field.addEventListener('blur', function () {
      push(field.value)
    })
    field.addEventListener('change', function () {
      push(field.value)
    })
  }

  var bound = false

  function attach() {
    if (bound) {
      return
    }
    var field = document.getElementById(FIELD_ID)
    if (field) {
      bound = true
      bind(field)
    }
  }

  // The checkout block renders after this script runs, and re-renders as the
  // shopper moves through it, so watch for the field appearing.
  document.addEventListener('DOMContentLoaded', function () {
    attach()
    var observer = new MutationObserver(attach)
    observer.observe(document.body, { childList: true, subtree: true })
  })
})()
