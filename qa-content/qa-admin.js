/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-content/qa-admin.js
	Version: See define()s at top of qa-include/qa-base.php
	Description: Javascript for admin pages to handle Ajax-triggered operations


	This program is free software; you can redistribute it and/or
	modify it under the terms of the GNU General Public License
	as published by the Free Software Foundation; either version 2
	of the License, or (at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	More about this license: http://www.question2answer.org/license.php
*/

var as_recalc_running=0;

window.onbeforeunload=function(event)
{
	if (as_recalc_running>0) {
		event=event||window.event;
		var message=as_warning_recalc;
		event.returnValue=message;
		return message;
	}
};

function as_recalc_click(state, elem, value, noteid)
{
	if (elem.as_recalc_running) {
		elem.as_recalc_stopped=true;
	
	} else {
		elem.as_recalc_running=true;
		elem.as_recalc_stopped=false;
		as_recalc_running++;
		
		document.getElementById(noteid).innerHTML='';
		elem.as_original_value=elem.value;
		if (value)
			elem.value=value;
		
		as_recalc_update(elem, state, noteid);
	}
	
	return false;
}

function as_recalc_update(elem, state, noteid)
{
	if (state)
		as_ajax_post('recalc', {state:state, code:(elem.form.elements.code_recalc ? elem.form.elements.code_recalc.value : elem.form.elements.code.value)},
			function(lines) {
				if (lines[0]=='1') {
					if (lines[2])
						document.getElementById(noteid).innerHTML=lines[2];
					
					if (elem.as_recalc_stopped)
						as_recalc_cleanup(elem);
					else
						as_recalc_update(elem, lines[1], noteid);
				
				} else if (lines[0]=='0') {
					document.getElementById(noteid).innerHTML=lines[2];
					as_recalc_cleanup(elem);
				
				} else {
					as_ajax_error();
					as_recalc_cleanup(elem);
				}
			}
		);

	else
		as_recalc_cleanup(elem);
}

function as_recalc_cleanup(elem)
{
	elem.value=elem.as_original_value;
	elem.as_recalc_running=null;
	as_recalc_running--;
}

function as_mailing_start(noteid, pauseid)
{
	as_ajax_post('mailing', {},
		function (lines) {
			if (lines[0]=='1') {
				document.getElementById(noteid).innerHTML=lines[1];
				window.setTimeout(function() { as_mailing_start(noteid, pauseid); }, 1); // don't recurse
			
			} else if (lines[0]=='0') {
				document.getElementById(noteid).innerHTML=lines[1];
				document.getElementById(pauseid).style.display='none';
				
			} else {
				as_ajax_error();
			}
		}
	);
}

function as_admin_click(target)
{
	var p=target.name.split('_');
	
	var params={entityid:p[1], action:p[2]};
	params.code=target.form.elements.code.value;

	as_ajax_post('click_admin', params,
		function (lines) {
			if (lines[0]=='1')
				as_conceal(document.getElementById('p'+p[1]), 'admin');
			else if (lines[0]=='0') {
				alert(lines[1]);
				as_hide_waiting(target);
			} else
				as_ajax_error();
		}
	);
	
	as_show_waiting_after(target, false);
	
	return false;
}

function as_version_check(uri, versionkey, version, urikey, elem)
{
	var params={uri:uri, versionkey:versionkey, version:version, urikey:urikey};
	
	as_ajax_post('version', params,
		function (lines) {
			if (lines[0]=='1')
				document.getElementById(elem).innerHTML=lines[1];
		}
	);
}