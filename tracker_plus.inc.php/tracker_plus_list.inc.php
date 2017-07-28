<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: tracker_plus_list.inc.php,v 1.0 2005/05/05 19:50:14 jjyun Exp $
//
// Issue tracker list plugin (a part of tracker plugin)

require_once(PLUGIN_DIR . 'tracker_plus.inc.php');

function plugin_tracker_plus_list_init()
{
	if (function_exists('plugin_tracker_plus_init'))
		plugin_tracker_plus_init();
}
?>
