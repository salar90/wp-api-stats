<?php
if ( ! defined( 'WPINC' ) ) {
	die;
} ?>

<table class="api-stats-table">
    <thead>
        <tr>
            <th><?php _e('Method', 'api-stats') ?></th>
            <th><?php _e('Route', 'api-stats') ?></th>
            <th><?php _e('Request Count', 'api-stats') ?></th>
            <th><?php _e('Average Duration', 'api-stats') ?></th>
        </tr>
    </thead>
    <tbody>
    <?php foreach($tableData as $row) {?>
        <tr>
            <td><?= $row->method ?></td>
            <td><?= $row->route ?></td>
            <td><?= $row->count ?></td>
            <td><?= floatval($row->average_duration) ?></td>

        </tr>
    <?php }  ?>
    </tbody>
</table>