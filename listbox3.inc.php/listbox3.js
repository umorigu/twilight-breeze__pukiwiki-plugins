/* ******************************************************************
 *  $Id: listbox3.js v 0.3 2006/03/04 10:45:42 jjyun Exp $
 * -----------------------------------------------------------------
 * Copyright (C) 2006 written by jjyun
 * ( http://www2.g-com.ne.jp/~jjyun/twilight-breeze/pukiwiki.php )
 ********************************************************************
 * This script is tested only for NN6,Mozilla,IE5.
 */

function _plugin_listbox3_initSelector()
{
	for( var i=0; i < document.forms.length; i++ )
	{
		formName = document.forms[i].name;
		if( formName.length == undefined ) return;

		if( formName.indexOf( "listbox3", 0) >= 0)
		{
			//for NN6,Mozilla,IE5
			if(document.getElementById)
			{
				document.forms[i].select.disabled = true;
			}
		}
	}
}

function _plugin_listbox3_changeMode( obj, edit, refer, imgPath)
{
	imgPathEdit  = "" + imgPath + edit;
	imgPathRefer = "" + imgPath + refer;
	
	// for NN6,Mozilla,IE5
	if(document.getElementById) 
	{	
		if( obj.editTrigger.src.indexOf( edit ) >= 0 )
		{
			obj.editTrigger.src = imgPathRefer;
			obj.select.disabled = false;
	        }
	        else
		{
			obj.editTrigger.src = imgPathEdit;
			obj.select.disabled = true;
		}
	}
}

try{
	window.addEventListener('load', _plugin_listbox3_initSelector, false );
}
catch (e)
{
	window.attachEvent('onload',  _plugin_listbox3_initSelector);
}
