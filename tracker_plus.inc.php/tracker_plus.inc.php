<?php
// PukiWiki - Yet another WikiWikiWeb clone
// $Id: tracker_plus.inc.php,v 3.1 2006/05/13 22:05:12 jjyun Exp $
// Copyright (C) 
//   2004-2006 written by jjyun ( http://www2.g-com.ne.jp/~jjyun/twilight-breeze/pukiwiki.php )
// License: GPL v2 or (at your option) any later version
//
// Issue from tracker.inc.php plugin (See Also bugtrack plugin)

require_once(PLUGIN_DIR . 'tracker.inc.php');

/////////////////////////////////////////////////////////////////////////////
// tracker_listで表示しないページ名(正規表現で)
// 'SubMenu'ページ および '/'を含むページを除外する
define('TRACKER_PLUS_LIST_EXCLUDE_PATTERN','#^SubMenu$|/#');
// 制限しない場合はこちら
//define('TRACKER_PLUS_LIST_EXCLUDE_PATTERN','#(?!)#');

/////////////////////////////////////////////////////////////////////////////
// 項目の取り出しに失敗したページを一覧に表示する
define('TRACKER_PLUS_LIST_SHOW_ERROR_PAGE',TRUE);

/////////////////////////////////////////////////////////////////////////////
// CacheLevelのデフォルトの設定
// ** 設定値の説明 ** 負の値は冗長モードを表します
//        0 : キャッシュロジックを利用しない 
//  1 or -1 : ページの読み込み処理に対するキャッシュを有効にする
//  2 or -2 : htmlに変換後のデータのキャッシュを有効にする
define('TRACKER_PLUS_LIST_CACHE_DEFAULT', 0); 
// define('TRACKER_PLUS_LIST_CACHE_DEFAULT', 1); 
// define('TRACKER_PLUS_LIST_CACHE_DEFAULT', 2); 

/////////////////////////////////////////////////////////////////////////////
// [Dynamic Filter Configration Section]
// フィルタを指定時における、動的フィルタ選択のデフォルト設定
//  ** 設定値の説明 ** filter名の頭に + or - を付けるとリスト表示の制御は可能ですが...
//   TRUE : filter名の先頭に + を指定しなくてもフィルタ選択用のリストを表示します
//  FALSE : filter名の先頭に + を指定しないと、フィルタ選択用のリストは表示しません
define('TRACKER_PLUS_LIST_DYNAMIC_FILTER_DEFAULT', TRUE);
//
/////////////////////////////////////////////////////////////////////////////
// 動的フィルタのリストラベルの拡張設定
define('TRACKER_PLUS_LIST_APPLY_LISTFORMAT',TRUE);
//
//////////////////////////////////////////////////////////////////////////////
// [Paging Configration Section] 
// リスト表示時のページング機能の設定
// 0: Disable paging ( same as tracker.inc.php)
// 1:  Enable paging with only LinkMark.
// 2:  Enable paging with only less/more MarkStr.
// 3:  Enable paging with LinkMark and less/more MarkStr.
//define('TRACKER_PLUS_LIST_PAGING', 0); 
//define('TRACKER_PLUS_LIST_PAGING', 1);
//define('TRACKER_PLUS_LIST_PAGING', 2);
define('TRACKER_PLUS_LIST_PAGING', 3);
// 一度に表示する linkMark の数 
define('TRACKER_PLUS_LIST_PAGING_MARK_NUMBER_PER_ONCE', 10);
//
/////////////////////////////////////////////////////////////////////////////
// リスト作成時にページレイアウトを評価する範囲を
// "//////////" の直前までに制限する.
// ( パターンマッチングするレイアウト範囲の制御
define('TRACKER_PLUS_LIST_APPLY_LIMIT_PARSERANGE',TRUE);
//
// *** Definition for codes, Don't modify...***
define('TRACKER_PLUS_LIST_PAGING_ENABLE_LINKMARK', 1); 
define('TRACKER_PLUS_LIST_PAGING_ENABLE_STRGMARK', 2); 

function plugin_tracker_plus_init()
{
    switch (LANG)
    {
    case 'ja' :
        $msg = plugin_tracker_plus_init_ja();
        break;
    default:
        $msg = plugin_tracker_plus_init_en();
    }
    set_plugin_messages($msg);
}

function plugin_tracker_plus_init_ja()
{
    $msg = array(
     //        '_tracker_plus_msg' => array(),
        '_tracker_plus_list_msg' => array(
            'nodata'             => '一覧に表示する項目はありません.',
            'paging'             => '全 $1件中、$2 件目 から $3 件目 まで表示しています.' ,
            'paging_linkMark'    => '■',
            'paging_lessMark'    => '《',
            'paging_moreMark'    => '》',
            'paging_lessMarkStr' => '[前の $1 件]',
            'paging_moreMarkStr' => '[次の $1 件]',
            'filter_label'          => '絞り込み条件一覧',
            'filter_extTitle'    => '拡張見出し',
            'filter_title_logicalOperator' => '連結条件',
            'filter_title_targetField'     => '対象項目',
            'filter_title_operator'        => '条件',
            'filter_title_conditionValues' => '条件値',
            'filter_definition_error'      => 'フィルタ条件が不正です'
        )
    ); 
    return $msg;
}

function plugin_tracker_plus_init_en()
{
     $msg = array(
     //        '_tracker_plus_msg' => array(),
        '_tracker_plus_list_msg' => array(
            'nodata'             => 'There is no item displayed in List.',
            'paging'             => 'This displays it from the $2 th to the $3 th among $1.',
            'paging_linkMark'    => '■',
            'paging_lessMark'    => '《',
            'paging_moreMark'    => '》',
            'paging_lessMarkStr' => '[Before $1 ]',
            'paging_moreMarkStr' => '[Next $1 ]',
            'filter_label'       => 'FilterList of TrackerList',
            'filter_extTitle'    => 'EXTENTED_TITLE',
            'filter_title_logicalOperator' => 'LogicalOperator',
            'filter_title_targetField'     => 'Target',
            'filter_title_operator'        => 'Operator',
            'filter_title_conditionValues' => 'ConditionValues',
            'filter_definition_error'      => 'filter definition is irregal.'
        )
     );
    return $msg;
}

function plugin_tracker_plus_convert()
{
    global $script,$vars;

    if( defined('PKWK_READONLY') && PKWK_READONLY) return ''; // Show nothing

    $base = $refer = $vars['page'];

    $config_name = 'default';
    $form = 'form';

    $isDatefield = FALSE;

    if( func_num_args() )
    {
        $options = array();
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

        unset($options, $args);
    }

    $config = new Config('plugin/tracker/'.$config_name);

    if( ! $config->read() )
    {
        return "<p>config file '".htmlspecialchars($config_name)."' not found.</p>";
    }

    // (Attension) Config Class don't have config_name member. This make extra definision. (jjyun)
    $config->config_name = $config_name;

    $fields = plugin_tracker_plus_get_fields( $base, $refer, $config );

    $form = $config->page.'/'.$form;
    
    if( ! is_page($form) )
    {
        return "<p>config file '".make_pagelink($form)."' not found.</p>";
    }
    $retval = convert_html(plugin_tracker_plus_get_source($form));
    $hiddens = '';

    foreach( array_keys($fields) as $name )
    {
        if( is_a($fields[$name],'Tracker_field_datefield') )
        {
            $isDatefield = TRUE;
        }

        $replace = $fields[$name]->get_tag();
        if( is_a($fields[$name],'Tracker_field_hidden') )
        {
            $hiddens .= $replace;
            $replace = '';
        }
        $retval = str_replace("[$name]",$replace,$retval);
    }

    if( $isDatefield == TRUE )
    {
        Tracker_field_datefield::set_head_declaration();
        $number = plugin_tracker_plus_getNumber();
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

function plugin_tracker_plus_getNumber()
{
    global $vars;
    static $numbers = array();
    if( ! array_key_exists($vars['page'],$numbers) )
    {
        $numbers[$vars['page']] = 0;
    }
    return $numbers[$vars['page']]++;
}

function plugin_tracker_plus_action()
{
    global $post, $now;

    if( defined('PKWK_READONLY') && PKWK_READONLY )
        die_message('PKWK_READONLY prohibits editing');

    $config_name = array_key_exists('_config',$post) ? $post['_config'] : '';

    $config = new Config('plugin/tracker/'.$config_name);
    if( ! $config->read() )
    {
        return "<p>config file '".htmlspecialchars($config_name)."' not found.</p>";
    }
    $config->config_name = $config_name;

    $source = $config->page.'/page';

    $refer = array_key_exists('_refer',$post) ? $post['_refer'] : $post['_base'];

    if( ! is_pagename($refer) )
    {
        return array(
            'msg'=>'cannot write',
            'body'=>'page name ('.htmlspecialchars($refer).') is not valid.'
        );
    }
    if( ! is_page($source) )
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
    if( array_key_exists('_page',$post) )
    {
        $page = $real = $post['_page'];
    }
    else
    {
        $real = is_pagename($name) ? $name : ++$num;
        $page = get_fullname('./'.$real,$base);
    }
    if( ! is_pagename($page) )
    {
        $page = $base;
    }

    while( is_page($page) )
    {
        $real = ++$num;
        $page = "$base/$real";
    }

    // org: QuestionBox3/211: Apply edit_auth_pages to creating page
    edit_auth($page,true,true);

    // ページデータを生成
    $postdata = plugin_tracker_plus_get_source($source);

    // 規定のデータ
    $_post = array_merge($post,$_FILES);
    $_post['_date'] = $now;
    $_post['_page'] = $page;
    $_post['_name'] = $name;
    $_post['_real'] = $real;
    // $_post['_refer'] = $_post['refer'];

    $fields = plugin_tracker_plus_get_fields($page,$refer,$config);

    // Creating an empty page, before attaching files
           touch(get_filename($page));

    foreach( array_keys($fields) as $key)
    {
            // modified for hidden2 by jjyun
        // $value = array_key_exists($key,$_post) ?
        //     $fields[$key]->format_value($_post[$key]) : '';
            $value = '';
        if( array_key_exists($key,$_post) )
        {
            $value = is_a($fields[$key],"Tracker_field_hidden2") ?
                $fields[$key]->format_value($_post[$key],$_post) :
                $fields[$key]->format_value($_post[$key]);
        }

        foreach( array_keys($postdata) as $num)
        {
            if( trim($postdata[$num]) == '' )
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

    if( function_exists('pkwk_headers_sent') )
        pkwk_headers_sent();

    header('Location: ' . get_script_uri() . '?' . $r_page);
    exit;
}

// フィールドオブジェクトを構築する
function plugin_tracker_plus_get_fields($base,$refer,&$config)
{
    global $now,$_tracker_messages;

    $fields = array();
    // 予約語
    foreach( array(
                 '_date'   => 'text',        // 投稿日時
                 '_update' => 'date',        // 最終更新
                 '_past'   => 'past',        // 経過(passage)
                 '_page'   => 'page',        // ページ名
                 '_name'   => 'text',        // 指定されたページ名
                 '_real'   => 'real',        // 実際のページ名
                 '_refer'  => 'page',        // 参照元(フォームのあるページ)
                 '_base'   => 'page',        // 基準ページ
                 '_submit' => 'submit_plus', // 追加ボタン
                 ) as $field=>$class)
    {
        $class = 'Tracker_field_'.$class;
        $fields[$field] = &new $class(array($field,$_tracker_messages["btn$field"],'','20',''),$base,$refer,$config);
    }

    foreach( $config->get('fields') as $field)
    {
        // 0=>項目名 1=>見出し 2=>形式 3=>オプション 4=>デフォルト値
        $class = 'Tracker_field_'.$field[2];
        if( ! class_exists($class) )
        { // デフォルト
            $class = 'Tracker_field_text';
            $field[2] = 'text';
            $field[3] = '20';
        }
        $fields[$field[0]] = &new $class($field,$base,$refer,$config);
    }
    return $fields;
}


///////////////////////////////////////////////////////////////////////////
// 一覧表示
function plugin_tracker_plus_list_convert()
{
    global $vars;

    $config = 'default';
    $page = $refer = $vars['page'];
    $field = '_page';
    $order = '_real:SORT_DESC';
    $list = 'list';
    $limit = NULL;
    $filter_name = '';
    $cache = TRACKER_PLUS_LIST_CACHE_DEFAULT;

    if( func_num_args() )
    {
        $args = func_get_args();
        switch ( count($args) )
        {
        case 6:
            $cache = is_numeric($args[5]) ? $args[5] : $cache;
        case 5:
            $filter_name = $args[4];
        case 4:
            $limit = is_numeric($args[3]) ? $args[3] : $limit;
        case 3:
            $order = $args[2];
        case 2:
            $args[1] = get_fullname($args[1],$page);
            $page = is_pagename($args[1]) ? $args[1] : $page;
        case 1:
            $config = ( $args[0] != '' ) ? $args[0] : $config;
            list($config,$list) = array_pad( explode('/',$config,2), 2, $list );
        }
    }

    return plugin_tracker_plus_getlist($page,$refer,$config,$list,$order,$limit,$filter_name,$cache);
}

function plugin_tracker_plus_list_action()
{
    global $script,$vars,$_tracker_messages;

    $page = $refer = $vars['opage'];
    $orefer = $vars['orefer'];
    if( $orefer == NULL) $orefer = $refer;
    $s_orefer = make_pagelink($orefer);
    $config = $vars['config'];
    $list = array_key_exists('list',$vars) ? $vars['list'] : 'list';
    $order = array_key_exists('order',$vars) ? $vars['order'] : '_real:SORT_DESC';
    $filter_name = array_key_exists('filter',$vars) ? $vars['filter'] : NULL;

    $cache = isset($vars['cache']) ? $vars['cache'] : NULL;
    $dynamicFilter = isset($vars['dynamicFilter']) ? true : false;

    $limit = isset($vars['limit']) ? $vars['limit'] : NULL;
    $pagingNo = isset($vars['paging']) ? $vars['paging'] : NULL;

    // this delete tracker caches. 
    if( $cache == 'DELALL' )
    {
        if( ! Tracker_plus_list::delete_caches('(.*)(.tracker)$') )
          die_message( CACHE_DIR . ' is not found or not readable.');

        return array(
                 'result' => FALSE,
                 'msg' => 'tracker_plus_list caches are cleared.',
                 'body' =>'tracker_plus_list caches are cleared.',
                 );
    }

    if( $dynamicFilter )
    {
        $filter_name = $vars['value'];
    }
    
    return array(
             'msg' => str_replace('$1',$page,$_tracker_messages['msg_list']),
             'body'=> str_replace('$1',$s_orefer,$_tracker_messages['msg_back']).
             plugin_tracker_plus_getlist($page,$refer,$config,$list,$order,$limit,$filter_name,$cache, $orefer, $pagingNo)
    );
}

function plugin_tracker_plus_getlist($page, $refer, $config_name, $list_name, $order = '', $limit = NULL,
                     $filter_name = NULL, $cache, $orefer = NULL, $pagingNo = 0)
{
    $limit = ($limit == "" ) ? NULL : $limit;

    $config = new Config('plugin/tracker/'.$config_name);

    if( ! $config->read() )
    {
        return "<p>config file '".htmlspecialchars($config_name)."' is not exist.";
    }

    $config->config_name = $config_name;

    if( ! is_page($config->page.'/'.$list_name) )
    {
        return "<p>config file '".make_pagelink($config->page.'/'.$list_name)."' not found.</p>";
    }

    // Attension: Original code use $list as another use in this. (before this, $list contains list_name). by jjyun.
    $list = &new Tracker_plus_list($page,$refer,$config,$list_name,$filter_name,$cache,$orefer);

    $filter_selector = '';
    if( $list->filter_name != NULL || $list->dynamic_filter == TRUE )
    {
        $filter_config = new Tracker_plus_FilterConfig('plugin/tracker/'.$config_name.'/filters');

        if( ! $filter_config->read() && $list->filter_name != NULL )
        {
                // filterの設定がなされていなければ, エラーログを返す
                return "<p>config file '".htmlspecialchars($config->page.'/filters')."' not found</p>";
        }

        $list_filter = &new Tracker_plus_list_filter($filter_config, $list->filter_name, $list->dynamic_filter);
        
        $filter_selector = $list->get_selector($list_filter, $order, $limit);
        $list_filter->list_filter($list);
        unset($list_filter);
    }

    $list->sort($order);
    
    return $filter_selector . $list->toString($limit, $pagingNo);
}    

class Tracker_plus_FilterConfig extends Config
{

    function Tracker_plus_FilterConfig($name)
    {
        parent::Config($name);
    }

    function get_keys()
    {
        return array_keys($this->objs);
    }


    function before_read($title)
    {
        $before_obj = NULL;
        $obj = & $this->get_object($title);
  
        foreach( $obj->before as $before_line )
        {
            if ( strlen($before_line) > 0 && $before_line{0} == '|' && preg_match('/^\|(.+)\|([hH]?)\s*$/', $before_line, $matches) )
            {
                // Table row
                if (! is_a($before_obj, 'ConfigTable_Sequential') )
                    $before_obj = & new ConfigTable_Sequential('', $before_obj);
                // Trim() each table cell
                $before_obj->add_value(array_map('trim', explode('|', $matches[1])));
            }
        }
        if( is_null($before_obj) )
            return NULL;
        else
            return $before_obj->values[0];
    }
    
    function after_read($title)
    {
        $after_obj = NULL;
        $obj = & $this->get_object($title);
        
        foreach( $obj->after as $after_line )
        {
            if( $after_line == '' )
                continue;
            
            if( $after_line{0} == '|' && preg_match('/^\|(.+)\|([fF]?)\s*$/', $after_line, $matches) )
            {
                // Table row
                if( ! is_a($after_obj, 'ConfigTable_Sequential') )
                    $after_obj = & new ConfigTable_Sequential('', $after_obj);
                // Trim() each table cell
                $after_obj->add_value(array_map('trim', explode('|', $matches[1])));
            }

        }

        if( is_null($after_obj) )
            return NULL;
        else
            return $after_obj->values;
    }
}


// 一覧クラス
class Tracker_plus_list extends Tracker_list
{
    var $filter_name;
    var $dynamic_filter = TRACKER_PLUS_LIST_DYNAMIC_FILTER_DEFAULT;
    var $orefer;
    var $cache_level = array(
               'NO'  => 0, // キャッシュロジックを利用しない
               'LV1' => 1, // ページの読み込み処理に対するキャッシュを有効にする
               'LV2' => 2, // htmlに変換後のデータのキャッシュを有効にする
               );

    var $cache = array('level' => TRACKER_PLUS_LIST_CACHE_DEFAULT ,
               'state' => array('stored_total' => 0,  // cacheにあるデータ数
                        'hits' => 0,          // cache内にある有効なデータ数
                        'total' => 0,         // 更新分を含めて最終的に有効なデータ数
                        'cnvrt' => FALSE), 
               'verbs' => FALSE,
               );

    function Tracker_plus_list($page,$refer,&$config,$list,$filter_name,$cache,$orefer = NULL)
    {
        $this->page = $page;
        $this->config = &$config;
        $this->list = $list;
        $this->refer = $refer;
        $this->orefer = ($orefer != NULL) ? $orefer : $refer;

        if( preg_match("/^[+|-](.*)/", $filter_name, $matches) )
        {
            $this->filter_name = $matches[1];
            $this->dynamic_filter = ( ord($filter_name) == ord("+") ) ? true : false;
        }
        else
        {
            $this->filter_name = $filter_name;
            if( strlen($this->filter_name) == 0 )
            {
                $this->dynamic_filter = false;
            }
        }

        $this->fields = plugin_tracker_plus_get_fields($page,$refer,$config);
        
        $pattern = join('',plugin_tracker_plus_get_source($config->page.'/page'));
        
        // "/page"の内容が長すぎるとpreg_match()が失敗するバグ(?)があるので
        // "//////////"までをマッチ対象とさせる
        // ( see http://dex.qp.land.to/pukiwiki.php, Top/Memo/Wikiメモ)
        $pattern_endpos = strpos($pattern, "//////////");
        if( $pattern_endpos > 0 )
        {
            $pattern = substr($pattern, 0, $pattern_endpos);
        }
        
        // ブロックプラグインをフィールドに置換
        // #commentなどで前後に文字列の増減があった場合に、[_block_xxx]に吸い込ませるようにする
        $pattern = preg_replace('/^\#([^\(\s]+)(?:\((.*)\))?\s*$/m','[_block_$1]',$pattern);

        // パターンを生成
        $this->pattern = '';
        $this->pattern_fields = array();
        $pattern = preg_split('/\\\\\[(\w+)\\\\\]/',preg_quote($pattern,'/'),-1,PREG_SPLIT_DELIM_CAPTURE);
        while( count($pattern) )
        {
            $this->pattern .= preg_replace('/\s+/','\\s*','(?>\\s*'.trim(array_shift($pattern)).'\\s*)');
            if( count($pattern) )
            {
                $field = array_shift($pattern);
                $this->pattern_fields[] = $field;
                $this->pattern .= '(.*)';
            }
        }

        $this->cache['verbs'] = ($cache < 0) ? TRUE : FALSE;
        $this->cache['level'] = (abs($cache) <= $this->cache_level['LV2']) ? abs($cache) : $this->cache_level['NO']; 

        // ページの列挙と取り込み

        // cache から cache作成時刻からデータを取り込む
        // $this->add()での処理の前にあらかじめ cache から取り込んでおくことで、
        // $this->add()の無限ループ防止ロジックを利用して、対象データを含むページの読み込みを行わせない。
        $this->get_cache_rows();

        $pattern = "$page/";
        $pattern_len = strlen($pattern);
        foreach( get_existpages() as $_page)
        {
            if( strpos($_page,$pattern) === 0 )
            {
                $name = substr($_page,$pattern_len);
                if( preg_match(TRACKER_PLUS_LIST_EXCLUDE_PATTERN,$name) )
                {
                    continue;
                }
                $this->add($_page,$name);
            }
        }
        $this->cache['state']['total'] = count($this->rows);
        $this->put_cache_rows();
    }

    // over-wride function.
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

        // "/page"の内容が長すぎるとpreg_match()が失敗するバグ(?)があるので
        // "//////////"までをマッチ対象とさせる
        if( TRACKER_PLUS_LIST_APPLY_LIMIT_PARSERANGE )
        {
            $source_endpos = strpos($source, "//////////");
            if( $source_endpos > 0 )
            {
                $source = substr($source, 0, $source_endpos);
            }
        }

		// デフォルト値
		$this->rows[$name] = array(
			'_page'  => "[[$page]]",
			'_refer' => $this->page,
			'_real'  => $name,
			'_update'=> get_filetime($page),
			'_past'  => get_filetime($page)
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

    // over-wride function.
    function replace_title($arr)
    {
        global $script;

        $field = $sort = $arr[1];
        if( $sort == '_name' or $sort == '_page' )
        {
            $sort = '_real';
        }
        if( ! array_key_exists($field,$this->fields) )
        {
            return $arr[0];
        }
        $dir = SORT_ASC;
        $arrow = '';
        $order = $this->order;
        
        if( is_array($order) && isset($order[$sort]) )
        {
            // BugTrack2/106: Only variables can be passed by reference from PHP 5.0.5
            $order_keys = array_keys($order); // with array_shift();

            $index = array_flip($order_keys);
            $pos = 1 + $index[$sort];
            $b_end = ($sort == array_shift($order_keys));
            $b_order = ($order[$sort] == SORT_ASC);
            $dir = ($b_end xor $b_order) ? SORT_ASC : SORT_DESC;
            $arrow = '&br;'.($b_order ? '&uarr;' : '&darr;')."($pos)";

            unset($order[$sort], $order_keys);
        }
        $title = $this->fields[$field]->title;

        $r_opage = rawurlencode($this->page);
        $r_orefer = rawurlencode($this->orefer);
        $r_config = rawurlencode($this->config->config_name);
        $r_list = rawurlencode($this->list);
        $_order = array("$sort:$dir");
        if( is_array($order) )
        {
            foreach( $order as $key=>$value)
                $_order[] = "$key:$value";
        }
        $r_order = rawurlencode(join(';',$_order));

        // attension : if you modified this, you should also see $this->get_selector()
        $_filter = ($this->dynamic_filter) ? "+". $this->filter_name : $this->filter_name; 
        $r_filter = rawurlencode($_filter);

        $_cache = ( $this->cache['level'] == $this->cache_level['LV2'] ) ? $this->cache_level['LV1'] : $this->cache['level'];
        if( $this->cache['verbs'] )
            $_cache = -1 * $_cache;
        $r_cache = rawurlencode($_cache);
        
        return "[[$title$arrow>$script?plugin=tracker_plus_list&opage=$r_opage&orefer=$r_orefer&config=$r_config&list=$r_list&order=$r_order&filter=$r_filter&cache=$r_cache]]";
    }

    function get_selector($filter,$order,$limit)
    {
        global $script;
        global $_tracker_plus_list_msg;

        if( ! $filter->filter_select )
            return "";

        $optionsHTML = $filter->get_options_html();

        $s_refer = htmlspecialchars($this->refer);
        $s_orefer = htmlspecialchars($this->orefer);
        $s_opage = htmlspecialchars($this->page);
        $s_config = htmlspecialchars($this->config->config_name);
        $s_list = htmlspecialchars($this->list);

        $s_limit = htmlspecialchars($limit);
        $s_order = htmlspecialchars($order);
        $s_filter = htmlspecialchars($filter->name);

        $_cache = ( $this->cache['level'] == $this->cache_level['LV2'] ) ? $this->cache_level['LV1'] : $this->cache['level'];
        if( $this->cache['verbs'] )
            $_cache = -1 * $_cache;
        $s_cache = htmlspecialchars($_cache);
        $s_script = htmlspecialchars($script);

        $selector_html = <<< EOD
<form action="$s_script" method="get" style="margin:0;">
<div>
$_tracker_plus_list_msg[filter_label] : <select name="value" style="vertical-align:middle" onchange="this.form.submit();">
$optionsHTML
</select>
<input type="hidden" name="plugin" value="tracker_plus_list" />
<input type="hidden" name="refer"  value="$s_refer" />
<input type="hidden" name="opage"  value="$s_opage" />
<input type="hidden" name="orefer" value="$s_orefer" />
<input type="hidden" name="config" value="$s_config" />
<input type="hidden" name="list"   value="$s_list" />
<input type="hidden" name="limit"  value="$s_limit" />
<input type="hidden" name="order"  value="$s_order" />
<input type="hidden" name="filter" value="$s_filter" />
<input type="hidden" name="cache"  value="$s_cache" />
<input type="hidden" name="dynamicFilter" value="on" />
</div>
</form>
EOD;

        return $selector_html;
    }

    // It is called only from toString. (Ver2.9- ) 
    function make_paging_links($limit, $pagingNo)
    {
        global $_tracker_plus_list_msg;

        $maxListedMarkNum = TRACKER_PLUS_LIST_PAGING_MARK_NUMBER_PER_ONCE;

        $abovePgLink = "";
        $belowPgLink = "";

        $total = count($this->rows);
        $offset = floor($pagingNo / $maxListedMarkNum ) * $maxListedMarkNum;

        if( ( TRACKER_PLUS_LIST_PAGING & TRACKER_PLUS_LIST_PAGING_ENABLE_LINKMARK ) == TRACKER_PLUS_LIST_PAGING_ENABLE_LINKMARK )
        {
            for( $i = 0 ; ($offset + $i) * $limit < $total && $i < $maxListedMarkNum ; $i++ )
            { 
                $linkMark    = $_tracker_plus_list_msg['paging_linkMark'];
                $abovePgLink .= $this->make_paging_link($limit, $offset + $i, $linkMark); 

            }

            if( $offset > 0 )
            {
                $lessMark    = $_tracker_plus_list_msg['paging_lessMark'];
                $abovePgLink = 
                  $this->make_paging_link($limit, $offset - $maxListedMarkNum , $lessMark) . '&nbsp;' . $abovePgLink;
            }

            if( ($offset + $maxListedMarkNum) * $limit < $total )
            {
                $moreMark    = $_tracker_plus_list_msg['paging_moreMark'];
                $abovePgLink = 
                  $abovePgLink . '&nbsp;' . $this->make_paging_link($limit, $offset + $maxListedMarkNum, $moreMark);
            }

            if( strlen( $abovePgLink ) > 0 )
            {
                $abovePgLink = "<div class='tracker_plus_list_paging_marklist'>" . $abovePgLink . "</div>";
            }
        }

        if( ( TRACKER_PLUS_LIST_PAGING & TRACKER_PLUS_LIST_PAGING_ENABLE_STRGMARK ) == TRACKER_PLUS_LIST_PAGING_ENABLE_STRGMARK )
        {
            if( $pagingNo > 0 )
            {
                $lessMarkStr =
                  str_replace( array('$1'), array($limit), $_tracker_plus_list_msg['paging_lessMarkStr']);
                $belowPgLink .= $this->make_paging_link($limit, $pagingNo - 1 , $lessMarkStr) . '&nbsp;' ;
            }
                
            if( ($pagingNo + 1) * $limit < $total )
            {
                $moreMarkStr = 
                  str_replace( array('$1'), array($limit), $_tracker_plus_list_msg['paging_moreMarkStr']);
                $belowPgLink  .= $this->make_paging_link($limit, $pagingNo + 1 , $moreMarkStr);
            }
            
            if( strlen( $belowPgLink ) > 0 )
            {
                $belowPgLink = "<div class='tracker_plus_list_paging_markstr'>" . $belowPgLink . "</div>";
            }
        }
        
        $showMax = ( ($pagingNo + 1) * $limit > $total) ? $total : ($pagingNo + 1) * $limit;
        $pagingStatus = str_replace( array('$1','$2','$3'),
                        array( $total, $limit * $pagingNo + 1 , $showMax),
                         $_tracker_plus_list_msg['paging']);
        $abovePgLink = "<div class='tracker_plus_list_paging_status'>". $pagingStatus . "</div>" . $abovePgLink;

         return array($abovePgLink, $belowPgLink);
    }

    // It is called only from make_paging_links(). (Ver2.9- ) 
    function make_paging_link($limit, $pagingNo,$mark)
    {
        global $script;

        $r_opage = rawurlencode($this->page);
        $r_orefer = rawurlencode($this->orefer);
        $r_config = rawurlencode($this->config->config_name);
        $r_list = rawurlencode($this->list);
        $_order = array();
        $order = $this->order;
        if( is_array($order) )
        {
            foreach( $order as $key=>$value)
            {
                $_order[] = "$key:$value";
            }
        }
        $r_order = rawurlencode(join(';',$_order));

        // attension : if you modified this, you should also see $this->get_selector()
        $_filter = ($this->dynamic_filter) ? "+". $this->filter_name : $this->filter_name; 
        $r_filter = rawurlencode($_filter);

        $_cache = ( $this->cache['level'] == $this->cache_level['LV2'] ) ? $this->cache_level['LV1'] : $this->cache['level'];
        if( $this->cache['verbs'] )
            $_cache = -1 * $_cache;
        $r_cache = rawurlencode($_cache);
        
        $r_limit  = rawurlencode($limit);
        $r_pageNo = rawurlencode($pagingNo);

          $r_newpage = "plugin=tracker_plus_list&opage=$r_opage&orefer=$r_orefer&config=$r_config&list=$r_list&order=$r_order&filter=$r_filter&cache=$r_cache&limit=$r_limit&paging=$r_pageNo";
          $r_pglabel = $limit * $pagingNo + 1;
          return '<a href="' . $script . '?' . $r_newpage . '" title="' . $r_pglabel . '- ">'. $mark . '</a> '; 
//        return "[[$mark>$script?plugin=tracker_plus_list&opage=$r_opage&orefer=$r_orefer&config=$r_config&list=$r_list&order=$r_order&filter=$r_filter&cache=$r_cache&limit=$r_limit&paging=$r_pageNo]]";

    }

    // over-wride function.
    function toString($limit=NULL,$pagingNo=0)
    {
        global $_tracker_messages;
        global $_tracker_plus_list_msg;

        $source = '';
        //    $sort_types = '';

        // for paging variables.
        $abovePgLink = "";
        $belowPgLink = "";

        $body = array();

        if( $limit !== NULL and count($this->rows) > $limit )
        {
            if( TRACKER_PLUS_LIST_PAGING > 0 )
            { 
                list($abovePgLink, $belowPgLink) = $this->make_paging_links($limit, $pagingNo);
                $this->rows = array_splice( $this->rows, $limit * $pagingNo, $limit);
            }
            else
            {
                $source = str_replace(
                    array('$1','$2'),
                    array(count($this->rows),$limit),
                    $_tracker_messages['msg_limit'])."\n";
                $this->rows = array_splice($this->rows,0,$limit);
            }
        }
        if( count($this->rows) == 0 )
        {
            return $_tracker_plus_list_msg['nodata'];
        }

        $htmls = $this->get_cache_cnvrt();
        if( strlen($htmls) > 0 )
        {
            return $htmls;
        }

        // This case is cache flag or status is not valie.
        foreach( plugin_tracker_plus_get_source($this->config->page.'/'.$this->list) as $line)
        {
            if( preg_match('/^\|(.+)\|[hHfFcC]$/',$line) )
            {
                $source .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'replace_title'),$line);

//                  // for sortable table
//                   if( preg_match('/^\|(.+)\|[hH]$/',$line))
//                   {
//                       $sort_types .= preg_replace_callback('/\[([^\[\]]+)\]/',array(&$this,'get_sort_type'),$line);
//                   }
            }
            else
            {
                $body[] = $line;
            }
        }

        
        $lineno = ( $limit === NULL ) ? 0 : ($limit * $pagingNo);
        $lineno += 1;
        foreach( $this->rows as $key=>$row )
        {
            if( ! TRACKER_PLUS_LIST_SHOW_ERROR_PAGE and !$row['_match'] )
            {
                continue;
            }

            $row['_line'] = $lineno++;  
            $this->items = $row;
            foreach( $body as $line )
            {
                if( trim($line) == '' )
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

        if( $this->cache['verbs'] == TRUE ) 
        {
            $htmls .= $this->get_verbose_cachestatus();
        }

        if( TRACKER_PLUS_LIST_PAGING > 0 )
        {
            $htmls = $abovePgLink . $htmls . $belowPgLink;
        }

        return $htmls;
    }

//     // original functions.
//     function make_sortable_script($htmls,$sort_types)
//     {
//         $js = '<script type="text/javascript" src="' . SKIN_DIR . 'sortabletable.js"></script>';

//         $sort_types = convert_html($sort_types);
//         $sort_types = strip_tags($sort_types);
        
//         $sort_types_arr = array();
//         $sort_types_arr = explode(",", $sort_types);
//         array_pop($sort_types_arr);

//         $sort_types_str = '"' . implode($sort_types_arr,'","') . '"';

//         $number = plugin_tracker_plus_getNumber();
//         $htmls = strstr($htmls, "<thead>");
//         $htmls = '<div class="ie5"><table class="style_table" id="tracker_plus_list_' . $number . '" cellspacing="1" border="0">' . $htmls;
//         if( $number == 0 ) $htmls = $js . $htmls;

//          $htmls .= <<<EOF
// <script type="text/javascript">
// var st1 = new SortableTable(document.getElementById("tracker_plus_list_$number"), [$sort_types_str]);
// </script>
// EOF;
//         return $htmls;
//     }

//     function get_sort_type($arr)
//     {
//         $field = $arr[1];
//         if( ! array_key_exists($field,$this->fields))
//         {
//             return $arr[0];
//         }
//         $sort_type = $this->fields[$field]->sort_type;

//          if( $sort_type == SORT_NUMERIC )
//          {
//              $ret= 'Number';
//          }
//          else if( $sort_type == SORT_STRING )
//          {
//              $ret = 'String';
//          }
//          else if( $sort_type == SORT_REGULAR )
//          {
//              $ret = 'CaseInsensitiveString';
//          }
//          else
//          {
//              $ret= 'Number';
//          }

//         return "$ret,";
//     }

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
        if( ! file_exists($cachefile) )
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
          or die_message('cannot open '.$cachefile);

        if( ! flock($fp, LOCK_SH) )
        {
            fclose($fp);
            die_message("flock() failed.");
        }

        rewind($fp);

        // This will get us the main column names.
        // (jjyun) I tryed csv_explode() function , but this behavior is not match as I wanted.
        $column_names = fgetcsv($fp, filesize($cachefile));

        $stored_total = 0;
        while( $arr = fgetcsv($fp, filesize($cachefile)) )
        {
            $row = array();
            $stored_total +=1;
            // This code include cache contents in $rows.
            foreach( $arr as $key => $value )
            {
                $column_name = $column_names[$key];
                // (note) '_match' is not fields ,
                //  but '_match' value is effect for tracker_list.
                if( isset($this->fields[$column_name]) || $column_name =='_match')
                {
                    $row[$column_name] = stripslashes($value);
                }
            }

            // This code check cache effective.
            //  by means of comparing filetime between real page and cache contents.
            // If cache is effective, this code include cache contents in rows.
            if( isset($row['_real'])
                 and isset($row['_update']) 
                 and (get_filetime($this->page.'/'.$row['_real']) == $row['_update']) )
            {
                $this->rows[$row['_real']] = $row;
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        $this->cache['state']['stored_total'] = $stored_total;
        $this->cache['state']['hits'] = count($this->rows);
    }

    function put_cache_rows()
    {
        $cachefiles_pattern = '^' . $this->get_cache_filename() . '(.*).tracker$';

        if( $this->cache['level'] == $this->cache_level['NO'] 
            and (! $this->delete_caches($cachefiles_pattern) ) )
          die_message( CACHE_DIR . ' is not found or not readable.');

        if( $this->cache['state']['hits'] == $this->cache['state']['total'] 
           and $this->cache['state']['hits'] == $this->cache['state']['stored_total'] )
        {  
            return '';
        }

        // This delete cachefiles related this Lv1.cache.
        if( ! $this->delete_caches($cachefiles_pattern) )
            die_message( CACHE_DIR . ' is not found or not readable.');

        ksort($this->rows);
        $filename = $this->get_listcache_filename();
        
        // If $filename exist, fopen() with 'w' mode destories file contents before acquiring flock() 
        // because that open truncate filesize to 0.
        $fp = fopen($filename, file_exists($filename) ? 'r+' : 'w')
            or die_message('cannot open '.$filename);

        if( ! flock($fp, LOCK_EX) )
        {
            fclose($fp);
            die_message("flock() failed.");
        }

        // for opening with 'r+' mode.
        rewind($fp);
        ftruncate($fp, 0);
        
        $column_names = array();
        foreach( plugin_tracker_plus_get_source($this->config->page.'/'.$this->list) as $line )
        {
            if( ! preg_match('/^\|(.+)\|[hHfFcC]$/',$line))
            {
                // It convert '|' for table separation to ',' for CSV format separation.
                preg_match_all('/\[([^\[\]]+)\]/',$line,$item_array);
                foreach( $item_array[1] as $item )
                {
                    $params = explode(',',$item);
                    $name = array_shift($params);
                    if( $name != '' )
                        array_push($column_names,"$name");
                }
            }
        }
        // add default parameter
        $column_names = array_merge($column_names, array('_page','_refer','_real','_update','_match'));
        $column_names = array_unique($column_names);

        // it skip '_line' value because that's value is temporary.
        $index = array_search( '_line', $column_names );
        if( $index !== FALSE ) 
        {
            $endValue = end($column_names);
            $column_names[ $index ] = $endValue;
            unset($column_names[ count($column_names) ]);
        }
        
        fputs($fp, "\"" . implode('","', $column_names)."\"\n");

        foreach( $this->rows as $row )
        {
            $arr = array();
            foreach( $column_names as $key )
            {
                 $arr[$key] = addslashes($row[$key]);
            }
            fputs($fp, "\"" . implode('","', $arr) . "\"\n");
        }

        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    function get_cache_cnvrt()
    {
        $cachefile = $this->get_cnvtcache_filename(); 
        if( ! file_exists($cachefile) ) 
        {  
            return '';
        }
        if( $this->cache['level'] != $this->cache_level['LV2'] )
        {
            unlink($cachefile);  
            return '';
        }
        if($this->cache['state']['hits'] != $this->cache['state']['total'] or 
           $this->cache['state']['hits'] != $this->cache['state']['stored_total'] ) 
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

        $fp = fopen($cachefile,'r')
          or die_message('cannot open '.$cachefile);

        if( ! flock($fp, LOCK_SH) )
        {
            fclose($fp);
            die_message("flock() failed.");
        }

        rewind($fp);

        $htmls = '';
        if( function_exists('file_get_contents' ) ) 
        {
            // file_get_contents is for PHP4 > 4.3.0, PHP5 function  
            $htmls = file_get_contents($cachefile); 
        }
        else 
        {
            while( !feof($fp) )
            {
                $html .= fread($fp, 8192);
            }
        }
        $this->cache['state']['cnvrt'] = TRUE;

        if( $this->cache['verbs'] == TRUE ) 
        {
            $htmls .= $this->get_verbose_cachestatus();
        }

        flock($fp, LOCK_UN);
        fclose($fp);

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

        // If $filename exist, fopen() with 'w' mode destories file contents before acquiring flock() 
        // because that open truncate filesize to 0.
        $fp = fopen($cachefile, file_exists($cachefile) ? 'r+' : 'w')
            or die_message('cannot open '.$cachefile);

        if( ! flock($fp, LOCK_EX) )
        {
            fclose($fp);
            die_message("flock() failed.");
        }

        // for opening with 'r+' mode.
        rewind($fp);
        ftruncate($fp, 0);

        fwrite($fp, $htmls);

        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    // static method.
    function delete_caches($del_pattern)
    {
            $dir = CACHE_DIR;
        if( ! $dp = @opendir($dir) )
        {
            return FALSE;
        }
        while( $file = readdir($dp) )
        {
            if( preg_match("/$del_pattern/",$file) )
            {
                unlink($dir . $file);
            }
        }
        closedir($dp);
        return TRUE;
    }

}

function plugin_tracker_plus_get_source($page)
{
    return plugin_tracker_get_source($page);
}

// I want to make Tracker_list_filter and Tracker_list_filterCondition to
// inner class of Tracker_list. But inner class is supported by PHP5, not PHP4.(jjyun)
class Tracker_plus_list_filter
{
    var $name;
    var $filter_config;
    var $filter_select;
    var $conditions = array();

    // setting in filter(), and use judge().
    var $field_config = Null;
  
    function Tracker_plus_list_filter($filter_config, $name = NULL, $filter_select = FALSE)
    {
        $this->name = $name;
        $this->filter_config = $filter_config;  
        $this->filter_select = $filter_select;

        $keys = $filter_config->before_read($name);
        foreach( $filter_config->get($name) as $filter )
        {
            foreach($keys as $index => $key )
            {
                $filterKeyValues[ $key ] = $filter[ $index ];
            }
            
            array_push( $this->conditions,
                        new Tracker_plus_list_filterCondition($filterKeyValues, $name) );
        }
    }

    function list_filter(& $list)
    {
        $field_config = array();
        foreach( $list->config->objs['fields']->values as $field )
        {
            $this->field_config[ $field[0] ] = $field;
        }

        if( $this->name != NULL )
        {
            $list->rows = array_filter($list->rows, array($this, "judge" ));
        }
    }

    function judge($var)
    {
        $counter = 0;  
        $condition_flag = true;
        foreach( $this->conditions as $condition )
        {
            if( $condition->isConjunction or $counter == 0 )
            {
                $condition_flag = ($condition->filter($var, $this->field_config) and $condition_flag );
            }
            else
            {  
                $condition_flag = ($condition->filter($var, $this->field_config)  or $condition_flag );
            }
            $counter++ ;
        }
        return $condition_flag;
    }

    function get_options_html()
    {
        $optionsHTML = '';
        $isSelect = false;
        $keys = $this->filter_config->get_keys();

        // for difference between servers.
        // one's $keys has "SelectAll", the other's doesn't have "SelectAll".
        // This flag is to avoid the difference.
        $isExistSelectAll = false;

        // attension : if you modified this, you should see Tracker_plus_list::replace_title()
        $d_select = ($this->filter_select) ? '+' : '';

        foreach( $keys as $option )
        {
            if( $option == "" )
                continue;

            if( $option == "SelectAll" )
                $isExistSelectAll = true;

            $encodedOption = htmlspecialchars($option);
            list($label, $style) = ( TRACKER_PLUS_LIST_APPLY_LISTFORMAT ) ? 
                            $this->get_option_style($option) : array($encodeOption ,"");
            
            if( $option == $this->name)
            {
                $isSelect = true;
                $optionsHTML .= "<option value='$d_select$encodedOption' $style selected='selected'>$label</option>";
            }
            else
            {
                $optionsHTML .= "<option value='$d_select$encodedOption' $style>$label</option>";
            }
        }
    
        if( ! $isExistSelectAll )
        {
                $allSelected = ( $isSelect ) ? "" : "selected='selected'" ;
                $optionsHTML = "<option value='SelectAll' $allSelected >SelectAll</option>"  . $optionsHTML;
        }
        
        return $optionsHTML;
    }

    function get_option_style($filter_name)
    {
        global $_tracker_plus_list_msg;

        $s_label = $filter_name;
        $s_format = "";
        
        $style_options = $this->filter_config->after_read($filter_name);
        if( is_null($style_options) )
          return array($s_label, $s_format);

        foreach( $style_options as $options )
        {
            if( $options[0] != $_tracker_plus_list_msg['filter_extTitle'] )
                continue;

            $s_label=$options[1];
            $s_format=$options[2];

            if( $s_format == '' )
                break;

            $format_enc = htmlspecialchars($s_format);
            $format_enc = preg_replace("/\%s/", '', $format_enc);

            $opt_format='';
            $matches=array();
            while( preg_match('/^(?:(BG)?COLOR\(([#\w]+)\)):(.*)$/', $format_enc, $matches) )
            {
                if( $matches[0] )
                {
                    $style_name = $matches[1] ? 'background-color' : 'color';
                    $opt_format .= $style_name . ':' . htmlspecialchars($matches[2]) . ';';
                    $format_enc = $matches[3];
                }
            }
            $s_format = ( strlen($opt_format) > 0 ) ? 'style='.$opt_format : "";
        }

        return array($s_label, $s_format);
    }

}
class Tracker_plus_list_filterCondition
{
    var $name;
    var $target;
    var $condValues;
    var $condExpress;
    var $isConjunction;
  
    function Tracker_plus_list_filterCondition( $keyValues, $name )
    {
        global $_tracker_plus_list_msg;

        $filter_titles = array( $_tracker_plus_list_msg['filter_title_logicalOperator'],
                                $_tracker_plus_list_msg['filter_title_targetField'],
                                $_tracker_plus_list_msg['filter_title_operator'],
                                $_tracker_plus_list_msg['filter_title_conditionValues'] );

        $this->name = $name;

        foreach( $filter_titles as $key )
        {  
            if( ! isset( $keyValues[$key] ) )
            {
                die_message( $_tracker_plus_list_msg['filter_definition_error'] . ": missing \"$key\" in definition.");
            }

            switch( $key )
            {
            case $_tracker_plus_list_msg['filter_title_logicalOperator'] :
                $this->isConjunction = ($keyValues[ $key ] == 'AND' ) ? true : false ;
                break;
            case $_tracker_plus_list_msg['filter_title_targetField'] :
                $this->target = $keyValues[ $key ];
                break;
            case $_tracker_plus_list_msg['filter_title_operator'] :
                $this->condExpress = $keyValues[ $key ];
                break;
            case $_tracker_plus_list_msg['filter_title_conditionValues'] :
                $this->condValues = preg_quote($keyValues[ $key ],'/');
                $this->condValues = implode(explode(',',$this->condValues) ,'|');
                break;
            default :
                // internal error.
            }
        }        
    }
  
    function filter($var, &$field_config)
    {
        global $_tracker_plus_list_msg;

//           $flag = preg_match("/$this->condValues/",$var[$this->target]);
//           return ($this->isConjunction) ? $flag : ! $flag;

        $ret = false;
        $target_value = $var[$this->target];
            
        if( $field_config[$this->target][2] == 'datefield' &&
            ( $this->condExpress == '?='  ||
              $this->condExpress == '?<'  || $this->condExpress == '?<=' || 
              $this->condExpress == '?>'  || $this->condExpress == '?>=' ) )
        {
            require_once(PLUGIN_DIR . 'datefield.inc.php');

            $str_datefield_options = $field_config[$this->target][3];
            $args = explode("," , $str_datefield_options);
            $formatStr = ( count($args) < 2 || $args[1] == '' ) ?  'YYYY-MM-DD' : $args[1];

            $condDate = '00000000';
            if( $this->condValues == 'TODAY' )
            {
                $condDate = date( "Ymd",time() );
            }
            else
            {
                $condTmpDate = plugin_datefield_getDate( $this->condValues, $formatStr);
                if( $condTmpDate['year'] == -1 || $condTmpDate['month'] == -1 || $condTmpDate['day'] == -1)
                    die_message( $_tracker_plus_list_msg['filter_definition_error'] . ": datefield condition value is irregal." );

                $condDate = sprintf("%02d%02d%02d", $condTmpDate['year'], $condTmpDate['month'], $condTmpDate['day']);
            }

            $targetTmpDate = plugin_datefield_getDate( $target_value, $formatStr );
            if( $targetTmpDate['year'] == -1 || $targetTmpDate['month'] == -1 || $targetTmpDate['day'] == -1)
                return false;
            $targetDate = sprintf("%02d%02d%02d", $targetTmpDate['year'], $targetTmpDate['month'], $targetTmpDate['day']);

            switch ( $this->condExpress )
            {
            case '?=':
                $ret = $targetDate == $condDate;
                break;
            case '?<' :
                $ret = $targetDate < $condDate;
                break;
            case '?<=':
                $ret = $targetDate <= $condDate;
                break;
            case '?>':
                $ret = $targetDate > $condDate;
                break;
            case '?>=':
                $ret = $targetDate >= $condDate;
                break;
            default:
                die_message( $_tracker_plus_list_msg['filter_definition_error'] . ": operator is not valid in datefield field.");
            }
        }
        else
        {
            switch ( $this->condExpress )
            {
            case  'EXIST':
                $ret = preg_match("/$this->condValues/",$target_value);
                break;
            case 'NOT EXIST':
                $ret = ! preg_match("/$this->condValues/",$target_value);
                break;
            default:
                die_message( $_tracker_plus_list_msg['filter_definition_error'] . ": operator is not valid." );
            }
        }

        return $ret;
    }

    function toString()
    {
        $str =
          "name   : $this->name |"
          . "target : $this->target |"
          . "condValues: $this->condValues |"
          . "exc-lgc: $this->condExpress | "
          . "cnctlgc: $this->isConjunction |";

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
        if( $empty )
        {
            $retval .= " <option value=\"\"></option>\n";
        }
        $defaults = array_flip( preg_split('/\s*,\s*/',$this->default_value,-1,PREG_SPLIT_NO_EMPTY));

        foreach( $this->config->get($this->name) as $option)
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
        if( ! array_key_exists($this->name,$options))
        { 
            $options[$this->name] = array_flip( array_map(create_function('$arr','return $arr[0];'),
                                      $this->config->get($this->name) ) );
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
        $arg= Tracker_plus_field_string_utility::get_argument_from_block_type_plugin_string($str);
        return $arg;
    }
  
    // 引数(page内の該当部分)にconfigページの属性値一覧で定義した要素が含まれれば、
    // その見出しの値を返す(tracker_list表示で、利用されている)
    function format_cell($str)
    {
        return $this->get_key($str);
    }
}

class Tracker_field_select3 extends Tracker_field_select2
{
    // (styleの適用時、list表示内容に、利用される)
    // 引数(page内の該当部分)にconfigページの属性値一覧で定義した要素が含まれれば、
    // その見出しの値を返す
    function get_key($str)
    {
        // 該当フィールドのBlockPluginを為す文字列から0番目の引数にあたる文字列を読み取る
        $arg = Tracker_plus_field_string_utility::get_argument_from_block_type_plugin_string($str);

        // configページで設定された属性値と比較する
        foreach( $this->config->get($this->name) as $option )
        {
            if( strcmp( $option[0], $arg) == 0 )
                return $option[0];

        }
        return htmlspecialchars( $this->values[0] );
    }

}

class Tracker_field_hidden2 extends Tracker_field_hidden
{
    var $sort_type = SORT_REGULAR;
  
    function extract_value($value)
    {
        $extract_arg_num = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
          htmlspecialchars($this->values[0]) : '' ;
        $target_plugin_name = array_key_exists(1,$this->values) ?
          htmlspecialchars($this->values[1]) : '.*' ;
        $target_plugin_type = array_key_exists(2,$this->values) ?
          htmlspecialchars($this->values[2]) : 'block' ;
    
        // オプションの指定がなければ、拡張処理は行わない
        if( $extract_arg_num == '' )
        {
          return $value;
        }

        // 指定されたプラグインから位置の引数を抽出する
        $arg = Tracker_plus_field_string_Utility::get_argument_from_plugin_string(
                $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

        return $arg;
    }

    // (sortの適用時に、利用されている)
    // 引数(page内の該当部分)に対して、configページのオプション指定に従って、
    // ブロック型のプラグイン引数から指定された部分の文字列を
    // 切り出した値を返す処理を含む
    function get_value($value)
    {
        return $this->extract_value($value);
    }
      
    // 引数(page内の該当部分)に対して、
    // configページのオプション指定に従って切り出した値を返し、
    // page内の該当部分にconfigページの属性値一覧で定義した要素が含まれれば、
    // その見出しの値を返す（styleの適用時に、利用されている）
    function get_key($str)
    {
        // 引数(page内の該当部分)に対して、configページのオプション指定に従って切り出す。
        $str= $this->extract_value($str);
        foreach( $this->config->get($this->name) as $option )
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
        return $this->extract_value($str);
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
    function extract_value($value)
    {
        $extract_arg_num = (array_key_exists(0,$this->values) and is_numeric($this->values[0])) ?
          htmlspecialchars($this->values[0]) : '' ;
        $target_plugin_name = array_key_exists(1,$this->values) ?
          htmlspecialchars($this->values[1]) : '.*' ;
        $target_plugin_type = array_key_exists(2,$this->values) ?
          htmlspecialchars($this->values[2]) : 'block' ;
    
        // オプションの指定がなければ、拡張処理は行わない
        if( $extract_arg_num == '' )
        {
          return $value;
        }

        // 指定されたプラグインから位置の引数を抽出する
        $arg = Tracker_plus_field_string_Utility::get_argument_from_plugin_string(
                $value, $extract_arg_num, $target_plugin_name, $target_plugin_type);

        // 抽出した文字列から数値である部分のみを抽出し、ソートや表示の処理対象とする
        $arg = (preg_match("/(\d+)/",$arg,$match) ) ? $match[1] : 0;

        return $arg;
    }
}

class Tracker_field_hidden2select extends Tracker_field_hidden2
{
    var $sort_type = SORT_NUMERIC;

    function get_value($value)
    {
        return Tracker_field_select2::get_value($value);
    }

}

class Tracker_field_submit_plus extends Tracker_field
{
    function get_tag()
    {
        $s_title = htmlspecialchars($this->title);
        $s_page = htmlspecialchars($this->page);
        $s_refer = htmlspecialchars($this->refer);
        $s_config = htmlspecialchars($this->config->config_name);
        
                return <<<EOD
<input type="submit" value="$s_title" />
<input type="hidden" name="plugin" value="tracker_plus" />
<input type="hidden" name="_refer" value="$s_refer" />
<input type="hidden" name="_base" value="$s_page" />
<input type="hidden" name="_config" value="$s_config" />
EOD;
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
        
        // デフォルト値を現在の日付にする
        if( $s_value=="TODAY" )
        {
          $s_value = $this->get_datestr_with_format($s_format, $s_year, $s_month, $s_date);
        }
        // Javascriptに引きわたす形式のフォーマット文字列に変更する
        $s_format = $this->form_format($s_format);
        
        return <<<EOD
<input type="text" name="$s_name" size="$s_size" value="$s_value" />
<input type="button" value="..." onclick="_plugin_datefield_dspCalendar(this.form.$s_name, event, $s_format, 0 , $s_year, $s_month-1, $s_date, 0);" />
EOD;
    }
  
    // sortの適用時に、その値を以って処理を行わせる。
    // 該当部分に含まれるブロックプラグインの0番目の引数を返す
    function get_value($value)
    {
        $arg= Tracker_plus_field_string_utility::get_argument_from_block_type_plugin_string($value,0,'datefield');
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
        if( $s_unmdfy != 'TRUE' )
        {
          $value = "#datefield($value,$s_format)";
        }
        return parent::format_value($value);
    }

    function form_format($format_opt)
    {
        $format_str= trim($format_opt);
        if( strlen($format_str) == 0 )
            $format_str = 'YYYY/MM/DD';
        if( preg_match('/^[\'\"].*[\"\']$/',$format_str)) /* " */
        { 
            $format_str = '\'' . substr($format_str,1,strlen($format_str)-2) . '\'';
        }
        else
        {
            $format_str = '\'' . $format_str . '\'';
        }
        return $format_str;
    }

    function get_datestr_with_format($format_opt,$yyyy,$mm,$dd )
    {
        // 引数の月の値の範囲 month is 1 - 12
        $strWithFormat = $format_opt;
        $yy = $yyyy%100;
        
        $yy = sprintf("%02d",$yy);
        $mm = sprintf("%02d",$mm);
        $dd = sprintf("%02d",$dd);
        $strWithFormat = preg_replace('/YYYY/i', $yyyy, $strWithFormat);
        $strWithFormat = preg_replace('/YY/i',   $yy,   $strWithFormat);
        $strWithFormat = preg_replace('/MM/i',   $mm,   $strWithFormat);
        $strWithFormat = preg_replace('/DD/i',   $dd,   $strWithFormat);

        return $strWithFormat;
    }

    function set_head_declaration()
    {
        global $pkwk_dtd, $javascript;  
        
        // XHTML 1.0 Transitional
        if( ! isset($pkwk_dtd) || $pkwk_dtd == PKWK_DTD_XHTML_1_1 )
            $pkwk_dtd = PKWK_DTD_XHTML_1_0_TRANSITIONAL;
        
        // <head> タグ内への <meta>宣言の追加
        $javascript = TRUE;
    }
}

class Tracker_plus_field_string_utility {
  
    function get_argument_from_block_type_plugin_string($str, $extract_arg_num = 0, $plugin_name = '.*' )
    {
        return Tracker_plus_field_string_utility::get_argument_from_plugin_string($str,$extract_arg_num,$plugin_name,'block');
    }
  
    // plugin_type : block type = 0, inline type = 1.
    // extract_arg_num : first argument number is 0.  
    function get_argument_from_plugin_string($str, $extract_arg_num, $plugin_name, $plugin_type = 'block')
    {
        $str_plugin = ($plugin_type == 'inline') ? '\&' : '\#' ;
        $str_plugin .= $plugin_name;
        
        $matches = array();

        // 複数のplugin指定が存在する場合でも全てに対して抽出を行う
        if( preg_match_all("/(?:$str_plugin\(([^\)]*)\))/", $str, $matches, PREG_SET_ORDER) )
        {
            $paddata = preg_split("/$str_plugin\([^\)]*\)/", $str);
            $str = $paddata[0];
            foreach( $matches as $i => $match )
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
            if( preg_match("/^\;.*$/",$paddata[$i+1],$exrep) && count($exrep) > 1 )
            {
                $paddata[$i+1] = $exrep[1];
            }
            $str .= $extract_arg . $paddata[$i+1];
        }
        return $str;
    }
}
?>
