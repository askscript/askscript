<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-app-blobs.php
	Version: See define()s at top of as-include/as-base.php
	Description: Application-level blob-management functions


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

	if (!defined('AS_VERSION')) { // don't allow this page to be requested directly from browser
		header('Location: ../');
		exit;
	}

	
	function as_get_blob_url($blobid, $absolute=false)
/*
	Return the URL which will output $blobid from the database when requested, $absolute or relative
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		return as_path('blob', array('as_blobid' => $blobid), $absolute ? as_opt('site_url') : null, AS_URL_FORMAT_PARAMS);
	}
	
	
	function as_get_blob_directory($blobid)
/*
	Return the full path to the on-disk directory for blob $blobid (subdirectories are named by the first 3 digits of $blobid)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

		return rtrim(AS_BLOBS_DIRECTORY, '/').'/'.substr(str_pad($blobid, 20, '0', STR_PAD_LEFT), 0, 3);
	}
	
	
	function as_get_blob_filename($blobid, $format)
/*
	Return the full page and filename of blob $blobid which is in $format ($format is used as the file name suffix e.g. .jpg)
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

		return as_get_blob_directory($blobid).'/'.$blobid.'.'.preg_replace('/[^A-Za-z0-9]/', '', $format);
	}
	
	
	function as_create_blob($content, $format, $sourcefilename=null, $userid=null, $cookieid=null, $ip=null)
/*
	Create a new blob (storing the content in the database or on disk as appropriate) with $content and $format, returning its blobid.
	Pass the original name of the file uploaded in $sourcefilename and the $userid, $cookieid and $ip of the user creating it
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-blobs.php';
		
		$blobid=as_db_blob_create(defined('AS_BLOBS_DIRECTORY') ? null : $content, $format, $sourcefilename, $userid, $cookieid, $ip);

		if (isset($blobid) && defined('AS_BLOBS_DIRECTORY'))
			if (!as_write_blob_file($blobid, $content, $format))
				as_db_blob_set_content($blobid, $content); // still write content to the database if writing to disk failed

		return $blobid;
	}
	
	
	function as_write_blob_file($blobid, $content, $format)
/*
	Write the on-disk file for blob $blobid with $content and $format. Returns true if the write succeeded, false otherwise.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

		$written=false;
		
		$directory=as_get_blob_directory($blobid);
		if (is_dir($directory) || mkdir($directory, fileperms(rtrim(AS_BLOBS_DIRECTORY, '/')) & 0777)) {
			$filename=as_get_blob_filename($blobid, $format);
			
			$file=fopen($filename, 'xb');
			if (is_resource($file)) {
				if (fwrite($file, $content)>=strlen($content))
					$written=true;

				fclose($file);
				
				if (!$written)
					unlink($filename);
			}
		}
		
		return $written;	
	}
	
	
	function as_read_blob($blobid)
/*
	Retrieve blob $blobid from the database, reading the content from disk if appropriate
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-blobs.php';
	
		$blob=as_db_blob_read($blobid);
		
		if (defined('AS_BLOBS_DIRECTORY') && !isset($blob['content']))
			$blob['content']=as_read_blob_file($blobid, $blob['format']);
			
		return $blob;
	}
	
	
	function as_read_blob_file($blobid, $format)
/*
	Read the content of blob $blobid in $format from disk. On failure, it will return false.
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

		return file_get_contents(as_get_blob_filename($blobid, $format));
	}
	
	
	function as_delete_blob($blobid)
/*
	Delete blob $blobid from the database, and remove the on-disk file if appropriate
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		require_once AS_INCLUDE_DIR.'as-db-blobs.php';
	
		if (defined('AS_BLOBS_DIRECTORY')) {
			$blob=as_db_blob_read($blobid);
			
			if (isset($blob) && !isset($blob['content']))
				unlink(as_get_blob_filename($blobid, $blob['format']));
		}
		
		as_db_blob_delete($blobid);
	}
	
	
	function as_delete_blob_file($blobid, $format)
/*
	Delete the on-disk file for blob $blobid in $format
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }
		
		unlink(as_get_blob_filename($blobid, $format));
	}
	
	
	function as_blob_exists($blobid)
/*
	Check if blob $blobid exists
*/
	{
		if (as_to_override(__FUNCTION__)) { $args=func_get_args(); return as_call_override(__FUNCTION__, $args); }

		require_once AS_INCLUDE_DIR.'as-db-blobs.php';
		
		return as_db_blob_exists($blobid);
	}
	

/*
	Omit PHP closing tag to help avoid accidental output
*/