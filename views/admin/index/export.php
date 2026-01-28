<?php
echo head([
    'title' => __('Export a Static Site'),
    'bodyclass' => 'static-sites export'
]);
echo flash();
?>

<form method="post" action="">
    <section class="seven columns alpha">
        <?php echo $form->getElement('base_url'); ?>
        <?php echo $form->getElement('theme'); ?>
        <?php echo $form->getElement('include_private'); ?>
    </section>
    <section class="three columns omega">
        <div id="save" class="panel">
            <input type="submit" name="submit" class="submit big green button" id="export_static_site" value="<?php echo __('Export Static Site'); ?>" />
        </div>
    </section>
    <?php echo $csrf; ?>
</form>

<?php echo foot(); ?>
