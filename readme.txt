=== SecuPress Backdoor User ===

Script Name: SecuPress Backdoor User
Script URI: https://secupress.me/blog/backdoor-user/
Author URI: https://secupress.me
Author: Julio Potier
Version: 3.1
Contributors: Kévin (@DarkLG), Fanchy (fanchy@hotmail.fr)
Tags: security, admin, user
License: GPLv3


== Description ==

This script is used to create, delete, edit, or log in in a WordPress installation when you do not have dashboard access but only FTP access.
You can also (since 3.1) deactivate plugins without being logged in (because you can't if it's broken).
Just rename, upload, run it and read.


== Installation ==

1. Rename this file
2. Upload it in any folder, even at WordPress root install
3. Go to this file in your favorite browser
4. 4 users choices, create user, delete user, log in with a user, edit role or password for a user
5. 2 plugins choices, deactivate all or choose the good ones.
6. Do not forget to delete the file after use, it will not be automatically deleted.


== Usage ==

1. User creation:
	- Fill the fields for login, pass, email,
	- Choose a role,
	- Check or not the login box, if yes, you'll be logged in with this user,
	- Click "Create".
	- For each missing fields, a random value will be created.
		* Example for a random editor user : "editor_2j1p12"
		* Example for a random pass : mmm really random, change it or lose this account
		* Example for random email : 134659872145@fake134659872145.com
2. User log in
	- Choose a user,
	- Click "Log in".
3. User deletion
	- Choose a user,
	- Choose another user which will receive all posts from the first one
	- Click "Delete"
	- If you do not choose a user for re-attribution, all posts will be deleted.
4. User edition
	- Choose a user,
	- Change his role,
	or/and
	- Change his pass,
	- Click "Edit"
5. Plugin Deactivation
	- Choose the plugins to be deactivated
	- Or choose the deactivate all action
6. MU-Plugin Deletion
	- Choose the plugins to be deleted
	- Or choose the delete all action
	
== Hash md5 secupress-backdoor-user.php v3.1.1 ==
a614b0086b98604506228049f692cdb1
