<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: tracker_summary_item.inc.php,v 0.3 2005/02/05 04:03:02 jjyun Exp $
//
// License   : GNU General Public License (GPL) 
// 

require_once( PLUGIN_DIR . 'tracker.inc.php');

function plugin_tracker_summary_item_convert()
{
	global $vars;

	$target_name = '';
	$input_format = '';
	$output_format = '';
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
		        case 7:
			        $output_format = ($args[6] != '') ? $args[6] : NULL ;
		        case 6:
			        $input_format = ($args[5] != '') ? $args[5] : NULL ;
		        case 5:
			        $target_name = ($args[4] != '') ? $args[4] : NULL ;
		        case 4:
			        $cache = is_numeric($args[3]) ? $args[3] : $cache ;
		        case 3:
			        $filter = ($args[2] != '') ? $args[2] : NULL ;
			case 2:
				$args[1] = get_fullname($args[1],$page);
				$page = is_pagename($args[1]) ? $args[1] : $page;
			case 1:
				$config = ($args[0] != '') ? $args[0] : $config;
				list($config,$list) = array_pad(explode('/',$config,2),2,$list);
		}
	}

	list( $isSuccess, $errmsg, $title, $sum )
	  = plugin_tracker_summary_item_getsum($page,$refer,$config,$list,$filter,$cache,
					       $target_name, $input_format, $output_format);

	if( $isSuccess )
	{
		return $title . 'の合計：' . $sum;
	}
	else
	{
		return $errmsg;
	}
}

function plugin_tracker_summary_item_inline()
{
	global $vars;

	$target_name = '';
	$input_format = '';
	$output_format = '';
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
		        case 7:
			        $output_format = ($args[6] != '') ? $args[6] : NULL ;
		        case 6:
			        $input_format = ($args[5] != '') ? $args[5] : NULL ;
		        case 5:
			        $target_name = ($args[4] != '') ? $args[4] : NULL ;
		        case 4:
			        $cache = is_numeric($args[3]) ? $args[3] : $cache ;
		        case 3:
			        $filter = ($args[2] != '') ? $args[2] : NULL ;
			case 2:
				$args[1] = get_fullname($args[1],$page);
				$page = is_pagename($args[1]) ? $args[1] : $page;
			case 1:
				$config = ($args[0] != '') ? $args[0] : $config;
				list($config,$list) = array_pad(explode('/',$config,2),2,$list);
		}
	}

	list( $isSuccess, $errmsg, $title, $sum )
		= plugin_tracker_summary_item_getsum($page, $refer, $config, $list, $filter, $cache,
						     $target_name, $input_format, $output_format);

	if( $isSuccess )
	{
		return $title . 'の合計：' . $sum;
	}
	else
	{
		return $errmsg;
	}
}

function plugin_tracker_summary_item_getsum($page, $refer, $config_name, $list,
					    $filter_name=NULL, $cache=TRACKER_LIST_CACHE_DEFAULT,
					    $target_name, $input_format, $output_format )
{
	$isSuccess = FALSE;
	$sum = 0;
	$title = '';
	$errmsg = '';

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		$errmsg = "<p>config file '".htmlspecialchars($config_name)."' is not exist.";
		return array( $isSuccess, $errmsg, $title , $sum);
	}

	$config->config_name = $config_name;

	if (!is_page($config->page.'/'.$list))
	{
		$errmsg = "<p>config file '" . make_pagelink($config->page.'/'.$list) . "' not found.</p>";
		return array( $isSuccess, $errmsg, $title , $sum);
	}

	if ( $target_name == '' )
	{
		$errmsg = "<p>summary target item is not setting.</p>";
		return array( $isSuccess, $errmsg, $title , $sum);
	}

	if($filter_name != NULL)
	{
	        $filter_config = new Config('plugin/tracker/'.$config->config_name.'/filters');
		if(!$filter_config->read())
		{
		        // filterの設定がなされていなければ, エラーログを返す
		        $errmsg = "<p>config file '".htmlspecialchars($config->page.'/filters')."' not found</p>";
			return array( $isSuccess, $errmsg, $title , $sum);
		}
	        $list_filter = &new Tracker_list_filter($filter_config, $filter_name);
	}
	unset($filter_config);

	// $list 変数が別の意味で使いまわされているので注意!! (jjyun's comment)
	$list = &new Tracker_list($page,$refer,$config,$list,$filter_name,$cache);

	if($filter_name != NULL)
	{
		$list->rows = array_filter($list->rows, array($list_filter, 'filters') );
	}

	$summary_calc = new Tracker_summary_item_calcuation($target_name, $input_format, $output_format);
	$summary_calc->sum_up($list);

	if( $summary_calc->target_name == '_line' )
	{ 
		$title = 'リスト件数';
		$sum = $summary_calc->get_count();
	}
	else 
	{
		$title =
		  isset( $list->fields[$summary_calc->target_name]->title ) ? 
		  $list->fields[$summary_calc->target_name]->title :
		  $summary_calc->target_name;

		$sum = $summary_calc->get_sum();
	}
	$isSuccess = TRUE;

	return array( $isSuccess, $errmsg, $title , $sum);
}


class Tracker_summary_item_calcuation
{
	var $summary_config = array();
	var $target_name = '';
	var $input_format = '';
	var $output_format = '';
	var $sum = 0;
	var $count = 0;		

	function Tracker_summary_item_calcuation($target_name, $input_format, $output_format)
	{
		$this->target_name = $target_name;
		$this->input_format = $input_format;
		$this->output_format = $output_format;
	}

	function sum_up($list)
	{
		if(count($list->rows) == 0)
		{
			return '';
		}

		$this->sum = 0;
		$this->count = 0;

		// 各フィールドを加算
		foreach($list->rows as $key=>$row)
		{
			if( $row['_match'])
			{
				continue;
			}

                        // tracker の ページ内容の取得
                        $list->items = $row;
			$this->count += 1;
                        if( isset($list->items[$this->target_name]) )
                        {
                                // 値の取得
                                $value = $list->items[$this->target_name];
                                if( isset( $list->fields[$this->target_name]) )
                                {
                                        $value = $list->fields[$this->target_name]->get_value($value);
                                }

				// $this->sum += is_numeric($value) ? $value : 0 ;
				$this->sum += $value;
                        }
		}
	}

	function get_sum()
	{
		return $this->sum;  
	}

	function get_count()
	{
		return $this->count;  
	}
}
?>
