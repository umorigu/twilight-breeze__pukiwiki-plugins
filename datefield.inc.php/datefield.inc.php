<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: datefield.inc.php,v 0.9 2004/11/22 03:47:12 jjyun Exp $
//

/* [概略の説明]
 * 日付入力補助画面付きフィールド提供プラグイン
 *
 * 日付入力を行わせたいテキストフィールドと、
 * 日付入力を行うためのカレンダーを表示するボタンを提供します。
 * カレンダーによる日付入力により,該当ページの更新が行われます。
 * カレンダーへの引数には、テキストフィールドへの入力値
 * 日付書式(デフォルト値(ブランク可能),[日付書式設定])
 * 
 * 制限：JavaScript を使っているのため,
 * Javascriptが使用できる環境でなければ動きません。
 */ 

function plugin_datefield_action() {
  global $script, $vars;
  global $html_transitional;
  check_editable($vars['refer'], true, true);

  $number = 0;
  $pagedata = '';
  $pagedata_old  = get_source($vars['refer']);

  foreach($pagedata_old as $line) {
    if (!preg_match('/^(?:\/\/| )/', $line)) {
      if (preg_match_all('/(?:#datefield\(([^\)]*)\))/', $line,
  			 $matches, PREG_SET_ORDER)) {
	$paddata = preg_split('/#datefield\([^\)]*\)/', $line);
  	$line = $paddata[0];
	
  	foreach($matches as $i => $match) {
  	  $opt = $match[1];
  	  if ($vars['number'] == $number++) {
  	    //ターゲットのプラグイン部分
	    $para_array=preg_split('/,/',$opt);
	    $errmsg = plugin_datefield_chkFormat($vars['infield'],$para_array[1]);
	    if(strlen($errmsg)>0){
		plugin_datefield_outputErrMsg($vars['refer'], $errmsg);
	    }	    
	    
  	    $opt = preg_replace('/[^,]*/', $vars['infield'], $opt, 1);
  	  }
  	  $line .= "#datefield($opt)" . $paddata[$i+1];
  	}
      }
    }
    $pagedata .= $line;
  }

  page_write($vars['refer'], $pagedata); 
  if( $pagedata != '' ) {
    header("Location: $script?".rawurlencode($vars['refer'])."#datefield_no_".$vars['number']);
    exit;
  }
  return array('msg' => '', 'body' => '');
}

/* * function plugin_datefield_chkFormat($chkedStr, $formatStr) 
 * 日付(確認対象)文字列と日付書式文字列の確認を行う
 * 問題がなければ空文字列を、不具合があればその内容を示す文字列を返す
 */
function plugin_datefield_chkFormat($chkedStr, $formatStr){
  if( strlen($formatStr) == 0) $formatStr='YYYY/MM/DD';
  $formatReg = $formatStr;

  /* クォート文字 の存在確認 */
  if(preg_match('/^.*[\'\"].*$/',$formatReg) ){ /* match character..." ' */ 
    $errmsg =
      "日付書式文字列 " . $formatStr .
      " にクォート文字(&nbsp;&#039;&nbsp;&quot;&nbsp;)を使用しないでください。";
    return $errmsg;
  }

  /* 入力値と日付書式との比較 */
  $formatReg = preg_replace('/\//','\\/',$formatReg);
  $formatReg = '/^' . preg_replace('/[YMD]/i','\\d',$formatReg) .'$/';
  if( ! preg_match($formatReg,$chkedStr) ){
    $errmsg =
      "入力値が日付書式 " . $formatStr . 
      " と合致しません。<br />ゼロパディングも考慮してください。";
    return $errmsg;
  }

  $date = plugin_datefield_getDate($chkedStr, $formatStr);
  $year  = $date['year'];
  $month = $date['month'];
  $day   = $date['day'];

  if( $year == -1 and $month == -1 and $day == -1){
    $errmsg =  "日付確認時の想定外エラーです。<br />";
    $errmsg .= "確認対象文字列: $chkedStr <br />";
    $errmsg .= "日付書式文字列: $formatStr <br />";
    $errmsg .= "引受変数文字列: {$date['dateArgs']} <br />";
    $errmsg .= "パース書式状態: {$date['parseStr']} <br />";
    $errmsg .= "読み取り状態:year = $year, month = $month, day = $day<br />";
    return $errmsg;
  }else if($month <= 0 or $month > 12){
    /* 月の指定は必須 */
    $errmsg = "月の指定 " . $chkedStr    . " が通常取り得る値から外れています。";
    return $errmsg;
  }else{
    /* 月指定がある状態 */
    if( $day > 31){
      $errmsg = "日付の指定 " . $chkedStr  . " が通常取り得る値から外れています。";
      return $errmsg;
    }else{
      /* 指定がない時は 補間する */
      if($year == -1) $year = date("Y",time());
      if($day  == -1) $day  = 1;
      if (! checkdate( $month, $day , $year) ){
        $errmsg = "入力日付 " . $chkedStr . " が不適切です。";
	return $errmsg;
      }
    }
  }
  return "";
}
  
function plugin_datefield_outputErrMsg($page, $errmsg){
  global $_title_cannotedit;

  $body = $title =
  str_replace('$1',htmlspecialchars(strip_bracket($page)),$_title_cannotedit);
  $body .= "<br />datefield.inc.php : <br />" .$errmsg;
  
  $page = str_replace('$1',make_search($page),$_title_cannotedit);
  catbody($title,$page,$body);
  exit;
}  

function plugin_datefield_getDate($dateStr, $formatStr){
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

  if(! strcmp($scanStr,$formatPtn) == 0){
    $formatPtn = ",\"" . $formatPtn . "\"";
    $parseStr = "sscanf(\"$scanStr\" $formatPtn $dateArgs);";
    eval($parseStr);
  }
  $date = array(
    "year"      => $year,
    "month"     => $month,
    "day"       => $day,
    "formatPtn" => $formatPtn,
    "dateArgs"  => $dateArgs,
    "parseStr"  => $parseStr );
  
  return $date;
}

function plugin_datefield_getDateStrWithFormat($format_opt,$yyyy,$mm,$dd ){
  $strWithFormat = $format_opt;
  $yy = $yyyy%100;

  $mm += 1; // 引数の月の値の範囲 month is 0 - 11
  if ($yy < 10) $yy = "0" . $yy;
  if ($mm < 10) $mm = "0" . $mm;
  if ($dd < 10) $dd = "0" . $dd;
  $strWithFormat = preg_replace('/YYYY/i', $yyyy, $strWithFormat);
  $strWithFormat = preg_replace('/YY/i',   $yy,   $strWithFormat);
  $strWithFormat = preg_replace('/MM/i',   $mm,   $strWithFormat);
  $strWithFormat = preg_replace('/DD/i',   $dd,   $strWithFormat);
  return $strWithFormat;
}

// Javasciptを用いること、<form>タグにname属性を用いることを通知する
function plugin_datefield_headDeclaration() {
  global $html_transitional, $head_tags;
  
  // XHTML 1.0 Transitional
  $html_transitional = TRUE;

  // <head> タグ内への <meta>宣言の追加
  $meta_str =
   " <meta http-equiv=\"content-script-type\" content=\"text/javascript\" /> ";
  if(! in_array($meta_str, $head_tags) ){
    $head_tags[] = $meta_str;
  }
}


function plugin_datefield_convert() {

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
      
      return plugin_datefield_getBody(
	     $number, $value, $format_opt, $caldsp_opt);
    }
  return FALSE;
}

function plugin_datefield_getNumber() {
  global $vars;
  static $numbers = array();
  if (!array_key_exists($vars['page'],$numbers))
    {
      $numbers[$vars['page']] = 0;
    }
  return $numbers[$vars['page']]++;
}

function plugin_datefield_formFormat($format_opt) {
  $format_str= trim($format_opt);
  if(strlen($format_str) == 0 )  $format_str = 'YYYY/MM/DD';
  if(preg_match('/^[\'\"].*[\"\']$/',$format_str)){ /* " */
    $format_str = '\'' . substr($format_str,1,strlen($format_str)-2) . '\'';
  }else{
    $format_str = '\'' . $format_str . '\'';
  }
  
  return $format_str;
}

function plugin_datefield_getBody($number, $value, $format_opt, $caldsp_opt) {
  global $script, $vars;

  $page_enc = htmlspecialchars($vars['page']);
  $script_enc = htmlspecialchars($script);

  // datefield 用の<script>タグの挿入
  $body = ($number == 0) ? plugin_datefield_getScript() : '';

  // 日付書式指定文字列に対する処理
  $format_opt= plugin_datefield_formFormat($format_opt);
  
  // カレンダー表示設定に対する処理
  if($caldsp_opt != 'CUR') $caldsp_opt = 'REL';

  // 保存された日付から値を取得
  $formatStr =substr($format_opt,1,strlen($format_opt)-2);
  $errmsg = plugin_datefield_chkFormat($value,$formatStr);
  if(strlen($errmsg)==0 and $caldsp_opt == 'REL'){
    $date= plugin_datefield_getDate($value, $formatStr);
    /* 指定がない時は 補間する */
    if($date['year'] == -1) $date['year'] = date("Y",time());
    if($date['day']  == -1) $date['day']  = 1;
  }else{
    $date = array(
     "year"  => date("Y",time()),
     "month" => date("m",time()),
     "day"   => date("d",time()) ); 
  }

  $field_size = strlen($format_opt); 

  $body .= <<<EOD
    <form name="subClndr$number" action="$script_enc"
    method='post' style="margin:0;">
    <a id="datefield_no_$number">
    <div  style="white-space:nowrap; ">
    <input type="text" name="infield" value="$value" size="{$field_size}"
    onchange="this.form.submit();" />
    <input type="button" value="…"
    onclick="dspCalendar(this.form.infield, event, $format_opt, 0,
     {$date['year']},{$date['month']}-1,{$date['day']},1 );" />
      <input type="hidden" name="refer" value="$page_enc" />
      <input type="hidden" name="plugin" value="datefield" />
      <input type="hidden" name="number" value="$number" />
    </div>
    </a>
    </form>
EOD;
  return $body;
}

function plugin_datefield_getScript() {
  global $script, $vars;
  $page_enc = htmlspecialchars($vars['page']);
  $script_enc = htmlspecialchars($script);
  $js = '<script type="text/javascript" src="'. SKIN_DIR . 'datefield.js" ></script>';
  return $js;
}
?>
