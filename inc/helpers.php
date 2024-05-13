<?php

require_once 'CCBOrderControllerChild.php';
/**
 * add ajax action
 */
add_action(
	'init',
	function () {
		if (class_exists('\cBuilder\Classes\CCBOrderController')){
					\cBuilder\Classes\CCBOrderControllerChild::init();
		}
	}
);

