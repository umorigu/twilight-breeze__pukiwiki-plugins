<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: tracker_summary_item.inc.php,v 0.2 2005/01/27 00:55:58 jjyun Exp $
//
// License   : GNU General Public License (GPL) 
// 

require_once( PLUGIN_DIR . 'tracker.inc.php');

function plugin_tracker_summary_item_convert()
{
	global $vars;

	$summary_item = '';
	$config = 'default';
	$list = 'list';
	$page = $refer = $vars['page'];
	$filter = '';
	$cache = TRACKER_LIST_CACHE_DEFAULT;

	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
		        case 5:
			        $cache = is_numeric($args[4]) ? $args[4] : $cache ;
		        case 4:
			        $filter = ($args[3] != '') ? $args[3] : NULL ;
		        case 3:
			        $summary_target = $args[2];
			case 2:
				$args[1] = get_fullname($args[1],$page);
				$page = is_pagename($args[1]) ? $args[1] : $page;
			case 1:
				$config = ($args[0] != '') ? $args[0] : $config;
				list($config,$list) = array_pad(explode('/',$config,2),2,$list);
		}
	}

	list( $title, $sum ) = plugin_tracker_summary_item_getsum($page,$refer,$config,$list,$summary_target, $filter,$cache);
	
	return $title . 'の合計：' . $sum;
}

function plugin_tracker_summary_item_inline()
{
	global $vars;

	$summary_item = '';
	$config = 'default';
	$list = 'list';
	$page = $refer = $vars['page'];
	$filter = '';
	$cache = TRACKER_LIST_CACHE_DEFAULT;

	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
		        case 6:

		        case 5:
			        $cache = is_numeric($args[4]) ? $args[4] : $cache ;
		        case 4:
			        $filter = ($args[3] != '') ? $args[3] : NULL ;
		        case 3:
			        $summary_target = $args[2];
			case 2:
				$args[1] = get_fullname($args[1],$page);
				$page = is_pagename($args[1]) ? $args[1] : $page;
			case 1:
				$config = ($args[0] != '') ? $args[0] : $config;
				list($config,$list) = array_pad(explode('/',$config,2),2,$list);
		}
	}
	list($title , $sum ) = plugin_tracker_summary_item_getsum($page,$refer,$config,$list,$summary_target, $filter,$cache);

	return $sum;
}

function plugin_tracker_summary_item_getsum($page, $refer, $config_name, $list,	$summary_item_name,
					    $filter_name=NULL, $cache=TRACKER_LIST_CACHE_DEFAULT)
{
	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' is not exist.";
	}

	$config->config_name = $config_name;

	if (!is_page($config->page.'/'.$list))
	{
		return "<p>config file '".make_pagelink($config->page.'/'.$list)."' not found.</p>";
	}

	if($filter_name != NULL)
	{
	        $filter_config = new Config('plugin/tracker/'.$config->config_name.'/filters');
		if(!$filter_config->read())
		{
		        // filterの設定がなされていなければ, エラーログを返す
		        return "<p>config file '".htmlspecialchars($config->page.'/filters')."' not found</p>";
		}
	        $list_filter = &new Tracker_list_filter($filter_config, $filter_name);
	}
	unset($filter_config);

	// $list 変数が別の意味で使いまわされているので注意!! (jjyun's comment)
	$list = &new Tracker_Summary_Item_sum_up_list($page,$refer,$config,$list,$filter_name,$cache);

	if($filter_name != NULL)
	{
		$list->rows = array_filter($list->rows, array($list_filter, 'filters') );
	}

	$title = isset( $list->fields[$summary_item_name]->title ) ? 
	  $list->fields[$summary_item_name]->title : $summary_item_name;
	$sum  = $list->sum_up($summary_item_name);

	return array( $title , $sum );
}


class Tracker_Summary_Item_sum_up_list extends Tracker_list
{
	function Tracker_Summary_Item_sum_up_list($page, $refer,$config,$list,$filter_name,$cache)
	{
		// 親クラスのコンストラクタを呼び出して初期化
                $this->Tracker_list($page,$refer,$config,$list,$filter_name,$cache);
	}

	function sum_up($summary_item_name)
	{
		if(count($this->rows) == 0)
		{
			return '';
		}

		$sum = 0;

		foreach($this->rows as $key=>$row)
		{
			if( $row['_match'])
			{
				continue;
			}

			// tracker の ページ内容の取得
			$this->items = $row;
			if( isset($this->items[$summary_item_name]) )
			{
				// 値の取得
				$value = $this->items[$summary_item_name];
				if( isset( $this->fields[$summary_item_name]) )
				{
					$value = $this->fields[$summary_item_name]->get_value($value);
				}

				$sum += $value;
			}
		}
		return $sum;
	}
}
?>
