<?php
/////////////////////////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: listbox3.inc.php,v 1.1 2006/04/30 09:16:10 jjyun Exp $
//   This script is based on listbox2.inc.php by KaWaZ
// -----------------------------------------------------------------
// Copyright (C)
//   2004-2006 written by jjyun ( http://www2.g-com.ne.jp/~jjyun/twilight-breeze/pukiwiki.php )
// License: GPL v2 or (at your option) any later version
//

// 修正後のリロード時に、編集箇所へ表示箇所を移す
// 有効にする場合には、TRUE , 無効にする場合には FALSE を指定
define('LISTBOX3_JUMP_TO_MODIFIED_PLACE',FALSE); // TRUE or FALSE
// リストアイテムに書式を適用する(色指定のみ)
define('LISTBOX3_APPLY_FORMAT',TRUE); // TRUE or FALSE
// モード変更を適用する
define('LISTBOX3_APPLY_MODECHANGE',TRUE); // TRUE or FALSE

function plugin_listbox3_init()
{
	$cfg = array(
			'_listbox3_cfg' => array (
				'imgEdit'  => 'paraedit.png',
				'imgRefer' => 'close.png'
				)
			);
	set_plugin_messages($cfg);
}

function plugin_listbox3_action()
{
	global $script, $vars;
	check_editable($vars['refer'], true, true);

	$number = 0;
	$pagedata = '';
	$pagedata_old  = get_source($vars['refer']);
	foreach($pagedata_old as $line)
	{
		if( ! preg_match('/^(?:\/\/| )/', $line) &&
			preg_match_all('/(?:#listbox3\(([^\)]*)\))/',
						   $line, $matches, PREG_SET_ORDER) )
		{
			$paddata = preg_split('/#listbox3\([^\)]*\)/', $line);
			$line = $paddata[0];
			foreach($matches as $i => $match)
			{
				$opt = $match[1];
				if($vars['number'] == $number++)
				{
					//ターゲットのプラグイン部分
					$opt = preg_replace('/[^,]*/', $vars['select'], $opt, 1);
				}
				$line .= "#listbox3($opt)" . $paddata[$i+1];
			}
		}
		$pagedata .= $line;
	}
	page_write($vars['refer'], $pagedata);

	if( LISTBOX3_JUMP_TO_MODIFIED_PLACE && $pagedata != '' )
	{
		header("Location: $script?".rawurlencode($vars['refer'])."#listbox3_no_".$vars['number']);
		exit;
	}
	return array('msg' => '', 'body' => '');
}

// header宣言の中で以下の２つの定義を行う
// ・Javasciptを用いること、
// ・XHTML1.0 Transitional Modeでの動作（<form>タグにname属性を用いる）
function plugin_listbox3_headDeclaration()
{
	global $pkwk_dtd, $javascript,$head_tags;

	// Javasciptを用いること、<form>タグにname属性を用いることを通知する
	if( PKWK_ALLOW_JAVASCRIPT && LISTBOX3_APPLY_MODECHANGE )
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

function plugin_listbox3_convert()
{
	global $head_tags;

	$number = plugin_listbox3_getNumber();

	// header の宣言
	if( $number == 0 )
	{
		plugin_listbox3_headDeclaration();
	}

	if(func_num_args() > 1)
	{
		$options = func_get_args();
		$value     = array_shift($options);
		$template  = array_shift($options);
		$fieldname = array_shift($options);
		return plugin_listbox3_getBody($number, $value, $template, $fieldname);
	}
	return FALSE;
}

function plugin_listbox3_getNumber()
{
	global $vars;
	static $numbers = array();
	if( !array_key_exists($vars['page'],$numbers) )
	{
      $numbers[$vars['page']] = 0;
	}
	return $numbers[$vars['page']]++;
}

function plugin_listbox3_getBody($number, $value, $template, $fieldname)
{
	global $script, $vars;
	global $_listbox3_cfg;

	$page_enc = htmlspecialchars($vars['page']);
	$script_enc = htmlspecialchars($script);
	
	// listbox3 用の<script>タグの挿入 ( for LISTBOX3_APPLY_MODECHANGE )
	$extrascript = (PKWK_ALLOW_JAVASCRIPT && LISTBOX3_APPLY_MODECHANGE && $number == 0) ? plugin_listbox3_getScript() : '';

	$options_html = plugin_listbox3_getOptions($value, $template, $fieldname);
	$imgPath  = IMAGE_DIR;
	$imgEdit  = $_listbox3_cfg['imgEdit'];
	$imgRefer = $_listbox3_cfg['imgRefer'];
	$body = <<<EOD
	  <select name="select" style="vertical-align:middle" onchange="this.form.submit();">
	  $options_html
	  </select>
	  <input type="hidden" name="number" value="$number" />
	  <input type="hidden" name="plugin" value="listbox3" />
	  <input type="hidden" name="refer"  value="$page_enc" />
    <noscript>
	  <input type="submit" size="9" value="set" />
    </noscript>
EOD;

	if( PKWK_ALLOW_JAVASCRIPT && LISTBOX3_APPLY_MODECHANGE )
	{
		$body .= <<< EOD
		  <img name="editTrigger" src="$imgPath$imgEdit" alt="edit/refer"
		  onclick="_plugin_listbox3_changeMode( document.listbox3$number, '$imgEdit', '$imgRefer','$imgPath' );" />
EOD;
	}
  
	if( LISTBOX3_JUMP_TO_MODIFIED_PLACE )
	{
		$body = <<< EOD
		  <a id="listbox3_no_$number">
		  $body
		  </a>
EOD;
	}
	
	$body = <<< EOD
	  <form name="listbox3$number" action="$script_enc" method="post" style="margin:0;"> 
	  <div>
	  $body
	  </div>
	  </form>
EOD;

	return $extrascript . $body;
}

function plugin_listbox3_getScript()
{
	$js = '<script type="text/javascript" src="'. SKIN_DIR . 'listbox3.js" ></script>';
	return $js;
}

function plugin_listbox3_getOptions($value, $config_name, $field_name)
{
	$options_html = '';
  
	$config = new Config('plugin/tracker/'.$config_name);
	if( ! $config->read() )
	{
		return "<p>config file '" . htmlspecialchars($config_name)."' not found.</p>";    
	}
	$config->name = $config_name;

	$isSelect = 0;
	foreach($config->get($field_name) as $options)
	{
		$s_option=$options[0];
		$s_format=$options[1];
		if($s_option == '') continue;

		$option_fmt = ( LISTBOX3_APPLY_FORMAT ) ? plugin_listbox3_getStyle($s_format) : '';
		
		$option_enc = htmlspecialchars($s_option);
		if($value == $s_option)
		{
			$isSelect = 1;
			$options_html .= "<option value='$option_enc' style='$option_fmt' selected='selected'>$option_enc</option>";
		}
		else
		{
			$options_html .= "<option value='$option_enc' style='$option_fmt' >$option_enc</option>";
		}
	}
  
	if( $isSelect == 0 )
	{
		$options_html = "<option value='…' selected='selected'>…</option>" . $options_html;
	}
	return $options_html;
}

function plugin_listbox3_getStyle($s_format)
{
	if( $s_format == '') return '';
  
	$format_enc = htmlspecialchars($s_format);
	$format_enc = preg_replace("/\%s/", '', $format_enc);
	
	$opt_format='';
	$matches=array();
	while ( preg_match('/^(?:(BG)?COLOR\(([#\w]+)\)):(.*)$/', $format_enc, $matches) )
	{
		if ($matches[0])
		{
			$style_name = $matches[1] ? 'background-color' : 'color';
			$opt_format .= $style_name . ':' . htmlspecialchars($matches[2]) . ';';
			$format_enc = $matches[3];
		}
	}
	return $opt_format;
}
?>
