<?php
/////////////////////////////////////////////////
// PukiWiki - Yet another WikiWikiWeb clone.
//
// $Id: listbox3.inc.php,v 0.1 2004/01/27 07:43:02 jjyun Exp $

// This script is based on listbox2.inc.php by KaWaZ

function plugin_listbox3_action() {
  global $vars, $post;
  $number = 0;
  $pagedata = '';
  $pagedata_old  = get_source($post['refer']);
  foreach($pagedata_old as $line) {
    if(!preg_match('/^(?:\/\/| )/', $line)) {
      if (preg_match_all('/(?:#listbox3\(([^\)]*)\))/', $line, $matches, PREG_SET_ORDER)) {
	$paddata = preg_split('/#listbox3\([^\)]*\)/', $line);
	$line = $paddata[0];
	foreach($matches as $i => $match) {
	  $opt = $match[1];
	  if($post['number'] == $number++) {
	    //ターゲットのプラグイン部分
	    $opt = preg_replace('/[^,]*/', $post['value'], $opt, 1);
	  }
	  $line .= "#listbox3($opt)" . $paddata[$i+1];
	}
      }
    }
    $pagedata .= $line;
  }
  page_write($post['refer'], $pagedata);
  return array('msg' => '', 'body' => '');
}

function plugin_listbox3_convert()
{
  $number = plugin_listbox3_getNumber();
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

function plugin_listbox3_getNumber() {
  global $vars;
  static $numbers = array();
  if (!array_key_exists($vars['page'],$numbers))
    {
      $numbers[$vars['page']] = 0;
    }
  return $numbers[$vars['page']]++;
}

function plugin_listbox3_getBody($number, $value, $template, $fieldname) {
  global $script, $vars;
  $page_enc = htmlspecialchars($vars['page']);
  $script_enc = htmlspecialchars($script);
  $options_html = plugin_listbox3_getOptions($value, $template, $fieldname);
  $body = <<<EOD
    <form action="$script_enc" method="post" style="margin:0;"> 
    <div>
    <select name="value" style="vertical-align:middle;" onchange="this.form.submit();">
    $options_html
    </select>
    <input type="hidden" name="number" value="$number" />
    <input type="hidden" name="plugin" value="listbox3" />
    <input type="hidden" name="refer" value="$page_enc" />
    </div>
    </form>
EOD;
  //$body = preg_replace("/\s+</", '<', $body);
  //$body = preg_replace("/>\s+/", '>', $body);
  return $body;
}

function plugin_listbox3_getOptions($value, $config_name, $field_name) {
  $options_html = '';
  
  $config = new Config('plugin/tracker/'.$config_name);
  if(!$config->read()){
    return "<p>config file '" . 
      htmlspecialchars($config_name)."' not found.</p>";    
  }
  $config->name = $config_name;

  foreach($config->get($field_name) as $options) {
    $s_option=$options[0];
    if($s_option == '') {
      continue;
    }
    $option_enc = htmlspecialchars($s_option);
    if($value == $s_option) {
      $options_html .= "<option value='$option_enc' selected='selected'>$option_enc</option>";
    } else {
      $options_html .= "<option value='$option_enc'>$option_enc</option>";
    }
  }

  return $options_html;
}
?>
