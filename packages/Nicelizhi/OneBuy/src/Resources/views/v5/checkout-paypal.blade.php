<script>
  function paypalInit() {
    const existingScript = document.querySelector('script[src*="paypal.com/sdk/js"]');
    if (existingScript) {
      existingScript.remove();
    }

    // Create new script element
    const script = document.createElement('script');
    script.type = 'text/javascript';
    script.async = true;

    const paypalAcc = '<?php echo $paypal_pay_acc; ?>';
    <?php if ($payment_airwallex_vault == 1) { ?>
      script.src = `https://www.paypal.com/sdk/js?client-id=${paypalAcc}&components=buttons,messages,funding-eligibility&vault=true&commit=true&currency=${currency}`;
      script.setAttribute('data-user-id-token', '<?php echo $paypal_id_token; ?>');
    <?php } else { ?>
      script.src = `https://www.paypal.com/sdk/js?client-id=${paypalAcc}&components=buttons,messages,funding-eligibility&currency=${currency}`;
    <?php } ?>
    if (script.readyState) {
      // IE
      script.onreadystatechange = function() {
        if (
          script.readyState === 'loaded' ||
          script.readyState === 'complete'
        ) {
          script.onreadystatechange = null
          if (airwallexChange == '2') {
            creatPaypalCardButton()
          } else {
            creatPaypalCardButton2()
          }
        }
      }
    } else {
      script.onload = function() {
        if (airwallexChange == '2') {
          creatPaypalCardButton()
        } else {
          creatPaypalCardButton2()
        }
      }
    }
    document.body.appendChild(script);
    $("#loading").hide();
  }

  function creatPaypalCardButton() {
    console.log('creatPaypalCardButton')
    let cartId = ''
    paypal.Buttons({
      style: {
        layout: 'horizontal',
        tagline: false,
        height: 55
      },

      onInit(data, actions) {

      },
      onError(err) {
        $("#loading").hide();
        console.log("paypal " + JSON.stringify(err));
      },
      onCancel: function(data) {},
      onClick() {},
      // Call your server to set up the transaction
      createOrder: function(data, actions) {
        $("#loading").show();
        productParams.payment_method = 'paypal'
        productParams.paypal_vault_id = "<?php echo $paypal_vault_id; ?>";
        productParams.payment_return_url = "<?php echo $payment_return_url; ?>";
        productParams.payment_cancel_url = "<?php echo $payment_cancel_url; ?>";
        paramsInit()
        crmTrack('add_pay')
        var url = '/api/onebuy/order/addr/after?currency={{ core()->getCurrentCurrencyCode() }}&_token={{ csrf_token() }}&time=' + new Date().getTime() + "&force=" + localStorage.getItem("force");
        return fetch(url, {
          body: JSON.stringify(productParams),
          method: 'POST',
          headers: {
            'content-type': 'application/json'
          }
        }).then(function(res) {
          return res.json();
        }).then(function(res) {
          $('#loading').hide();
          let data = res;
          console.log('------------------')
          console.log(data)
          console.log('------------------')
          cartId = data.cart.id
          if (data.order.statusCode === 200 || data.order.statusCode === 201) {
            let order_info = data.order.result;
            let cart_info = data.cart;

            console.log(order_info)
            localStorage.setItem("order_id", order_info.id);
            localStorage.setItem("cart_id", cart_info.id);

            if (order_info.status === "COMPLETED") {
              gotoSuccess(data, cartId);
              return
            }
            return order_info.id;
          } else {
            if (data.code == '202') {
              if (confirm(data.error) == true) {
                localStorage.setItem("force", 1);
              }
            }
            var pay_error = JSON.parse(data.error);
            var pay_error_message = pay_error.details;

            if (pay_error_message && pay_error_message.length) {
              alert(pay_error_message)
            }
          }
        }).catch(function(res) {
          $('#loading').hide();
        });
      },

      // Call your server to finalize the transaction
      onApprove: function(data, actions) {
        gotoSuccess(data, cartId)
      }
    }).render('#complete-btn-id');
  }

  function gotoSuccess(data, cartId) {
    console.log(cartId, 'cartId=====');
    $('#loading').show();
    var orderData = {
      paymentID: localStorage.getItem('order_id'),
      orderID: localStorage.getItem('order_id'),
      cartId: cartId,
    };
    var paypalParams = {
      first_name: shippingAddress.first_name || '',
      second_name: shippingAddress.last_name || '',
      email: shippingAddress.email || '',
      phone_full: shippingAddress.phone || '',
      address: shippingAddress.address1 || '',
      city: shippingAddress.city || '',
      country: shippingAddress.country || '',
      province: shippingAddress.state || '',
      code: shippingAddress.postcode || ''
    }
    var request_params = {
      client_secret: localStorage.getItem('order_id'),
      id: localStorage.getItem('order_id'),
      orderData: orderData,
      data: data,
      params: paypalParams,
      cart_id: cartId
    }
    var url = "/api/onebuy/order/status?_token={{ csrf_token() }}&currency={{ core()->getCurrentCurrencyCode() }}";
    return fetch(url, {
      method: 'post',
      body: JSON.stringify(request_params),
      headers: {
        'content-type': 'application/json'
      },
    }).then(function(res) {
      return res.json();
    }).then(function(res) {
      $('#loading').hide();
      if (res.result == 200) {
        localStorage.setItem('from', 'success');
        alert("@lang('onebuy::app.v4.Payment successful')");
        window.location.href = '/onebuy/checkout/v5/success/' + localStorage.getItem('order_id');
        return true;
      }
      if (res.error == 'INSTRUMENT_DECLINED') {}
    }).catch(function(res) {
      $('#loading').hide();
    });
  }

  function creatPaypalCardButton2() {
    console.log('creatPaypalCardButton2')
    let cartId = ''
    paypal.Buttons({
      style: {
        layout: 'horizontal',
        tagline: false,
        height: 55
      },

      onInit(data, actions) {

      },
      onError(err) {
        $("#loading").hide();
        console.log("paypal " + JSON.stringify(err));
      },
      onCancel: function(data) {},
      onClick() {},
      // Call your server to set up the transaction
      createOrder: function(data, actions) {
        $("#loading").show();
        productParams.payment_method = 'paypal'
        productParams.paypal_vault_id = "<?php echo $paypal_vault_id; ?>";
        productParams.payment_return_url = "<?php echo $payment_return_url; ?>";
        productParams.payment_cancel_url = "<?php echo $payment_cancel_url; ?>";
        //   productParams.payment_vault = localStorage.getItem('payment_vault') ? JSON.parse(localStorage.getItem('payment_vault')) : 0;
        // productParams.payment_vault = 1;
        paramsInit2()
        crmTrack('add_pay')
        var url = '/api/onebuy/order/addr/after?currency={{ core()->getCurrentCurrencyCode() }}&_token={{ csrf_token() }}&time=' + new Date().getTime() + "&force=" + localStorage.getItem("force");
        return fetch(url, {
          body: JSON.stringify(productParams),
          method: 'POST',
          headers: {
            'content-type': 'application/json'
          }
        }).then(function(res) {
          return res.json();
        }).then(function(res) {
          $('#loading').hide();
          let data = res;
          cartId = data.cart.id
          console.log('------------------')
          console.log(data)
          console.log('------------------')

          if (data.order.statusCode == 201) {
            let order_info = data.order.result;

            console.log(order_info)

            localStorage.setItem("order_id", order_info.id);

            if (order_info.status === "COMPLETED") {
              gotoSuccess(data, cartId);
              return
            }
            return order_info.id;
          } else {
            if (data.code == '202') {
              if (confirm(data.error) == true) {
                localStorage.setItem("force", 1);
              }
            }
            var pay_error = JSON.parse(data.error);
            var pay_error_message = pay_error.details;

            if (pay_error_message && pay_error_message.length) {
              alert(pay_error_message)
            }
          }
        }).catch(function(res) {
          $('#loading').hide();
        });
      },

      // Call your server to finalize the transaction
      onApprove: function(data, actions) {
        gotoSuccess(data, cartId)
      }
    }).render('#complete-btn-id2');
  }
</script>