/*
 * Order Received — Razorpay Standard Checkout (Phase 8, TEST MODE).
 *
 * The browser never decides the amount or the result:
 *  1. Ask Laravel to create/reuse the Razorpay order (amount from the saved order).
 *  2. Open Razorpay Checkout with that order id and the PUBLIC key id.
 *  3. Send Checkout's result to Laravel, which checks the signature and asks
 *     Razorpay whether the payment is really captured before marking it paid.
 */
(function () {
  'use strict';

  var button = document.getElementById('btnPayNow');
  if (!button) return;

  var message = document.querySelector('[data-payment-message]');
  var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  var originalLabel = button.textContent;

  function show(text, isError) {
    if (!message) return;
    message.hidden = false;
    message.textContent = text;   // textContent: never interpreted as HTML
    message.className = isError ? 'checkout-live-error' : 'checkout-live-note';
  }

  function busy(isBusy, label) {
    button.disabled = isBusy;
    button.textContent = isBusy ? label : originalLabel;
  }

  function post(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
      body: JSON.stringify(body || {}),
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (data) {
        if (response.status === 401) { window.location.reload(); }
        data.httpStatus = response.status;
        return data;
      });
    });
  }

  button.addEventListener('click', function () {
    if (typeof window.Razorpay !== 'function') {
      show('The payment window could not be loaded. Please check your connection and try again.', true);
      return;
    }
    busy(true, 'Preparing payment…');
    show('', false); message && (message.hidden = true);

    post(button.dataset.initiateUrl).then(function (data) {
      if (!data.success) {
        busy(false);
        show(data.message || 'Payment could not be started. You have not been charged.', true);
        if (data.httpStatus === 409) { setTimeout(function () { window.location.reload(); }, 2500); }
        return;
      }

      var settled = false;
      var failed = false;
      var checkout = new window.Razorpay({
        key: data.key,                 // public key id (never the secret)
        order_id: data.order_id,       // created by the server
        amount: data.amount,
        currency: data.currency,
        name: data.name,
        description: data.description,
        prefill: data.prefill,
        handler: function (result) {
          settled = true;
          busy(true, 'Confirming payment…');
          show('Confirming your payment with Razorpay…', false);
          post(button.dataset.verifyUrl, {
            razorpay_payment_id: result.razorpay_payment_id,
            razorpay_order_id: result.razorpay_order_id,
            razorpay_signature: result.razorpay_signature,
          }).then(function (outcome) {
            show(outcome.message || 'Payment status updated.', !outcome.success);
            setTimeout(function () { window.location.reload(); }, 1500);
          }).catch(function () {
            show('We could not confirm the payment yet. Please refresh this page in a minute — do not pay again.', true);
          });
        },
        modal: {
          ondismiss: function () {
            if (settled) return;
            busy(false);
            if (!failed) {   // after a failure, keep the failure message
              show('Payment was cancelled. Your order is still awaiting payment — you can try again.', true);
            }
          },
        },
      });

      checkout.on('payment.failed', function (response) {
        var paymentId = response && response.error && response.error.metadata && response.error.metadata.payment_id;
        failed = true;
        show('The payment failed. You have not been charged — you can try again.', true);
        if (paymentId) {
          post(button.dataset.failureUrl, { razorpay_payment_id: paymentId }).catch(function () {});
        }
      });

      busy(true, 'Payment window open…');
      checkout.open();
    }).catch(function () {
      busy(false);
      show('Network error — payment was not started. Please try again.', true);
    });
  });
})();
