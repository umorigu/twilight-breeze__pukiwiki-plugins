<?php
// $Id: memox.inc.php,v 0.3 2004/09/03 01:03:21 jjyun Exp $
//  this script based .. memo.inc.php,v 1.11 2004/07/24 14:58:41 henoheno Exp $

/////////////////////////////////////////////////
// テキストエリアのカラム数
define('MEMOX_DEFAULT_COLS', 80);
// テキストエリアの行数
define('MEMOX_DEFAULT_ROWS', 5);

/////////////////////////////////////////////////
function plugin_memox_action()
{
	global $script, $vars, $cols, $rows;
	global $_title_collided, $_msg_collided, $_title_updated;

	if (! isset($vars['msg']) || $vars['msg'] == '') return;

	$s_cols = htmlspecialchars($vars['cols']);
	$s_rows = htmlspecialchars($vars['rows']);

	$memo_body = preg_replace("/\r/", '', $vars['msg']);
	$memo_body = str_replace("\n", "\\n", $memo_body);
	$memo_body = str_replace('"', '&#x22;', $memo_body); // Escape double quotes
	$memo_body = str_replace(',', '&#x2c;', $memo_body); // Escape commas

	$postdata_old  = get_source($vars['refer']);
	$postdata = '';
	$memox_no = 0;
	foreach($postdata_old as $line)
	{
	  if(preg_match('/^(?:\/\/| )/', $line)) // Skip Comment lines 
	    continue;  

	  if(preg_match_all('/(?:#memox\(([^\)]*)\))/', $line,$matches, PREG_SET_ORDER))
	  {
	    $paddata = preg_split('/#memox\(([^\)]*)\)/', $line);
	    $line = $paddata[0];
	    foreach($matches as $i => $match) 
	    {
	      $opt = $match[1];
	      if ($memox_no++ == $vars['memox_no'])
	      {
		$opt = "$s_cols,$s_rows,$memo_body";
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
	else
	{
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
	global $_btn_memo_update;
	static $numbers = array();

	if (! isset($numbers[$vars['page']]))
	{
		$numbers[$vars['page']] = 0;
	}
	$memox_no = $numbers[$vars['page']]++;

	$data = func_get_args();

	$s_cols = htmlspecialchars( array_shift($data) );
	$s_rows = htmlspecialchars( array_shift($data) );
	if(! is_numeric($s_cols)) $s_cols = MEMOX_DEFAULT_COLS;
	if(! is_numeric($s_rows)) $s_rows = MEMOX_DEFAULT_ROWS;

	$data = implode(',', $data);	// Care all arguments
	$data = str_replace('&#x2c;', ',', $data); // Unescape commas
	$data = str_replace('&#x22;', '"', $data); // Unescape double quotes
	$data = htmlspecialchars(str_replace("\\n", "\n", $data));
	$s_page   = htmlspecialchars($vars['page']);
	$s_digest = htmlspecialchars($digest);

	$string = <<<EOD
<form action="$script" method="post" class="memo" style="margin:0;"> 
 <div>
  <input type="hidden" name="memox_no" value="$memox_no" />
  <input type="hidden" name="refer"   value="$s_page" />
  <input type="hidden" name="plugin"  value="memox" />
  <input type="hidden" name="digest"  value="$s_digest" />
  <input type="hidden" name="cols"    value="$s_cols" />
  <input type="hidden" name="rows"    value="$s_rows" />
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
  <input type="submit" name="memox"    value="$_btn_memo_update" />
 </div>
</form>
EOD;

	return $string;
}
?>
