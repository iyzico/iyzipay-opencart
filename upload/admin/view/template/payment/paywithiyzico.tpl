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
                <div>
                    <div style="width: 30%"><?php echo $text_paywithiyzico; ?>
                        <span><strong>v:</strong><?php echo $module_version; ?></span></div>
                </div>
                <form action="<?php echo $action; ?>" method="post" enctype="multipart/form-data" id="form-popular" class="form-horizontal">
                    <div class="form-group">
                        <label class="col-sm-2 control-label" for="input-status"><?php echo $extension_status; ?></label>
                        <div class="col-sm-10">
                            <select name="paywithiyzico_status" id="input-status" class="form-control">
                                <?php if ($paywithiyzico_status) { ?>
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
                        <label class="col-sm-2 control-label" for="input-order-status">
                            <span data-toggle="tooltip" title="<?php echo $order_status_after_payment_tooltip; ?>">
                                <?php echo $entry_order_status; ?>
                            </span>
                        </label>
                        <div class="col-sm-10">
                            <select name="paywithiyzico_order_status_id" id="input-order-status" class="form-control">
                                <?php foreach ($order_statuses as $order_status) { ?>
                                <?php if ($order_status['order_status_id'] == $paywithiyzico_order_status_id) { ?>
                                <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
                                <?php } else { if($paywithiyzico_order_status_id == null and  $order_status['order_status_id'] == 5){ ?>
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
                            <select name="paywithiyzico_cancel_order_status_id" id="input-cancel-order-status" class="form-control">
                                <?php foreach ($order_statuses as $order_status) { ?>
                                <?php if ($order_status['order_status_id'] == $paywithiyzico_cancel_order_status_id) { ?>
                                <option value="<?php echo $order_status['order_status_id']; ?>" selected="selected"><?php echo $order_status['name']; ?></option>
                                <?php } else { if( $paywithiyzico_cancel_order_status_id == null and $order_status['order_status_id'] == 7){ ?>
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
                            <input type="text" id="input-extension-sort" name="paywithiyzico_form_sort_order" value="<?php echo $paywithiyzico_form_sort_order; ?>" size="1" class="form-control"/>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<?php echo $footer; ?>
