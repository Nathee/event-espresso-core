<section id="display-event-datetime-<?php echo $dtt_row; ?>" class="datetime-summary">
	<span class="datetime-title"><?php echo $dtt_name; ?></span><span class="gear-icon clickable" data-datetime-row="<?php echo $dtt_row; ?>" data-context="datetime"></span><span data-datetime-row="<?php echo $dtt_row; ?>"  data-context="datetime" class="ticket-icon clickable"></span><span  data-context="datetime" data-datetime-row="<?php echo $dtt_row; ?>" class="<?php echo $clone_icon; ?>"></span><span  data-context="datetime" data-datetime-row="<?php echo $dtt_row; ?>" class="<?php echo $trash_icon; ?>"></span><span  data-context="datetime" data-datetime-row="<?php echo $dtt_row; ?>" class="datetime-tickets-sold"><?php printf( __('Total Tickets Sold: %s', 'event_espresso'), $dtt_sold ); ?></span>
</section>
<div style="clear:both;"></div>

<?php
/**
 * Template args in use
 *
 * $dtt_row
 * $dtt_name
 * $dtt_sold
 * $clone_icon
 * $trash_icon
 */