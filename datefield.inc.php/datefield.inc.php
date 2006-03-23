<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: datefield.inc.php,v 1.5 2006/03/23 21:10:25 jjyun Exp $
//

/* [概略の説明]
 * 日付入力補助画面付きフィールド提供プラグイン 
 *    for pukiwiki-1.4.6
 *
 * 日付入力を行わせたいテキストフィールドと、
 * 日付入力を行うためのカレンダーを表示するボタンを提供します。
 * カレンダーによる日付入力により,該当ページの更新が行われます。
 * カレンダーへの引数には、テキストフィールドへの入力値
 * 日付書式(デフォルト値(ブランク可能),[日付書式設定])
 * 
 * 制限：JavaScript を使っているのため,
 * Javascriptが使用できる環境でなければ動きません。
 * 
 * ブラウザ側の設定の他に、サーバ側の設定として
 * pukiwiki.ini.php の PKWK_ALLOW_JAVASCRIPT を以下の設定にする必要があります
 *   define('PKWK_ALLOW_JAVASCRIPT', 1);     // 0 or 1
 */ 

// 修正後のリロード時に、編集箇所へ表示箇所を移す
// 有効にする場合には、TRUE , 無効にする場合には FALSE を指定
define('DATEFIELD_JUMP_TO_MODIFIED_PLACE',FALSE); // TRUE or FALSE
// モード変更を適用する
define('DATEFIELD_APPLY_MODECHANGE',TRUE); // TRUE or FALSE

function plugin_datefield_init()
{
	$cfg = array(
			'_datefield_cfg' => array (
				'editImage'  => 'paraedit.png',
				'referImage' => 'close.png',
				)
			);
	set_plugin_messages($cfg);

	switch( LANG ) 
	{
	case 'ja' :
	  $msg = plugin_datefield_init_ja();
	default:
	  $msg = plugin_datefield_init_en();
	}
	set_plugin_messages($msg);
}

function plugin_datefield_init_ja()
{
	$msg = array(
		'_datefield_msg' => array(
			'format_not_effective'        => "日付書式文字列 %s にクォート文字(&nbsp;&#039;&nbsp;&quot;&nbsp;)を使用しないでください。" ,
			'input_pattern_not_effective' =>  "入力値が日付書式 %s と合致しません。<br />"
												+ "ゼロパディングも考慮してください。",
			'datecheck_irregular_error' => "日付確認時の想定外エラーです。<br />" 
												+ "確認対象文字列: %s <br />"
												+ "日付書式文字列: %s <br />"
												+ "引受変数文字列: %s <br />"
												+ "パース書式状態: %s <br />"
												+ "読み取り状態:year = %s, month = %s, day = %s<br />",
			'datecheck_not_effective_month' => "月の指定 %s が通常取り得る値から外れています。", 
			'datecheck_not_effective_day'   => "日付の指定 %s が通常取り得る値から外れています。", 
			'datecheck_not_effective_date'  => "入力日付 %s が不適切です。",
			)
		);
	return $msg;
}

function plugin_datefield_init_en()
{
	$msg = array(
		'_datefield_msg' => array(
			'format_not_effective'        => "You should not use quote character(&nbsp;&#039;&nbsp;&quot;&nbsp;) "
												+ "in the date format string( = %s)." ,
			'input_pattern_not_effective' => "It doesn't match input value with date format (= %s).<br />"
												+ "Consider 0 padding, please.",
			'datecheck_irregular_error'   => "A error beyond assumptions occurred when it confirmed input value with date format.<br />" 
												+ "string of input value       : %s <br />"
												+ "string of the date format   : %s <br />"
												+ "string valuable for receive : %s <br />"
												+ "state of parse format       : %s <br />"
												+ "state of reading            : year = %s, month = %s, day = %s<br />",
			'datecheck_not_effective_month' => "Month value of the input( = %s ) is out range of month.",
			'datecheck_not_effective_day'   => "Date value of the input( = %s ) is out range of date.",
			'datecheck_not_effective_date'  => "Input value ( = %s ) is invalid.",
			)
		);
	return $msg;
}

function plugin_datefield_action() {
    global $script, $vars;
	check_editable($vars['refer'], true, true);
	
	$number = 0;
	$pagedata = '';
	$pagedata_old  = get_source($vars['refer']);
	
	foreach($pagedata_old as $line)
	{
		if (! preg_match('/^(?:\/\/| )/', $line) &&
			preg_match_all('/(?:#datefield\(([^\)]*)\))/',
						   $line, $matches, PREG_SET_ORDER))
		{
			$paddata = preg_split('/#datefield\([^\)]*\)/', $line);
			$line = $paddata[0];
	
			foreach($matches as $i => $match)
			{
				$opt = $match[1];
				if ($vars['number'] == $number++)
				{
					//ターゲットのプラグイン部分
					$para_array = preg_split('/,/',$opt);
					$errmsg = plugin_datefield_chkFormat($vars['infield'],$para_array[1]);
					if( strlen($errmsg) > 0 )
					{
						plugin_datefield_outputErrMsg($vars['refer'], $errmsg);
					}	    
	    
					$opt = preg_replace('/[^,]*/', $vars['infield'], $opt, 1);
				}
				$line .= "#datefield($opt)" . $paddata[$i+1];
			}
		}
		$pagedata .= $line;
	}

	page_write($vars['refer'], $pagedata); 
	if( DATEFIELD_JUMP_TO_MODIFIED_PLACE  && $pagedata != '' )
	{
		header("Location: $script?".rawurlencode($vars['refer'])."#datefield_no_".$vars['number']);
		exit;
	}
	return array('msg' => '', 'body' => '');
}

/* * function plugin_datefield_chkFormat($chkedStr, $formatStr) 
 * 日付(確認対象)文字列と日付書式文字列の確認を行う
 * 問題がなければ空文字列を、不具合があればその内容を示す文字列を返す
 */
function plugin_datefield_chkFormat($chkedStr, $formatStr)
{
	global $_datefield_msg;
	
	if( strlen($chkedStr) == 0 )
	  return "";

	if( strlen($formatStr) == 0) $formatStr='YYYY/MM/DD';
	$formatReg = $formatStr;

	/* クォート文字 の存在確認 */
	if(preg_match('/^.*[\'\"].*$/',$formatReg) ) /* match character..." ' */ 
	{ 
		$errmsg = sprintf($_datefield_msg['format_not_effective'], $formatStr);
		return $errmsg;
	}

	/* 入力値と日付書式との比較 */
	$formatReg = preg_replace('/\//','\\/',$formatReg);
	$formatReg = '/^' . preg_replace('/[YMD]/i','\\d',$formatReg) .'$/';
	if( ! preg_match($formatReg,$chkedStr) )
	{
		$errmsg = sprintf($_datefield_msg['input_pattern_not_effective'], $formatStr);
		return $errmsg;
	}

	$date = plugin_datefield_getDate($chkedStr, $formatStr);
	$year  = $date['year'];
	$month = $date['month'];
	$day   = $date['day'];

	if( $year == -1 and $month == -1 and $day == -1)
	{
		$errmsg = sprintf($_datefield_msg['datecheck_irregular_error'],
						  $chkedStr, $formatStr, $date['dateArgs'], $date['parseStr'],
						  $year, $month, $day);
		return $errmsg;
	}
	else if($month <= 0 or $month > 12)
	{
		/* 月の指定は必須 */
		$errmsg = sprintf($_datefield_msg['datecheck_not_effective_month'], $chkedStr );
		return $errmsg;
	}
	else
	{
		/* 月指定がある状態 */
		if( $day > 31)
		{
				$errmsg = sprintf($_datefield_msg['datecheck_not_effective_day'], $chkedStr );
				return $errmsg;
		}
		else
		{
				/* 指定がない時は 補間する */
				if($year == -1) $year = date("Y",time());
				if($day  == -1) $day  = 1;
				if (! checkdate( $month, $day , $year) )
				{
				  $errmsg = sprintf($_datefield_msg['datefield_not_effective_date'], $chkedStr );
				  return $errmsg;
				}
		}
	}
	return "";
}

function plugin_datefield_outputErrMsg($page, $errmsg)
{
	global $_title_cannotedit;

	$body = $title =
	  str_replace('$1',htmlspecialchars(strip_bracket($page)),$_title_cannotedit);
	$body .= "<br />datefield.inc.php : <br />" .$errmsg;
	
	$page = str_replace('$1',make_search($page),$_title_cannotedit);
	catbody($title,$page,$body);
	exit;
}

function plugin_datefield_getDate($dateStr, $formatStr)
{
	$formatPtn = $dateArgs = preg_replace('/\//','\\/',$formatStr);
	$year = $month = $day = -1;

	$formatPtn = preg_replace('/YYYY/i','%04d',$formatPtn);
	$formatPtn = preg_replace('/(YY|MM|DD)/i','%02d',$formatPtn);

	$dateArgs =  preg_replace('/YYYY|YY/i',',\$year',$dateArgs);
	$dateArgs =  preg_replace('/MM/i',',\$month',$dateArgs);
	$dateArgs =  preg_replace('/DD/i',',\$day',$dateArgs);
	$dateArgs =  preg_replace('/[^(?!:,\$year|,\$month|,\$day)]+/','',$dateArgs);

	// 区切り文字が '/'(バックスラッシュ)の場合はエスケープ文字を付与する
	$scanStr = preg_replace('/\//','\\/',$dateStr);

	if(! strcmp($scanStr,$formatPtn) == 0)
	{
		$formatPtn = ",\"" . $formatPtn . "\"";
		$parseStr = "sscanf(\"$scanStr\" $formatPtn $dateArgs);";
		eval($parseStr);
	}
	if( $year < 100 && $year > 0) $year += 2000;
	$date = array(
				  "year"      => $year,
				  "month"     => $month,
				  "day"       => $day,
				  "formatPtn" => $formatPtn,
				  "dateArgs"  => $dateArgs,
				  "parseStr"  => $parseStr );
	
	return $date;
}

// header宣言の中で以下の２つの定義を行う
// ・Javasciptを用いること、
// ・XHTML1.0 Transitional Modeでの動作（<form>タグにname属性を用いる）
function plugin_datefield_headDeclaration()
{
	global $pkwk_dtd, $javascript, $head_tags;

	// Javasciptを用いること、<form>タグにname属性を用いることを通知する
	if( PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE )
	{
		// XHTML 1.0 Transitional
		if (! isset($pkwk_dtd) || $pkwk_dtd == PKWK_DTD_XHTML_1_1)
		{
			$pkwk_dtd = PKWK_DTD_XHTML_1_0_TRANSITIONAL;
		}
    
		// <head> タグ内への <meta>宣言の追加
		$javascript = TRUE;
	}

	// <head> タグ内への <meta>宣言の追加
	$meta_str =
		" <meta http-equiv=\"content-script-type\" content=\"text/javascript\" /> ";
	if(! in_array($meta_str, $head_tags) )
	{
		$head_tags[] = $meta_str;
	}

}

function plugin_datefield_convert()
{
	// Javasciptを用いること、<form>タグにname属性を用いることを通知する
	plugin_datefield_headDeclaration();
  
	// datefield プラグインの部分のHTML出力
	$number = plugin_datefield_getNumber();
	if(func_num_args() > 0) 
    {
		$options = func_get_args();
		$value      = array_shift($options);
		$format_opt = array_shift($options);
		$caldsp_opt = array_shift($options);
		
		return plugin_datefield_getBody($number, $value, $format_opt, $caldsp_opt);
    }
	return FALSE;
}

function plugin_datefield_getNumber()
{
	global $vars;
	static $numbers = array();
	if ( ! array_key_exists($vars['page'],$numbers) )
    {
		$numbers[$vars['page']] = 0;
    }
	return $numbers[$vars['page']]++;
}

function plugin_datefield_formFormat($format_opt)
{
	$format_str= trim($format_opt);
	if( strlen($format_str) == 0 )  $format_str = 'YYYY/MM/DD';
	if( preg_match('/^[\'\"].*[\"\']$/',$format_str) )
	{ 
		$format_str = '\'' . substr($format_str,1,strlen($format_str)-2) . '\'';
	}
	else
	{
		$format_str = '\'' . $format_str . '\'';
	}
	return $format_str;
}

function plugin_datefield_getBody($number, $value, $format_opt, $caldsp_opt = '')
{
	global $script, $vars;
	global $_datefield_cfg;

	$page_enc = htmlspecialchars($vars['page']);
	$script_enc = htmlspecialchars($script);
	
	// datefield 用の<script>タグの挿入
	$extrascript = (PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE && $number == 0) ? plugin_datefield_getScript() : '';

	// 日付書式指定文字列に対する処理
	$format_opt= plugin_datefield_formFormat($format_opt);
	
	// カレンダー表示設定に対する処理
	if($caldsp_opt != 'CUR') $caldsp_opt = 'REL';

	// 保存された日付から値を取得
	$formatStr =substr($format_opt,1,strlen($format_opt)-2);
	$errmsg = plugin_datefield_chkFormat($value,$formatStr);
	if( strlen($value) != 0 && strlen($errmsg) == 0 and $caldsp_opt == 'REL')
	{
		$date= plugin_datefield_getDate($value, $formatStr);
		/* 指定がない時は 補間する */
		if($date['year'] == -1) $date['year'] = date("Y",time());
		if($date['day']  == -1) $date['day']  = 1;
	}
	else
	{
		$date = array(
					  "year"  => date("Y",time()),
					  "month" => date("m",time()),
					  "day"   => date("d",time()) ); 
	}

	$field_size = strlen($format_opt); 
  
	$imagePath = IMAGE_DIR;
	$imgEdit   = $_datefield_cfg['editImage'];
	$imgRefer  = $_datefield_cfg['referImage'];

	$body = <<<EOD
	  <input type="text" name="infield" value="$value" size="{$field_size}" onchange="this.form.submit();" />
EOD;

	if( PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE )
	{
		$body .= <<< EOD
		<input type="checkbox" name="calendar" value="null" checkd=false
			onclick="_plugin_datefield_onclickClndrModal(this.form, event, $format_opt, {$date['year']},{$date['month']},{$date['day']});" />
EOD;
	}

	$body .= <<<EOD
		<input type="hidden" name="refer" value="$page_enc" />
		<input type="hidden" name="plugin" value="datefield" />
		<input type="hidden" name="number" value="$number" />
EOD;

	if( PKWK_ALLOW_JAVASCRIPT && DATEFIELD_APPLY_MODECHANGE )
	{
		$body .= <<< EOD
		<img name="editTrigger" src="$imagePath$imgEdit" alt="edit/refer"
			onclick="_plugin_datefield_changeMode( document.datefield$number, '$imgEdit', '$imgRefer', '$imagePath');" />
EOD;
	}

	if( DATEFIELD_JUMP_TO_MODIFIED_PLACE )
	{
		$body = <<< EOD
		<a id="datefield_no_$number">
		$body
		</a>
EOD;
	}

	$body = <<< EOD
		<form name="datefield$number" action="$script_enc" method='post' style="margin:0;">
		<div style="white-space:nowrap; ">
		$body
		</div>
		</form>
EOD;

	return $extrascript . $body;
}

function plugin_datefield_getScript()
{
	global $script, $vars;
	$page_enc = htmlspecialchars($vars['page']);
	$script_enc = htmlspecialchars($script);
	$js = '<script type="text/javascript" src="'. SKIN_DIR . 'datefield.js" ></script>';
	return $js;
}
?>
