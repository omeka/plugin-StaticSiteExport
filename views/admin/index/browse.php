<?php
echo head([
    'title' => __('Browse Static Sites') . ' ' . __('(%s total)', $total_results),
    'bodyclass' => 'static-sites browse',
]);
echo flash();
?>
<?php if ($total_results): ?>

<?php echo pagination_links(['attributes' => ['aria-label' => __('Top pagination')]]); ?>
<a href="<?php echo html_escape(url('static-site-export/export')); ?>" class="add full-width-mobile button green"><?php echo __('Export a Site'); ?></a>

<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th width="200px"><?php echo __('Name'); ?></th>
                <th><?php echo __('Status'); ?></th>
                <th><?php echo __('Owner'); ?></th>
                <th><?php echo __('Date Added'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (loop('StaticSite') as $staticSite): ?>
            <?php $owner = $staticSite->getOwner(); ?>
            <tr>
                <td><span class="title"><?php echo html_escape($staticSite->getName()); ?></a></span></td>
                <td><?php echo $staticSite->getStatus(); ?></td>
                <td><?php echo $owner ? sprintf('%s (%s)', $owner->name, $owner->username) : ''; ?></td>
                <td><?php echo format_date(metadata('static_site', 'added'), Zend_Date::DATETIME_MEDIUM); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<a href="<?php echo html_escape(url('static-site-export/export')); ?>" class="add full-width-mobile button green"><?php echo __('Export a Site'); ?></a>
<?php echo pagination_links(['attributes' => ['aria-label' => __('Bottom pagination')]]); ?>

<?php else: ?>

<h2><?php echo __('You have no static sites.'); ?></h2>
<p><?php echo __('Get started by exporting your first static site.'); ?></p>
<a href="<?php echo html_escape(url('static-site-export/export')); ?>" class="add big green button"><?php echo __('Export a Site'); ?></a>

<?php endif; ?>

<?php echo foot(); ?>
