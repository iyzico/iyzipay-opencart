<style>
    .loading{width:40px;height:40px;background-color:#1E64FF;margin:100px auto;-webkit-animation:sk-rotateplane 1.2s infinite ease-in-out;animation:sk-rotateplane 1.2s infinite ease-in-out}@-webkit-keyframes sk-rotateplane{0%{-webkit-transform:perspective(120px)}50%{-webkit-transform:perspective(120px) rotateY(180deg)}100%{-webkit-transform:perspective(120px) rotateY(180deg) rotateX(180deg)}}@keyframes sk-rotateplane{0%{transform:perspective(120px) rotateX(0) rotateY(0);-webkit-transform:perspective(120px) rotateX(0) rotateY(0)}50%{transform:perspective(120px) rotateX(-180.1deg) rotateY(0);-webkit-transform:perspective(120px) rotateX(-180.1deg) rotateY(0)}100%{transform:perspective(120px) rotateX(-180deg) rotateY(-179.9deg);-webkit-transform:perspective(120px) rotateX(-180deg) rotateY(-179.9deg)}}.brand{margin:auto}.brand p{color:#1E64FF;text-align:center;margin-top:-100px}

                                                                                                                                                                                                                                                                                                                                                                                                                                    header.css-cc3hwu-InstallmentRadiosHeader.eltfla65::before{
                                                                                                                                                                                                                                                                                                                                                                                                                                        width: 0px!important;
                                                                                                                                                                                                                                                                                                                                                                                                                                    }

    header.css-cc3hwu-InstallmentRadiosHeader.eltfla65{
        box-shadow: none!important;
    }
</style>


<div id="loadingContainer">
    <div class="loading"></div>
    <div class="brand">
        <p>iyzico</p>
    </div>
</div>

<div class="iyzico_checkout_form_payment">
    <div class="iyzico-payment-form-wrapper" id="payment"></div>
    <div id="iyzipay-checkout-form" class="<?php echo $form_class; ?>"></div>
</div>
<?php
if($cart_total == 0) { ?>
<div class="iyzico_checkout_form_confirm">
    <div class="buttons">
        <div class="pull-right">
            <input type="button" value="<?php echo $button_confirm; ?>" id="button-confirm" class="btn btn-primary" data-loading-text="<?php echo $text_wait; ?>" />
        </div>
    </div>
</div>

<?php }
?>

<script type="text/javascript">
    $(document).ready(function(){

        if (typeof iyziInit != 'undefined') {
            delete iyziInit;
        }

        $(".iyzico_checkout_form_payment").hide();
        $.ajax({
            url: 'index.php?route=extension/payment/iyzico/getcheckoutformtoken',
            type: 'post',
            data: $('#payment :input'),
            dataType: 'json',
            cache: false,
            beforeSend: function() {
                $('#button-confirm').button('loading');
            },
            complete: function() {
                $('#button-confirm').button('reset');
            },
            success: function(json) {
                $('#loadingContainer').css('display','none');

                if (typeof json.checkoutFormContent != "undefined" && json.checkoutFormContent != "") {
                    $(".iyzico_checkout_form_payment").show();
                    $('.iyzico-payment-form-wrapper').append(json.checkoutFormContent);
                } else {
                    $(".iyzico_checkout_form_payment").show();
                    $('.iyzico-payment-form-wrapper').append('<div class="alert alert-danger"><button type="button" class="close" data-dismiss="alert">x</button>' + json.errorMessage + '</div>');
                }
            }
        });

    });
</script>