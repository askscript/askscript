<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: qa-include/qa-util-debug.php
	Version: See define()s at top of qa-include/qa-base.php
	Description: Debugging stuff, currently used for tracking resource usage


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

	function as_usage_init()
/*
	Initialize the counts of resource usage
*/
	{
		global $as_database_usage, $as_database_queries, $as_usage_start, $as_usage_last;
		
		$as_database_usage=array('queries' => 0, 'clock' => 0);
		$as_database_queries='';
		$as_usage_last=$as_usage_start=as_usage_get();
	}

	
	function as_usage_get()
/*
	Return an array representing the resource usage as of now
*/
	{
		global $as_database_usage;
		
		$usage=array(
			'files' => count(get_included_files()),
			'queries' => $as_database_usage['queries'],
			'ram' => function_exists('memory_get_usage') ? memory_get_usage() : 0,
			'clock' => array_sum(explode(' ', microtime())),
			'mysql' => $as_database_usage['clock'],
		);
		
		if (function_exists('getrusage')) {
			$rusage=getrusage();
			$usage['cpu']=$rusage["ru_utime.tv_sec"]+$rusage["ru_stime.tv_sec"]
				+($rusage["ru_utime.tv_usec"]+$rusage["ru_stime.tv_usec"])/1000000;
		} else
			$usage['cpu']=0;
			
		$usage['other']=$usage['clock']-$usage['cpu']-$usage['mysql'];
			
		return $usage;
	}

	
	function as_usage_delta($oldusage, $newusage)
/*
	Return the difference between two resource usage arrays, as an array
*/
	{
		$delta=array();
		
		foreach ($newusage as $key => $value)
			$delta[$key]=max(0, $value-@$oldusage[$key]);
			
		return $delta;
	}

	
	function as_usage_mark($stage)
/*
	Mark the beginning of a new stage of script execution and store usages accordingly
*/
	{
		global $as_usage_last, $as_usage_stages;
		
		$usage=as_usage_get();
		$as_usage_stages[$stage]=as_usage_delta($as_usage_last, $usage);
		$as_usage_last=$usage;
	}


	function as_usage_line($stage, $usage, $totalusage)
/*
	Return HTML to represent the resource $usage, showing appropriate proportions of $totalusage
*/
	{
		return sprintf(
			"%s &ndash; <b>%.1fms</b> (%d%%) &ndash; PHP %.1fms (%d%%), MySQL %.1fms (%d%%), Other %.1fms (%d%%) &ndash; %d PHP %s, %d DB %s, %dk RAM (%d%%)",
			$stage, $usage['clock']*1000, $usage['clock']*100/$totalusage['clock'],
			$usage['cpu']*1000, $usage['cpu']*100/$totalusage['clock'],
			$usage['mysql']*1000, $usage['mysql']*100/$totalusage['clock'],
			$usage['other']*1000, $usage['other']*100/$totalusage['clock'],
			$usage['files'], ($usage['files']==1) ? 'file' : 'files',
			$usage['queries'], ($usage['queries']==1) ? 'query' : 'queries',
			$usage['ram']/1024, $usage['ram'] ? ($usage['ram']*100/$totalusage['ram']) : 0
		);
	}

	
	function as_usage_output()
/*
	Output an (ugly) block of HTML detailing all resource usage and database queries
*/
	{
		global $as_usage_start, $as_usage_stages, $as_database_queries;
		
		echo '<p><br><table bgcolor="#cccccc" cellpadding="8" cellspacing="0" width="100%">';
	
		echo '<tr><td colspan="2">';
		
		$totaldelta=as_usage_delta($as_usage_start, as_usage_get());
		
		echo as_usage_line('Total', $totaldelta, $totaldelta).'<br>';
		
		foreach ($as_usage_stages as $stage => $stagedelta)
			
		echo '<br>'.as_usage_line(ucfirst($stage), $stagedelta, $totaldelta);
		
		echo '</td></tr><tr valign="bottom"><td width="30%"><textarea cols="40" rows="20" style="width:100%;">';
		
		foreach (get_included_files() as $file)
			echo as_html(implode('/', array_slice(explode('/', $file), -3)))."\n";
		
		echo '</textarea></td>';
		
		echo '<td width="70%"><textarea cols="40" rows="20" style="width:100%;">'.as_html($as_database_queries).'</textarea></td>';
		
		echo '</tr></table>';
	}


/*
	Omit PHP closing tag to help avoid accidental output
*/