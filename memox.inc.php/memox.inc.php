<?php
// $Id: memox.inc.php,v 0.7 2006/05/02 00:01:11 jjyun Exp $
/**
 * PukiWiki メモ拡張プラグイン(memo eXtented plugin)
 * (C) 2004, jjyun. http://www2.g-com.ne.jp/~jjyun/twilight-breeze/pukiwiki.php
 *
 * License: PukiWiki 本体と同じく GNU General Public License (GPL) です
 * http://www.gnu.org/licenses/gpl.txt
 *
 * Description:
 *  #memox([column],[rows],[title],DELIM-STR,[contents])
 * 
 * このコードの説明は、PukiWiki本体に同梱されている memo.inc.php,v 1.11 に
 * カラム,行数,更新ボタンのラベルを設置時に変更できるよう修正を加えたものです。
 */

/////////////////////////////////////////////////
// テキストエリアのカラム数
define('MEMOX_DEFAULT_COLS', 80);
// テキストエリアの行数
define('MEMOX_DEFAULT_ROWS', 5);
// デリミタ(DELIM-STR)の設定
define('MEMOX_DELIM_STR',  '<DELIM>');

/////////////////////////////////////////////////

function plugin_memox_init()
{
    switch (LANG) {
    case 'ja' :
        $msg = plugin_memox_init_ja();
        break;
    default:
        $msg = plugin_memox_init_en();
    }
    set_plugin_messages($msg);
}

function plugin_memox_init_ja()
{
    $msg = array(
                 '_btn_memox_update' => "更新",
                 );
    return $msg;
}

function plugin_memox_init_en()
{
    $msg = array(
                 '_btn_memox_update' => "Update",
                 );
    return $msg;
}

function plugin_memox_action()
{
	global $script, $vars, $cols, $rows;
	global $_title_collided, $_msg_collided, $_title_updated;
    check_editable($vars['refer'], true, true);
    
	if (! isset($vars['msg']) ) return;
    
	$s_cols  = htmlspecialchars($vars['cols']);
	$s_rows  = htmlspecialchars($vars['rows']);
	$s_blabel = htmlspecialchars($vars['blabel']);
    
	$memo_body = preg_replace("/\r/", '', $vars['msg']);
	$memo_body = str_replace("\n", "\\n", $memo_body);
	$memo_body = str_replace('"', '&#x22;', $memo_body); // Escape double quotes
	$memo_body = str_replace(',', '&#x2c;', $memo_body); // Escape commas
	$memo_body = str_replace(')', '&#x29;', $memo_body); // Escape closese-parenthesis

	$postdata_old  = get_source($vars['refer']);
	$postdata = '';
	$memox_no = 0;
	foreach($postdata_old as $line)
    {
        if(preg_match('/^(?:\/\/| )/', $line)){ // Skip Comment lines 
            $postdata .= $line;
            continue;
        }

        // ブロックプラグインの場合は、表の中の利用も考慮すること
        if(preg_match_all('/(?:#memox\(([^\)]*)\))/', $line,$matches, PREG_SET_ORDER))
        {
            $paddata = preg_split('/#memox\([^\)]*\)/', $line);
            $line = $paddata[0];
            foreach($matches as $i => $match) 
            {
                if ($vars['memox_no'] == $memox_no++ ) {
                    // ターゲットのプラグイン部分
                    $opt = "$s_cols,$s_rows,$s_blabel";
                    $opt .= ",". MEMOX_DELIM_STR . ",$memo_body";

                    if( strcmp($opt,$match[1]) == 0 ) return;
                }
                else {
                    $opt = $match[1];
                }
                $line .= "#memox($opt)" . $paddata[$i+1];
            }
        }
        $postdata .= $line;
	}
	$postdata_input = "$memo_body\n";
    
	if (md5(@join('', get_source($vars['refer']))) != $vars['digest'])
    {
		$title = $_title_collided;
        
		$body = "$_msg_collided\n";
        
		$s_refer  = htmlspecialchars($vars['refer']);
		$s_digest = htmlspecialchars($vars['digest']);
		$s_postdata_input = htmlspecialchars($postdata_input);
        
		$body .= <<<EOD
<form action="$script?cmd=preview" method="post">
 <div>
  <input type="hidden" name="refer"  value="$s_refer" />
  <input type="hidden" name="digest" value="$s_digest" />
  <textarea name="msg" rows="$rows" cols="$cols" id="textarea">$s_postdata_input</textarea><br />
 </div>
</form>
EOD;
	}
	else {
		page_write($vars['refer'], $postdata);
        
		$title = $_title_updated;
	}
    
	$retvars['msg'] = $title;
	$retvars['body'] = $body;
    
	$vars['page'] = $vars['refer'];
    
	return $retvars;
}

function plugin_memox_convert()
{
	global $script, $vars, $digest;
	global $_btn_memox_update;
    
	static $numbers = array();
    
	if (! isset($numbers[$vars['page']]))
    {
        $numbers[$vars['page']] = 0;
	}
	$memox_no = $numbers[$vars['page']]++;
    
	$data = func_get_args();
    
	// split delim
	$delim_pos = array_search(MEMOX_DELIM_STR,$data);
	if($delim_pos === FALSE){
        $func_args = $data;
        $data = '';
	}
	else {
        $func_args = array_splice($data, 0, $delim_pos);
        array_shift($data);
	}
    
	$s_cols = htmlspecialchars( array_shift($func_args) );
	$s_rows = htmlspecialchars( array_shift($func_args) );
	$s_blabel = htmlspecialchars( array_shift($func_args) );
    
	if(! is_numeric($s_cols)) $s_cols = MEMOX_DEFAULT_COLS;
	if(! is_numeric($s_rows)) $s_rows = MEMOX_DEFAULT_ROWS;
	if($s_blabel == NULL) $s_blabel = $_btn_memox_update;
    
	$data = implode(',', $data);	// Care all arguments
	$data = str_replace('&#x2c;', ',', $data); // Unescape commas
	$data = str_replace('&#x22;', '"', $data); // Unescape double quotes
	$data = str_replace('&#x29;', ')', $data); // Unescape closese-parenthesis
	$data = htmlspecialchars(str_replace("\\n", "\n", $data));
	$s_page   = htmlspecialchars($vars['page']);
	$s_digest = htmlspecialchars($digest);
    
	$string = <<<EOD
<form action="$script" method="post" class="memo" style="margin:0;"> 
 <div>
  <input type="hidden" name="memox_no" value="$memox_no" />
  <input type="hidden" name="refer"    value="$s_page" />
  <input type="hidden" name="plugin"   value="memox" />
  <input type="hidden" name="digest"   value="$s_digest" />
  <input type="hidden" name="cols"     value="$s_cols" />
  <input type="hidden" name="rows"     value="$s_rows" />
  <input type="hidden" name="blabel"    value="$s_blabel" />
EOD;
	if($s_rows < 2) {
        $string .= <<<EOF
<input type="text" name="msg" value="$data" size="$s_cols" />
EOF;
	}
	else {
        $string .= <<<EOF
<textarea name="msg" rows="$s_rows" cols="$s_cols">$data</textarea><br />
EOF;
	}
   
	$string .= <<<EOD
  <input type="submit" name="memox" value="$s_blabel" />
 </div>
</form>
EOD;
    
	return $string;
}
?>
