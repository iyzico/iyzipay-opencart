<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right">
                <button type="submit" form="form-popular" data-toggle="tooltip" title="<?php echo $button_save; ?>" class="btn btn-primary"><i class="fa fa-save"></i></button>
                <a href="<?php echo $cancel; ?>" data-toggle="tooltip" title="<?php echo $button_cancel; ?>" class="btn btn-default"><i class="fa fa-reply"></i></a></div>
            <h1><i class="fa fa-credit-card"></i> <?php echo $heading_title; ?> </h1>
        </div>
    </div>
    <div class="container-fluid">
        <?php if ($error_warning) { ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?php echo $error_warning; ?>
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        </div>
        <?php } ?>
        <div class="panel panel-default">
            <div class="panel-body">
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-popular" class="form-horizontal">
                    <ul class="nav nav-tabs">
                        <li class="active"><a href="#tab-general" data-toggle="tab"> <?php echo $iyzico_settings; ?> </a></li>
                        <li><a href="#tab-iyzico-webhook" data-toggle="tab"> <?php echo $iyzico_webhook; ?> </a></li>
                    </ul>
                    <div class="tab-content">
                        <div class="tab-pane active" id="tab-general">
                            <div class="panel panel-primary">
                                <div class="panel-heading"><?php echo $text_iyzico; ?><span><strong>v: </strong><?php echo $module_version; ?> - opencart2x</span></div>
                                <div class="panel-body">
                                    <?php if ($api_status == "success" ){  ?>
                                    <strong> <?php echo $api_connection_text; ?>: </strong> <strong style="color:green;"> <?php echo $api_connection_success; ?>  </strong>
                                    <?php } else { ?>
                                    <strong> <?php echo $api_connection_text; ?>: </strong> <strong style="color:red;"> <?php echo $api_connection_failed; ?>  </strong>
                                    <?php } ?>
                                    <br>
                                    <?php if ($iyzico_webhook_url_key) { ?>
                                    <strong>Webhook URL: </strong> <?php echo $iyzico_webhook_url; ?>
                                    <br>
                                    <?php echo $iyzico_webhook_url_description; ?>
                                    <?php } else { ?>
                                    <strong style="color:red;"> <?php echo $iyzico_webhook_url_key_error; ?> </strong>
                                    <br>
                                    <strong>Webhook URL: </strong> <?php echo $iyzico_webhook_url; ?>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-status"><?php echo $extension_status; ?></label>
                                <div class="col-sm-10">
                                    <select name="iyzico_status" id="input-status" class="form-control">
                                        <?php if ($iyzico_status) { ?>
                                        <option value="1" selected="selected"><?php echo $text_enabled; ?></option>
                                        <option value="0"><?php echo $text_disabled; ?></option>
                                        <?php } else { ?>
                                        <option value="1"><?php echo $text_enabled; ?></option>
                                        <option value="0" selected="selected"><?php echo $text_disabled; ?></option>
                                        <?php } ?>
                                    </select>
                                    <?php if(!empty($error_status)){ ?>
                                    <div class="text-danger"><?php echo $error_status; ?></div>
                                    <?php }?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="iyzico_api_channel"><span data-toggle="tooltip" title="<?php echo $api_channel_tooltip; ?>"><?php echo $entry_api_channel; ?></span></label>
                                <div class="col-sm-10">
                                    <select name="iyzico_api_channel" class="form-control">
                                        <?php if ($iyzico_api_channel == "live") { ?>
                                        <option value="live" selected="selected"><?php echo $entry_api_live; ?></option>
                                        <option value="sandbox"><?php echo $entry_api_sandbox; ?></option>
                                        <?php } else { ?>
                                        <option value="live"><?php echo $entry_api_live; ?></option>
                                        <option value="sandbox" selected="selected"><?php echo $entry_api_sandbox; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-order-status">  <?php echo $entry_api_id_live; ?></label>
                                <div class="col-sm-10">
                                    <input type="text" name="iyzico_checkout_form_api_id_live" value="<?php echo $iyzico_checkout_form_api_id_live; ?>" class="form-control"/>
                                    <?php if ($error_api_id_live) { ?>
                                    <span class="text-danger"><?php echo $error_api_id_live; ?></span>
                                    <?php } ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-order-status"> <?php echo $entry_secret_key_live; ?></label>
                                <div class="col-sm-10">
                                    <input type="text" name="iyzico_checkout_form_secret_key_live" value="<?php echo $iyzico_checkout_form_secret_key_live; ?>" class="form-control"/>
                                    <?php if ($error_secret_key_live) { ?>
                                    <span class="text-danger"><?php echo $error_secret_key_live; ?></span>
                                    <?php } ?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="iyzico_form_class"><?php echo $entry_class; ?></label>
                                <div class="col-sm-10">
                                    <select name="iyzico_form_class" class="form-control">
                                        <?php if ($iyzico_form_class == "responsive") { ?>
                                        <option value="popup"><?php echo $entry_class_popup; ?></option>
                                        <option value="responsive" selected="selected"><?php echo $entry_class_responsive; ?></option>
                                        <?php } else { ?>
                                        <option value="popup" selected="selected"><?php echo $entry_class_popup ?></option>
                                        <option value="responsive"><?php echo $entry_class_responsive; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php if ($opencart_version > 2.1) {  ?>
                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="buyer_protection"><span data-toggle="tooltip" title="<?php echo $buyer_protection_tooltip; ?>"><?php echo $entry_buyer_protection; ?></span></label>
                                <div class="col-sm-10">
                                    <select name="iyzico_overlay_status" class="form-control">
                                        <option value=""><?php echo $general_select; ?></option>
                                        <?php if ($iyzico_overlay_status == "bottomLeft") { ?>
                                        <option value="bottomLeft" selected="selected"><?php echo $entry_overlay_bottom_left; ?></option>
                                        <option value="bottomRight"><?php echo $entry_overlay_bottom_right; ?></option>
                                        <option value="closed"><?php echo $entry_overlay_closed; ?></option>
                                        <?php } elseif ($iyzico_overlay_status == "bottomRight") { ?>
                                        <option value="bottomLeft"><?php echo $entry_overlay_bottom_left; ?></option>
                                        <option value="bottomRight" selected="selected"><?php echo $entry_overlay_bottom_right; ?></option>
                                        <option value="closed"><?php echo $entry_overlay_closed; ?></option>
                                        <?php } elseif ($iyzico_overlay_status == "closed") { ?>
                                        <option value="bottomLeft"><?php echo $entry_overlay_bottom_left; ?></option>
                                        <option value="bottomRight"><?php echo $entry_overlay_bottom_right; ?></option>
                                        <option value="closed" selected="selected"><?php echo $entry_overlay_closed; ?></option>
                                        <?php } else { ?>
                                        <option value="bottomLeft"><?php echo $entry_overlay_bottom_left; ?></option>
                                        <option value="bottomRight"><?php echo $entry_overlay_bottom_right; ?></option>
                                        <option value="closed"><?php echo $entry_overlay_closed; ?></option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>
                            <?php } ?>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-order-status">
                            <span data-toggle="tooltip" title="<?php echo $order_status_after_payment_tooltip; ?>">
                                <?php echo $entry_order_status; ?>
                            </span>
                                </label>
                                <div class="col-sm-10">
                                    <select name="iyzico_order_status_id" id="input-order-status" class="form-control">
                                        <?php foreach ($order_statuses as $order_status) { ?>
                                        <?php if ($order_status['order_status_id'] == $iyzico_order_status_id) { ?>
                                        <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
                                        <?php } else { if($iyzico_order_status_id == null and  $order_status['order_status_id'] == 2){ ?>
                                        <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
                                        <?php }
                                    else{ ?>
                                        <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                                        <?php } ?>
                                        <?php } ?>
                                        <?php } ?>
                                    </select>
                                    <?php if(!empty($error_order_status_id)){ ?>
                                    <div class="text-danger"><?php echo $error_order_status_id; ?></div>
                                    <?php }?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-cancel-order-status"><span data-toggle="tooltip" title="<?php echo $order_status_after_cancel_tooltip; ?>"><?php echo $entry_cancel_order_status; ?></span></label>
                                <div class="col-sm-10">
                                    <select name="iyzico_cancel_order_status_id" id="input-cancel-order-status" class="form-control">
                                        <?php foreach ($order_statuses as $order_status) { ?>
                                        <?php if ($order_status['order_status_id'] == $iyzico_cancel_order_status_id) { ?>
                                        <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
                                        <?php } else { if( $iyzico_cancel_order_status_id == null and $order_status['order_status_id'] == 7){ ?>
                                        <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
                                        <?php }
                                    else { ?>
                                        <option value="<?php echo $order_status['order_status_id']; ?>"><?php echo $order_status['name']; ?></option>
                                        <?php } ?>
                                        <?php } ?>
                                        <?php } ?>
                                    </select>
                                    <?php if(!empty($error_cancel_order_status_id)){ ?>
                                    <div class="text-danger"><?php echo $error_cancel_order_status_id; ?></div>
                                    <?php }?>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="col-sm-2 control-label" for="input-extension-sort"><?php echo $entry_sort_order; ?></label>
                                <div class="col-sm-10">
                                    <input type="text" id="input-extension-sort" name="iyzico_form_sort_order" value="<?php echo $iyzico_form_sort_order; ?>" size="1" class="form-control"/>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane" id="tab-iyzico-webhook">
                            <div class="col-sm-12">

                                <?php if ($locale == 'tr') { ?>
                                <h1>iyzico Opencart Webhooks</h1>
                                <p><strong>iyzico webhooks yapısının kullanılması ile, müşterilerinizin ödeme sonrasında yaşayabileceği internet, tarayıcı kaynaklı problemlerde siparişlerin opencart panel tarafına doğru bir şekilde iletilmesini sağlayabilirsiniz.</strong></p>
                                <p>Opencart'ta webhooks yapısını aktif edebilmek için aşağıdaki adımları uygulamanız gerekmektedir.</p>

                                <h1>Webhook Entegrasyon Adımları</h1>
                                <ol>
                                    <li>Aşağıdaki Webhook URL adresini kopyalayın.</li>
                                    <li>iyzico üye işyeri paneline <a href="https://merchant.iyzipay.com/" target="_blank">(https://merchant.iyzipay.com/)</a> giriş yaptıktan sonra, Sol menüden Ayarlar->Firma Ayarları tıklayın.</li>
                                    <li>Açılan sayfada İşyeri Bildirimleri bölümündeki URL alanına webhook URL adresinizi yapıştırın.</li>
                                    <li>İşyeri Bildirimleri bölümündeki “Ödeme bildirimlerini gönder” seçeneğini aktif edin.</li>
                                    <li>Kaydet’e tıklayın.</li>
                                </ol>

                                <?php } else { ?>
                                <h1>iyzico Opencart Webhooks</h1>
                                <p><strong>When a payment attempt is made, it is possible to receive the transaction result via notification.</strong></p>
                                <p>In order to activate the webhooks in Opencart, you need to follow the steps below.</p>

                                <h1>Webhook Integration Steps</h1>
                                <ol>
                                    <li>Copy webhook URL below.</li>
                                    <li>Sing in to  <a href="https://merchant.iyzipay.com/" target="_blank">(https://merchant.iyzipay.com/)</a> and click  Settings->Merchant Settings on left panel.</li>
                                    <li>Find merchant notifications area in the page, paste webhook URL to merchant notification url.</li>
                                    <li>Turn on Receive notifications for payments button.</li>
                                    <li>Save Settings.</li>
                                </ol>
                                <?php } ?>

                                <h1>Webhook URL</h1>

                                <?php if ($iyzico_webhook_url_key) { ?>
                                <?php echo $iyzico_webhook_url; ?>
                                <br>
                                <?php } else { ?>
                                <strong style="color:red;"> <?php echo $iyzico_webhook_url_key_error; ?> </strong>
                                <br>
                                <?php echo $iyzico_webhook_url; ?>
                                <?php } ?>

                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php echo $footer; ?>
