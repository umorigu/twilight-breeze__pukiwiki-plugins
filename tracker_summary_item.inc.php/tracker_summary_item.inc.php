<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: tracker_summary_item.inc.php,v 0.8 2006/04/04 00:41:52 jjyun Exp $
//
// License   : GNU General Public License (GPL) 
// 
// 1秒の大きさの定義
define ('TRACKER_SUMMARY_ITEM_TIME_TYPE_SEC'  ,1);
define ('TRACKER_SUMMARY_ITEM_TIME_TYPE_MSEC' ,1000);
define ('TRACKER_SUMMARY_ITEM_TIME_TYPE_FILM' ,24);
define ('TRACKER_SUMMARY_ITEM_TIME_TYPE_PAL'  ,25);
define ('TRACKER_SUMMARY_ITEM_TIME_TYPE_NTSC' ,30);

require_once( PLUGIN_DIR . 'tracker_plus.inc.php');

function plugin_tracker_summary_item_init()
{
    plugin_tracker_plus_init();
}

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
	$cache = TRACKER_PLUS_LIST_CACHE_DEFAULT;

	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
		        case 7:
			        $output_format = ($args[6] != '') ? htmlspecialchars($args[6]) : NULL ;
		        case 6:
			        $input_format = ($args[5] != '') ? htmlspecialchars($args[5]) : NULL ;
		        case 5:
			        $target_name = ($args[4] != '') ? htmlspecialchars($args[4]) : NULL ;
		        case 4:
			        $cache = is_numeric($args[3]) ? $args[3] : $cache ;
		        case 3:
			        $filter = ($args[2] != '') ? htmlspecialchars($args[2]) : NULL ;
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
	$cache = TRACKER_PLUS_LIST_CACHE_DEFAULT;

	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
			case 8: // no-action(for inline plugin's bracket part)

			case 7:
			        $output_format = ($args[6] != '') ? htmlspecialchars($args[6]) : NULL ;
			case 6:
			        $input_format = ($args[5] != '') ? htmlspecialchars($args[5]) : NULL ;
		        case 5:
			        $target_name = ($args[4] != '') ? htmlspecialchars($args[4]) : NULL ;
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
		return $sum;
	}
	else
	{
		return $errmsg;
	}
}

function plugin_tracker_summary_item_getsum($page, $refer, $config_name, $list,
					    $filter_name=NULL, $cache=TRACKER_PLUS_LIST_CACHE_DEFAULT,
					    $target_name, $input_format, $output_format )
{
	$isSuccess = FALSE;
	$sum = 0;
	$title = '';
	$errmsg = '';

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		$errmsg = "config file '".htmlspecialchars($config_name)."' is not exist.";
		return array( $isSuccess, $errmsg, $title , $sum);
	}

	$config->config_name = $config_name;

	if (!is_page($config->page.'/'.$list))
	{
		$errmsg = "config file '" . make_pagelink($config->page.'/'.$list) . "' not found.";
		return array( $isSuccess, $errmsg, $title , $sum);
	}

	if ( $target_name == '' )
	{
		$errmsg = "summary target item is not setting.";
		return array( $isSuccess, $errmsg, $title , $sum);
	}

	// $list 変数が別の意味で使いまわされているので注意!! (jjyun's comment)
	$list = &new Tracker_plus_list($page,$refer,$config,$list,$filter_name,$cache);

	if($filter_name != NULL)
	{
		$filter_config = new Tracker_plus_FilterConfig('plugin/tracker/'.$config->config_name.'/filters');
		if( ! $filter_config->read() )
		{
			// filterの設定がなされていなければ, エラーログを返す
			$errmsg = "config file '".htmlspecialchars($config->page.'/filters')."' not found.";
			return array( $isSuccess, $errmsg, $title , $sum);
		}
		$list_filter = &new Tracker_plus_list_filter($filter_config, $filter_name);

		//		$list->rows = array_filter($list->rows, array($list_filter, 'filters') );
		// $list->rows = array_filter($list->rows, array($list_filter, 'judge') );

        $list_filter->list_filter($list);
		unset($filter_config);
	}

	$summary_calc = new Tracker_summary_item_calcuation($target_name, $input_format, $output_format);

	if( strlen($summary_calc->errMsg) > 0 )
	{
		$errmsg = $summary_calc->errMsg;
		return array( $isSuccess, $errmsg, $title , $sum);
        }

	

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
	var $errMsg = '';

	var $data_type; 

	var $sum = -1;
	var $count = -1;		

	function Tracker_summary_item_calcuation($target,$input_format,$output_format)
	{
	  
		list($target_name,$target_type) = array_pad(explode(':',$target),2,'VALUE');

		$this->target_name = $target_name;
		
		if( $target_type == 'TIME' )
		{
		  $this->data_type =
		    new Tracker_summary_item_DATA_TYPE_TIME($input_format,$output_format);
		}
		else
		{
		  $this->data_type =
		    new Tracker_summary_item_DATA_TYPE_VALUE();
		}

		$this->errMsg = $this->data_type->errMsg;
	}

	function sum_up($list)
	{
		$this->sum = 0;
		$this->count = 0;

		if(count($list->rows) == 0)
		{
			return;
		}

		// addition the field in each rows.
		foreach($list->rows as $key=>$row)
		{

		  	if(!TRACKER_PLUS_LIST_SHOW_ERROR_PAGE && !$row['_match'])
			{
				continue;
			}

                        // get contents for each tracker's page
			$this->count += 1;
                        $list->items = $row;
                        if( isset($list->items[$this->target_name]) )
                        {
                                // get_value
                                $value = $list->items[$this->target_name];
                                if( isset( $list->fields[$this->target_name]) )
                                {
                                        $value = $list->fields[$this->target_name]->get_value($value);
                                }

				$this->sum += $this->data_type->getValue($value);
                        }
		}
	}

	function get_sum()
	{
		return $this->data_type->getStr($this->sum);  
	}

	function get_count()
	{
		return $this->count;  
	}
}

class Tracker_summary_item_DATA_TYPE
{
	var $errMsg = '';

	function Tracker_summary_item_DATA_TYPE(){}
	function getValue($dataStr){} // in other words, this is parseStr(). 
	function getStr($sumValue){} // in other words, this is parseValue()
}

class Tracker_summary_item_DATA_TYPE_VALUE extends Tracker_summary_item_DATA_TYPE
{
	function getValue($dataStr) // in other words, this is parseStr(). 
	{
		return is_numeric($dataStr) ? $dataStr : 0;
	}

	function getStr($sumValue) // in other words, this is parseValue()
	{
		return $sumValue;
	}
}

class Tracker_summary_item_DATA_TYPE_TIME extends Tracker_summary_item_DATA_TYPE
{
	var $data_type;

	var $inputFormatStr;
	var $outputFormatStr;


	var $inputFormatPtn;
	var $outputFormatPtn;

	var $inputArgsPtn;
	var $outputArgsPtn;
  
	function Tracker_summary_item_DATA_TYPE_TIME($input_format='H:M:S',
						     $output_format='H:M:S')
	{

		$this->inputFormatStr = $input_format;  
		$this->outputFormatStr = $output_format;  

		// for input format 
		if(! $this->checkFormat($input_format) )
		{
			return FALSE;
		}

		// for output format 
		if(! $this->checkFormat($output_format) )
		{
			return FALSE;
		}
		

		$this->data_type = $this->getTimeDataType($input_format);
		$output_data_type = $this->getTimeDataType($output_format);

		if( $output_data_type != TRACKER_SUMMARY_ITEM_TIME_TYPE_SEC && 
		    $this->data_type != $output_data_type )
		{
			$this->errMsg = "入出力フォーマット間に、互換性のない指定がなされています。";
			return FALSE;
		}

		$this->makeFormatPtn($input_format, TRUE);
		$this->makeVariableArgsPtn($input_format, TRUE);

		$this->makeFormatPtn($output_format, FALSE);
		$this->makeVariableArgsPtn($output_format, FALSE);

		return TRUE;
	}

	function checkFormat($formatStr)
	{
		$formatReg = $formatStr;

		// === format check part ===

		// クォート文字の存在確認
		if( preg_match('/^.*[\'\"].*$/',$formatReg) ){ /* match character..." ' */
		  $this->errMsg = sprintf("時間書式文字列 %s にクォート文字(&nbsp;&#039;&nbsp;&quot;&nbsp;)を使用しないでください", $formatStr);
		  return FALSE;
		}

		/* 入力値と時刻書式との比較 */
		// - available time format - 
		// H:hour , M:minute, S:sec 
		// F:FILM, N:NTSC, P:PAL , m:millisec 

		// duplication check in format 
		$dupcheck=count_chars($formatReg);
		$f_dupcnt=0; // variable for checking for 1秒以下の単位
		$time_formats = array("H","M","S","m","F","P","N");
		foreach($time_formats as $unit)
		{
			if( isset( $dupcheck[ord($unit)] )) 
			{
				if($dupcheck[ord($unit)] > 1 )
				{
				  $this->errMsg = sprintf("時間書式文字列 %s に時刻指定子の重複があります。", $formatStr);
				  return FALSE;
			  }
			  
			// 1秒以下の単位の重複確認
			  if( $unit == "m" || $unit == "F" || 
			      $unit == "P" || $unit == "N" ) 
			  {
			      $f_dupcnt = $dupcheck[ord($unit)];
			  }
			}

		}
		if( $f_dupcnt > 1)
		{
			$this->errMsg = sprintf("時間書式文字列 %s に秒以下のフォーマットが重複してます。", $formatStr);
			return FALSE;
		}
		return TRUE;
	}

	function makeFormatPtn($formatStr,$isInput)
	{

		$numFormat='';
		$msecFormat='';

		if($isInput)
		{
		  $numFormat='%d';
		  $msecFormat='%d';
		}
		else
		{
		  $numFormat='%02d';
		  $msecFormat='%03d';
		}

		// 区切り文字が '/'(バックスラッシュ)の場合はエスケープ文字を付与する
		$formatPtn = preg_replace('/\//','\\/',$formatStr);

		$formatPtn = preg_replace('/(H|M|S)/',$numFormat,$formatPtn);
		$formatPtn = preg_replace('/(F|P|N)/',$numFormat,$formatPtn);
		$formatPtn = preg_replace('/(m)/',$msecFormat,$formatPtn);

		if($isInput)
		{
			$this->inputFormatPtn = $formatPtn;
		}
		else
		{
			$this->outputFormatPtn = $formatPtn;
		}
		return TRUE;
	}

	function getTimeDataType($formatStr)
	{
		$decisionArr=count_chars($formatStr);
		if( $decisionArr[ord("F")] == 1 )
		{
			return TRACKER_SUMMARY_ITEM_TIME_TYPE_FILM;
		}
		else if( $decisionArr[ord("P")] == 1 )
		{
			return TRACKER_SUMMARY_ITEM_TIME_TYPE_PAL;
		}
		else if( $decisionArr[ord("N")] == 1 )
		{
			return TRACKER_SUMMARY_ITEM_TIME_TYPE_NTSC;
		}
		else if( $decisionArr[ord("m")] == 1 )
		{
			return TRACKER_SUMMARY_ITEM_TIME_TYPE_MSEC;
		}
		else
		{
			return TRACKER_SUMMARY_ITEM_TIME_TYPE_SEC;
		}
	}

	function makeVariableArgsPtn($formatStr,$isInput)
	{
		// making timeArgs
		// 区切り文字が '/'(バックスラッシュ)の場合はエスケープ文字を付与する
		$timeArgs =  preg_replace('/\//','\\/',$formatStr);

		// 小文字の 'm' を先に処理する必要がある
		if( $isInput) 
		{
			$timeArgs =  preg_replace('/m/',',\$msec', $timeArgs);
			$timeArgs =  preg_replace('/F/',',\$film', $timeArgs);
			$timeArgs =  preg_replace('/P/',',\$pal',  $timeArgs);
			$timeArgs =  preg_replace('/N/',',\$ntsc', $timeArgs);
		}
		else
		{
			$timeArgs =  preg_replace('/(m|F|P|N)/',',\$ftime', $timeArgs);
		}

		$timeArgs =  preg_replace('/H/',',\$hour', $timeArgs);
		$timeArgs =  preg_replace('/M/',',\$min', $timeArgs);
		$timeArgs =  preg_replace('/S/',',\$sec', $timeArgs);

		$timeArgs =  preg_replace('/[^(?!,\$hour|,\$min|,\$sec|,\$msec|,\$film|,\$pal|,\$ntsc|,\$ftime)]+/','',$timeArgs);


		if( $isInput) 
		{
			$this->inputArgsPtn = $timeArgs;
		}
		else
		{
			$this->outputArgsPtn = $timeArgs;
		}
		return TRUE;
	}

	function getValue($dataStr) // in other words, this is parseStr(). 
	{
		// 区切り文字が '/'(バックスラッシュ)の場合はエスケープ文字を付与する
		$scanStr = preg_replace('/\//','\\/',$dataStr);
		
		// 定数が入力されていた場合
		if(strcmp($scanStr,$this->inputFormatPtn) == 0)
		{
			return 0;
		}

		$formatPtn = ",\"" . $this->inputFormatPtn . "\"";
		$dateArgs = $this->inputArgsPtn;

		$hour = $min = $sec = 0;
		$msec = $film = $pal = $ntsc = 0;

		$parseStr = "sscanf(\"$scanStr\" $formatPtn $dateArgs);";
		eval($parseStr);

		$time = ($hour * 60 + $min ) * 60 + $sec;
		switch ( $this->data_type )
		{
			case TRACKER_SUMMARY_ITEM_TIME_TYPE_SEC:  $ftime = 0; break;
			case TRACKER_SUMMARY_ITEM_TIME_TYPE_MSEC: $ftime = $msec;  break;
			case TRACKER_SUMMARY_ITEM_TIME_TYPE_FILM: $ftime = $film;  break;
			case TRACKER_SUMMARY_ITEM_TIME_TYPE_PAL:  $ftime = $pal;   break;
			case TRACKER_SUMMARY_ITEM_TIME_TYPE_NTSC: $ftime = $ntsc;  break;
			default: $ftime = 0;
		}

		$time = $this->data_type * $time + $ftime;
		return $time;
	}

	function getStr($sumValue) // in other words, this is parseValue()
	{
		$hour = $min = $sec = $ftime = -1;

		// 1秒以下の値の切り出し
		$ftime = $sumValue % $this->data_type ;
		$sec = floor($sumValue / $this->data_type) ;

		$outputFormater=count_chars($this->outputFormatStr);
		if( $outputFormater[ord("H")] > 0)
		{
			$hour = floor($sec / 3600);
			$sec = $sec % 3600;
		}
		if( $outputFormater[ord("M")] > 0)
		{
			$min = floor($sec / 60);
			$sec = $sec % 60;
		}

		$formatPtn = $this->outputFormatPtn;
		$dateArgs = $this->outputArgsPtn;
		$resultStr = '';
		$parseStr = "\$resultStr = sprintf(\"$formatPtn\" $dateArgs);";
		eval($parseStr);

		return $resultStr;
	}
}
?>
