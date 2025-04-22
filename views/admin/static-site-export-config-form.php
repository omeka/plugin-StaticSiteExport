<div class="field">
    <div class="two columns alpha">
        <label for="sites_directory_path"><?php echo __('Sites directory path'); ?></label>
    </div>
    <div class="inputs five columns omega">
        <p class="explanation"><?php echo __('Enter the path to the directory where your static sites will be saved. The path must exist and be writable by the web server.'); ?></p>
        <?php echo $this->formText(
            'sites_directory_path',
            get_option('static_site_export_sites_directory_path')
        ); ?>
    </div>
</div>
