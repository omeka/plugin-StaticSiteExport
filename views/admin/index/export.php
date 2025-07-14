<?php
echo head([
    'title' => __('Export a Static Site'),
    'bodyclass' => 'static-sites export'
]);
echo flash();
?>

<form method="post" enctype="multipart/form-data" id="item-form" action="">
    <section class="seven columns alpha" id="edit-form">
        <div class="field">
            <?php echo $this->form->getElement('base_url'); ?>
        </div>
    </section>
    <section class="seven columns alpha" id="edit-form">
        <div class="field">
            <?php echo $this->form->getElement('theme'); ?>
        </div>
    </section>
    <section class="three columns omega">
        <div id="save" class="panel">
            <input type="submit" name="submit" class="submit big green button" id="export_static_site" value="<?php echo __('Export Static Site'); ?>" />
        </div>
    </section>
    <?php echo $csrf; ?>
</form>

<?php echo foot(); ?>
