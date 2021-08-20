<?php echo $header; ?><?php echo $column_left; ?>
<div id="content">
    <div class="page-header">
        <div class="container-fluid">
            <div class="pull-right"></div>
            <h1><i class="fa fa-credit-card"></i> <?php echo $heading_title; ?> </h1>
        </div>
    </div>
    <div class="container-fluid">
        <div class="panel-body">
            <div class="alert alert-danger" role="alert">
                <h1> <?php echo $pwi_status_error; ?> </h1>
            </div>
            <h4> <?php echo $pwi_status_error_detail; ?> <br><br>
                <?php echo $dev_iyzipay_detail; ?> <a href="<?php echo $dev_iyzipay_opencart_link; ?>" target="_blank"> <?php echo $dev_iyzipay_opencart_link; ?></a></h4>
        </div>
    </div>
</div>