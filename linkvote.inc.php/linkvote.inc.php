<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: linkvote.inc.php,v 0.5 2006/04/02 22:32:42 jjyun Exp $
/**
 *  PukiWiki - link with used counter plugin
 * (C) 2004, jjyun. http://www2.g-com.ne.jp/~jjyun/twilight-breeze/pukiwiki.php
 *
 * License     : It is same license as PukiWiki core, that is GPL.
 * Update Logs : See the end of this file.
 *
 * Description :
 *  &linkvote([vote2-option,...]],[link-title],[link-url],[bunner-path]);
 * This script is create by jjyun, based on vote2.inc.php, v 0.12.
 * 
 */

// リンクを別窓で開く.
define('LINKVOTE_OPEN_LINK_WITH_ANOTHER_WINDOW', false);

function plugin_linkvote_init()
{
	$messages = array(
		'_linkvote_messages' => array(
			'arg_notimestamp' => 'notimestamp',
			'arg_nonumber'    => 'nonumber',
			'arg_nolabel'     => 'nolabel',
			'arg_notitle'     => 'notitle',
			'title_error' => 'Error in linkvote',
			'no_page_error' => '$1 のページは存在しません',
			'update_failed' => '投票失敗：$1において投票先が無いか項目が合致しませんでした。',
			'body_error' => 'あるべき引数が渡されていないか、引数にエラーがあります。',
			'msg_collided'  => '<h3>あなたが投票している間に、他の人が同じページの内容を更新してしまったようです。<br />従って、投票する位置を間違える可能性があります。<br /><br />
あなたの更新を無効にしました。前のページをリロードしてやり直してください。</h3>'

		),
	);
	set_plugin_messages($messages);
}
function plugin_linkvote_action()
{
	global $vars, $_linkvote_messages;
	$vote_no = 0;
	$block_flag = 0;
	
	if ( ! is_page($vars['refer']) ){
		$error = str_replace('$1', $vars['refer'], $_linkvote_messages['no_page_error']);
		return array(
			'msg'  => $_linkvote_messages['title_error'], 
			'body' => $error,
		);
	}
	if ( array_key_exists('vote_no', $vars) ) {
		$vote_no = $vars['vote_no'];
		$block_flag = 1;
	}
	else if ( array_key_exists('vote_inno', $vars) ){
		$vote_no = $vars['vote_inno'];
		$block_flag = 0;
	}
	if ( preg_match('/^(\d+)([ib]?)$/', $vote_no, $match) ){
		$vote_no = $match[1];
		switch ( $match[2] ){
			case 'i': $block_flag = 0; break;
			case 'b': $block_flag = 1; break;
			default: break;
		}
		switch ( $block_flag ) {
			case 1:
				return plugin_linkvote_action_block($vote_no);
				break;
			case 0:
			default:
				return plugin_linkvote_action_inline($vote_no);
				break;
		}
	}
	return array(
		'msg'  => $_linkvote_messages['title_error'], 
		'body' => $_linkvote_messages['body_error'],
	);
}
function plugin_linkvote_inline()
{
	global $script,$vars,$digest, $_linkvote_messages, $_vote_plugin_votes;
	global $_vote_plugin_choice, $_vote_plugin_votes;
    global $pkwk_dtd;
	static $numbers = array();
	static $notitle = FALSE;
	$str_notimestamp = $_linkvote_messages['arg_notimestamp'];
	$str_nonumber    = $_linkvote_messages['arg_nonumber'];
	$str_nolabel     = $_linkvote_messages['arg_nolabel'];
	$str_notitle     = $_linkvote_messages['arg_notitle'];
	$str_disturl = '';   
	$str_bunner = '';

	$args = func_get_args();
	array_pop($args); // {}内の要素の削除
	$page = $vars['page'];
	if (!array_key_exists($page,$numbers))	$numbers[$page] = 0;
	$vote_inno = $numbers[$page]++;
	$o_vote_inno = $f_vote_inno = $vote_inno;

	$ndigest = $digest;
	$arg = '';
	$cnt = 0;
	$nonumber = $nolabel = FALSE;

	if( LINKVOTE_OPEN_LINK_WITH_ANOTHER_WINDOW )
	{
		// XHTML 1.0 Transitional
		if (! isset($pkwk_dtd) || $pkwk_dtd == PKWK_DTD_XHTML_1_1)
		{
			$pkwk_dtd = PKWK_DTD_XHTML_1_0_TRANSITIONAL;
		}
    }

	foreach ( $args as $opt ){
		$opt = trim($opt);
		if ( $opt == $str_notimestamp || $opt == '' ){
		}
		else if ( $opt == $str_nonumber ){
			$nonumber = TRUE;
		}
		else if ( $opt == $str_nolabel ){
			$nolabel = TRUE;
		}
		else if ( $opt == $str_notitle ){
			$notitle = TRUE;
		}
		else if ( preg_match('/^(.+(?==))=([+-]?\d+)([ibr]?)$/',$opt,$match) ){
			list($page,$vote_inno,$f_vote_inno,$ndigest) 
				= plugin_linkvote_address($match,$vote_inno,$page,$ndigest);
		}
		else if ( preg_match('/^(http:|https:)\/\/(.*)$/',$opt,$match) ){
		  $str_disturl = htmlspecialchars($match[0]);
		}
		else if ( preg_match('/(?:.*(\.png|\.gif|\.jpg))$/',$opt,$match) ){
		  $str_bunner = htmlspecialchars($match[0]);
		}
		else if ( $arg == '' and preg_match("/^(.*)\[(\d+)\]$/",$opt,$match)){
			$arg = $match[1];
			$cnt = $match[2];
		}
		else if ( $arg == '' ) {
			$arg = $opt;
		}
	}
//	if ( $arg == ''  ) return '';
	$link = make_link($arg);
	$e_arg = encode($arg);
	$f_page = rawurlencode($page);
	$f_digest = rawurlencode($ndigest);
	$f_vote_plugin_votes = rawurlencode($_vote_plugin_votes);
	$f_disturl = rawurlencode($str_disturl);
	$f_cnt = '';
    $target = LINKVOTE_OPEN_LINK_WITH_ANOTHER_WINDOW ? "target=\"_blank\"" : "";


	if ( $nonumber == FALSE ) {
		$title = $notitle ? '' : "title=\"$o_vote_inno\"";
		$f_cnt = "<span $title>&nbsp;" . $cnt . "&nbsp;</span>";
	}
	if ( $nolabel == FALSE ) {
		$title = $notitle ? '' : "title=\"$f_vote_inno\"";
		if( $str_bunner == '' ) {
		  return <<<EOD
<a href="$script?plugin=linkvote&amp;refer=$f_page&amp;vote_inno=$vote_inno&amp;vote_$e_arg=$f_vote_plugin_votes&amp;digest=$f_digest&amp;dist_url=$f_disturl" $title $target>$link</a>$f_cnt
EOD;
		}
		else {
		  require_once(PLUGIN_DIR.'ref.inc.php');
		  $ret_ref = plugin_ref_body(array($str_bunner,'nolink'));
		  
		  if (isset($ret_ref['_error']) && $ret_ref['_error'] != '' ) {
		    return $_linkvote_messages['body_error'];
		  }
		  else {
		    return <<<EOD
<a href="$script?plugin=linkvote&amp;refer=$f_page&amp;vote_inno=$vote_inno&amp;vote_$e_arg=$f_vote_plugin_votes&amp;digest=$f_digest&amp;dist_url=$f_disturl" $title $target>{$ret_ref['_body']}</a>$f_cnt
EOD;
		  }
		}
	}
	else {
		return $f_cnt;
	}
}
function plugin_linkvote_address($match, $vote_no, $page, $ndigest)
{
  	static $digests = array();

	$this_flag = FALSE;
	$npage          = trim($match[1]);
	$linkvote_no_arg   = $match[2];
	$linkvote_attr_arg = $match[3];

	if ( $npage == 'this' ) {
		$npage   = $page;
		$this_flag = TRUE;
	}
	else {
		$npage      = preg_replace('/^\[\[(.*)\]\]$/','$1', $npage);
		if ( $npage == $page ){
			$this_flag = TRUE;
		}
		else if ( ! is_page($npage) ) {
			$linkvote_attr_arg = 'error';
		}
		else if ( array_key_exists($npage, $digests) ) {
			$ndigest = $digests[$npage];
		}
		else {
			$ndigest    = md5(join('',get_source($npage)));
			$digests[$npage] = $ndigest;
		}
	}
	switch ( $linkvote_attr_arg ){
		case '': 
		case 'i': 
		case 'b': $vote_no  = $linkvote_no_arg . $linkvote_attr_arg; break;
		case 'r': 
			if ( $this_flag ) {
				$vote_no += $linkvote_no_arg;
			}
			else {
				$vote_no = 'error';
			}
			 break;
		default:  $vote_no  = 'error'; break;
	}
	$f_vote_no = htmlspecialchars($npage . '=' . $vote_no);
	return array($npage, $vote_no, $f_vote_no, $ndigest);
}

function plugin_linkvote_action_inline($vote_no)
{
	global $vars,$script,$cols,$rows, $_linkvote_messages;
	global $_title_collided,$_msg_collided,$_title_updated;
	global $_vote_plugin_choice, $_vote_plugin_votes;
	$str_notimestamp = $_linkvote_messages['arg_notimestamp'];
	$str_nonumber    = $_linkvote_messages['arg_nonumber'];
	$str_nolabel     = $_linkvote_messages['arg_nolabel'];
	$str_notitle     = $_linkvote_messages['arg_notitle'];
	
	$str_plugin = 'linkvote';
	$len_plugin = strlen($str_plugin) + 1;
	$title = $body = $postdata = '';
	$vote_ct = $skipflag = 0;
	$page = $vars['page'];
	$postdata_old  = get_source($vars['refer']);

	$str_disturl = rawurldecode($vars['dist_url']);

	$ic = new InlineConverter(array('plugin'));
	$notimestamp = $update_flag = FALSE;
	foreach($postdata_old as $line)
	{
		if ( $skipflag || substr($line,0,1) == ' ' || substr($line,0,2) == '//' ){
		    $postdata .= $line;
	    	continue;
		}
		$pos = 0;
		$arr = $ic->get_objects($line,$page);
		while ( count($arr) ){
			$obj = array_shift($arr);
			if ( $obj->name != $str_plugin ) continue;
			$pos = strpos($line, '&' . $str_plugin, $pos);
			if ( $vote_ct++ < $vote_no ) {
				$pos += $len_plugin;
				continue;
			}
			$l_line = substr($line,0,$pos);
			$r_line = substr($line,$pos + strlen($obj->text));
			$options = explode(',', $obj->param);
			$cnt = 0;
			$name = '';
			$vote = array();
			foreach ( $options as $opt ){
				$arg = trim($opt);
				if ( $arg == $str_notimestamp ){
					$notimestamp = TRUE;
				}
				else if ( $arg == '' ){
					continue;
				}
				else if ( $arg == $str_nonumber || $arg == $str_nolabel || $arg == $str_notitle ) {
				} 
				else if (preg_match("/^.+(?==)=[+-]?\d+[bir]?$/",$arg,$match)){
				}
				else if ( $name == '' and preg_match("/^(.*)\[(\d+)\]$/",$arg,$match)){
					$name = $match[1];
					$cnt  = $match[2];
					continue;
				}
				else if ( $name == '' ){
					$name = $arg;
					continue;
				}
				$vote[] = $arg;
			}
			array_unshift($vote, $name .'['.($cnt+1).']');
			$vote_str = "&$str_plugin(".join(',',$vote).');';
			$pline = $l_line . $vote_str . $r_line;
			if ( $pline !== $line ) $update_flag = TRUE;
			$postdata_input = $line = $pline;
			$skipflag = 1;
			break;
		}
		$postdata .= $line;
	}

	if ( md5(@join('',get_source($vars['refer']))) != $vars['digest'])
	{
		$title = $_title_collided;
		$body  = $_linkvote_messages['msg_collided'] . make_pagelink($vars['refer']) . 
				"<hr />\n $postdata_input";
	}
	else if ( $update_flag == TRUE ) 
	{
		page_write($vars['refer'],$postdata,$notimestamp);
		$title = $_title_updated;

//$body = convert_html($postdata . "\n----\n"). $postdata_input . "/" . $vote_str . "/" . $vote . "/" . $name;
//$title = "debug for linkvote";
	}
	else {
		$title = $_linkvote_messages['update_failed'];
	}

	$str_disturl=trim($str_disturl);
	if($str_disturl != ''){
	  header("Location: $str_disturl");
	}

	$retvars['msg'] = $title;
	$retvars['body'] = $body;

	$vars['page'] = $vars['refer'];

	return $retvars;
}
function plugin_linkvote_action_block($vote_no)
{
	global $vars,$script,$cols,$rows, $_linkvote_messages;
	global $_title_collided,$_msg_collided,$_title_updated;
	global $_vote_plugin_choice, $_vote_plugin_votes;
	$str_notimestamp = $_linkvote_messages['arg_notimestamp'];
	$str_nonumber    = $_linkvote_messages['arg_nonumber'];
	$str_nolabel     = $_linkvote_messages['arg_nolabel'];
	$str_notitle     = $_linkvote_messages['arg_notitle'];
	$notimestamp = $update_flag = FALSE;

	$str_disturl = rawurldecode($vars['dist_url']);

	$postdata_old  = get_source($vars['refer']);
	$vote_ct = 0;
	$title = $body = $postdata = '';

	foreach($postdata_old as $line)
	{
		if (!preg_match("/^#linkvote\((.*)\)\s*$/",$line,$arg))
		{
			$postdata .= $line;
			continue;
		}
		
		if ($vote_ct++ != $vote_no)
		{
			$postdata .= $line;
			continue;
		}
		$args = explode(',',$arg[1]);
		
		foreach($args as $arg)
		{
			$arg = trim($arg);
			$cnt = 0;
			if ( $arg == $str_notimestamp ){
				$notimestamp = TRUE;
				$votes[] = $arg;
				continue;
			}
			else if ( $arg == '' ) {
				continue;
			} 
			else if ( $arg == $str_nonumber || $arg == $str_nolabel || $arg == $str_notitle ){
				$votes[] =  $arg;
				continue;
			}
			else if (preg_match("/^.+(?==)=[+-]?\d+[bir]?$/",$arg,$match)){
				$votes[] = $arg;
				continue;
			}
			else if (preg_match("/^(.*)\[(\d+)\]$/",$arg,$match))
			{
				$arg = $match[1];
				$cnt = $match[2];
			}
			$e_arg = encode($arg);
			if (!empty($vars["vote_$e_arg"]) and $vars["vote_$e_arg"] == $_vote_plugin_votes)
			{
				$cnt++;
				$update_flag = TRUE;
			}
			$votes[] =  $arg.'['.$cnt.']';
		}
		$vote_str = '#linkvote('.@join(',',$votes).")\n";
		
		$postdata_input = $vote_str;
		$postdata .= $vote_str;
	}

	if ( md5(@join('',get_source($vars['refer']))) != $vars['digest'] )
	{
		$title = $_title_collided;
		$body  = $_linkvote_messages['msg_collided'] . make_pagelink($vars['refer']) . 
				"<hr />\n $postdata_input";
	}
	else if ( $update_flag == TRUE ) 
	{
		$title = $_title_updated;
		page_write($vars['refer'],$postdata,$notimestamp);
	}
	else {
		$title = $_linkvote_messages['update_failed'];
	}

	$str_disturl=trim($str_disturl);
	if($str_disturl != ''){
	  header("Location: $str_disturl");
	}

	$retvars['msg'] = $title;
	$retvars['body'] = $body;

	$vars['page'] = $vars['refer'];

	return $retvars;
}
// -- linkvote.inc.php --
// Update Logs - Modified by jjyun. (2004/02/22 - 2004/12/10)
//  v0.5 2006/04/02 modified by jjyun.
//    リンク先を別窓で表示させるフラグ(LINKVOTE_OPEN_LINK_WITH_ANOTHER_WINDOW)を追加.
//  v0.4 2004/12/10 modified by jjyun.
//    内部コードの修正(変更箇所 plugin_linkvote_inline(), plugin_linkvote_address() )
//  v0.3 2004/09/05 modified by jjyun.
//    リンク先の表記として画像ファイルを指定できるようにする
//  v0.2 2004/08/13 modified by jjyun.
//    内部コードの修正($post,$get を用いている部分を削除)
//  v0.1 2004/07/28 create by jjyun. based on vote2.inc.php, v 0.12 
//
// (vote2.inc.php より)
//   vote2.inc.php, v 0.12 2003/10/05 17:55:04 sha 
//   based on vote.inc.php v1.14
//
?>
