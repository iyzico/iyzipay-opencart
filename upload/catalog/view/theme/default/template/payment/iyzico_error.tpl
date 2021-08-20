<?php echo $header; ?>
<div id="error-not-found" class="container">

    <div class="row"><?php echo $column_left; ?>
        <div style="text-align:center;" id="content" class="  <?php      if ($column_left and $column_right){ ?>
        col-sm-6
        <?php }
        elseif ($column_left or $column_right){ ?>
        col-sm-9
        <?php }
        else { ?>
        col-sm-12
       <?php }
?>"><?php echo $content_top; ?>
            <h1 style="text-alicen:center;"><?php echo $error_title; ?></h1>
            <img src="<?php echo $error_icon; ?>" width="64" height="64" style="text-align:center;"/>
            <p style="text-align:center;"><?php echo $error_message; ?></p>
            <div class="buttons clearfix">
                <div class="pull-right"><a href="<?php echo $continue ?>" class="btn btn-primary"> <?php echo $homepage_button_text; ?> </a></div>
            </div>
            <?php echo $content_bottom; ?></div>
        <?php echo $column_right; ?></div>
</div>
<?php echo $footer; ?>
