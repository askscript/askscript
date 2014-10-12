<?php

/*
	Question2Answer by Gideon Greenspan and contributors

	http://www.question2answer.org/

	
	File: as-include/as-db-maxima.php
	Version: See define()s at top of as-include/as-base.php
	Description: Definitions that determine database column size and rows retrieved


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


//	Maximum column sizes - any of these can be defined in as-config.php to override the defaults below,
//	but you need to do so before creating the database, otherwise it's too late.

	@define('AS_DB_MAX_EMAIL_LENGTH', 80);
	@define('AS_DB_MAX_HANDLE_LENGTH', 20);
	@define('AS_DB_MAX_TITLE_LENGTH', 800);
	@define('AS_DB_MAX_CONTENT_LENGTH', 8000);
	@define('AS_DB_MAX_FORMAT_LENGTH', 20);
	@define('AS_DB_MAX_TAGS_LENGTH', 800);
	@define('AS_DB_MAX_NAME_LENGTH', 40);
	@define('AS_DB_MAX_WORD_LENGTH', 80);
	@define('AS_DB_MAX_CAT_PAGE_TITLE_LENGTH', 80);
	@define('AS_DB_MAX_CAT_PAGE_TAGS_LENGTH', 200);
	@define('AS_DB_MAX_CAT_CONTENT_LENGTH', 800);
	@define('AS_DB_MAX_WIDGET_TAGS_LENGTH', 800);
	@define('AS_DB_MAX_WIDGET_TITLE_LENGTH', 80);
	@define('AS_DB_MAX_OPTION_TITLE_LENGTH', 40);
	@define('AS_DB_MAX_PROFILE_TITLE_LENGTH', 40);
	@define('AS_DB_MAX_PROFILE_CONTENT_LENGTH', 8000);
	@define('AS_DB_MAX_CACHE_AGE', 86400);
	@define('AS_DB_MAX_BLOB_FILE_NAME_LENGTH', 255);
	@define('AS_DB_MAX_META_TITLE_LENGTH', 40);
	@define('AS_DB_MAX_META_CONTENT_LENGTH', 8000);


//	How many records to retrieve for different circumstances. In many cases we retrieve more records than we
//	end up needing to display once we know the value of an option. Wasteful, but allows one query per page.

	@define('AS_DB_RETRIEVE_QS_AS', 50);
	@define('AS_DB_RETRIEVE_TAGS', 200);
	@define('AS_DB_RETRIEVE_USERS', 200);
	@define('AS_DB_RETRIEVE_ASK_TAG_QS', 500);
	@define('AS_DB_RETRIEVE_COMPLETE_TAGS', 1000);
	@define('AS_DB_RETRIEVE_MESSAGES', 20);

	
//	Keep event streams trimmed - not worth storing too many events per question because we only display the
//	most recent event for each question, that has not been invalidated due to hiding/unselection/etc...

	@define('AS_DB_MAX_EVENTS_PER_Q', 5);


/*
	Omit PHP closing tag to help avoid accidental output
*/