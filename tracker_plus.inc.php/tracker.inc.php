<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: tracker.inc.php,v 1.28 2005/01/23 08:29:20 henoheno Exp $
//
// Issue tracker plugin (See Also bugtrack plugin)
// This script is modified by jjyun. (2004/02/22 - 2005/02/08) 
//   tracker.inc.php-modified, v 1.4 2005/02/08 00:27:52 jjyun
//
// License   : PukiWiki 本体と同じく GNU General Public License (GPL) です

// tracker_listで表示しないページ名(正規表現で)
// 'SubMenu'ページ および '/'を含むページを除外する
define('TRACKER_LIST_EXCLUDE_PATTERN','#^SubMenu$|/#');
// 制限しない場合はこちら
//define('TRACKER_LIST_EXCLUDE_PATTERN','#(?!)#');

// 項目の取り出しに失敗したページを一覧に表示する
define('TRACKER_LIST_SHOW_ERROR_PAGE',TRUE);

// CacheLevelのデフォルトの設定
// ** 設定値の説明 ** 負の値は冗長モードを表します
//        0 : キャッシュロジックを利用しない 
//  1 or -1 : ページの読み込み処理に対するキャッシュを有効にする
//  2 or -2 : htmlに変換後のデータのキャッシュを有効にする

define('TRACKER_LIST_CACHE_DEFAULT', 0); 
// define('TRACKER_LIST_CACHE_DEFAULT', 1); 
// define('TRACKER_LIST_CACHE_DEFAULT', 2); 

function plugin_tracker_convert()
{
	global $script,$vars;

	if (PKWK_READONLY) return ''; // Show nothing

	$base = $refer = $vars['page'];

	$config_name = 'default';
	$form = 'form';
	$options = array();

	global $html_transitional;
	$isDatefield = FALSE;

	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
			case 3:
				$options = array_splice($args,2);
			case 2:
				$args[1] = get_fullname($args[1],$base);
				$base = is_pagename($args[1]) ? $args[1] : $base;
			case 1:
				$config_name = ($args[0] != '') ? $args[0] : $config_name;
				list($config_name,$form) = array_pad(explode('/',$config_name,2),2,$form);
		}
	}

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' not found.</p>";
	}

	// Configクラスには、config_name は定義されていない。(jjyun's comment)
	$config->config_name = $config_name;

	$fields = plugin_tracker_get_fields($base,$refer,$config);

	$form = $config->page.'/'.$form;
	if (!is_page($form))
	{
		return "<p>config file '".make_pagelink($form)."' not found.</p>";
	}
	$retval = convert_html(plugin_tracker_get_source($form));
	$hiddens = '';

	foreach (array_keys($fields) as $name)
	{
	        if (is_a($fields[$name],'Tracker_field_datefield')) {
			$isDatefield = TRUE;
		}

		$replace = $fields[$name]->get_tag();
		if (is_a($fields[$name],'Tracker_field_hidden'))
		{
			$hiddens .= $replace;
			$replace = '';
		}
		$retval = str_replace("[$name]",$replace,$retval);
	}

	if($isDatefield == TRUE)
	{
		Tracker_field_datefield::set_head_declaration();
		$number = plugin_tracker_getNumber();
		$form_scp = '<script type="text/javascript" src="' . SKIN_DIR . 'datefield.js"></script>';
		$form_scp .= <<<FORMSTR
<form enctype="multipart/form-data" action="$script" method="post" name="tracker$number" >
FORMSTR;
	}
	else
	{
		$form_scp = <<<FORMSTR
<form enctype="multipart/form-data" action="$script" method="post" >
FORMSTR;
	}

	return <<<EOD
$form_scp
<div>
$retval
$hiddens
</div>
</form>
EOD;
}

function plugin_tracker_getNumber() {
	global $vars;
	static $numbers = array();
	if (!array_key_exists($vars['page'],$numbers))
	{
		$numbers[$vars['page']] = 0;
	}
	return $numbers[$vars['page']]++;
}

function plugin_tracker_action()
{
	global $post, $vars, $now;

	if (PKWK_READONLY) die_message('PKWK_READONLY prohibits editing');

	$config_name = array_key_exists('_config',$post) ? $post['_config'] : '';

	$config = new Config('plugin/tracker/'.$config_name);
	if (!$config->read())
	{
		return "<p>config file '".htmlspecialchars($config_name)."' not found.</p>";
	}
	$config->config_name = $config_name;
	$source = $config->page.'/page';

	$refer = array_key_exists('_refer',$post) ? $post['_refer'] : $post['_base'];

	if (!is_pagename($refer))
	{
		return array(
			'msg'=>'cannot write',
			'body'=>'page name ('.htmlspecialchars($refer).') is not valid.'
		);
	}
	if (!is_page($source))
	{
		return array(
			'msg'=>'cannot write',
			'body'=>'page template ('.htmlspecialchars($source).') is not exist.'
		);
	}
	// ページ名を決定
	$base = $post['_base'];
	$num = 0;
	$name = (array_key_exists('_name',$post)) ? $post['_name'] : '';
	if (array_key_exists('_page',$post))
	{
		$page = $real = $post['_page'];
	}
	else
	{
		$real = is_pagename($name) ? $name : ++$num;
		$page = get_fullname('./'.$real,$base);
	}
	if (!is_pagename($page))
	{
		$page = $base;
	}

	while (is_page($page))
	{
		$real = ++$num;
		$page = "$base/$real";
	}
	// ページデータを生成
	$postdata = plugin_tracker_get_source($source);

	// 規定のデータ
	$_post = array_merge($post,$_FILES);
	$_post['_date'] = $now;
	$_post['_page'] = $page;
	$_post['_name'] = $name;
	$_post['_real'] = $real;
	// $_post['_refer'] = $_post['refer'];

	$fields = plugin_tracker_get_fields($page,$refer,$config);

	// Creating an empty page, before attaching files
       	touch(get_filename($page));

	foreach (array_keys($fields) as $key)
	{
	        // modified for hidden2 by jjyun
		// $value = array_key_exists($key,$_post) ?
		// 	$fields[$key]->format_value($_post[$key]) : '';
	        $value = '';
		if( array_key_exists($key,$_post) ){
		  $value = is_a($fields[$key],"Tracker_field_hidden2") ?
		    $fields[$key]->format_value($_post[$key],$_post) :
		    $fields[$key]->format_value($_post[$key]);
		}

		foreach (array_keys($postdata) as $num)
		{
			if (trim($postdata[$num]) == '')
			{
				continue;
			}
			$postdata[$num] = str_replace(
				"[$key]",
				($postdata[$num]{0} == '|' or $postdata[$num]{0} == ':') ?
					str_replace('|','&#x7c;',$value) : $value,
				$postdata[$num]
			);
		}
	}

	// Writing page data, without touch
	page_write($page, join('', $postdata));

	$r_page = rawurlencode($page);

	pkwk_headers_sent();
	header('Location: ' . get_script_uri() . '?' . $r_page);
	exit;
}
/*
function plugin_tracker_inline()
{
	global $vars;

	if (PKWK_READONLY) return ''; // Show nothing

	$args = func_get_args();
	if (count($args) < 3)
	{
		return FALSE;
	}
	$body = array_pop($args);
	list($config_name,$field) = $args;

	$config = new Config('plugin/tracker/'.$config_name);

	if (!$config->read())
	{
		return "config file '".htmlspecialchars($config_name)."' not found.";
	}

	$config->config_name = $config_name;

	$fields = plugin_tracker_get_fields($vars['page'],$vars['page'],$config);
	$fields[$field]->default_value = $body;
	return $fields[$field]->get_tag();
}
*/
// フィールドオブジェクトを構築する
function plugin_tracker_get_fields($base,$refer,&$config)
{
	global $now,$_tracker_messages;

	$fields = array();
	// 予約語
	foreach (array(
		'_date'=>'text',    // 投稿日時
		'_update'=>'date',  // 最終更新
		'_past'=>'past',    // 経過(passage)
		'_page'=>'page',    // ページ名
		'_name'=>'text',    // 指定されたページ名
		'_real'=>'real',    // 実際のページ名
		'_refer'=>'page',   // 参照元(フォームのあるページ)
		'_base'=>'page',    // 基準ページ
		'_submit'=>'submit' // 追加ボタン
		) as $field=>$class)
	{
		$class = 'Tracker_field_'.$class;
		$fields[$field] = &new $class(array($field,$_tracker_messages["btn$field"],'','20',''),$base,$refer,$config);
	}

	foreach ($config->get('fields') as $field)
	{
		// 0=>項目名 1=>見出し 2=>形式 3=>オプション 4=>デフォルト値
		$class = 'Tracker_field_'.$field[2];
		if (!class_exists($class))
		{ // デフォルト
			$class = 'Tracker_field_text';
			$field[2] = 'text';
			$field[3] = '20';
		}
		$fields[$field[0]] = &new $class($field,$base,$refer,$config);
	}
	return $fields;
}
// フィールドクラス
class Tracker_field
{
	var $name;
	var $title;
	var $values;
	var $default_value;
	var $page;
	var $refer;
	var $config;
	var $data;
	var $sort_type = SORT_REGULAR;

	function Tracker_field($field,$page,$refer,&$config)
	{
		global $post;

		$this->name = $field[0];
		$this->title = $field[1];
		$this->values = explode(',',$field[3]);
		$this->default_value = $field[4];
		$this->page = $page;
		$this->refer = $refer;
		$this->config = &$config;
		$this->data = array_key_exists($this->name,$post) ? $post[$this->name] : '';
	}
	function get_tag()
	{
	}
	function get_style($str)
	{
		return '%s';
	}
	function format_value($value)
	{
		return $value;
	}
	function format_cell($str)
	{
		return $str;
	}
	function get_value($value)
	{
		return $value;
	}
}
class Tracker_field_text extends Tracker_field
{
	var $sort_type = SORT_STRING;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = htmlspecialchars($this->values[0]);
		$s_value = htmlspecialchars($this->default_value);
		return "<input type=\"text\" name=\"$s_name\" size=\"$s_size\" value=\"$s_value\" />";
	}
}
class Tracker_field_page extends Tracker_field_text
{
	var $sort_type = SORT_STRING;

	function format_value($value)
	{
		global $WikiName;

		$value = strip_bracket($value);
		if (is_pagename($value))
		{
			$value = "[[$value]]";
		}
		return parent::format_value($value);
	}
}
class Tracker_field_real extends Tracker_field_text
{
	var $sort_type = SORT_REGULAR;
}
class Tracker_field_title extends Tracker_field_text
{
	var $sort_type = SORT_STRING;

	function format_cell($str)
	{
		make_heading($str);
		return $str;
	}
}
class Tracker_field_textarea extends Tracker_field
{
	var $sort_type = SORT_STRING;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_cols = htmlspecialchars($this->values[0]);
		$s_rows = htmlspecialchars($this->values[1]);
		$s_value = htmlspecialchars($this->default_value);
		return "<textarea name=\"$s_name\" cols=\"$s_cols\" rows=\"$s_rows\">$s_value</textarea>";
	}
	function format_cell($str)
	{
		$str = preg_replace('/[\r\n]+/','',$str);
		if (!empty($this->values[2]) and strlen($str) > ($this->values[2] + 3))
		{
			$str = mb_substr($str,0,$this->values[2]).'...';
		}
		return $str;
	}
}
class Tracker_field_format extends Tracker_field
{
	var $sort_type = SORT_STRING;

	var $styles = array();
	var $formats = array();

	function Tracker_field_format($field,$page,$refer,&$config)
	{
		parent::Tracker_field($field,$page,$refer,$config);

		foreach ($this->config->get($this->name) as $option)
		{
			list($key,$style,$format) = array_pad(array_map(create_function('$a','return trim($a);'),$option),3,'');
			if ($style != '')
			{
				$this->styles[$key] = $style;
			}
			if ($format != '')
			{
				$this->formats[$key] = $format;
			}
		}
	}
	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = htmlspecialchars($this->values[0]);
		return "<input type=\"text\" name=\"$s_name\" size=\"$s_size\" />";
	}
	function get_key($str)
	{
		return ($str == '') ? 'IS NULL' : 'IS NOT NULL';
	}
	function format_value($str)
	{
		if (is_array($str))
		{
			return join(', ',array_map(array($this,'format_value'),$str));
		}
		$key = $this->get_key($str);
		return array_key_exists($key,$this->formats) ? str_replace('%s',$str,$this->formats[$key]) : $str;
	}
	function get_style($str)
	{
		$key = $this->get_key($str);
		return array_key_exists($key,$this->styles) ? $this->styles[$key] : '%s';
	}
}
class Tracker_field_file extends Tracker_field_format
{
	var $sort_type = SORT_STRING;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = htmlspecialchars($this->values[0]);
		return "<input type=\"file\" name=\"$s_name\" size=\"$s_size\" />";
	}
	function format_value($str)
	{
		if (array_key_exists($this->name,$_FILES))
		{
			require_once(PLUGIN_DIR.'attach.inc.php');
			$result = attach_upload($_FILES[$this->name],$this->page);
			if ($result['result']) // アップロード成功
			{
				return parent::format_value($this->page.'/'.$_FILES[$this->name]['name']);
			}
		}
		// ファイルが指定されていないか、アップロードに失敗
		return parent::format_value('');
	}
}
class Tracker_field_radio extends Tracker_field_format
{
	var $sort_type = SORT_NUMERIC;

	function get_tag()
	{
		$s_name = htmlspecialchars($this->name);
		$retval = '';
		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$checked = trim($option[0]) == trim($this->default_value) ? ' checked="checked"' : '';
			$retval .= "<input type=\"radio\" name=\"$s_name\" value=\"$s_option\"$checked />$s_option\n";
		}

		return $retval;
	}
	function get_key($str)
	{
		return $str;
	}
	function get_value($value)
	{
		static $options = array();
		if (!array_key_exists($this->name,$options))
		{
			$options[$this->name] = array_flip(array_map(create_function('$arr','return $arr[0];'),$this->config->get($this->name)));
		}
		return array_key_exists($value,$options[$this->name]) ? $options[$this->name][$value] : $value;
	}
}
class Tracker_field_select extends Tracker_field_radio
{
	var $sort_type = SORT_NUMERIC;

	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$s_size = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
			' size="'.htmlspecialchars($this->values[0]).'"' : '';
		$s_multiple = (array_key_exists(1,$this->values) and strtolower($this->values[1]) == 'multiple') ?
			' multiple="multiple"' : '';
		$retval = "<select name=\"{$s_name}[]\"$s_size$s_multiple>\n";
		if ($empty)
		{
			$retval .= " <option value=\"\"></option>\n";
		}
		$defaults = array_flip(preg_split('/\s*,\s*/',$this->default_value,-1,PREG_SPLIT_NO_EMPTY));
		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$selected = array_key_exists(trim($option[0]),$defaults) ? ' selected="selected"' : '';
			$retval .= " <option value=\"$s_option\"$selected>$s_option</option>\n";
		}
		$retval .= "</select>";

		return $retval;
	}
}
class Tracker_field_checkbox extends Tracker_field_radio
{
	var $sort_type = SORT_NUMERIC;

	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$defaults = array_flip(preg_split('/\s*,\s*/',$this->default_value,-1,PREG_SPLIT_NO_EMPTY));
		$retval = '';
		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$checked = array_key_exists(trim($option[0]),$defaults) ?
				' checked="checked"' : '';
			$retval .= "<input type=\"checkbox\" name=\"{$s_name}[]\" value=\"$s_option\"$checked />$s_option\n";
		}

		return $retval;
	}
}
class Tracker_field_hidden extends Tracker_field_radio
{
	var $sort_type = SORT_NUMERIC;

	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$s_default = htmlspecialchars($this->default_value);
		$retval = "<input type=\"hidden\" name=\"$s_name\" value=\"$s_default\" />\n";

		return $retval;
	}
}
class Tracker_field_submit extends Tracker_field
{
	function get_tag()
	{
		$s_title = htmlspecialchars($this->title);
		$s_page = htmlspecialchars($this->page);
		$s_refer = htmlspecialchars($this->refer);
		$s_config = htmlspecialchars($this->config->config_name);

		return <<<EOD
<input type="submit" value="$s_title" />
<input type="hidden" name="plugin" value="tracker" />
<input type="hidden" name="_refer" value="$s_refer" />
<input type="hidden" name="_base" value="$s_page" />
<input type="hidden" name="_config" value="$s_config" />
EOD;
	}
}
class Tracker_field_date extends Tracker_field
{
	var $sort_type = SORT_NUMERIC;

	function format_cell($timestamp)
	{
		return format_date($timestamp);
	}
}
class Tracker_field_past extends Tracker_field
{
	var $sort_type = SORT_NUMERIC;

	function format_cell($timestamp)
	{
		return get_passage($timestamp,FALSE);
	}
	function get_value($value)
	{
		return UTIME - $value;
	}
}
///////////////////////////////////////////////////////////////////////////
// 一覧表示
function plugin_tracker_list_convert()
{
	global $vars;

	$config = 'default';
	$page = $refer = $vars['page'];
	$field = '_page';
	$order = '_real:SORT_DESC';
	$list = 'list';
	$limit = NULL;
	$filter = '';
	$cache = TRACKER_LIST_CACHE_DEFAULT;
	if (func_num_args())
	{
		$args = func_get_args();
		switch (count($args))
		{
		        case 6:
			        $cache = is_numeric($args[5]) ? $args[5] : $cache;
		        case 5:
			        $filter = $args[4];
			case 4:
				$limit = is_numeric($args[3]) ? $args[3] : $limit;
			case 3:
				$order = $args[2];
			case 2:
				$args[1] = get_fullname($args[1],$page);
				$page = is_pagename($args[1]) ? $args[1] : $page;
			case 1:
				$config = ($args[0] != '') ? $args[0] : $config;
				list($config,$list) = array_pad(explode('/',$config,2),2,$list);
		}
	}
	return plugin_tracker_getlist($page,$refer,$config,$list,$order,$limit,$filter,$cache);
}
function plugin_tracker_list_action()
{
	global $script,$vars,$_tracker_messages;

	$page = $refer = $vars['refer'];
	$s_page = make_pagelink($page);
	$config = $vars['config'];
	$list = array_key_exists('list',$vars) ? $vars['list'] : 'list';
	$order = array_key_exists('order',$vars) ? $vars['order'] : '_real:SORT_DESC';
	$filter = array_key_exists('filter',$vars) ? $vars['filter'] : NULL;

	$cache = isset($vars['cache']) ? $vars['cache'] : NULL;

	// this delete tracker caches. 
	if( $cache == 'DELALL' )
	{
		if(! Tracker_list::delete_caches('(.*)(.tracker)$') )
		  die_message( CACHE_DIR . ' is not found or not readable.');

		return array(
			     'result' => FALSE,
			     'msg' => 'tracker_list caches are cleared.',
			     'body' =>'tracker_list caches are cleared.',
			     );
	}

	return array(
		     'msg' => $_tracker_messages['msg_list'],
		     'body'=> str_replace('$1',$s_page,$_tracker_messages['msg_back']).
		     plugin_tracker_getlist($page,$refer,$config,$list,$order,NULL,$filter,$cache)
	);
}
function plugin_tracker_getlist($page,$refer,$config_name,$list,$order='',$limit=NULL,$filter_name=NULL,$cache)
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
	$list = &new Tracker_list($page,$refer,$config,$list,$filter_name,$cache);

	if($filter_name != NULL)
	{
		$list->rows = array_filter($list->rows, array($list_filter, 'filters') );
	}
	$list->sort($order);
	return $list->toString($limit);
}

// 一覧クラス
class Tracker_list
{
	var $page;
	var $config;
	var $list;
	var $fields;
	var $pattern;
	var $pattern_fields;
	var $rows;
	var $order;
	var $filter_name;
	
	var $cache_level = array(
			   'NO'  => 0, // キャッシュロジックを利用しない
			   'LV1' => 1, // ページの読み込み処理に対するキャッシュを有効にする
			   'LV2' => 2, // htmlに変換後のデータのキャッシュを有効にする
			   );

	var $cache = array('level' => TRACKER_LIST_CACHE_DEFAULT ,
			   'state' => array('hits' => 0, 'total' => 0, 'cnvrt' => FALSE), 
			   'verbs' => FALSE,
			   );

	function Tracker_list($page,$refer,&$config,$list,$filter_name,$cache)
	{
		$this->page = $page;
		$this->config = &$config;
		$this->list = $list;
		$this->filter_name = $filter_name;
		$this->fields = plugin_tracker_get_fields($page,$refer,$config);
		
		$pattern = join('',plugin_tracker_get_source($config->page.'/page'));
		// ブロックプラグインをフィールドに置換
		// #commentなどで前後に文字列の増減があった場合に、[_block_xxx]に吸い込ませるようにする
		$pattern = preg_replace('/^\#([^\(\s]+)(?:\((.*)\))?\s*$/m','[_block_$1]',$pattern);

		// パターンを生成
		$this->pattern = '';
		$this->pattern_fields = array();
		$pattern = preg_split('/\\\\\[(\w+)\\\\\]/',preg_quote($pattern,'/'),-1,PREG_SPLIT_DELIM_CAPTURE);
		while (count($pattern))
		{
			$this->pattern .= preg_replace('/\s+/','\\s*','(?>\\s*'.trim(array_shift($pattern)).'\\s*)');
			if (count($pattern))
			{
				$field = array_shift($pattern);
				$this->pattern_fields[] = $field;
				$this->pattern .= '(.*)';
			}
		}

		$this->cache['verbs'] = ($cache < 0) ? TRUE : FALSE;
		$this->cache['level'] = (abs($cache) <= $this->cache_level['LV2']) ? abs($cache) : $this->cache_level['NO']; 

                // ページの列挙と取り込み
                $this->get_cache_rows();
		$this->cache['state']['hits'] = count($this->rows);

                $pattern = "$page/";
                $pattern_len = strlen($pattern);
                foreach (get_existpages() as $_page)
		{
			if (strpos($_page,$pattern) === 0)
			{
				$name = substr($_page,$pattern_len);
				if (preg_match(TRACKER_LIST_EXCLUDE_PATTERN,$name))
				{
					continue;
				}
				$this->add($_page,$name);
			}
		}
		$this->cache['state']['total'] = count($this->rows);
                $this->put_cache_rows();
        }
	function add($page,$name)
	{
		static $moved = array();

		// 無限ループ防止
		if (array_key_exists($name,$this->rows))
		{
			return;
		}

		$source = plugin_tracker_get_source($page);
		if (preg_match('/move\sto\s(.+)/',$source[0],$matches))
		{
			$page = strip_bracket(trim($matches[1]));
			if (array_key_exists($page,$moved) or !is_page($page))
			{
				return;
			}
			$moved[$page] = TRUE;
			return $this->add($page,$name);
		}
		$source = join('',preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/','$1$2',$source));

		// デフォルト値
		$this->rows[$name] = array(
			'_page'  => "[[$page]]",
			'_refer' => $this->page,
			'_real'  => $name,
			'_update'=> get_filetime($page),
			'_past'  => get_filetime($page),
		);
		if ($this->rows[$name]['_match'] = preg_match("/{$this->pattern}/s",$source,$matches))
		{
			array_shift($matches);
			foreach ($this->pattern_fields as $key=>$field)
			{
				$this->rows[$name][$field] = trim($matches[$key]);
			}
		}
	}
	function sort($order)
	{
		if ($order == '')
		{
			return;
		}
		$names = array_flip(array_keys($this->fields));
		$this->order = array();
		foreach (explode(';',$order) as $item)
		{
			list($key,$dir) = array_pad(explode(':',$item),1,'ASC');
			if (!array_key_exists($key,$names))
			{
				continue;
			}
			switch (strtoupper($dir))
			{
				case 'SORT_ASC':
				case 'ASC':
				case SORT_ASC:
					$dir = SORT_ASC;
					break;
				case 'SORT_DESC':
				case 'DESC':
				case SORT_DESC:
					$dir = SORT_DESC;
					break;
				default:
					continue;
			}
			$this->order[$key] = $dir;
		}
		$keys = array();
		$params = array();
		foreach ($this->order as $field=>$order)
		{
			if (!array_key_exists($field,$names))
			{
				continue;
			}
			foreach ($this->rows as $row)
			{
				$keys[$field][] = $this->fields[$field]->get_value($row[$field]);
			}
			$params[] = $keys[$field];
			$params[] = $this->fields[$field]->sort_type;
			$params[] = $order;

		}
		$params[] = &$this->rows;

		call_user_func_array('array_multisort',$params);
	}
	function replace_item($arr)
	{
		$params = explode(',',$arr[1]);
		$name = array_shift($params);
		if ($name == '')
		{
			$str = '';
		}
		else if (array_key_exists($name,$this->items))
		{
			$str = $this->items[$name];
			if (array_key_exists($name,$this->fields)) 
			{
				$str = $this->fields[$name]->format_cell($str);
			}
		}
		else
		{
			return $this->pipe ? str_replace('|','&#x7c;',$arr[0]) : $arr[0];
		}
		$style = count($params) ? $params[0] : $name;
		if (array_key_exists($style,$this->items)
			and array_key_exists($style,$this->fields))
		{
			$str = sprintf($this->fields[$style]->get_style($this->items[$style]),$str);
		}
		return $this->pipe ? str_replace('|','&#x7c;',$str) : $str;
	}
	function replace_title($arr)
	{
		global $script;

		$field = $sort = $arr[1];
		if ($sort == '_name' or $sort == '_page')
		{
			$sort = '_real';
		}
		if (!array_key_exists($field,$this->fields))
		{
			return $arr[0];
		}
		$dir = SORT_ASC;
		$arrow = '';
		$order = $this->order;

		if (is_array($order) && isset($order[$sort]))
		{
			$index = array_flip(array_keys($order));
			$pos = 1 + $index[$sort];
			$b_end = ($sort == array_shift(array_keys($order)));
			$b_order = ($order[$sort] == SORT_ASC);
			$dir = ($b_end xor $b_order) ? SORT_ASC : SORT_DESC;
			$arrow = '&br;'.($b_order ? '&uarr;' : '&darr;')."($pos)";
			unset($order[$sort]);
		}
		$title = $this->fields[$field]->title;
		$r_page = rawurlencode($this->page);
		$r_config = rawurlencode($this->config->config_name);
		$r_list = rawurlencode($this->list);
		$_order = array("$sort:$dir");
		if (is_array($order))
			foreach ($order as $key=>$value)
				$_order[] = "$key:$value";
		$r_order = rawurlencode(join(';',$_order));
		$r_filter = rawurlencode($this->filter_name);
		return "[[$title$arrow>$script?plugin=tracker_list&refer=$r_page&config=$r_config&list=$r_list&order=$r_order&filter=$r_filter]]";
	}
	function toString($limit=NULL)
	{
		global $_tracker_messages;

		$source = '';
		$body = array();

		if ($limit !== NULL and count($this->rows) > $limit)
		{
			$source = str_replace(
				array('$1','$2'),
				array(count($this->rows),$limit),
				$_tracker_messages['msg_limit'])."\n";
			$this->rows = array_splice($this->rows,0,$limit);
		}
		if (count($this->rows) == 0)
		{
			return '';
		}

		$htmls = $this->get_cache_cnvrt();
		if( strlen($htmls) > 0 )
		{
			return $htmls;
		}

		// This case is cache flag or status is not valie.
		foreach (plugin_tracker_get_source($this->config->page.'/'.$this->list) as $line)
		{
			if (preg_match('/^\|(.+)\|[hHfFcC]$/',$line))
			{
				$source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_title'),$line);
			}
			else
			{
				$body[] = $line;
			}
		}

		$lineno = 1;
		foreach ($this->rows as $key=>$row)
		{
			if (!TRACKER_LIST_SHOW_ERROR_PAGE and !$row['_match'])
			{
				continue;
			}

			$row['_line'] = $lineno++;  
			$this->items = $row;
			foreach ($body as $line)
			{
				if (trim($line) == '')
				{
					$source .= $line;
					continue;
				}
				$this->pipe = ($line{0} == '|' or $line{0} == ':');

				$source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_item'),$line);
			}
		}

		$htmls = convert_html($source);
		$this->put_cache_cnvrt($htmls);

		if($this->cache['verbs'] == TRUE) 
		{
			$htmls .= $this->get_verbose_cachestatus();
		}

		return $htmls;
	}

	function get_cache_filename()
	{
		$r_page   = encode($this->page);
                $r_config = encode($this->config->config_name);
                $r_list   = encode($this->list);
		return "$r_page-$r_config-$r_list";
	}		
	function get_listcache_filename()
	{
		return CACHE_DIR . $this->get_cache_filename().".1.tracker";
	}		
	function get_cnvtcache_filename()
	{
                $r_filter   = encode($this->filter_name);
		return CACHE_DIR . $this->get_cache_filename()."-$r_filter.2.tracker";
	}
 
	function get_verbose_cachestatus()
	{
		if( $this->cache['level'] == $this->cache_level['NO'] )
		{
			return '';  
		} 
		else
		{
			$status = '<div style="text-align: right; font-size: x-small;" > '
			  . "cache level = {$this->cache['level']}, "
			  . "Level1.cache hit rate = "
			  . "{$this->cache['state']['hits']}/{$this->cache['state']['total']} "
			  . "Level2.cache is = ";

			if( $this->cache['state']['cnvrt'] )
			{
			  $status .= 'Valid';
			}
			else
			{
			  $status .= ( $this->cache['level'] == $this->cache_level['LV2'] )  ? 'NotValid': 'NotEffective';
			}
		}
		$status .= '</div>';
		return $status;
	}
	function get_cache_rows()
	{
		$this->rows = array();
		$cachefile = $this->get_listcache_filename();
		if (! file_exists($cachefile) )
		{
			return;
		}
		// This confirm whether config files were changed or not. 
		$cache_time = filemtime($cachefile) - LOCALZONE;
		if( ( get_filetime($this->config->page) > $cache_time) 
		    or ( get_filetime($this->config->page . '/' .  $this->list) > $cache_time ) )
		{
			return ;
		}


		$fp = fopen($cachefile,'r')
		  or die('cannot open '.$cachefile);

		set_file_buffer($fp, 0);
		flock($fp,LOCK_EX);
		rewind($fp);

		// This will get us the main column names.
		// (jjyun) I tryed csv_explode() function , but this behavior is not match as I wanted.

		$column_names = fgetcsv($fp, filesize($cachefile));
		while ($arr = fgetcsv($fp, filesize($cachefile)) )
		{
			$row = array();
			foreach($arr as $key => $value)
			{
				$column_name = $column_names[$key];
				// '_match' is not fields , but this value is effect for tracker_list.
				if( isset($this->fields[$column_name]) || $column_name =='_match')
				{
					$row[$column_name] = stripslashes($value);
				}
			}

			if ( isset($row['_real']) 
			     and isset($row['_update']) 
			     and (get_filetime($this->page.'/'.$row['_real']) == $row['_update']) )
			{
				$this->rows[$row['_real']] = $row;
			}
		}

		flock($fp,LOCK_UN);
		fclose($fp);
	}

	function put_cache_rows()
	{
		$cachefiles_pattern = '^' . $this->get_cache_filename() . '(.*).tracker$';

		if( $this->cache['level'] == $this->cache_level['NO'] )
		{
			if(! $this->delete_caches($cachefiles_pattern) )
			  die_message( CACHE_DIR . ' is not found or not readable.');
			return;
		}
		if($this->cache['state']['hits'] == $this->cache['state']['total']) 
		{  
			return '';
		}

		// This delete cachefiles related this Lv1.cache.
		if(! $this->delete_caches($cachefiles_pattern) )
		  die_message( CACHE_DIR . ' is not found or not readable.');

		ksort($this->rows);
		$filename = $this->get_listcache_filename();
		
		$fp = fopen($filename, 'w')
			or die('cannot open '.$filename);

		set_file_buffer($fp, 0);
		if(! flock($fp, LOCK_EX) )
		{
			return FALSE;  
		}

		$column_names = array();
                foreach (plugin_tracker_get_source($this->config->page.'/'.$this->list) as $line)
		{
			if (! preg_match('/^\|(.+)\|[hHfFcC]$/',$line))
			{
				// It convert '|' for table separation to ',' for CSV format separation.
				preg_match_all('/\[([^\[\]]+)\]/',$line,$item_array);
				foreach ($item_array[1] as $item)
				{
					$params = explode(',',$item);
					$name = array_shift($params);
					if($name != '')
						array_push($column_names,"$name");
				}
			}
		}
                // add default parameter
                $column_names = array_merge($column_names,
					    array('_page','_refer','_real','_update','_match'));
                $column_names = array_unique($column_names);

		fputs($fp, "\"" . implode('","', $column_names)."\"\n");

		foreach ($this->rows as $row)
		{
			$arr = array();
			foreach ( $column_names as $key)
			{
	 			$arr[$key] = addslashes($row[$key]);
			}
		fputs($fp, "\"" . implode('","', $arr) . "\"\n");
		}
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	function get_cache_cnvrt()
	{
		$cachefile = $this->get_cnvtcache_filename(); 
		if(! file_exists($cachefile) ) 
		{  
			return '';
		}
		if( $this->cache['level'] != $this->cache_level['LV2'] )
		{
			unlink($cachefile);  
			return '';
		}
		if($this->cache['state']['hits'] != $this->cache['state']['total']) 
		{  
			return '';
		}
		// This confirm whether config files were changed or not. 
		$cache_time = filemtime($cachefile) - LOCALZONE;
		if( ( get_filetime($this->config->page) > $cache_time) 
		    or ( get_filetime($this->config->page . $this->list) > $cache_time ) 
		    or ( is_page($this->config->page . $this->filter_name)
			 and get_filetime($this->config->page . $this->filter_name) > $cache_time ) )
		{
			unlink($cachefile);  
			return '';
		}

		if( function_exists('file_get_contents' ) ) 
		{
			// file_get_contents is for PHP4 > 4.3.0, PHP5 function  
			$htmls = file_get_contents($cachefile); 
		}
		else 
		{
			$fp = fopen($cachefile,'r')
			  or die('cannot open '.$cachefile);
			$htmls = "";
			do
			{
				$data = fread($fp, 8192);
				if (strlen($data) == 0) {
					break;
				}
				$htmls .= $data;
			} while(true);
		}
		$this->cache['state']['cnvrt'] = TRUE;

		if( $this->cache['verbs'] == TRUE ) 
		{
			$htmls .= $this->get_verbose_cachestatus();
		}

		return $htmls;
	}
	function put_cache_cnvrt($htmls)
	{
		if( $this->cache['level'] != $this->cache_level['LV2'] )
		{
			return ;
		}

		// toString() の結果をキャッシュとして書き出す
		$cachefile = $this->get_cnvtcache_filename(); 

		$fp = fopen($cachefile, 'w')
			or die('cannot open '.$cachefile);

		set_file_buffer($fp, 0);
		flock($fp, LOCK_EX);
		fwrite($fp, $htmls);
		flock($fp,LOCK_UN);
		fclose($fp);
	}

	// static method.
	function delete_caches($del_pattern)
	{
	        $dir = CACHE_DIR;
		if(! $dp = @opendir($dir) )
		{
			return FALSE;
		}
		while($file = readdir($dp))
		{
			if(preg_match("/$del_pattern/",$file))
			{
				unlink($dir . $file);
			}
		}
		closedir($dp);
		return TRUE;
	}
}

function plugin_tracker_get_source($page)
{
	$source = get_source($page);
	// 見出しの固有ID部を削除
	$source = preg_replace('/^(\*{1,3}.*)\[#[A-Za-z][\w-]+\](.*)$/m','$1$2',$source);
	// #freezeを削除
	return preg_replace('/^#freeze\s*$/im', '', $source);

}

// I want to make Tracker_list_filter and Tracker_list_filterCondition to
// inner class of Tracker_list. But inner class is supported by PHP5, not PHP4.(jjyun)
class Tracker_list_filter
{
	var $filter_name;
	var $filter_conditions = array();
  
	function Tracker_list_filter($filter_config, $filter_name)
	{
		$this->filter_name = $filter_name;
		foreach( $filter_config->get($filter_name) as $filter )
		{
			array_push( $this->filter_conditions,
				    new Tracker_list_filterCondition($filter, $filter_name) );
		}
	}

	function filters($var)
	{
		$condition_flag = true;
		foreach($this->filter_conditions as $filter)
		{
			if($filter->is_cnctlogic_AND)
			{
				$condition_flag = ($filter->filter($var) and $condition_flag );
			}
			else
			{  
				$condition_flag = ($filter->filter($var)  or $condition_flag );
			}
		}
		return $condition_flag;
	}
}
class Tracker_list_filterCondition
{
	var $name;
	var $target;
	var $matches;
	var $is_exclued;
	var $is_cnctlogic_AND;
  
	function Tracker_list_filterCondition($field,$name)
	{
		$this->name = $name;
		$this->is_cnctlogic_AND = ($field[0] == "かつ") ? true : false ;
		$this->target = $field[1];
		$this->matches = preg_quote($field[2],'/');
		$this->matches = implode(explode(',',$this->matches) ,'|');
		$this->is_exclued = ($field[3] == "除外") ? true : false ;
		
	}
  
	function filter($var)
	{
		$flag = preg_match("/$this->matches/",$var[$this->target]);
		return ($this->is_exclued) ? (! $flag): $flag;
	}

	function toString()
	{
		$str =
		  "name   : $this->name |"
		  . "target : $this->target |"
		  . "matches: $this->matches |"
		  . "exc-lgc: $this->is_exclued | "
		  . "cnctlgc: $this->is_cnctlogic_AND |";
		return $str;
	}
}
class Tracker_field_select2 extends Tracker_field_select
{
	var $sort_type = SORT_NUMERIC;
  

	//Tracker_field_select にあるmultiple 指定ができないようにする。
	function get_tag($empty=FALSE)
	{
		$s_name = htmlspecialchars($this->name);
		$retval = "<select name=\"{$s_name}[]\">\n";
		if ($empty)
		{
			$retval .= " <option value=\"\"></option>\n";
		}
		$defaults = array_flip( preg_split('/\s*,\s*/',$this->default_value,-1,PREG_SPLIT_NO_EMPTY));

		foreach ($this->config->get($this->name) as $option)
		{
			$s_option = htmlspecialchars($option[0]);
			$selected = array_key_exists(trim($option[0]),$defaults) ? ' selected="selected"' : '';
			$retval .= " <option value=\"$s_option\"$selected>$s_option</option>\n";
		}
		$retval .= "</select>";
    
		return $retval;
	}
  
	// (sortの適用時に利用)
	// 引数(page内の該当部分)にconfigページの属性値一覧で定義した要素が含まれれば、
	// 属性値一覧で定義された、見出しの値を返す
	function get_value($value)
	{
		// config ページの属性値の読み取り、
		// この属性値に対して指定順に昇順に数を振った配列を作成する
		static $options = array();
		if (!array_key_exists($this->name,$options))
		{ 
			$options[$this->name] = array_flip(array_map(create_function('$arr','return $arr[0];'), $this->config->get($this->name)));
		}

		$regmatch_value=$this->get_key($value);

		// 該当値が config ページで指定された値であれば、
		// 上記で求めた設定順を示す値を返す
		if( array_key_exists($regmatch_value,$options[$this->name]) ) 
		{
			return $options[$this->name][$regmatch_value];
		}
		else 
		{
		  return $regmatch_value;
		}
	}
  
	// (styleの適用時、list表示内容に、利用される)
	// 引数(page内の該当部分)にconfigページの属性値一覧で定義した要素が含まれれば、
	// その見出しの値を返す
	function get_key($str)
	{
		// 該当フィールドのBlockPluginを為す文字列から0番目の引数にあたる文字列を読み取る
		$arg= Tracker_field_string_utility::get_argument_from_block_type_plugin_string($str);

		// configページで設定された属性値と比較する
		foreach ($this->config->get($this->name) as $option) {
			// '/'文字が選択候補文字列に入っても処理できるようにescapeする
		 	$eoption=preg_quote($option[0],'/');
			if(preg_match("/^$eoption$/",$arg)){
			  return $option[0];
			}
		}
		return $arg;
	}
  
	// 引数(page内の該当部分)にconfigページの属性値一覧で定義した要素が含まれれば、
	// その見出しの値を返す(tracker_list表示で、利用されている)
	function format_cell($str)
	{
		return $this->get_key($str);
	}
}
class Tracker_field_hidden2 extends Tracker_field_hidden
{
	var $sort_type = SORT_REGULAR;
  
	// (sortの適用時に、利用されている)
	// 引数(page内の該当部分)に対して、configページのオプション指定に従って、
	// ブロック型のプラグイン引数から指定された部分の文字列を
	// 切り出した値を返す処理を含む
	function get_value($value)
	{
		$extract_arg_num = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
		  htmlspecialchars($this->values[0]) : '' ;
		$target_plugin_name = array_key_exists(1,$this->values) ?
		  htmlspecialchars($this->values[1]) : '.*' ;
		$target_plugin_type = array_key_exists(2,$this->values) ?
		  htmlspecialchars($this->values[2]) : 'block' ;
    
		// オプションの指定がなければ、拡張処理は行わない
		if($extract_arg_num == '')
		{
		  return $value;
		}

		// 指定されたプラグインから位置の引数を抽出する
		$arg = Tracker_field_string_Utility::get_argument_from_plugin_string(
			    $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

		// 抽出した位置の引数に対して、さらに正規表現による抽出指定があればそれを行う
		if( $expatern_with_argument != null && 
		    preg_match("/$expatern_with_argument/",$arg,$match) )
		{
			$arg = $match[1];
		}

		return $arg;
	}
	// 引数(page内の該当部分)に対して、
	// configページのオプション指定に従って切り出した値を返し、
	// page内の該当部分にconfigページの属性値一覧で定義した要素が含まれれば、
	// その見出しの値を返す（styleの適用時に、利用されている）
	function get_key($str)
	{
		// 引数(page内の該当部分)に対して、configページのオプション指定に従って切り出す。
		$str= $this->get_value($str);
		foreach ($this->config->get($this->name) as $option)
		{
			// '/'文字が選択候補文字列に入っても処理できるようにescapeする
			$eoption=preg_quote($option[0],'/');
			if( preg_match("/$eoption/",$str) )
			{
				return $option[0];
			}
		}
		return $str;
	}
  
	// 引数(page内の該当部分)に対して、configページのオプション指定に従って切り出した値を返し、
	// page内の該当部分にconfigページの属性値一覧で定義した要素が含まれれば、
	// その見出しの値を返す(tracker_list表示で、利用されている)
	function format_cell($str)
	{
		return $this->get_value($str);
	}
	// Pageへ転記する際の値を返す
	function format_value($value,$post)
	{
		$str=$value;
		
		foreach( array_keys($post) as $postkey )
		{
			if( preg_match("[$postkey]",$str) )
			{
				// 置換候補が Arrayになる場合は、配列から先頭要素を割り当てる
				if( is_array($post[$postkey]) )
				{
				  $str = str_replace("[$postkey]",array_shift($post[$postkey]),$str);
				}
				else
				{
				  $str = str_replace("[$postkey]",$post[$postkey],$str);
				}
			}
		}
		return parent::format_value($str);
	}
}
class Tracker_field_hidden3 extends Tracker_field_hidden2
{
	var $sort_type = SORT_NUMERIC;
	// (sortの適用時に、利用されている)
	// 引数(page内の該当部分)に対して、configページのオプション指定に従って、
	// ブロック型のプラグイン引数から指定された部分の文字列を
	// 切り出した値を返す処理を含む
	function get_value($value)
	{
		$extract_arg_num = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
		  htmlspecialchars($this->values[0]) : '' ;
		$target_plugin_name = array_key_exists(1,$this->values) ?
		  htmlspecialchars($this->values[1]) : '.*' ;
		$target_plugin_type = array_key_exists(2,$this->values) ?
		  htmlspecialchars($this->values[2]) : 'block' ;
    
		// オプションの指定がなければ、拡張処理は行わない
		if($extract_arg_num == '')
		{
		  return $value;
		}

		// 指定されたプラグインから位置の引数を抽出する
		$arg = Tracker_field_string_Utility::get_argument_from_plugin_string(
			    $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

		// 抽出した位置の引数に対して、さらに正規表現による抽出指定があればそれを行う
		$arg = (preg_match("/(\d+)/",$arg,$match) ) ? $match[1] : 0;

		return $arg;
	}
}

class Tracker_field_datefield extends Tracker_field
{
	function get_tag()
	{
    		$s_name = htmlspecialchars($this->name);
		$s_size = (array_key_exists(0,$this->values)) ? htmlspecialchars($this->values[0]) : '10';
		$s_format = (array_key_exists(1,$this->values)) ? htmlspecialchars($this->values[1]) : 'YYYY-MM-DD';
		$s_value = htmlspecialchars($this->default_value);
		
		$s_year  = date("Y",time());
		$s_month = date("m",time()); 
		$s_date  = date("d",time()); 
		
		require_once( PLUGIN_DIR . 'datefield.inc.php');
		// デフォルト値を現在の日付にする
		if($s_value=="NOW")
		{
		  $s_value = Tracker_field_datefield::get_datestr_with_format($s_format, $s_year, $s_month-1, $s_date);
		}
		// Javascriptに引きわたす形式のフォーマット文字列に変更する
		$s_format = Tracker_field_datefield::form_format($s_format);
		
		return <<<EOD
<input type="text" name="$s_name" size="$s_size" value="$s_value" />
<input type="button" value="..." onclick="dspCalendar(this.form.$s_name, event, $s_format, 0 , $s_year, $s_month-1, $s_date, 0);" />
EOD;
	}
  
	// sortの適用時に、その値を以って処理を行わせる。
	// 該当部分に含まれるブロックプラグインの0番目の引数を返す
	function get_value($value)
	{
		$arg= Tracker_field_string_utility::get_argument_from_block_type_plugin_string($value,0,'datefield');
		return $arg;
	}

	// tracker_list表示での、出力内容を返す
	function format_cell($str)
	{
		return $this->get_value($str);
	}
  
	// Pageへ転記する際の値を返す
	function format_value($value)
	{
		$s_format = (array_key_exists(1,$this->values)) ? htmlspecialchars($this->values[1]) : 'YYYY-MM-DD';
		$s_unmdfy = (array_key_exists(2,$this->values)) ? htmlspecialchars($this->values[2]) : 'FALSE';
    		if($s_unmdfy != 'TRUE')
		{
		  $value = "#datefield($value,$s_format)";
		}
		return parent::format_value($value);
	}

	function form_format($format_opt) {
		$format_str= trim($format_opt);
		if(strlen($format_str) == 0 )  $format_str = 'YYYY/MM/DD';
		if(preg_match('/^[\'\"].*[\"\']$/',$format_str)) /* " */
		{ 
			$format_str = '\'' . substr($format_str,1,strlen($format_str)-2) . '\'';
		}
		else
		{
			$format_str = '\'' . $format_str . '\'';
		}
		return $format_str;
	}

	// 月日の値は既に2桁の値として渡すこと
	function get_datestr_with_format($format_opt,$yyyy,$mm,$dd ){
		$strWithFormat = $format_opt;
		$yy = $yyyy%100;
		
		$mm += 1; // 引数の月の値の範囲 month is 0 - 11
		$strWithFormat = preg_replace('/YYYY/i', $yyyy, $strWithFormat);
		$strWithFormat = preg_replace('/YY/i',   $yy,   $strWithFormat);
		$strWithFormat = preg_replace('/MM/i',   $mm,   $strWithFormat);
		$strWithFormat = preg_replace('/DD/i',   $dd,   $strWithFormat);

		return $strWithFormat;
	}

	function set_head_declaration() {
		global $html_transitional, $javascript;

		// XHTML 1.0 Transitional
		$html_transitional = TRUE;
		
		// <head> タグ内への <meta>宣言の追加
		$javascript = TRUE;
	}
}

class Tracker_field_string_utility {
  
  	function get_argument_from_block_type_plugin_string($str,
						      $extract_arg_num = 0,
						      $plugin_name = '.*' ) {
	  return Tracker_field_string_utility::get_argument_from_plugin_string($str,$extract_arg_num,$plugin_name,'block');
	}
  
	// plugin_type : block type = 0, inline type = 1.
	// extract_arg_num : first argument number is 0.  
	function get_argument_from_plugin_string($str, 
						 $extract_arg_num, $plugin_name, $plugin_type = 'block')
	{
		$str_plugin = ($plugin_type == 'inline') ? '\&' : '\#' ;
		$str_plugin .= $plugin_name;
		
		$matches = array();

		// 複数のplugin指定が存在する場合でも全てに対して抽出を行う
		if( preg_match_all("/(?:$str_plugin\(([^\)]*)\))/", $str, $matches, PREG_SET_ORDER) )
		{
			$paddata = preg_split("/$str_plugin\([^\)]*\)/", $str);
			$str = $paddata[0];
                        foreach($matches as $i => $match)
			{
				$args = array();

				$str_arg = $match[1];
				$args = explode("," , $str_arg);
				if( is_numeric($extract_arg_num) && $extract_arg_num < count($args) )
				{
					$extract_arg = $args[ $extract_arg_num ];
				}
				else
				{
		  			$extract_arg = $str_plugin . $str_arg;
				}
			}
			// block-type,inline-type のプラグイン指定において、
			// 最後の括弧の後にセミコロンがある場合とない場合が存在するため、
			// セミコロンが直後にあった場合は、プラグイン指定の一部と捉えて除去を行う
			if( preg_match("/^\;.*$/",$paddata[$i+1],$exrep) )
			{
				$paddata[$i+1] = $exrep[1];
			}
                        $str .= $extract_arg . $paddata[$i+1];
                }
		return $str;
	}
}
?>
