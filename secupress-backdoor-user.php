<?php

/*---------------------------------------------------------------------------

=== SecuPress Backdoor User ===

Script Name: SecuPress Backdoor User
Script URI: https://secupress.me/blog/backdoor-user/
Author URI: https://secupress.me
Author: Julio Potier
Version: 3.1.3
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
6. Do not forget to delete the file after use, it will be automatically deleted.


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
	
Thanks to:
==========
• Julien Maury (github@jmau111) for fixing the do_login fatal error

---------------------------------------------------------------------------*/

define( 'VERSION', '3.1.3' );
define( 'DONOTCACHEPAGE', true );

// Optional deleting file after use
$delete_file = true;

// Force the file to be renamed for security reasons
if ( 'secupress-backdoor-user.php' === strtolower( basename( __FILE__ ) ) ) {
	die("Please rename the file before use!");
}

// Load WordPress
while ( ! is_file( 'wp-load.php' ) ) {
	if ( is_dir( '..' ) && getcwd() != '/' ) {
		chdir( '..' );
	} else {
		die( 'Could not find WordPress!' );
	}
}

require_once( 'wp-load.php' );
require_once( './wp-admin/includes/user.php' );

// Get all roles
$roles = new WP_Roles();
$roles = $roles->get_names();
$roles = array_map( 'translate_user_role', $roles );

if ( is_multisite() ) {
    $roles = array_merge( [ 'site_admin' => 'Super Admin' ], $roles );
}

// Plugins
// Get active plugins from options table
$plugins = array_filter( get_option( 'active_plugins', array() ) );
// Get active mu plugins from mu-pugins dir
$mu_plugins = array_filter( wp_get_mu_plugins() );

// if an action is triggered
if ( isset( $_REQUEST['action'] ) ) {

	switch( $_REQUEST['action'] ) {

		// User Creation
		case 'create_user':

			// Default new user informations
			$new_user_email = str_replace( ' ', '+', $_REQUEST['user_email'] != '' ? $_REQUEST['user_email'] : time() . '@fake' . time() . '.com' );
			$new_user_pass  = $_REQUEST['user_pass'] != '' ? $_REQUEST['user_pass'] : time();
			$new_user_role  = array_key_exists( $_REQUEST['user_role'], $roles ) ? $_REQUEST['user_role'] : 'administrator';
			$new_user_login = $_REQUEST['user_login'] != '' ? $_REQUEST['user_login'] : $new_user_role . '_' . substr( md5( uniqid() . time() ), 0, 7 );

			// Is this user_exists, stop script
			if ( username_exists( $new_user_login ) ) {
				wp_die( new WP_Error( 'existing_user_login', 'This username is already registered.' ) );
			}

			// Create this user
			$my_user_id = wp_create_user( $new_user_login, $new_user_pass, $new_user_email );

			// Problem on creation? Stop script
			if ( is_wp_error( $my_user_id ) ) {
				wp_die( new WP_Error( 'registerfail', sprintf( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !' ), esc_attr( get_option( 'admin_email' ) ) ) );
			}
			// Set admin role to this user
			$user = new WP_User( $my_user_id );

			if ( is_multisite() && 'site_admin' === $new_user_role ) {
			    grant_super_admin( $my_user_id );
				$user->set_role( 'administrator' );
            } else {
			    $user->set_role( $new_user_role );
			}

			// is we want to log in
			if ( isset( $_REQUEST['log_in'] ) ) {
				// Sign on this user
				wp_signon( array( 'user_login' => $new_user_login, 'user_password' => $new_user_pass ) );

				// Delete this file for security reasons
				if ( $delete_file ) {
					unlink( __FILE__ );
				}

				// Redirects you on your profile, change your password ;)
				wp_redirect( admin_url( 'profile.php' ) );
				die();
			}
			// Little message
			$msg = 'User created!';
		break;

		// login user action
		case 'login_user' :
			// get current user's data
			$user_data = get_userdata( $_REQUEST['user_ID'] );

			// Set current user to this user's id
			wp_set_current_user( $user_data->ID, $user_data->user_login );

			// Same with cookie
			wp_set_auth_cookie( $user_data->ID );

			// The hook "wp_login"
			do_action( 'wp_login', $user_data->user_login, $user_data );

			// Delete this file for security reasons
			if( $delete_file ) {
				unlink( __FILE__ );
			}

			// Redirect on dashboard
			wp_redirect( admin_url( 'index.php' ) );
			die();
		break;

		// delete user action
		case 'delete_user':
			// Delete this user and re-attribute is needed
			wp_delete_user( $_REQUEST['user_ID'], $_REQUEST['new_user_ID'] );
			$msg = 'User deleted!';
		break;

		// deactivate plugins
		case 'plugins':
			// Deactivated array
			$deac = array();

			// Deactivation side
			if( ( isset( $_POST['deactivate'] ) && is_array( $_POST['deactivate'] ) ) || isset( $_POST['deactivate_all'] ) ) {
				// Because we need "sanitize_option()" used by "update_option()"
				require_once( 'wp-includes/formatting.php' );
				// We do not deactivate all
				if( !isset( $_POST['deactivate_all'] ) ) {
					// for each plugins from DB ...
					foreach( $_POST['deactivate'] as $des ) {
						// if this plugin is the one
						if( (int)$des == $des && isset( $plugins[$des] ) ):
							// save it in my $deac array
							$deac[] = $plugins[$des];
							// and unset it
							unset( $plugins[$des] );
						endif;
					}
					// reorder array
					$plugins = array_values( $plugins );
					// update options "active_plugins"
					update_option( 'active_plugins', (array)$plugins );
				}else{
					// update options "active_plugins"
					update_option( 'active_plugins', array() );
					// empty $plugins array
					$plugins = array();
				}
				$msg = 'Plugins deactivated!';
			}

			// Delete mu plugins
			if( ( isset( $_POST['delete'] ) && is_array( $_POST['delete'] ) ) || isset( $_POST['delete_all'] ) ) {
				// Because we need "sanitize_option()" used by "update_option()"
				require_once( 'wp-includes/formatting.php' );
				// We do not delete all
				if( !isset( $_POST['delete_all'] ) ) {
					// for each plugins from DB ...
					foreach( $_POST['delete'] as $del ) {
						// if this plugin is the one
						if( (int)$del == $del && isset($mu_plugins[$del]) ):
							// save it in my $deac array
							$dele[] = $mu_plugins[$del];
							// delete file
							@unlink( $mu_plugins[$del] );
							// and unset it
							unset( $mu_plugins[$del] );
						endif;
					}
					// reorder array
					$mu_plugins = array_values( $mu_plugins );
				}else{ // delete all
					foreach( $mu_plugins as $mup )
						@unlink( $mup );
					// empty $mu_plugins array
					$mu_plugins = array();
				}
				$msg = 'Must-Use plugins deleted!';
			}
		break;

		// edit user action
		case 'edit_user':
			// If a role change is needed
			if ( $_REQUEST['user_role'] != '-1' ) {
				// Get the user
				$user = new WP_User( $_REQUEST['user_ID'] );

				// Set his role
				if ( is_multisite() && 'site_admin' === $new_user_role ) {
					grant_super_admin( $my_user_id );
					$user->set_role( 'administrator' );
				} else {
					$user->set_role( $new_user_role );
				}

				$msg = 'User updated!';
			}

			// If a pass change is needed
			if( $_REQUEST['user_pass'] != '') {
				// update the member's pass
				wp_update_user( array(
					'ID'        => $_REQUEST['user_ID'],
					'user_pass' => $_REQUEST['user_pass']
				) );
				$msg = 'User updated!';
			}
		break;

		// just unlink the file
		case 'delete_file':
			unlink( __FILE__ );
			$msg = 'File deleted!';
		break;
	}

}

// Create selectbox for roles
$select_roles = '';
foreach( $roles as $krole => $i18nrole ) {
	$select_roles .= '<option value="' . $krole . '">' . $i18nrole . '</option>' . "\n";
}

// Get all users
if ( function_exists( 'get_users' ) ) {
	$all_users = get_users();
} else {
	$usersID = $wpdb->get_col( 'SELECT ID FROM ' . $wpdb->users . ' ORDER BY ID ASC' );
	foreach ( $usersID as $uid ) {
		$all_users[] = get_userdata( $uid );
	}
}

// Create selectbox for users
$select_users = '';
foreach ( $all_users as $user ) {
	$the_user = new WP_User( $user->ID );
	if ( isset( $the_user->roles[0] ) ) {
		$select_users .= '<option value="' . $user->ID . '">' . $user->user_login . ' (' . $the_user->roles[0] . ')</option>' . "\n";
	}
}

if ( $delete_file ) {
	$warning = <<<HTML
<div class="alert alert-danger" role="alert">
	<small>
		<i class="fa fa-times"></i>
		This file will be automatically deleted then.
	</small>
</div>
HTML;
}else{
	$warning = <<<HTML
<div class="alert alert-warning" role="alert">
	<small>
		<i class="fa fa-warning"></i>
		Do not forget to delete this file after use!
	</small>
</div>
HTML;
}

// Create the HTML code for plugins
$plugs = '';
for( $i=0; $i<=count($plugins)-1; $i++ ){
	$plugs .= '<p><label><input type="checkbox" name="deactivate[]" value="'.$i.'" /> ' . $plugins[$i] . '</label></p>';
}
$plugs = $plugs != '' ?  $plugs . '<p><label><input type="checkbox" name="deactivate_all" value="1" /> <b>Deactivate all plugins</b></label></p>' : '';
// Same for mu plugins
$mu_plugs = '';
for( $i=0; $i<count($mu_plugins); $i++ ) {
	$mu_plugs .= '<p><label><input type="checkbox" name="delete[]" value="'.$i.'" /> ' . basename( $mu_plugins[$i] ) . '</label></p>';
}
$mu_plugs = $mu_plugs != '' ?  $mu_plugs . '<p><label><input type="checkbox" name="delete_all" value="1" /> <b>Delete all mu-plugins</b></label></p>' : '';

// HTML CODE
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->

		<title>WP Backdoor User v<?php echo VERSION; ?></title>

		<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">

		<style>
			/*!
			 * Bootstrap v4.0.0-alpha.5 (https://getbootstrap.com)
			 * Copyright 2011-2016 The Bootstrap Authors
			 * Copyright 2011-2016 Twitter, Inc.
			 * Licensed under MIT (https://github.com/twbs/bootstrap/blob/master/LICENSE)
			 *//*! normalize.css v4.2.0 | MIT License | github.com/necolas/normalize.css */html{font-family:sans-serif;line-height:1.15;-ms-text-size-adjust:100%;-webkit-text-size-adjust:100%}body{margin:0}article,aside,details,figcaption,figure,footer,header,main,menu,nav,section,summary{display:block}audio,canvas,progress,video{display:inline-block}audio:not([controls]){display:none;height:0}progress{vertical-align:baseline}[hidden],template{display:none}a{background-color:transparent;-webkit-text-decoration-skip:objects}a:active,a:hover{outline-width:0}abbr[title]{border-bottom:none;text-decoration:underline;text-decoration:underline dotted}b,strong{font-weight:inherit}b,strong{font-weight:bolder}dfn{font-style:italic}h1{font-size:2em;margin:.67em 0}mark{background-color:#ff0;color:#000}small{font-size:80%}sub,sup{font-size:75%;line-height:0;position:relative;vertical-align:baseline}sub{bottom:-.25em}sup{top:-.5em}img{border-style:none}svg:not(:root){overflow:hidden}code,kbd,pre,samp{font-family:monospace,monospace;font-size:1em}figure{margin:1em 40px}hr{-webkit-box-sizing:content-box;box-sizing:content-box;height:0;overflow:visible}button,input,optgroup,select,textarea{font:inherit;margin:0}optgroup{font-weight:700}button,input{overflow:visible}button,select{text-transform:none}[type=reset],[type=submit],button,html [type=button]{-webkit-appearance:button}[type=button]::-moz-focus-inner,[type=reset]::-moz-focus-inner,[type=submit]::-moz-focus-inner,button::-moz-focus-inner{border-style:none;padding:0}[type=button]:-moz-focusring,[type=reset]:-moz-focusring,[type=submit]:-moz-focusring,button:-moz-focusring{outline:1px dotted ButtonText}fieldset{border:1px solid silver;margin:0 2px;padding:.35em .625em .75em}legend{-webkit-box-sizing:border-box;box-sizing:border-box;color:inherit;display:table;max-width:100%;padding:0;white-space:normal}textarea{overflow:auto}[type=checkbox],[type=radio]{-webkit-box-sizing:border-box;box-sizing:border-box;padding:0}[type=number]::-webkit-inner-spin-button,[type=number]::-webkit-outer-spin-button{height:auto}[type=search]{-webkit-appearance:textfield;outline-offset:-2px}[type=search]::-webkit-search-cancel-button,[type=search]::-webkit-search-decoration{-webkit-appearance:none}::-webkit-input-placeholder{color:inherit;opacity:.54}::-webkit-file-upload-button{-webkit-appearance:button;font:inherit}@media print{*,::after,::before,::first-letter,blockquote::first-line,div::first-line,li::first-line,p::first-line{text-shadow:none!important;-webkit-box-shadow:none!important;box-shadow:none!important}a,a:visited{text-decoration:underline}abbr[title]::after{content:" (" attr(title) ")"}pre{white-space:pre-wrap!important}blockquote,pre{border:1px solid #999;page-break-inside:avoid}thead{display:table-header-group}img,tr{page-break-inside:avoid}h2,h3,p{orphans:3;widows:3}h2,h3{page-break-after:avoid}.navbar{display:none}.btn>.caret,.dropup>.btn>.caret{border-top-color:#000!important}.tag{border:1px solid #000}.table{border-collapse:collapse!important}.table td,.table th{background-color:#fff!important}.table-bordered td,.table-bordered th{border:1px solid #ddd!important}}html{-webkit-box-sizing:border-box;box-sizing:border-box}*,::after,::before{-webkit-box-sizing:inherit;box-sizing:inherit}@-ms-viewport{width:device-width}html{font-size:16px;-ms-overflow-style:scrollbar;-webkit-tap-highlight-color:transparent}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;font-size:1rem;line-height:1.5;color:#373a3c;background-color:#fff}[tabindex="-1"]:focus{outline:0!important}h1,h2,h3,h4,h5,h6{margin-top:0;margin-bottom:.5rem}p{margin-top:0;margin-bottom:1rem}abbr[data-original-title],abbr[title]{cursor:help;border-bottom:1px dotted #818a91}address{margin-bottom:1rem;font-style:normal;line-height:inherit}dl,ol,ul{margin-top:0;margin-bottom:1rem}ol ol,ol ul,ul ol,ul ul{margin-bottom:0}dt{font-weight:700}dd{margin-bottom:.5rem;margin-left:0}blockquote{margin:0 0 1rem}a{color:#0275d8;text-decoration:none}a:focus,a:hover{color:#014c8c;text-decoration:underline}a:focus{outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}a:not([href]):not([tabindex]){color:inherit;text-decoration:none}a:not([href]):not([tabindex]):focus,a:not([href]):not([tabindex]):hover{color:inherit;text-decoration:none}a:not([href]):not([tabindex]):focus{outline:0}pre{margin-top:0;margin-bottom:1rem;overflow:auto}figure{margin:0 0 1rem}img{vertical-align:middle}[role=button]{cursor:pointer}[role=button],a,area,button,input,label,select,summary,textarea{-ms-touch-action:manipulation;touch-action:manipulation}table{border-collapse:collapse;background-color:transparent}caption{padding-top:.75rem;padding-bottom:.75rem;color:#818a91;text-align:left;caption-side:bottom}th{text-align:left}label{display:inline-block;margin-bottom:.5rem}button:focus{outline:1px dotted;outline:5px auto -webkit-focus-ring-color}button,input,select,textarea{line-height:inherit}input[type=checkbox]:disabled,input[type=radio]:disabled{cursor:not-allowed}input[type=date],input[type=time],input[type=datetime-local],input[type=month]{-webkit-appearance:listbox}textarea{resize:vertical}fieldset{min-width:0;padding:0;margin:0;border:0}legend{display:block;width:100%;padding:0;margin-bottom:.5rem;font-size:1.5rem;line-height:inherit}input[type=search]{-webkit-appearance:none}output{display:inline-block}[hidden]{display:none!important}.h1,.h2,.h3,.h4,.h5,.h6,h1,h2,h3,h4,h5,h6{margin-bottom:.5rem;font-family:inherit;font-weight:500;line-height:1.1;color:inherit}.h1,h1{font-size:2.5rem}.h2,h2{font-size:2rem}.h3,h3{font-size:1.75rem}.h4,h4{font-size:1.5rem}.h5,h5{font-size:1.25rem}.h6,h6{font-size:1rem}.lead{font-size:1.25rem;font-weight:300}.display-1{font-size:6rem;font-weight:300}.display-2{font-size:5.5rem;font-weight:300}.display-3{font-size:4.5rem;font-weight:300}.display-4{font-size:3.5rem;font-weight:300}hr{margin-top:1rem;margin-bottom:1rem;border:0;border-top:1px solid rgba(0,0,0,.1)}.small,small{font-size:80%;font-weight:400}.mark,mark{padding:.2em;background-color:#fcf8e3}.list-unstyled{padding-left:0;list-style:none}.list-inline{padding-left:0;list-style:none}.list-inline-item{display:inline-block}.list-inline-item:not(:last-child){margin-right:5px}.initialism{font-size:90%;text-transform:uppercase}.blockquote{padding:.5rem 1rem;margin-bottom:1rem;font-size:1.25rem;border-left:.25rem solid #eceeef}.blockquote-footer{display:block;font-size:80%;color:#818a91}.blockquote-footer::before{content:"\2014 \00A0"}.blockquote-reverse{padding-right:1rem;padding-left:0;text-align:right;border-right:.25rem solid #eceeef;border-left:0}.blockquote-reverse .blockquote-footer::before{content:""}.blockquote-reverse .blockquote-footer::after{content:"\00A0 \2014"}dl.row>dd+dt{clear:left}.carousel-inner>.carousel-item>a>img,.carousel-inner>.carousel-item>img,.img-fluid{max-width:100%;height:auto}.img-thumbnail{padding:.25rem;background-color:#fff;border:1px solid #ddd;border-radius:.25rem;-webkit-transition:all .2s ease-in-out;-o-transition:all .2s ease-in-out;transition:all .2s ease-in-out;max-width:100%;height:auto}.figure{display:inline-block}.figure-img{margin-bottom:.5rem;line-height:1}.figure-caption{font-size:90%;color:#818a91}code,kbd,pre,samp{font-family:Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace}code{padding:.2rem .4rem;font-size:90%;color:#bd4147;background-color:#f7f7f9;border-radius:.25rem}kbd{padding:.2rem .4rem;font-size:90%;color:#fff;background-color:#333;border-radius:.2rem}kbd kbd{padding:0;font-size:100%;font-weight:700}pre{display:block;margin-top:0;margin-bottom:1rem;font-size:90%;color:#373a3c}pre code{padding:0;font-size:inherit;color:inherit;background-color:transparent;border-radius:0}.pre-scrollable{max-height:340px;overflow-y:scroll}.container{margin-left:auto;margin-right:auto;padding-left:15px;padding-right:15px}.container::after{content:"";display:table;clear:both}@media (min-width:576px){.container{width:540px;max-width:100%}}@media (min-width:768px){.container{width:720px;max-width:100%}}@media (min-width:992px){.container{width:960px;max-width:100%}}@media (min-width:1200px){.container{width:1140px;max-width:100%}}.container-fluid{margin-left:auto;margin-right:auto;padding-left:15px;padding-right:15px}.container-fluid::after{content:"";display:table;clear:both}.row{margin-right:-15px;margin-left:-15px}.row::after{content:"";display:table;clear:both}@media (min-width:576px){.row{margin-right:-15px;margin-left:-15px}}@media (min-width:768px){.row{margin-right:-15px;margin-left:-15px}}@media (min-width:992px){.row{margin-right:-15px;margin-left:-15px}}@media (min-width:1200px){.row{margin-right:-15px;margin-left:-15px}}.col-lg,.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,.col-lg-9,.col-md,.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9,.col-sm,.col-sm-1,.col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9,.col-xl,.col-xl-1,.col-xl-10,.col-xl-11,.col-xl-12,.col-xl-2,.col-xl-3,.col-xl-4,.col-xl-5,.col-xl-6,.col-xl-7,.col-xl-8,.col-xl-9,.col-xs,.col-xs-1,.col-xs-10,.col-xs-11,.col-xs-12,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,.col-xs-6,.col-xs-7,.col-xs-8,.col-xs-9{position:relative;min-height:1px;padding-right:15px;padding-left:15px}@media (min-width:576px){.col-lg,.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,.col-lg-9,.col-md,.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9,.col-sm,.col-sm-1,.col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9,.col-xl,.col-xl-1,.col-xl-10,.col-xl-11,.col-xl-12,.col-xl-2,.col-xl-3,.col-xl-4,.col-xl-5,.col-xl-6,.col-xl-7,.col-xl-8,.col-xl-9,.col-xs,.col-xs-1,.col-xs-10,.col-xs-11,.col-xs-12,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,.col-xs-6,.col-xs-7,.col-xs-8,.col-xs-9{padding-right:15px;padding-left:15px}}@media (min-width:768px){.col-lg,.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,.col-lg-9,.col-md,.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9,.col-sm,.col-sm-1,.col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9,.col-xl,.col-xl-1,.col-xl-10,.col-xl-11,.col-xl-12,.col-xl-2,.col-xl-3,.col-xl-4,.col-xl-5,.col-xl-6,.col-xl-7,.col-xl-8,.col-xl-9,.col-xs,.col-xs-1,.col-xs-10,.col-xs-11,.col-xs-12,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,.col-xs-6,.col-xs-7,.col-xs-8,.col-xs-9{padding-right:15px;padding-left:15px}}@media (min-width:992px){.col-lg,.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,.col-lg-9,.col-md,.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9,.col-sm,.col-sm-1,.col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9,.col-xl,.col-xl-1,.col-xl-10,.col-xl-11,.col-xl-12,.col-xl-2,.col-xl-3,.col-xl-4,.col-xl-5,.col-xl-6,.col-xl-7,.col-xl-8,.col-xl-9,.col-xs,.col-xs-1,.col-xs-10,.col-xs-11,.col-xs-12,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,.col-xs-6,.col-xs-7,.col-xs-8,.col-xs-9{padding-right:15px;padding-left:15px}}@media (min-width:1200px){.col-lg,.col-lg-1,.col-lg-10,.col-lg-11,.col-lg-12,.col-lg-2,.col-lg-3,.col-lg-4,.col-lg-5,.col-lg-6,.col-lg-7,.col-lg-8,.col-lg-9,.col-md,.col-md-1,.col-md-10,.col-md-11,.col-md-12,.col-md-2,.col-md-3,.col-md-4,.col-md-5,.col-md-6,.col-md-7,.col-md-8,.col-md-9,.col-sm,.col-sm-1,.col-sm-10,.col-sm-11,.col-sm-12,.col-sm-2,.col-sm-3,.col-sm-4,.col-sm-5,.col-sm-6,.col-sm-7,.col-sm-8,.col-sm-9,.col-xl,.col-xl-1,.col-xl-10,.col-xl-11,.col-xl-12,.col-xl-2,.col-xl-3,.col-xl-4,.col-xl-5,.col-xl-6,.col-xl-7,.col-xl-8,.col-xl-9,.col-xs,.col-xs-1,.col-xs-10,.col-xs-11,.col-xs-12,.col-xs-2,.col-xs-3,.col-xs-4,.col-xs-5,.col-xs-6,.col-xs-7,.col-xs-8,.col-xs-9{padding-right:15px;padding-left:15px}}.col-xs-1{float:left;width:8.333333%}.col-xs-2{float:left;width:16.666667%}.col-xs-3{float:left;width:25%}.col-xs-4{float:left;width:33.333333%}.col-xs-5{float:left;width:41.666667%}.col-xs-6{float:left;width:50%}.col-xs-7{float:left;width:58.333333%}.col-xs-8{float:left;width:66.666667%}.col-xs-9{float:left;width:75%}.col-xs-10{float:left;width:83.333333%}.col-xs-11{float:left;width:91.666667%}.col-xs-12{float:left;width:100%}.pull-xs-0{right:auto}.pull-xs-1{right:8.333333%}.pull-xs-2{right:16.666667%}.pull-xs-3{right:25%}.pull-xs-4{right:33.333333%}.pull-xs-5{right:41.666667%}.pull-xs-6{right:50%}.pull-xs-7{right:58.333333%}.pull-xs-8{right:66.666667%}.pull-xs-9{right:75%}.pull-xs-10{right:83.333333%}.pull-xs-11{right:91.666667%}.pull-xs-12{right:100%}.push-xs-0{left:auto}.push-xs-1{left:8.333333%}.push-xs-2{left:16.666667%}.push-xs-3{left:25%}.push-xs-4{left:33.333333%}.push-xs-5{left:41.666667%}.push-xs-6{left:50%}.push-xs-7{left:58.333333%}.push-xs-8{left:66.666667%}.push-xs-9{left:75%}.push-xs-10{left:83.333333%}.push-xs-11{left:91.666667%}.push-xs-12{left:100%}.offset-xs-1{margin-left:8.333333%}.offset-xs-2{margin-left:16.666667%}.offset-xs-3{margin-left:25%}.offset-xs-4{margin-left:33.333333%}.offset-xs-5{margin-left:41.666667%}.offset-xs-6{margin-left:50%}.offset-xs-7{margin-left:58.333333%}.offset-xs-8{margin-left:66.666667%}.offset-xs-9{margin-left:75%}.offset-xs-10{margin-left:83.333333%}.offset-xs-11{margin-left:91.666667%}@media (min-width:576px){.col-sm-1{float:left;width:8.333333%}.col-sm-2{float:left;width:16.666667%}.col-sm-3{float:left;width:25%}.col-sm-4{float:left;width:33.333333%}.col-sm-5{float:left;width:41.666667%}.col-sm-6{float:left;width:50%}.col-sm-7{float:left;width:58.333333%}.col-sm-8{float:left;width:66.666667%}.col-sm-9{float:left;width:75%}.col-sm-10{float:left;width:83.333333%}.col-sm-11{float:left;width:91.666667%}.col-sm-12{float:left;width:100%}.pull-sm-0{right:auto}.pull-sm-1{right:8.333333%}.pull-sm-2{right:16.666667%}.pull-sm-3{right:25%}.pull-sm-4{right:33.333333%}.pull-sm-5{right:41.666667%}.pull-sm-6{right:50%}.pull-sm-7{right:58.333333%}.pull-sm-8{right:66.666667%}.pull-sm-9{right:75%}.pull-sm-10{right:83.333333%}.pull-sm-11{right:91.666667%}.pull-sm-12{right:100%}.push-sm-0{left:auto}.push-sm-1{left:8.333333%}.push-sm-2{left:16.666667%}.push-sm-3{left:25%}.push-sm-4{left:33.333333%}.push-sm-5{left:41.666667%}.push-sm-6{left:50%}.push-sm-7{left:58.333333%}.push-sm-8{left:66.666667%}.push-sm-9{left:75%}.push-sm-10{left:83.333333%}.push-sm-11{left:91.666667%}.push-sm-12{left:100%}.offset-sm-0{margin-left:0%}.offset-sm-1{margin-left:8.333333%}.offset-sm-2{margin-left:16.666667%}.offset-sm-3{margin-left:25%}.offset-sm-4{margin-left:33.333333%}.offset-sm-5{margin-left:41.666667%}.offset-sm-6{margin-left:50%}.offset-sm-7{margin-left:58.333333%}.offset-sm-8{margin-left:66.666667%}.offset-sm-9{margin-left:75%}.offset-sm-10{margin-left:83.333333%}.offset-sm-11{margin-left:91.666667%}}@media (min-width:768px){.col-md-1{float:left;width:8.333333%}.col-md-2{float:left;width:16.666667%}.col-md-3{float:left;width:25%}.col-md-4{float:left;width:33.333333%}.col-md-5{float:left;width:41.666667%}.col-md-6{float:left;width:50%}.col-md-7{float:left;width:58.333333%}.col-md-8{float:left;width:66.666667%}.col-md-9{float:left;width:75%}.col-md-10{float:left;width:83.333333%}.col-md-11{float:left;width:91.666667%}.col-md-12{float:left;width:100%}.pull-md-0{right:auto}.pull-md-1{right:8.333333%}.pull-md-2{right:16.666667%}.pull-md-3{right:25%}.pull-md-4{right:33.333333%}.pull-md-5{right:41.666667%}.pull-md-6{right:50%}.pull-md-7{right:58.333333%}.pull-md-8{right:66.666667%}.pull-md-9{right:75%}.pull-md-10{right:83.333333%}.pull-md-11{right:91.666667%}.pull-md-12{right:100%}.push-md-0{left:auto}.push-md-1{left:8.333333%}.push-md-2{left:16.666667%}.push-md-3{left:25%}.push-md-4{left:33.333333%}.push-md-5{left:41.666667%}.push-md-6{left:50%}.push-md-7{left:58.333333%}.push-md-8{left:66.666667%}.push-md-9{left:75%}.push-md-10{left:83.333333%}.push-md-11{left:91.666667%}.push-md-12{left:100%}.offset-md-0{margin-left:0%}.offset-md-1{margin-left:8.333333%}.offset-md-2{margin-left:16.666667%}.offset-md-3{margin-left:25%}.offset-md-4{margin-left:33.333333%}.offset-md-5{margin-left:41.666667%}.offset-md-6{margin-left:50%}.offset-md-7{margin-left:58.333333%}.offset-md-8{margin-left:66.666667%}.offset-md-9{margin-left:75%}.offset-md-10{margin-left:83.333333%}.offset-md-11{margin-left:91.666667%}}@media (min-width:992px){.col-lg-1{float:left;width:8.333333%}.col-lg-2{float:left;width:16.666667%}.col-lg-3{float:left;width:25%}.col-lg-4{float:left;width:33.333333%}.col-lg-5{float:left;width:41.666667%}.col-lg-6{float:left;width:50%}.col-lg-7{float:left;width:58.333333%}.col-lg-8{float:left;width:66.666667%}.col-lg-9{float:left;width:75%}.col-lg-10{float:left;width:83.333333%}.col-lg-11{float:left;width:91.666667%}.col-lg-12{float:left;width:100%}.pull-lg-0{right:auto}.pull-lg-1{right:8.333333%}.pull-lg-2{right:16.666667%}.pull-lg-3{right:25%}.pull-lg-4{right:33.333333%}.pull-lg-5{right:41.666667%}.pull-lg-6{right:50%}.pull-lg-7{right:58.333333%}.pull-lg-8{right:66.666667%}.pull-lg-9{right:75%}.pull-lg-10{right:83.333333%}.pull-lg-11{right:91.666667%}.pull-lg-12{right:100%}.push-lg-0{left:auto}.push-lg-1{left:8.333333%}.push-lg-2{left:16.666667%}.push-lg-3{left:25%}.push-lg-4{left:33.333333%}.push-lg-5{left:41.666667%}.push-lg-6{left:50%}.push-lg-7{left:58.333333%}.push-lg-8{left:66.666667%}.push-lg-9{left:75%}.push-lg-10{left:83.333333%}.push-lg-11{left:91.666667%}.push-lg-12{left:100%}.offset-lg-0{margin-left:0%}.offset-lg-1{margin-left:8.333333%}.offset-lg-2{margin-left:16.666667%}.offset-lg-3{margin-left:25%}.offset-lg-4{margin-left:33.333333%}.offset-lg-5{margin-left:41.666667%}.offset-lg-6{margin-left:50%}.offset-lg-7{margin-left:58.333333%}.offset-lg-8{margin-left:66.666667%}.offset-lg-9{margin-left:75%}.offset-lg-10{margin-left:83.333333%}.offset-lg-11{margin-left:91.666667%}}@media (min-width:1200px){.col-xl-1{float:left;width:8.333333%}.col-xl-2{float:left;width:16.666667%}.col-xl-3{float:left;width:25%}.col-xl-4{float:left;width:33.333333%}.col-xl-5{float:left;width:41.666667%}.col-xl-6{float:left;width:50%}.col-xl-7{float:left;width:58.333333%}.col-xl-8{float:left;width:66.666667%}.col-xl-9{float:left;width:75%}.col-xl-10{float:left;width:83.333333%}.col-xl-11{float:left;width:91.666667%}.col-xl-12{float:left;width:100%}.pull-xl-0{right:auto}.pull-xl-1{right:8.333333%}.pull-xl-2{right:16.666667%}.pull-xl-3{right:25%}.pull-xl-4{right:33.333333%}.pull-xl-5{right:41.666667%}.pull-xl-6{right:50%}.pull-xl-7{right:58.333333%}.pull-xl-8{right:66.666667%}.pull-xl-9{right:75%}.pull-xl-10{right:83.333333%}.pull-xl-11{right:91.666667%}.pull-xl-12{right:100%}.push-xl-0{left:auto}.push-xl-1{left:8.333333%}.push-xl-2{left:16.666667%}.push-xl-3{left:25%}.push-xl-4{left:33.333333%}.push-xl-5{left:41.666667%}.push-xl-6{left:50%}.push-xl-7{left:58.333333%}.push-xl-8{left:66.666667%}.push-xl-9{left:75%}.push-xl-10{left:83.333333%}.push-xl-11{left:91.666667%}.push-xl-12{left:100%}.offset-xl-0{margin-left:0%}.offset-xl-1{margin-left:8.333333%}.offset-xl-2{margin-left:16.666667%}.offset-xl-3{margin-left:25%}.offset-xl-4{margin-left:33.333333%}.offset-xl-5{margin-left:41.666667%}.offset-xl-6{margin-left:50%}.offset-xl-7{margin-left:58.333333%}.offset-xl-8{margin-left:66.666667%}.offset-xl-9{margin-left:75%}.offset-xl-10{margin-left:83.333333%}.offset-xl-11{margin-left:91.666667%}}.table{width:100%;max-width:100%;margin-bottom:1rem}.table td,.table th{padding:.75rem;vertical-align:top;border-top:1px solid #eceeef}.table thead th{vertical-align:bottom;border-bottom:2px solid #eceeef}.table tbody+tbody{border-top:2px solid #eceeef}.table .table{background-color:#fff}.table-sm td,.table-sm th{padding:.3rem}.table-bordered{border:1px solid #eceeef}.table-bordered td,.table-bordered th{border:1px solid #eceeef}.table-bordered thead td,.table-bordered thead th{border-bottom-width:2px}.table-striped tbody tr:nth-of-type(odd){background-color:rgba(0,0,0,.05)}.table-hover tbody tr:hover{background-color:rgba(0,0,0,.075)}.table-active,.table-active>td,.table-active>th{background-color:rgba(0,0,0,.075)}.table-hover .table-active:hover{background-color:rgba(0,0,0,.075)}.table-hover .table-active:hover>td,.table-hover .table-active:hover>th{background-color:rgba(0,0,0,.075)}.table-success,.table-success>td,.table-success>th{background-color:#dff0d8}.table-hover .table-success:hover{background-color:#d0e9c6}.table-hover .table-success:hover>td,.table-hover .table-success:hover>th{background-color:#d0e9c6}.table-info,.table-info>td,.table-info>th{background-color:#d9edf7}.table-hover .table-info:hover{background-color:#c4e3f3}.table-hover .table-info:hover>td,.table-hover .table-info:hover>th{background-color:#c4e3f3}.table-warning,.table-warning>td,.table-warning>th{background-color:#fcf8e3}.table-hover .table-warning:hover{background-color:#faf2cc}.table-hover .table-warning:hover>td,.table-hover .table-warning:hover>th{background-color:#faf2cc}.table-danger,.table-danger>td,.table-danger>th{background-color:#f2dede}.table-hover .table-danger:hover{background-color:#ebcccc}.table-hover .table-danger:hover>td,.table-hover .table-danger:hover>th{background-color:#ebcccc}.thead-inverse th{color:#fff;background-color:#373a3c}.thead-default th{color:#55595c;background-color:#eceeef}.table-inverse{color:#eceeef;background-color:#373a3c}.table-inverse td,.table-inverse th,.table-inverse thead th{border-color:#55595c}.table-inverse.table-bordered{border:0}.table-responsive{display:block;width:100%;min-height:0%;overflow-x:auto}.table-reflow thead{float:left}.table-reflow tbody{display:block;white-space:nowrap}.table-reflow td,.table-reflow th{border-top:1px solid #eceeef;border-left:1px solid #eceeef}.table-reflow td:last-child,.table-reflow th:last-child{border-right:1px solid #eceeef}.table-reflow tbody:last-child tr:last-child td,.table-reflow tbody:last-child tr:last-child th,.table-reflow tfoot:last-child tr:last-child td,.table-reflow tfoot:last-child tr:last-child th,.table-reflow thead:last-child tr:last-child td,.table-reflow thead:last-child tr:last-child th{border-bottom:1px solid #eceeef}.table-reflow tr{float:left}.table-reflow tr td,.table-reflow tr th{display:block!important;border:1px solid #eceeef}.form-control{display:block;width:100%;padding:.5rem .75rem;font-size:1rem;line-height:1.25;color:#55595c;background-color:#fff;background-image:none;-webkit-background-clip:padding-box;background-clip:padding-box;border:1px solid rgba(0,0,0,.15);border-radius:.25rem}.form-control::-ms-expand{background-color:transparent;border:0}.form-control:focus{color:#55595c;background-color:#fff;border-color:#66afe9;outline:0}.form-control::-webkit-input-placeholder{color:#999;opacity:1}.form-control::-moz-placeholder{color:#999;opacity:1}.form-control:-ms-input-placeholder{color:#999;opacity:1}.form-control::placeholder{color:#999;opacity:1}.form-control:disabled,.form-control[readonly]{background-color:#eceeef;opacity:1}.form-control:disabled{cursor:not-allowed}select.form-control:not([size]):not([multiple]){height:calc(2.5rem - 2px)}select.form-control:focus::-ms-value{color:#55595c;background-color:#fff}.form-control-file,.form-control-range{display:block}.col-form-label{padding-top:.5rem;padding-bottom:.5rem;margin-bottom:0}.col-form-label-lg{padding-top:.75rem;padding-bottom:.75rem;font-size:1.25rem}.col-form-label-sm{padding-top:.25rem;padding-bottom:.25rem;font-size:.875rem}.col-form-legend{padding-top:.5rem;padding-bottom:.5rem;margin-bottom:0;font-size:1rem}.form-control-static{padding-top:.5rem;padding-bottom:.5rem;line-height:1.25;border:solid transparent;border-width:1px 0}.form-control-static.form-control-lg,.form-control-static.form-control-sm,.input-group-lg>.form-control-static.form-control,.input-group-lg>.form-control-static.input-group-addon,.input-group-lg>.input-group-btn>.form-control-static.btn,.input-group-sm>.form-control-static.form-control,.input-group-sm>.form-control-static.input-group-addon,.input-group-sm>.input-group-btn>.form-control-static.btn{padding-right:0;padding-left:0}.form-control-sm,.input-group-sm>.form-control,.input-group-sm>.input-group-addon,.input-group-sm>.input-group-btn>.btn{padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.input-group-sm>.input-group-btn>select.btn:not([size]):not([multiple]),.input-group-sm>select.form-control:not([size]):not([multiple]),.input-group-sm>select.input-group-addon:not([size]):not([multiple]),select.form-control-sm:not([size]):not([multiple]){height:1.8125rem}.form-control-lg,.input-group-lg>.form-control,.input-group-lg>.input-group-addon,.input-group-lg>.input-group-btn>.btn{padding:.75rem 1.5rem;font-size:1.25rem;border-radius:.3rem}.input-group-lg>.input-group-btn>select.btn:not([size]):not([multiple]),.input-group-lg>select.form-control:not([size]):not([multiple]),.input-group-lg>select.input-group-addon:not([size]):not([multiple]),select.form-control-lg:not([size]):not([multiple]){height:3.166667rem}.form-group{margin-bottom:1rem}.form-text{display:block;margin-top:.25rem}.form-check{position:relative;display:block;margin-bottom:.75rem}.form-check+.form-check{margin-top:-.25rem}.form-check.disabled .form-check-label{color:#818a91;cursor:not-allowed}.form-check-label{padding-left:1.25rem;margin-bottom:0;cursor:pointer}.form-check-input{position:absolute;margin-top:.25rem;margin-left:-1.25rem}.form-check-input:only-child{position:static}.form-check-inline{position:relative;display:inline-block;padding-left:1.25rem;margin-bottom:0;vertical-align:middle;cursor:pointer}.form-check-inline+.form-check-inline{margin-left:.75rem}.form-check-inline.disabled{color:#818a91;cursor:not-allowed}.form-control-feedback{margin-top:.25rem}.form-control-danger,.form-control-success,.form-control-warning{padding-right:2.25rem;background-repeat:no-repeat;background-position:center right .625rem;-webkit-background-size:1.25rem 1.25rem;background-size:1.25rem 1.25rem}.has-success .custom-control,.has-success .form-check-inline,.has-success .form-check-label,.has-success .form-control-feedback,.has-success .form-control-label{color:#5cb85c}.has-success .form-control{border-color:#5cb85c}.has-success .form-control:focus{-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,.075),0 0 6px #a3d7a3;box-shadow:inset 0 1px 1px rgba(0,0,0,.075),0 0 6px #a3d7a3}.has-success .input-group-addon{color:#5cb85c;border-color:#5cb85c;background-color:#eaf6ea}.has-success .form-control-success{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3E%3Cpath fill='#5cb85c' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3E%3C/svg%3E")}.has-warning .custom-control,.has-warning .form-check-inline,.has-warning .form-check-label,.has-warning .form-control-feedback,.has-warning .form-control-label{color:#f0ad4e}.has-warning .form-control{border-color:#f0ad4e}.has-warning .form-control:focus{-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,.075),0 0 6px #f8d9ac;box-shadow:inset 0 1px 1px rgba(0,0,0,.075),0 0 6px #f8d9ac}.has-warning .input-group-addon{color:#f0ad4e;border-color:#f0ad4e;background-color:#fff}.has-warning .form-control-warning{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3E%3Cpath fill='#f0ad4e' d='M4.4 5.324h-.8v-2.46h.8zm0 1.42h-.8V5.89h.8zM3.76.63L.04 7.075c-.115.2.016.425.26.426h7.397c.242 0 .372-.226.258-.426C6.726 4.924 5.47 2.79 4.253.63c-.113-.174-.39-.174-.494 0z'/%3E%3C/svg%3E")}.has-danger .custom-control,.has-danger .form-check-inline,.has-danger .form-check-label,.has-danger .form-control-feedback,.has-danger .form-control-label{color:#d9534f}.has-danger .form-control{border-color:#d9534f}.has-danger .form-control:focus{-webkit-box-shadow:inset 0 1px 1px rgba(0,0,0,.075),0 0 6px #eba5a3;box-shadow:inset 0 1px 1px rgba(0,0,0,.075),0 0 6px #eba5a3}.has-danger .input-group-addon{color:#d9534f;border-color:#d9534f;background-color:#fdf7f7}.has-danger .form-control-danger{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='#d9534f' viewBox='-2 -2 7 7'%3E%3Cpath stroke='%23d9534f' d='M0 0l3 3m0-3L0 3'/%3E%3Ccircle r='.5'/%3E%3Ccircle cx='3' r='.5'/%3E%3Ccircle cy='3' r='.5'/%3E%3Ccircle cx='3' cy='3' r='.5'/%3E%3C/svg%3E")}@media (min-width:576px){.form-inline .form-group{display:inline-block;margin-bottom:0;vertical-align:middle}.form-inline .form-control{display:inline-block;width:auto;vertical-align:middle}.form-inline .form-control-static{display:inline-block}.form-inline .input-group{display:inline-table;width:auto;vertical-align:middle}.form-inline .input-group .form-control,.form-inline .input-group .input-group-addon,.form-inline .input-group .input-group-btn{width:auto}.form-inline .input-group>.form-control{width:100%}.form-inline .form-control-label{margin-bottom:0;vertical-align:middle}.form-inline .form-check{display:inline-block;margin-top:0;margin-bottom:0;vertical-align:middle}.form-inline .form-check-label{padding-left:0}.form-inline .form-check-input{position:relative;margin-left:0}.form-inline .has-feedback .form-control-feedback{top:0}}.btn{display:inline-block;font-weight:400;line-height:1.25;text-align:center;white-space:nowrap;vertical-align:middle;cursor:pointer;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;border:1px solid transparent;padding:.5rem 1rem;font-size:1rem;border-radius:.25rem}.btn.active.focus,.btn.active:focus,.btn.focus,.btn:active.focus,.btn:active:focus,.btn:focus{outline:5px auto -webkit-focus-ring-color;outline-offset:-2px}.btn:focus,.btn:hover{text-decoration:none}.btn.focus{text-decoration:none}.btn.active,.btn:active{background-image:none;outline:0}.btn.disabled,.btn:disabled{cursor:not-allowed;opacity:.65}a.btn.disabled,fieldset[disabled] a.btn{pointer-events:none}.btn-primary{color:#fff;background-color:#0275d8;border-color:#0275d8}.btn-primary:hover{color:#fff;background-color:#025aa5;border-color:#01549b}.btn-primary.focus,.btn-primary:focus{color:#fff;background-color:#025aa5;border-color:#01549b}.btn-primary.active,.btn-primary:active,.open>.btn-primary.dropdown-toggle{color:#fff;background-color:#025aa5;border-color:#01549b;background-image:none}.btn-primary.active.focus,.btn-primary.active:focus,.btn-primary.active:hover,.btn-primary:active.focus,.btn-primary:active:focus,.btn-primary:active:hover,.open>.btn-primary.dropdown-toggle.focus,.open>.btn-primary.dropdown-toggle:focus,.open>.btn-primary.dropdown-toggle:hover{color:#fff;background-color:#014682;border-color:#01315a}.btn-primary.disabled.focus,.btn-primary.disabled:focus,.btn-primary:disabled.focus,.btn-primary:disabled:focus{background-color:#0275d8;border-color:#0275d8}.btn-primary.disabled:hover,.btn-primary:disabled:hover{background-color:#0275d8;border-color:#0275d8}.btn-secondary{color:#373a3c;background-color:#fff;border-color:#ccc}.btn-secondary:hover{color:#373a3c;background-color:#e6e6e6;border-color:#adadad}.btn-secondary.focus,.btn-secondary:focus{color:#373a3c;background-color:#e6e6e6;border-color:#adadad}.btn-secondary.active,.btn-secondary:active,.open>.btn-secondary.dropdown-toggle{color:#373a3c;background-color:#e6e6e6;border-color:#adadad;background-image:none}.btn-secondary.active.focus,.btn-secondary.active:focus,.btn-secondary.active:hover,.btn-secondary:active.focus,.btn-secondary:active:focus,.btn-secondary:active:hover,.open>.btn-secondary.dropdown-toggle.focus,.open>.btn-secondary.dropdown-toggle:focus,.open>.btn-secondary.dropdown-toggle:hover{color:#373a3c;background-color:#d4d4d4;border-color:#8c8c8c}.btn-secondary.disabled.focus,.btn-secondary.disabled:focus,.btn-secondary:disabled.focus,.btn-secondary:disabled:focus{background-color:#fff;border-color:#ccc}.btn-secondary.disabled:hover,.btn-secondary:disabled:hover{background-color:#fff;border-color:#ccc}.btn-info{color:#fff;background-color:#5bc0de;border-color:#5bc0de}.btn-info:hover{color:#fff;background-color:#31b0d5;border-color:#2aabd2}.btn-info.focus,.btn-info:focus{color:#fff;background-color:#31b0d5;border-color:#2aabd2}.btn-info.active,.btn-info:active,.open>.btn-info.dropdown-toggle{color:#fff;background-color:#31b0d5;border-color:#2aabd2;background-image:none}.btn-info.active.focus,.btn-info.active:focus,.btn-info.active:hover,.btn-info:active.focus,.btn-info:active:focus,.btn-info:active:hover,.open>.btn-info.dropdown-toggle.focus,.open>.btn-info.dropdown-toggle:focus,.open>.btn-info.dropdown-toggle:hover{color:#fff;background-color:#269abc;border-color:#1f7e9a}.btn-info.disabled.focus,.btn-info.disabled:focus,.btn-info:disabled.focus,.btn-info:disabled:focus{background-color:#5bc0de;border-color:#5bc0de}.btn-info.disabled:hover,.btn-info:disabled:hover{background-color:#5bc0de;border-color:#5bc0de}.btn-success{color:#fff;background-color:#5cb85c;border-color:#5cb85c}.btn-success:hover{color:#fff;background-color:#449d44;border-color:#419641}.btn-success.focus,.btn-success:focus{color:#fff;background-color:#449d44;border-color:#419641}.btn-success.active,.btn-success:active,.open>.btn-success.dropdown-toggle{color:#fff;background-color:#449d44;border-color:#419641;background-image:none}.btn-success.active.focus,.btn-success.active:focus,.btn-success.active:hover,.btn-success:active.focus,.btn-success:active:focus,.btn-success:active:hover,.open>.btn-success.dropdown-toggle.focus,.open>.btn-success.dropdown-toggle:focus,.open>.btn-success.dropdown-toggle:hover{color:#fff;background-color:#398439;border-color:#2d672d}.btn-success.disabled.focus,.btn-success.disabled:focus,.btn-success:disabled.focus,.btn-success:disabled:focus{background-color:#5cb85c;border-color:#5cb85c}.btn-success.disabled:hover,.btn-success:disabled:hover{background-color:#5cb85c;border-color:#5cb85c}.btn-warning{color:#fff;background-color:#f0ad4e;border-color:#f0ad4e}.btn-warning:hover{color:#fff;background-color:#ec971f;border-color:#eb9316}.btn-warning.focus,.btn-warning:focus{color:#fff;background-color:#ec971f;border-color:#eb9316}.btn-warning.active,.btn-warning:active,.open>.btn-warning.dropdown-toggle{color:#fff;background-color:#ec971f;border-color:#eb9316;background-image:none}.btn-warning.active.focus,.btn-warning.active:focus,.btn-warning.active:hover,.btn-warning:active.focus,.btn-warning:active:focus,.btn-warning:active:hover,.open>.btn-warning.dropdown-toggle.focus,.open>.btn-warning.dropdown-toggle:focus,.open>.btn-warning.dropdown-toggle:hover{color:#fff;background-color:#d58512;border-color:#b06d0f}.btn-warning.disabled.focus,.btn-warning.disabled:focus,.btn-warning:disabled.focus,.btn-warning:disabled:focus{background-color:#f0ad4e;border-color:#f0ad4e}.btn-warning.disabled:hover,.btn-warning:disabled:hover{background-color:#f0ad4e;border-color:#f0ad4e}.btn-danger{color:#fff;background-color:#d9534f;border-color:#d9534f}.btn-danger:hover{color:#fff;background-color:#c9302c;border-color:#c12e2a}.btn-danger.focus,.btn-danger:focus{color:#fff;background-color:#c9302c;border-color:#c12e2a}.btn-danger.active,.btn-danger:active,.open>.btn-danger.dropdown-toggle{color:#fff;background-color:#c9302c;border-color:#c12e2a;background-image:none}.btn-danger.active.focus,.btn-danger.active:focus,.btn-danger.active:hover,.btn-danger:active.focus,.btn-danger:active:focus,.btn-danger:active:hover,.open>.btn-danger.dropdown-toggle.focus,.open>.btn-danger.dropdown-toggle:focus,.open>.btn-danger.dropdown-toggle:hover{color:#fff;background-color:#ac2925;border-color:#8b211e}.btn-danger.disabled.focus,.btn-danger.disabled:focus,.btn-danger:disabled.focus,.btn-danger:disabled:focus{background-color:#d9534f;border-color:#d9534f}.btn-danger.disabled:hover,.btn-danger:disabled:hover{background-color:#d9534f;border-color:#d9534f}.btn-outline-primary{color:#0275d8;background-image:none;background-color:transparent;border-color:#0275d8}.btn-outline-primary:hover{color:#fff;background-color:#0275d8;border-color:#0275d8}.btn-outline-primary.focus,.btn-outline-primary:focus{color:#fff;background-color:#0275d8;border-color:#0275d8}.btn-outline-primary.active,.btn-outline-primary:active,.open>.btn-outline-primary.dropdown-toggle{color:#fff;background-color:#0275d8;border-color:#0275d8}.btn-outline-primary.active.focus,.btn-outline-primary.active:focus,.btn-outline-primary.active:hover,.btn-outline-primary:active.focus,.btn-outline-primary:active:focus,.btn-outline-primary:active:hover,.open>.btn-outline-primary.dropdown-toggle.focus,.open>.btn-outline-primary.dropdown-toggle:focus,.open>.btn-outline-primary.dropdown-toggle:hover{color:#fff;background-color:#014682;border-color:#01315a}.btn-outline-primary.disabled.focus,.btn-outline-primary.disabled:focus,.btn-outline-primary:disabled.focus,.btn-outline-primary:disabled:focus{border-color:#43a7fd}.btn-outline-primary.disabled:hover,.btn-outline-primary:disabled:hover{border-color:#43a7fd}.btn-outline-secondary{color:#ccc;background-image:none;background-color:transparent;border-color:#ccc}.btn-outline-secondary:hover{color:#fff;background-color:#ccc;border-color:#ccc}.btn-outline-secondary.focus,.btn-outline-secondary:focus{color:#fff;background-color:#ccc;border-color:#ccc}.btn-outline-secondary.active,.btn-outline-secondary:active,.open>.btn-outline-secondary.dropdown-toggle{color:#fff;background-color:#ccc;border-color:#ccc}.btn-outline-secondary.active.focus,.btn-outline-secondary.active:focus,.btn-outline-secondary.active:hover,.btn-outline-secondary:active.focus,.btn-outline-secondary:active:focus,.btn-outline-secondary:active:hover,.open>.btn-outline-secondary.dropdown-toggle.focus,.open>.btn-outline-secondary.dropdown-toggle:focus,.open>.btn-outline-secondary.dropdown-toggle:hover{color:#fff;background-color:#a1a1a1;border-color:#8c8c8c}.btn-outline-secondary.disabled.focus,.btn-outline-secondary.disabled:focus,.btn-outline-secondary:disabled.focus,.btn-outline-secondary:disabled:focus{border-color:#fff}.btn-outline-secondary.disabled:hover,.btn-outline-secondary:disabled:hover{border-color:#fff}.btn-outline-info{color:#5bc0de;background-image:none;background-color:transparent;border-color:#5bc0de}.btn-outline-info:hover{color:#fff;background-color:#5bc0de;border-color:#5bc0de}.btn-outline-info.focus,.btn-outline-info:focus{color:#fff;background-color:#5bc0de;border-color:#5bc0de}.btn-outline-info.active,.btn-outline-info:active,.open>.btn-outline-info.dropdown-toggle{color:#fff;background-color:#5bc0de;border-color:#5bc0de}.btn-outline-info.active.focus,.btn-outline-info.active:focus,.btn-outline-info.active:hover,.btn-outline-info:active.focus,.btn-outline-info:active:focus,.btn-outline-info:active:hover,.open>.btn-outline-info.dropdown-toggle.focus,.open>.btn-outline-info.dropdown-toggle:focus,.open>.btn-outline-info.dropdown-toggle:hover{color:#fff;background-color:#269abc;border-color:#1f7e9a}.btn-outline-info.disabled.focus,.btn-outline-info.disabled:focus,.btn-outline-info:disabled.focus,.btn-outline-info:disabled:focus{border-color:#b0e1ef}.btn-outline-info.disabled:hover,.btn-outline-info:disabled:hover{border-color:#b0e1ef}.btn-outline-success{color:#5cb85c;background-image:none;background-color:transparent;border-color:#5cb85c}.btn-outline-success:hover{color:#fff;background-color:#5cb85c;border-color:#5cb85c}.btn-outline-success.focus,.btn-outline-success:focus{color:#fff;background-color:#5cb85c;border-color:#5cb85c}.btn-outline-success.active,.btn-outline-success:active,.open>.btn-outline-success.dropdown-toggle{color:#fff;background-color:#5cb85c;border-color:#5cb85c}.btn-outline-success.active.focus,.btn-outline-success.active:focus,.btn-outline-success.active:hover,.btn-outline-success:active.focus,.btn-outline-success:active:focus,.btn-outline-success:active:hover,.open>.btn-outline-success.dropdown-toggle.focus,.open>.btn-outline-success.dropdown-toggle:focus,.open>.btn-outline-success.dropdown-toggle:hover{color:#fff;background-color:#398439;border-color:#2d672d}.btn-outline-success.disabled.focus,.btn-outline-success.disabled:focus,.btn-outline-success:disabled.focus,.btn-outline-success:disabled:focus{border-color:#a3d7a3}.btn-outline-success.disabled:hover,.btn-outline-success:disabled:hover{border-color:#a3d7a3}.btn-outline-warning{color:#f0ad4e;background-image:none;background-color:transparent;border-color:#f0ad4e}.btn-outline-warning:hover{color:#fff;background-color:#f0ad4e;border-color:#f0ad4e}.btn-outline-warning.focus,.btn-outline-warning:focus{color:#fff;background-color:#f0ad4e;border-color:#f0ad4e}.btn-outline-warning.active,.btn-outline-warning:active,.open>.btn-outline-warning.dropdown-toggle{color:#fff;background-color:#f0ad4e;border-color:#f0ad4e}.btn-outline-warning.active.focus,.btn-outline-warning.active:focus,.btn-outline-warning.active:hover,.btn-outline-warning:active.focus,.btn-outline-warning:active:focus,.btn-outline-warning:active:hover,.open>.btn-outline-warning.dropdown-toggle.focus,.open>.btn-outline-warning.dropdown-toggle:focus,.open>.btn-outline-warning.dropdown-toggle:hover{color:#fff;background-color:#d58512;border-color:#b06d0f}.btn-outline-warning.disabled.focus,.btn-outline-warning.disabled:focus,.btn-outline-warning:disabled.focus,.btn-outline-warning:disabled:focus{border-color:#f8d9ac}.btn-outline-warning.disabled:hover,.btn-outline-warning:disabled:hover{border-color:#f8d9ac}.btn-outline-danger{color:#d9534f;background-image:none;background-color:transparent;border-color:#d9534f}.btn-outline-danger:hover{color:#fff;background-color:#d9534f;border-color:#d9534f}.btn-outline-danger.focus,.btn-outline-danger:focus{color:#fff;background-color:#d9534f;border-color:#d9534f}.btn-outline-danger.active,.btn-outline-danger:active,.open>.btn-outline-danger.dropdown-toggle{color:#fff;background-color:#d9534f;border-color:#d9534f}.btn-outline-danger.active.focus,.btn-outline-danger.active:focus,.btn-outline-danger.active:hover,.btn-outline-danger:active.focus,.btn-outline-danger:active:focus,.btn-outline-danger:active:hover,.open>.btn-outline-danger.dropdown-toggle.focus,.open>.btn-outline-danger.dropdown-toggle:focus,.open>.btn-outline-danger.dropdown-toggle:hover{color:#fff;background-color:#ac2925;border-color:#8b211e}.btn-outline-danger.disabled.focus,.btn-outline-danger.disabled:focus,.btn-outline-danger:disabled.focus,.btn-outline-danger:disabled:focus{border-color:#eba5a3}.btn-outline-danger.disabled:hover,.btn-outline-danger:disabled:hover{border-color:#eba5a3}.btn-link{font-weight:400;color:#0275d8;border-radius:0}.btn-link,.btn-link.active,.btn-link:active,.btn-link:disabled{background-color:transparent}.btn-link,.btn-link:active,.btn-link:focus{border-color:transparent}.btn-link:hover{border-color:transparent}.btn-link:focus,.btn-link:hover{color:#014c8c;text-decoration:underline;background-color:transparent}.btn-link:disabled:focus,.btn-link:disabled:hover{color:#818a91;text-decoration:none}.btn-group-lg>.btn,.btn-lg{padding:.75rem 1.5rem;font-size:1.25rem;border-radius:.3rem}.btn-group-sm>.btn,.btn-sm{padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.btn-block{display:block;width:100%}.btn-block+.btn-block{margin-top:.5rem}input[type=button].btn-block,input[type=reset].btn-block,input[type=submit].btn-block{width:100%}.fade{opacity:0;-webkit-transition:opacity .15s linear;-o-transition:opacity .15s linear;transition:opacity .15s linear}.fade.in{opacity:1}.collapse{display:none}.collapse.in{display:block}tr.collapse.in{display:table-row}tbody.collapse.in{display:table-row-group}.collapsing{position:relative;height:0;overflow:hidden;-webkit-transition-timing-function:ease;-o-transition-timing-function:ease;transition-timing-function:ease;-webkit-transition-duration:.35s;-o-transition-duration:.35s;transition-duration:.35s;-webkit-transition-property:height;-o-transition-property:height;transition-property:height}.dropdown,.dropup{position:relative}.dropdown-toggle::after{display:inline-block;width:0;height:0;margin-left:.3em;vertical-align:middle;content:"";border-top:.3em solid;border-right:.3em solid transparent;border-left:.3em solid transparent}.dropdown-toggle:focus{outline:0}.dropup .dropdown-toggle::after{border-top:0;border-bottom:.3em solid}.dropdown-menu{position:absolute;top:100%;left:0;z-index:1000;display:none;float:left;min-width:10rem;padding:.5rem 0;margin:.125rem 0 0;font-size:1rem;color:#373a3c;text-align:left;list-style:none;background-color:#fff;-webkit-background-clip:padding-box;background-clip:padding-box;border:1px solid rgba(0,0,0,.15);border-radius:.25rem}.dropdown-divider{height:1px;margin:.5rem 0;overflow:hidden;background-color:#e5e5e5}.dropdown-item{display:block;width:100%;padding:3px 1.5rem;clear:both;font-weight:400;color:#373a3c;text-align:inherit;white-space:nowrap;background:0 0;border:0}.dropdown-item:focus,.dropdown-item:hover{color:#2b2d2f;text-decoration:none;background-color:#f5f5f5}.dropdown-item.active,.dropdown-item.active:focus,.dropdown-item.active:hover{color:#fff;text-decoration:none;background-color:#0275d8;outline:0}.dropdown-item.disabled,.dropdown-item.disabled:focus,.dropdown-item.disabled:hover{color:#818a91}.dropdown-item.disabled:focus,.dropdown-item.disabled:hover{text-decoration:none;cursor:not-allowed;background-color:transparent;background-image:none;filter:"progid:DXImageTransform.Microsoft.gradient(enabled = false)"}.open>.dropdown-menu{display:block}.open>a{outline:0}.dropdown-menu-right{right:0;left:auto}.dropdown-menu-left{right:auto;left:0}.dropdown-header{display:block;padding:.5rem 1.5rem;margin-bottom:0;font-size:.875rem;color:#818a91;white-space:nowrap}.dropdown-backdrop{position:fixed;top:0;right:0;bottom:0;left:0;z-index:990}.dropup .caret,.navbar-fixed-bottom .dropdown .caret{content:"";border-top:0;border-bottom:.3em solid}.dropup .dropdown-menu,.navbar-fixed-bottom .dropdown .dropdown-menu{top:auto;bottom:100%;margin-bottom:.125rem}.btn-group,.btn-group-vertical{position:relative;display:inline-block;vertical-align:middle}.btn-group-vertical>.btn,.btn-group>.btn{position:relative;float:left;margin-bottom:0}.btn-group-vertical>.btn.active,.btn-group-vertical>.btn:active,.btn-group-vertical>.btn:focus,.btn-group>.btn.active,.btn-group>.btn:active,.btn-group>.btn:focus{z-index:2}.btn-group-vertical>.btn:hover,.btn-group>.btn:hover{z-index:2}.btn-group .btn+.btn,.btn-group .btn+.btn-group,.btn-group .btn-group+.btn,.btn-group .btn-group+.btn-group{margin-left:-1px}.btn-toolbar{margin-left:-.5rem}.btn-toolbar::after{content:"";display:table;clear:both}.btn-toolbar .btn-group,.btn-toolbar .input-group{float:left}.btn-toolbar>.btn,.btn-toolbar>.btn-group,.btn-toolbar>.input-group{margin-left:.5rem}.btn-group>.btn:not(:first-child):not(:last-child):not(.dropdown-toggle){border-radius:0}.btn-group>.btn:first-child{margin-left:0}.btn-group>.btn:first-child:not(:last-child):not(.dropdown-toggle){border-bottom-right-radius:0;border-top-right-radius:0}.btn-group>.btn:last-child:not(:first-child),.btn-group>.dropdown-toggle:not(:first-child){border-bottom-left-radius:0;border-top-left-radius:0}.btn-group>.btn-group{float:left}.btn-group>.btn-group:not(:first-child):not(:last-child)>.btn{border-radius:0}.btn-group>.btn-group:first-child:not(:last-child)>.btn:last-child,.btn-group>.btn-group:first-child:not(:last-child)>.dropdown-toggle{border-bottom-right-radius:0;border-top-right-radius:0}.btn-group>.btn-group:last-child:not(:first-child)>.btn:first-child{border-bottom-left-radius:0;border-top-left-radius:0}.btn-group .dropdown-toggle:active,.btn-group.open .dropdown-toggle{outline:0}.btn+.dropdown-toggle-split{padding-right:.75rem;padding-left:.75rem}.btn+.dropdown-toggle-split::after{margin-left:0}.btn-group-sm>.btn+.dropdown-toggle-split,.btn-sm+.dropdown-toggle-split{padding-right:.375rem;padding-left:.375rem}.btn-group-lg>.btn+.dropdown-toggle-split,.btn-lg+.dropdown-toggle-split{padding-right:1.125rem;padding-left:1.125rem}.btn .caret{margin-left:0}.btn-group-lg>.btn .caret,.btn-lg .caret{border-width:.3em .3em 0;border-bottom-width:0}.dropup .btn-group-lg>.btn .caret,.dropup .btn-lg .caret{border-width:0 .3em .3em}.btn-group-vertical>.btn,.btn-group-vertical>.btn-group,.btn-group-vertical>.btn-group>.btn{display:block;float:none;width:100%;max-width:100%}.btn-group-vertical>.btn-group::after{content:"";display:table;clear:both}.btn-group-vertical>.btn-group>.btn{float:none}.btn-group-vertical>.btn+.btn,.btn-group-vertical>.btn+.btn-group,.btn-group-vertical>.btn-group+.btn,.btn-group-vertical>.btn-group+.btn-group{margin-top:-1px;margin-left:0}.btn-group-vertical>.btn:not(:first-child):not(:last-child){border-radius:0}.btn-group-vertical>.btn:first-child:not(:last-child){border-bottom-right-radius:0;border-bottom-left-radius:0}.btn-group-vertical>.btn:last-child:not(:first-child){border-top-right-radius:0;border-top-left-radius:0}.btn-group-vertical>.btn-group:not(:first-child):not(:last-child)>.btn{border-radius:0}.btn-group-vertical>.btn-group:first-child:not(:last-child)>.btn:last-child,.btn-group-vertical>.btn-group:first-child:not(:last-child)>.dropdown-toggle{border-bottom-right-radius:0;border-bottom-left-radius:0}.btn-group-vertical>.btn-group:last-child:not(:first-child)>.btn:first-child{border-top-right-radius:0;border-top-left-radius:0}[data-toggle=buttons]>.btn input[type=checkbox],[data-toggle=buttons]>.btn input[type=radio],[data-toggle=buttons]>.btn-group>.btn input[type=checkbox],[data-toggle=buttons]>.btn-group>.btn input[type=radio]{position:absolute;clip:rect(0,0,0,0);pointer-events:none}.input-group{position:relative;width:100%;display:table;border-collapse:separate}.input-group .form-control{position:relative;z-index:2;float:left;width:100%;margin-bottom:0}.input-group .form-control:active,.input-group .form-control:focus,.input-group .form-control:hover{z-index:3}.input-group .form-control,.input-group-addon,.input-group-btn{display:table-cell}.input-group .form-control:not(:first-child):not(:last-child),.input-group-addon:not(:first-child):not(:last-child),.input-group-btn:not(:first-child):not(:last-child){border-radius:0}.input-group-addon,.input-group-btn{width:1%;white-space:nowrap;vertical-align:middle}.input-group-addon{padding:.5rem .75rem;margin-bottom:0;font-size:1rem;font-weight:400;line-height:1.25;color:#55595c;text-align:center;background-color:#eceeef;border:1px solid rgba(0,0,0,.15);border-radius:.25rem}.input-group-addon.form-control-sm,.input-group-sm>.input-group-addon,.input-group-sm>.input-group-btn>.input-group-addon.btn{padding:.25rem .5rem;font-size:.875rem;border-radius:.2rem}.input-group-addon.form-control-lg,.input-group-lg>.input-group-addon,.input-group-lg>.input-group-btn>.input-group-addon.btn{padding:.75rem 1.5rem;font-size:1.25rem;border-radius:.3rem}.input-group-addon input[type=checkbox],.input-group-addon input[type=radio]{margin-top:0}.input-group .form-control:not(:last-child),.input-group-addon:not(:last-child),.input-group-btn:not(:first-child)>.btn-group:not(:last-child)>.btn,.input-group-btn:not(:first-child)>.btn:not(:last-child):not(.dropdown-toggle),.input-group-btn:not(:last-child)>.btn,.input-group-btn:not(:last-child)>.btn-group>.btn,.input-group-btn:not(:last-child)>.dropdown-toggle{border-bottom-right-radius:0;border-top-right-radius:0}.input-group-addon:not(:last-child){border-right:0}.input-group .form-control:not(:first-child),.input-group-addon:not(:first-child),.input-group-btn:not(:first-child)>.btn,.input-group-btn:not(:first-child)>.btn-group>.btn,.input-group-btn:not(:first-child)>.dropdown-toggle,.input-group-btn:not(:last-child)>.btn-group:not(:first-child)>.btn,.input-group-btn:not(:last-child)>.btn:not(:first-child){border-bottom-left-radius:0;border-top-left-radius:0}.form-control+.input-group-addon:not(:first-child){border-left:0}.input-group-btn{position:relative;font-size:0;white-space:nowrap}.input-group-btn>.btn{position:relative}.input-group-btn>.btn+.btn{margin-left:-1px}.input-group-btn>.btn:active,.input-group-btn>.btn:focus,.input-group-btn>.btn:hover{z-index:3}.input-group-btn:not(:last-child)>.btn,.input-group-btn:not(:last-child)>.btn-group{margin-right:-1px}.input-group-btn:not(:first-child)>.btn,.input-group-btn:not(:first-child)>.btn-group{z-index:2;margin-left:-1px}.input-group-btn:not(:first-child)>.btn-group:active,.input-group-btn:not(:first-child)>.btn-group:focus,.input-group-btn:not(:first-child)>.btn-group:hover,.input-group-btn:not(:first-child)>.btn:active,.input-group-btn:not(:first-child)>.btn:focus,.input-group-btn:not(:first-child)>.btn:hover{z-index:3}.custom-control{position:relative;display:inline-block;padding-left:1.5rem;cursor:pointer}.custom-control+.custom-control{margin-left:1rem}.custom-control-input{position:absolute;z-index:-1;opacity:0}.custom-control-input:checked~.custom-control-indicator{color:#fff;background-color:#0074d9}.custom-control-input:focus~.custom-control-indicator{-webkit-box-shadow:0 0 0 .075rem #fff,0 0 0 .2rem #0074d9;box-shadow:0 0 0 .075rem #fff,0 0 0 .2rem #0074d9}.custom-control-input:active~.custom-control-indicator{color:#fff;background-color:#84c6ff}.custom-control-input:disabled~.custom-control-indicator{cursor:not-allowed;background-color:#eee}.custom-control-input:disabled~.custom-control-description{color:#767676;cursor:not-allowed}.custom-control-indicator{position:absolute;top:.25rem;left:0;display:block;width:1rem;height:1rem;pointer-events:none;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;background-color:#ddd;background-repeat:no-repeat;background-position:center center;-webkit-background-size:50% 50%;background-size:50% 50%}.custom-checkbox .custom-control-indicator{border-radius:.25rem}.custom-checkbox .custom-control-input:checked~.custom-control-indicator{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 8 8'%3E%3Cpath fill='#fff' d='M6.564.75l-3.59 3.612-1.538-1.55L0 4.26 2.974 7.25 8 2.193z'/%3E%3C/svg%3E")}.custom-checkbox .custom-control-input:indeterminate~.custom-control-indicator{background-color:#0074d9;background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 4'%3E%3Cpath stroke='#fff' d='M0 2h4'/%3E%3C/svg%3E")}.custom-radio .custom-control-indicator{border-radius:50%}.custom-radio .custom-control-input:checked~.custom-control-indicator{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3E%3Ccircle r='3' fill='#fff'/%3E%3C/svg%3E")}.custom-controls-stacked .custom-control{float:left;clear:left}.custom-controls-stacked .custom-control+.custom-control{margin-left:0}.custom-select{display:inline-block;max-width:100%;height:calc(2.5rem - 2px);padding:.375rem 1.75rem .375rem .75rem;padding-right:.75rem\9;color:#55595c;vertical-align:middle;background:#fff url("data:image/svg+xml;charset=utf8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 4 5'%3E%3Cpath fill='#333' d='M2 0L0 2h4zm0 5L0 3h4z'/%3E%3C/svg%3E") no-repeat right .75rem center;background-image:none\9;-webkit-background-size:8px 10px;background-size:8px 10px;border:1px solid rgba(0,0,0,.15);border-radius:.25rem;-moz-appearance:none;-webkit-appearance:none}.custom-select:focus{border-color:#51a7e8;outline:0}.custom-select:focus::-ms-value{color:#55595c;background-color:#fff}.custom-select:disabled{color:#818a91;cursor:not-allowed;background-color:#eceeef}.custom-select::-ms-expand{opacity:0}.custom-select-sm{padding-top:.375rem;padding-bottom:.375rem;font-size:75%}.custom-file{position:relative;display:inline-block;max-width:100%;height:2.5rem;cursor:pointer}.custom-file-input{min-width:14rem;max-width:100%;margin:0;filter:alpha(opacity=0);opacity:0}.custom-file-control{position:absolute;top:0;right:0;left:0;z-index:5;height:2.5rem;padding:.5rem 1rem;line-height:1.5;color:#555;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none;background-color:#fff;border:1px solid #ddd;border-radius:.25rem}.custom-file-control:lang(en)::after{content:"Choose file..."}.custom-file-control::before{position:absolute;top:-1px;right:-1px;bottom:-1px;z-index:6;display:block;height:2.5rem;padding:.5rem 1rem;line-height:1.5;color:#555;background-color:#eee;border:1px solid #ddd;border-radius:0 .25rem .25rem 0}.custom-file-control:lang(en)::before{content:"Browse"}.nav{padding-left:0;margin-bottom:0;list-style:none}.nav-link{display:inline-block}.nav-link:focus,.nav-link:hover{text-decoration:none}.nav-link.disabled{color:#818a91}.nav-link.disabled,.nav-link.disabled:focus,.nav-link.disabled:hover{color:#818a91;cursor:not-allowed;background-color:transparent}.nav-inline .nav-item{display:inline-block}.nav-inline .nav-item+.nav-item,.nav-inline .nav-link+.nav-link{margin-left:1rem}.nav-tabs{border-bottom:1px solid #ddd}.nav-tabs::after{content:"";display:table;clear:both}.nav-tabs .nav-item{float:left;margin-bottom:-1px}.nav-tabs .nav-item+.nav-item{margin-left:.2rem}.nav-tabs .nav-link{display:block;padding:.5em 1em;border:1px solid transparent;border-top-right-radius:.25rem;border-top-left-radius:.25rem}.nav-tabs .nav-link:focus,.nav-tabs .nav-link:hover{border-color:#eceeef #eceeef #ddd}.nav-tabs .nav-link.disabled,.nav-tabs .nav-link.disabled:focus,.nav-tabs .nav-link.disabled:hover{color:#818a91;background-color:transparent;border-color:transparent}.nav-tabs .nav-item.open .nav-link,.nav-tabs .nav-item.open .nav-link:focus,.nav-tabs .nav-item.open .nav-link:hover,.nav-tabs .nav-link.active,.nav-tabs .nav-link.active:focus,.nav-tabs .nav-link.active:hover{color:#55595c;background-color:#fff;border-color:#ddd #ddd transparent}.nav-tabs .dropdown-menu{margin-top:-1px;border-top-right-radius:0;border-top-left-radius:0}.nav-pills::after{content:"";display:table;clear:both}.nav-pills .nav-item{float:left}.nav-pills .nav-item+.nav-item{margin-left:.2rem}.nav-pills .nav-link{display:block;padding:.5em 1em;border-radius:.25rem}.nav-pills .nav-item.open .nav-link,.nav-pills .nav-item.open .nav-link:focus,.nav-pills .nav-item.open .nav-link:hover,.nav-pills .nav-link.active,.nav-pills .nav-link.active:focus,.nav-pills .nav-link.active:hover{color:#fff;cursor:default;background-color:#0275d8}.nav-stacked .nav-item{display:block;float:none}.nav-stacked .nav-item+.nav-item{margin-top:.2rem;margin-left:0}.tab-content>.tab-pane{display:none}.tab-content>.active{display:block}.navbar{position:relative;padding:.5rem 1rem}.navbar::after{content:"";display:table;clear:both}@media (min-width:576px){.navbar{border-radius:.25rem}}.navbar-full{z-index:1000}@media (min-width:576px){.navbar-full{border-radius:0}}.navbar-fixed-bottom,.navbar-fixed-top{position:fixed;right:0;left:0;z-index:1030}@media (min-width:576px){.navbar-fixed-bottom,.navbar-fixed-top{border-radius:0}}.navbar-fixed-top{top:0}.navbar-fixed-bottom{bottom:0}.navbar-sticky-top{position:-webkit-sticky;position:sticky;top:0;z-index:1030;width:100%}@media (min-width:576px){.navbar-sticky-top{border-radius:0}}.navbar-brand{float:left;padding-top:.25rem;padding-bottom:.25rem;margin-right:1rem;font-size:1.25rem;line-height:inherit}.navbar-brand:focus,.navbar-brand:hover{text-decoration:none}.navbar-divider{float:left;width:1px;padding-top:.425rem;padding-bottom:.425rem;margin-right:1rem;margin-left:1rem;overflow:hidden}.navbar-divider::before{content:"\00a0"}.navbar-text{display:inline-block;padding-top:.425rem;padding-bottom:.425rem}.navbar-toggler{width:2.5em;height:2em;padding:.5rem .75rem;font-size:1.25rem;line-height:1;background:transparent no-repeat center center;-webkit-background-size:24px 24px;background-size:24px 24px;border:1px solid transparent;border-radius:.25rem}.navbar-toggler:focus,.navbar-toggler:hover{text-decoration:none}.navbar-toggleable-xs::after{content:"";display:table;clear:both}@media (max-width:575px){.navbar-toggleable-xs .navbar-brand{display:block;float:none;margin-top:.5rem;margin-right:0}.navbar-toggleable-xs .navbar-nav{margin-top:.5rem;margin-bottom:.5rem}.navbar-toggleable-xs .navbar-nav .dropdown-menu{position:static;float:none}}@media (min-width:576px){.navbar-toggleable-xs{display:block}}.navbar-toggleable-sm::after{content:"";display:table;clear:both}@media (max-width:767px){.navbar-toggleable-sm .navbar-brand{display:block;float:none;margin-top:.5rem;margin-right:0}.navbar-toggleable-sm .navbar-nav{margin-top:.5rem;margin-bottom:.5rem}.navbar-toggleable-sm .navbar-nav .dropdown-menu{position:static;float:none}}@media (min-width:768px){.navbar-toggleable-sm{display:block}}.navbar-toggleable-md::after{content:"";display:table;clear:both}@media (max-width:991px){.navbar-toggleable-md .navbar-brand{display:block;float:none;margin-top:.5rem;margin-right:0}.navbar-toggleable-md .navbar-nav{margin-top:.5rem;margin-bottom:.5rem}.navbar-toggleable-md .navbar-nav .dropdown-menu{position:static;float:none}}@media (min-width:992px){.navbar-toggleable-md{display:block}}.navbar-toggleable-lg::after{content:"";display:table;clear:both}@media (max-width:1199px){.navbar-toggleable-lg .navbar-brand{display:block;float:none;margin-top:.5rem;margin-right:0}.navbar-toggleable-lg .navbar-nav{margin-top:.5rem;margin-bottom:.5rem}.navbar-toggleable-lg .navbar-nav .dropdown-menu{position:static;float:none}}@media (min-width:1200px){.navbar-toggleable-lg{display:block}}.navbar-toggleable-xl{display:block}.navbar-toggleable-xl::after{content:"";display:table;clear:both}.navbar-toggleable-xl .navbar-brand{display:block;float:none;margin-top:.5rem;margin-right:0}.navbar-toggleable-xl .navbar-nav{margin-top:.5rem;margin-bottom:.5rem}.navbar-toggleable-xl .navbar-nav .dropdown-menu{position:static;float:none}.navbar-nav .nav-item{float:left}.navbar-nav .nav-link{display:block;padding-top:.425rem;padding-bottom:.425rem}.navbar-nav .nav-link+.nav-link{margin-left:1rem}.navbar-nav .nav-item+.nav-item{margin-left:1rem}.navbar-light .navbar-brand,.navbar-light .navbar-toggler{color:rgba(0,0,0,.9)}.navbar-light .navbar-brand:focus,.navbar-light .navbar-brand:hover,.navbar-light .navbar-toggler:focus,.navbar-light .navbar-toggler:hover{color:rgba(0,0,0,.9)}.navbar-light .navbar-nav .nav-link{color:rgba(0,0,0,.5)}.navbar-light .navbar-nav .nav-link:focus,.navbar-light .navbar-nav .nav-link:hover{color:rgba(0,0,0,.7)}.navbar-light .navbar-nav .active>.nav-link,.navbar-light .navbar-nav .active>.nav-link:focus,.navbar-light .navbar-nav .active>.nav-link:hover,.navbar-light .navbar-nav .nav-link.active,.navbar-light .navbar-nav .nav-link.active:focus,.navbar-light .navbar-nav .nav-link.active:hover,.navbar-light .navbar-nav .nav-link.open,.navbar-light .navbar-nav .nav-link.open:focus,.navbar-light .navbar-nav .nav-link.open:hover,.navbar-light .navbar-nav .open>.nav-link,.navbar-light .navbar-nav .open>.nav-link:focus,.navbar-light .navbar-nav .open>.nav-link:hover{color:rgba(0,0,0,.9)}.navbar-light .navbar-toggler{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 32 32' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(0, 0, 0, 0.5)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 8h24M4 16h24M4 24h24'/%3E%3C/svg%3E");border-color:rgba(0,0,0,.1)}.navbar-light .navbar-divider{background-color:rgba(0,0,0,.075)}.navbar-dark .navbar-brand,.navbar-dark .navbar-toggler{color:#fff}.navbar-dark .navbar-brand:focus,.navbar-dark .navbar-brand:hover,.navbar-dark .navbar-toggler:focus,.navbar-dark .navbar-toggler:hover{color:#fff}.navbar-dark .navbar-nav .nav-link{color:rgba(255,255,255,.5)}.navbar-dark .navbar-nav .nav-link:focus,.navbar-dark .navbar-nav .nav-link:hover{color:rgba(255,255,255,.75)}.navbar-dark .navbar-nav .active>.nav-link,.navbar-dark .navbar-nav .active>.nav-link:focus,.navbar-dark .navbar-nav .active>.nav-link:hover,.navbar-dark .navbar-nav .nav-link.active,.navbar-dark .navbar-nav .nav-link.active:focus,.navbar-dark .navbar-nav .nav-link.active:hover,.navbar-dark .navbar-nav .nav-link.open,.navbar-dark .navbar-nav .nav-link.open:focus,.navbar-dark .navbar-nav .nav-link.open:hover,.navbar-dark .navbar-nav .open>.nav-link,.navbar-dark .navbar-nav .open>.nav-link:focus,.navbar-dark .navbar-nav .open>.nav-link:hover{color:#fff}.navbar-dark .navbar-toggler{background-image:url("data:image/svg+xml;charset=utf8,%3Csvg viewBox='0 0 32 32' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath stroke='rgba(255, 255, 255, 0.5)' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 8h24M4 16h24M4 24h24'/%3E%3C/svg%3E");border-color:rgba(255,255,255,.1)}.navbar-dark .navbar-divider{background-color:rgba(255,255,255,.075)}.navbar-toggleable-xs::after{content:"";display:table;clear:both}@media (max-width:575px){.navbar-toggleable-xs .navbar-nav .nav-item{float:none;margin-left:0}}@media (min-width:576px){.navbar-toggleable-xs{display:block!important}}.navbar-toggleable-sm::after{content:"";display:table;clear:both}@media (max-width:767px){.navbar-toggleable-sm .navbar-nav .nav-item{float:none;margin-left:0}}@media (min-width:768px){.navbar-toggleable-sm{display:block!important}}.navbar-toggleable-md::after{content:"";display:table;clear:both}@media (max-width:991px){.navbar-toggleable-md .navbar-nav .nav-item{float:none;margin-left:0}}@media (min-width:992px){.navbar-toggleable-md{display:block!important}}.card{position:relative;display:block;margin-bottom:.75rem;background-color:#fff;border-radius:.25rem;border:1px solid rgba(0,0,0,.125)}.card-block{padding:1.25rem}.card-block::after{content:"";display:table;clear:both}.card-title{margin-bottom:.75rem}.card-subtitle{margin-top:-.375rem;margin-bottom:0}.card-text:last-child{margin-bottom:0}.card-link:hover{text-decoration:none}.card-link+.card-link{margin-left:1.25rem}.card>.list-group:first-child .list-group-item:first-child{border-top-right-radius:.25rem;border-top-left-radius:.25rem}.card>.list-group:last-child .list-group-item:last-child{border-bottom-right-radius:.25rem;border-bottom-left-radius:.25rem}.card-header{padding:.75rem 1.25rem;margin-bottom:0;background-color:#f5f5f5;border-bottom:1px solid rgba(0,0,0,.125)}.card-header::after{content:"";display:table;clear:both}.card-header:first-child{border-radius:calc(.25rem - 1px) calc(.25rem - 1px) 0 0}.card-footer{padding:.75rem 1.25rem;background-color:#f5f5f5;border-top:1px solid rgba(0,0,0,.125)}.card-footer::after{content:"";display:table;clear:both}.card-footer:last-child{border-radius:0 0 calc(.25rem - 1px) calc(.25rem - 1px)}.card-header-tabs{margin-right:-.625rem;margin-bottom:-.75rem;margin-left:-.625rem;border-bottom:0}.card-header-pills{margin-right:-.625rem;margin-left:-.625rem}.card-primary{background-color:#0275d8;border-color:#0275d8}.card-primary .card-footer,.card-primary .card-header{background-color:transparent}.card-success{background-color:#5cb85c;border-color:#5cb85c}.card-success .card-footer,.card-success .card-header{background-color:transparent}.card-info{background-color:#5bc0de;border-color:#5bc0de}.card-info .card-footer,.card-info .card-header{background-color:transparent}.card-warning{background-color:#f0ad4e;border-color:#f0ad4e}.card-warning .card-footer,.card-warning .card-header{background-color:transparent}.card-danger{background-color:#d9534f;border-color:#d9534f}.card-danger .card-footer,.card-danger .card-header{background-color:transparent}.card-outline-primary{background-color:transparent;border-color:#0275d8}.card-outline-secondary{background-color:transparent;border-color:#ccc}.card-outline-info{background-color:transparent;border-color:#5bc0de}.card-outline-success{background-color:transparent;border-color:#5cb85c}.card-outline-warning{background-color:transparent;border-color:#f0ad4e}.card-outline-danger{background-color:transparent;border-color:#d9534f}.card-inverse .card-footer,.card-inverse .card-header{border-color:rgba(255,255,255,.2)}.card-inverse .card-blockquote,.card-inverse .card-footer,.card-inverse .card-header,.card-inverse .card-title{color:#fff}.card-inverse .card-blockquote .blockquote-footer,.card-inverse .card-link,.card-inverse .card-subtitle,.card-inverse .card-text{color:rgba(255,255,255,.65)}.card-inverse .card-link:focus,.card-inverse .card-link:hover{color:#fff}.card-blockquote{padding:0;margin-bottom:0;border-left:0}.card-img{border-radius:calc(.25rem - 1px)}.card-img-overlay{position:absolute;top:0;right:0;bottom:0;left:0;padding:1.25rem}.card-img-top{border-top-right-radius:calc(.25rem - 1px);border-top-left-radius:calc(.25rem - 1px)}.card-img-bottom{border-bottom-right-radius:calc(.25rem - 1px);border-bottom-left-radius:calc(.25rem - 1px)}@media (min-width:576px){.card-deck{display:table;width:100%;margin-bottom:.75rem;table-layout:fixed;border-spacing:1.25rem 0}.card-deck .card{display:table-cell;margin-bottom:0;vertical-align:top}.card-deck-wrapper{margin-right:-1.25rem;margin-left:-1.25rem}}@media (min-width:576px){.card-group{display:table;width:100%;table-layout:fixed}.card-group .card{display:table-cell;vertical-align:top}.card-group .card+.card{margin-left:0;border-left:0}.card-group .card:first-child{border-bottom-right-radius:0;border-top-right-radius:0}.card-group .card:first-child .card-img-top{border-top-right-radius:0}.card-group .card:first-child .card-img-bottom{border-bottom-right-radius:0}.card-group .card:last-child{border-bottom-left-radius:0;border-top-left-radius:0}.card-group .card:last-child .card-img-top{border-top-left-radius:0}.card-group .card:last-child .card-img-bottom{border-bottom-left-radius:0}.card-group .card:not(:first-child):not(:last-child){border-radius:0}.card-group .card:not(:first-child):not(:last-child) .card-img-bottom,.card-group .card:not(:first-child):not(:last-child) .card-img-top{border-radius:0}}@media (min-width:576px){.card-columns{-webkit-column-count:3;-moz-column-count:3;column-count:3;-webkit-column-gap:1.25rem;-moz-column-gap:1.25rem;column-gap:1.25rem}.card-columns .card{display:inline-block;width:100%}}.breadcrumb{padding:.75rem 1rem;margin-bottom:1rem;list-style:none;background-color:#eceeef;border-radius:.25rem}.breadcrumb::after{content:"";display:table;clear:both}.breadcrumb-item{float:left}.breadcrumb-item+.breadcrumb-item::before{display:inline-block;padding-right:.5rem;padding-left:.5rem;color:#818a91;content:"/"}.breadcrumb-item+.breadcrumb-item:hover::before{text-decoration:underline}.breadcrumb-item+.breadcrumb-item:hover::before{text-decoration:none}.breadcrumb-item.active{color:#818a91}.pagination{display:inline-block;padding-left:0;margin-top:1rem;margin-bottom:1rem;border-radius:.25rem}.page-item{display:inline}.page-item:first-child .page-link{margin-left:0;border-bottom-left-radius:.25rem;border-top-left-radius:.25rem}.page-item:last-child .page-link{border-bottom-right-radius:.25rem;border-top-right-radius:.25rem}.page-item.active .page-link,.page-item.active .page-link:focus,.page-item.active .page-link:hover{z-index:2;color:#fff;cursor:default;background-color:#0275d8;border-color:#0275d8}.page-item.disabled .page-link,.page-item.disabled .page-link:focus,.page-item.disabled .page-link:hover{color:#818a91;pointer-events:none;cursor:not-allowed;background-color:#fff;border-color:#ddd}.page-link{position:relative;float:left;padding:.5rem .75rem;margin-left:-1px;color:#0275d8;text-decoration:none;background-color:#fff;border:1px solid #ddd}.page-link:focus,.page-link:hover{color:#014c8c;background-color:#eceeef;border-color:#ddd}.pagination-lg .page-link{padding:.75rem 1.5rem;font-size:1.25rem}.pagination-lg .page-item:first-child .page-link{border-bottom-left-radius:.3rem;border-top-left-radius:.3rem}.pagination-lg .page-item:last-child .page-link{border-bottom-right-radius:.3rem;border-top-right-radius:.3rem}.pagination-sm .page-link{padding:.275rem .75rem;font-size:.875rem}.pagination-sm .page-item:first-child .page-link{border-bottom-left-radius:.2rem;border-top-left-radius:.2rem}.pagination-sm .page-item:last-child .page-link{border-bottom-right-radius:.2rem;border-top-right-radius:.2rem}.tag{display:inline-block;padding:.25em .4em;font-size:75%;font-weight:700;line-height:1;color:#fff;text-align:center;white-space:nowrap;vertical-align:baseline;border-radius:.25rem}.tag:empty{display:none}.btn .tag{position:relative;top:-1px}a.tag:focus,a.tag:hover{color:#fff;text-decoration:none;cursor:pointer}.tag-pill{padding-right:.6em;padding-left:.6em;border-radius:10rem}.tag-default{background-color:#818a91}.tag-default[href]:focus,.tag-default[href]:hover{background-color:#687077}.tag-primary{background-color:#0275d8}.tag-primary[href]:focus,.tag-primary[href]:hover{background-color:#025aa5}.tag-success{background-color:#5cb85c}.tag-success[href]:focus,.tag-success[href]:hover{background-color:#449d44}.tag-info{background-color:#5bc0de}.tag-info[href]:focus,.tag-info[href]:hover{background-color:#31b0d5}.tag-warning{background-color:#f0ad4e}.tag-warning[href]:focus,.tag-warning[href]:hover{background-color:#ec971f}.tag-danger{background-color:#d9534f}.tag-danger[href]:focus,.tag-danger[href]:hover{background-color:#c9302c}.jumbotron{padding:2rem 1rem;margin-bottom:2rem;background-color:#eceeef;border-radius:.3rem}@media (min-width:576px){.jumbotron{padding:4rem 2rem}}.jumbotron-hr{border-top-color:#d0d5d8}.jumbotron-fluid{padding-right:0;padding-left:0;border-radius:0}.alert{padding:.75rem 1.25rem;margin-bottom:1rem;border:1px solid transparent;border-radius:.25rem}.alert-heading{color:inherit}.alert-link{font-weight:700}.alert-dismissible{padding-right:2.5rem}.alert-dismissible .close{position:relative;top:-.125rem;right:-1.25rem;color:inherit}.alert-success{background-color:#dff0d8;border-color:#d0e9c6;color:#3c763d}.alert-success hr{border-top-color:#c1e2b3}.alert-success .alert-link{color:#2b542c}.alert-info{background-color:#d9edf7;border-color:#bcdff1;color:#31708f}.alert-info hr{border-top-color:#a6d5ec}.alert-info .alert-link{color:#245269}.alert-warning{background-color:#fcf8e3;border-color:#faf2cc;color:#8a6d3b}.alert-warning hr{border-top-color:#f7ecb5}.alert-warning .alert-link{color:#66512c}.alert-danger{background-color:#f2dede;border-color:#ebcccc;color:#a94442}.alert-danger hr{border-top-color:#e4b9b9}.alert-danger .alert-link{color:#843534}@-webkit-keyframes progress-bar-stripes{from{background-position:1rem 0}to{background-position:0 0}}@-o-keyframes progress-bar-stripes{from{background-position:1rem 0}to{background-position:0 0}}@keyframes progress-bar-stripes{from{background-position:1rem 0}to{background-position:0 0}}.progress{display:block;width:100%;height:1rem;margin-bottom:1rem}.progress[value]{background-color:#eee;border:0;-webkit-appearance:none;-moz-appearance:none;appearance:none;border-radius:.25rem}.progress[value]::-ms-fill{background-color:#0074d9;border:0}.progress[value]::-moz-progress-bar{background-color:#0074d9;border-bottom-left-radius:.25rem;border-top-left-radius:.25rem}.progress[value]::-webkit-progress-value{background-color:#0074d9;border-bottom-left-radius:.25rem;border-top-left-radius:.25rem}.progress[value="100"]::-moz-progress-bar{border-bottom-right-radius:.25rem;border-top-right-radius:.25rem}.progress[value="100"]::-webkit-progress-value{border-bottom-right-radius:.25rem;border-top-right-radius:.25rem}.progress[value]::-webkit-progress-bar{background-color:#eee;border-radius:.25rem}.progress[value],base::-moz-progress-bar{background-color:#eee;border-radius:.25rem}@media screen and (min-width:0\0){.progress{background-color:#eee;border-radius:.25rem}.progress-bar{display:inline-block;height:1rem;text-indent:-999rem;background-color:#0074d9;border-bottom-left-radius:.25rem;border-top-left-radius:.25rem}.progress[width="100%"]{border-bottom-right-radius:.25rem;border-top-right-radius:.25rem}}.progress-striped[value]::-webkit-progress-value{background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);background-image:linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);-webkit-background-size:1rem 1rem;background-size:1rem 1rem}.progress-striped[value]::-moz-progress-bar{background-image:linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);background-size:1rem 1rem}.progress-striped[value]::-ms-fill{background-image:linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);background-size:1rem 1rem}@media screen and (min-width:0\0){.progress-bar-striped{background-image:-webkit-linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);background-image:-o-linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);background-image:linear-gradient(45deg,rgba(255,255,255,.15) 25%,transparent 25%,transparent 50%,rgba(255,255,255,.15) 50%,rgba(255,255,255,.15) 75%,transparent 75%,transparent);-webkit-background-size:1rem 1rem;background-size:1rem 1rem}}.progress-animated[value]::-webkit-progress-value{-webkit-animation:progress-bar-stripes 2s linear infinite;animation:progress-bar-stripes 2s linear infinite}.progress-animated[value]::-moz-progress-bar{animation:progress-bar-stripes 2s linear infinite}@media screen and (min-width:0\0){.progress-animated .progress-bar-striped{-webkit-animation:progress-bar-stripes 2s linear infinite;-o-animation:progress-bar-stripes 2s linear infinite;animation:progress-bar-stripes 2s linear infinite}}.progress-success[value]::-webkit-progress-value{background-color:#5cb85c}.progress-success[value]::-moz-progress-bar{background-color:#5cb85c}.progress-success[value]::-ms-fill{background-color:#5cb85c}@media screen and (min-width:0\0){.progress-success .progress-bar{background-color:#5cb85c}}.progress-info[value]::-webkit-progress-value{background-color:#5bc0de}.progress-info[value]::-moz-progress-bar{background-color:#5bc0de}.progress-info[value]::-ms-fill{background-color:#5bc0de}@media screen and (min-width:0\0){.progress-info .progress-bar{background-color:#5bc0de}}.progress-warning[value]::-webkit-progress-value{background-color:#f0ad4e}.progress-warning[value]::-moz-progress-bar{background-color:#f0ad4e}.progress-warning[value]::-ms-fill{background-color:#f0ad4e}@media screen and (min-width:0\0){.progress-warning .progress-bar{background-color:#f0ad4e}}.progress-danger[value]::-webkit-progress-value{background-color:#d9534f}.progress-danger[value]::-moz-progress-bar{background-color:#d9534f}.progress-danger[value]::-ms-fill{background-color:#d9534f}@media screen and (min-width:0\0){.progress-danger .progress-bar{background-color:#d9534f}}.media,.media-body{overflow:hidden}.media-body{width:10000px}.media-body,.media-left,.media-right{display:table-cell;vertical-align:top}.media-middle{vertical-align:middle}.media-bottom{vertical-align:bottom}.media-object{display:block}.media-object.img-thumbnail{max-width:none}.media-right{padding-left:10px}.media-left{padding-right:10px}.media-heading{margin-top:0;margin-bottom:5px}.media-list{padding-left:0;list-style:none}.list-group{padding-left:0;margin-bottom:0}.list-group-item{position:relative;display:block;padding:.75rem 1.25rem;margin-bottom:-1px;background-color:#fff;border:1px solid #ddd}.list-group-item:first-child{border-top-right-radius:.25rem;border-top-left-radius:.25rem}.list-group-item:last-child{margin-bottom:0;border-bottom-right-radius:.25rem;border-bottom-left-radius:.25rem}.list-group-item.disabled,.list-group-item.disabled:focus,.list-group-item.disabled:hover{color:#818a91;cursor:not-allowed;background-color:#eceeef}.list-group-item.disabled .list-group-item-heading,.list-group-item.disabled:focus .list-group-item-heading,.list-group-item.disabled:hover .list-group-item-heading{color:inherit}.list-group-item.disabled .list-group-item-text,.list-group-item.disabled:focus .list-group-item-text,.list-group-item.disabled:hover .list-group-item-text{color:#818a91}.list-group-item.active,.list-group-item.active:focus,.list-group-item.active:hover{z-index:2;color:#fff;text-decoration:none;background-color:#0275d8;border-color:#0275d8}.list-group-item.active .list-group-item-heading,.list-group-item.active .list-group-item-heading>.small,.list-group-item.active .list-group-item-heading>small,.list-group-item.active:focus .list-group-item-heading,.list-group-item.active:focus .list-group-item-heading>.small,.list-group-item.active:focus .list-group-item-heading>small,.list-group-item.active:hover .list-group-item-heading,.list-group-item.active:hover .list-group-item-heading>.small,.list-group-item.active:hover .list-group-item-heading>small{color:inherit}.list-group-item.active .list-group-item-text,.list-group-item.active:focus .list-group-item-text,.list-group-item.active:hover .list-group-item-text{color:#a8d6fe}.list-group-flush .list-group-item{border-right:0;border-left:0;border-radius:0}.list-group-item-action{width:100%;color:#555;text-align:inherit}.list-group-item-action .list-group-item-heading{color:#333}.list-group-item-action:focus,.list-group-item-action:hover{color:#555;text-decoration:none;background-color:#f5f5f5}.list-group-item-success{color:#3c763d;background-color:#dff0d8}a.list-group-item-success,button.list-group-item-success{color:#3c763d}a.list-group-item-success .list-group-item-heading,button.list-group-item-success .list-group-item-heading{color:inherit}a.list-group-item-success:focus,a.list-group-item-success:hover,button.list-group-item-success:focus,button.list-group-item-success:hover{color:#3c763d;background-color:#d0e9c6}a.list-group-item-success.active,a.list-group-item-success.active:focus,a.list-group-item-success.active:hover,button.list-group-item-success.active,button.list-group-item-success.active:focus,button.list-group-item-success.active:hover{color:#fff;background-color:#3c763d;border-color:#3c763d}.list-group-item-info{color:#31708f;background-color:#d9edf7}a.list-group-item-info,button.list-group-item-info{color:#31708f}a.list-group-item-info .list-group-item-heading,button.list-group-item-info .list-group-item-heading{color:inherit}a.list-group-item-info:focus,a.list-group-item-info:hover,button.list-group-item-info:focus,button.list-group-item-info:hover{color:#31708f;background-color:#c4e3f3}a.list-group-item-info.active,a.list-group-item-info.active:focus,a.list-group-item-info.active:hover,button.list-group-item-info.active,button.list-group-item-info.active:focus,button.list-group-item-info.active:hover{color:#fff;background-color:#31708f;border-color:#31708f}.list-group-item-warning{color:#8a6d3b;background-color:#fcf8e3}a.list-group-item-warning,button.list-group-item-warning{color:#8a6d3b}a.list-group-item-warning .list-group-item-heading,button.list-group-item-warning .list-group-item-heading{color:inherit}a.list-group-item-warning:focus,a.list-group-item-warning:hover,button.list-group-item-warning:focus,button.list-group-item-warning:hover{color:#8a6d3b;background-color:#faf2cc}a.list-group-item-warning.active,a.list-group-item-warning.active:focus,a.list-group-item-warning.active:hover,button.list-group-item-warning.active,button.list-group-item-warning.active:focus,button.list-group-item-warning.active:hover{color:#fff;background-color:#8a6d3b;border-color:#8a6d3b}.list-group-item-danger{color:#a94442;background-color:#f2dede}a.list-group-item-danger,button.list-group-item-danger{color:#a94442}a.list-group-item-danger .list-group-item-heading,button.list-group-item-danger .list-group-item-heading{color:inherit}a.list-group-item-danger:focus,a.list-group-item-danger:hover,button.list-group-item-danger:focus,button.list-group-item-danger:hover{color:#a94442;background-color:#ebcccc}a.list-group-item-danger.active,a.list-group-item-danger.active:focus,a.list-group-item-danger.active:hover,button.list-group-item-danger.active,button.list-group-item-danger.active:focus,button.list-group-item-danger.active:hover{color:#fff;background-color:#a94442;border-color:#a94442}.list-group-item-heading{margin-top:0;margin-bottom:5px}.list-group-item-text{margin-bottom:0;line-height:1.3}.embed-responsive{position:relative;display:block;height:0;padding:0;overflow:hidden}.embed-responsive .embed-responsive-item,.embed-responsive embed,.embed-responsive iframe,.embed-responsive object,.embed-responsive video{position:absolute;top:0;bottom:0;left:0;width:100%;height:100%;border:0}.embed-responsive-21by9{padding-bottom:42.857143%}.embed-responsive-16by9{padding-bottom:56.25%}.embed-responsive-4by3{padding-bottom:75%}.embed-responsive-1by1{padding-bottom:100%}.close{float:right;font-size:1.5rem;font-weight:700;line-height:1;color:#000;text-shadow:0 1px 0 #fff;opacity:.2}.close:focus,.close:hover{color:#000;text-decoration:none;cursor:pointer;opacity:.5}button.close{padding:0;cursor:pointer;background:0 0;border:0;-webkit-appearance:none}.modal-open{overflow:hidden}.modal{position:fixed;top:0;right:0;bottom:0;left:0;z-index:1050;display:none;overflow:hidden;outline:0}.modal.fade .modal-dialog{-webkit-transition:-webkit-transform .3s ease-out;transition:-webkit-transform .3s ease-out;-o-transition:-o-transform .3s ease-out;transition:transform .3s ease-out;transition:transform .3s ease-out,-webkit-transform .3s ease-out,-o-transform .3s ease-out;-webkit-transform:translate(0,-25%);-ms-transform:translate(0,-25%);-o-transform:translate(0,-25%);transform:translate(0,-25%)}.modal.in .modal-dialog{-webkit-transform:translate(0,0);-ms-transform:translate(0,0);-o-transform:translate(0,0);transform:translate(0,0)}.modal-open .modal{overflow-x:hidden;overflow-y:auto}.modal-dialog{position:relative;width:auto;margin:10px}.modal-content{position:relative;background-color:#fff;-webkit-background-clip:padding-box;background-clip:padding-box;border:1px solid rgba(0,0,0,.2);border-radius:.3rem;outline:0}.modal-backdrop{position:fixed;top:0;right:0;bottom:0;left:0;z-index:1040;background-color:#000}.modal-backdrop.fade{opacity:0}.modal-backdrop.in{opacity:.5}.modal-header{padding:15px;border-bottom:1px solid #e5e5e5}.modal-header::after{content:"";display:table;clear:both}.modal-header .close{margin-top:-2px}.modal-title{margin:0;line-height:1.5}.modal-body{position:relative;padding:15px}.modal-footer{padding:15px;text-align:right;border-top:1px solid #e5e5e5}.modal-footer::after{content:"";display:table;clear:both}.modal-scrollbar-measure{position:absolute;top:-9999px;width:50px;height:50px;overflow:scroll}@media (min-width:576px){.modal-dialog{max-width:600px;margin:30px auto}.modal-sm{max-width:300px}}@media (min-width:992px){.modal-lg{max-width:900px}}.tooltip{position:absolute;z-index:1070;display:block;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;font-style:normal;font-weight:400;letter-spacing:normal;line-break:auto;line-height:1.5;text-align:left;text-align:start;text-decoration:none;text-shadow:none;text-transform:none;white-space:normal;word-break:normal;word-spacing:normal;font-size:.875rem;word-wrap:break-word;opacity:0}.tooltip.in{opacity:.9}.tooltip.bs-tether-element-attached-bottom,.tooltip.tooltip-top{padding:5px 0;margin-top:-3px}.tooltip.bs-tether-element-attached-bottom .tooltip-inner::before,.tooltip.tooltip-top .tooltip-inner::before{bottom:0;left:50%;margin-left:-5px;content:"";border-width:5px 5px 0;border-top-color:#000}.tooltip.bs-tether-element-attached-left,.tooltip.tooltip-right{padding:0 5px;margin-left:3px}.tooltip.bs-tether-element-attached-left .tooltip-inner::before,.tooltip.tooltip-right .tooltip-inner::before{top:50%;left:0;margin-top:-5px;content:"";border-width:5px 5px 5px 0;border-right-color:#000}.tooltip.bs-tether-element-attached-top,.tooltip.tooltip-bottom{padding:5px 0;margin-top:3px}.tooltip.bs-tether-element-attached-top .tooltip-inner::before,.tooltip.tooltip-bottom .tooltip-inner::before{top:0;left:50%;margin-left:-5px;content:"";border-width:0 5px 5px;border-bottom-color:#000}.tooltip.bs-tether-element-attached-right,.tooltip.tooltip-left{padding:0 5px;margin-left:-3px}.tooltip.bs-tether-element-attached-right .tooltip-inner::before,.tooltip.tooltip-left .tooltip-inner::before{top:50%;right:0;margin-top:-5px;content:"";border-width:5px 0 5px 5px;border-left-color:#000}.tooltip-inner{max-width:200px;padding:3px 8px;color:#fff;text-align:center;background-color:#000;border-radius:.25rem}.tooltip-inner::before{position:absolute;width:0;height:0;border-color:transparent;border-style:solid}.popover{position:absolute;top:0;left:0;z-index:1060;display:block;max-width:276px;padding:1px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;font-style:normal;font-weight:400;letter-spacing:normal;line-break:auto;line-height:1.5;text-align:left;text-align:start;text-decoration:none;text-shadow:none;text-transform:none;white-space:normal;word-break:normal;word-spacing:normal;font-size:.875rem;word-wrap:break-word;background-color:#fff;-webkit-background-clip:padding-box;background-clip:padding-box;border:1px solid rgba(0,0,0,.2);border-radius:.3rem}.popover.bs-tether-element-attached-bottom,.popover.popover-top{margin-top:-10px}.popover.bs-tether-element-attached-bottom::after,.popover.bs-tether-element-attached-bottom::before,.popover.popover-top::after,.popover.popover-top::before{left:50%;border-bottom-width:0}.popover.bs-tether-element-attached-bottom::before,.popover.popover-top::before{bottom:-11px;margin-left:-11px;border-top-color:rgba(0,0,0,.25)}.popover.bs-tether-element-attached-bottom::after,.popover.popover-top::after{bottom:-10px;margin-left:-10px;border-top-color:#fff}.popover.bs-tether-element-attached-left,.popover.popover-right{margin-left:10px}.popover.bs-tether-element-attached-left::after,.popover.bs-tether-element-attached-left::before,.popover.popover-right::after,.popover.popover-right::before{top:50%;border-left-width:0}.popover.bs-tether-element-attached-left::before,.popover.popover-right::before{left:-11px;margin-top:-11px;border-right-color:rgba(0,0,0,.25)}.popover.bs-tether-element-attached-left::after,.popover.popover-right::after{left:-10px;margin-top:-10px;border-right-color:#fff}.popover.bs-tether-element-attached-top,.popover.popover-bottom{margin-top:10px}.popover.bs-tether-element-attached-top::after,.popover.bs-tether-element-attached-top::before,.popover.popover-bottom::after,.popover.popover-bottom::before{left:50%;border-top-width:0}.popover.bs-tether-element-attached-top::before,.popover.popover-bottom::before{top:-11px;margin-left:-11px;border-bottom-color:rgba(0,0,0,.25)}.popover.bs-tether-element-attached-top::after,.popover.popover-bottom::after{top:-10px;margin-left:-10px;border-bottom-color:#f7f7f7}.popover.bs-tether-element-attached-top .popover-title::before,.popover.popover-bottom .popover-title::before{position:absolute;top:0;left:50%;display:block;width:20px;margin-left:-10px;content:"";border-bottom:1px solid #f7f7f7}.popover.bs-tether-element-attached-right,.popover.popover-left{margin-left:-10px}.popover.bs-tether-element-attached-right::after,.popover.bs-tether-element-attached-right::before,.popover.popover-left::after,.popover.popover-left::before{top:50%;border-right-width:0}.popover.bs-tether-element-attached-right::before,.popover.popover-left::before{right:-11px;margin-top:-11px;border-left-color:rgba(0,0,0,.25)}.popover.bs-tether-element-attached-right::after,.popover.popover-left::after{right:-10px;margin-top:-10px;border-left-color:#fff}.popover-title{padding:8px 14px;margin:0;font-size:1rem;background-color:#f7f7f7;border-bottom:1px solid #ebebeb;border-radius:.2375rem .2375rem 0 0}.popover-title:empty{display:none}.popover-content{padding:9px 14px}.popover::after,.popover::before{position:absolute;display:block;width:0;height:0;border-color:transparent;border-style:solid}.popover::before{content:"";border-width:11px}.popover::after{content:"";border-width:10px}.carousel{position:relative}.carousel-inner{position:relative;width:100%;overflow:hidden}.carousel-inner>.carousel-item{position:relative;display:none;-webkit-transition:.6s ease-in-out left;-o-transition:.6s ease-in-out left;transition:.6s ease-in-out left}.carousel-inner>.carousel-item>a>img,.carousel-inner>.carousel-item>img{line-height:1}@media all and (transform-3d),(-webkit-transform-3d){.carousel-inner>.carousel-item{-webkit-transition:-webkit-transform .6s ease-in-out;transition:-webkit-transform .6s ease-in-out;-o-transition:-o-transform .6s ease-in-out;transition:transform .6s ease-in-out;transition:transform .6s ease-in-out,-webkit-transform .6s ease-in-out,-o-transform .6s ease-in-out;-webkit-backface-visibility:hidden;backface-visibility:hidden;-webkit-perspective:1000px;perspective:1000px}.carousel-inner>.carousel-item.active.right,.carousel-inner>.carousel-item.next{left:0;-webkit-transform:translate3d(100%,0,0);transform:translate3d(100%,0,0)}.carousel-inner>.carousel-item.active.left,.carousel-inner>.carousel-item.prev{left:0;-webkit-transform:translate3d(-100%,0,0);transform:translate3d(-100%,0,0)}.carousel-inner>.carousel-item.active,.carousel-inner>.carousel-item.next.left,.carousel-inner>.carousel-item.prev.right{left:0;-webkit-transform:translate3d(0,0,0);transform:translate3d(0,0,0)}}.carousel-inner>.active,.carousel-inner>.next,.carousel-inner>.prev{display:block}.carousel-inner>.active{left:0}.carousel-inner>.next,.carousel-inner>.prev{position:absolute;top:0;width:100%}.carousel-inner>.next{left:100%}.carousel-inner>.prev{left:-100%}.carousel-inner>.next.left,.carousel-inner>.prev.right{left:0}.carousel-inner>.active.left{left:-100%}.carousel-inner>.active.right{left:100%}.carousel-control{position:absolute;top:0;bottom:0;left:0;width:15%;font-size:20px;color:#fff;text-align:center;text-shadow:0 1px 2px rgba(0,0,0,.6);opacity:.5}.carousel-control.left{background-image:-webkit-gradient(linear,left top,right top,from(rgba(0,0,0,.5)),to(rgba(0,0,0,.0001)));background-image:-webkit-linear-gradient(left,rgba(0,0,0,.5) 0%,rgba(0,0,0,.0001) 100%);background-image:-o-linear-gradient(left,rgba(0,0,0,.5) 0%,rgba(0,0,0,.0001) 100%);background-image:linear-gradient(to right,rgba(0,0,0,.5) 0%,rgba(0,0,0,.0001) 100%);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#80000000', endColorstr='#00000000', GradientType=1)}.carousel-control.right{right:0;left:auto;background-image:-webkit-gradient(linear,left top,right top,from(rgba(0,0,0,.0001)),to(rgba(0,0,0,.5)));background-image:-webkit-linear-gradient(left,rgba(0,0,0,.0001) 0%,rgba(0,0,0,.5) 100%);background-image:-o-linear-gradient(left,rgba(0,0,0,.0001) 0%,rgba(0,0,0,.5) 100%);background-image:linear-gradient(to right,rgba(0,0,0,.0001) 0%,rgba(0,0,0,.5) 100%);background-repeat:repeat-x;filter:progid:DXImageTransform.Microsoft.gradient(startColorstr='#00000000', endColorstr='#80000000', GradientType=1)}.carousel-control:focus,.carousel-control:hover{color:#fff;text-decoration:none;outline:0;opacity:.9}.carousel-control .icon-next,.carousel-control .icon-prev{position:absolute;top:50%;z-index:5;display:inline-block;width:20px;height:20px;margin-top:-10px;font-family:serif;line-height:1}.carousel-control .icon-prev{left:50%;margin-left:-10px}.carousel-control .icon-next{right:50%;margin-right:-10px}.carousel-control .icon-prev::before{content:"\2039"}.carousel-control .icon-next::before{content:"\203a"}.carousel-indicators{position:absolute;bottom:10px;left:50%;z-index:15;width:60%;padding-left:0;margin-left:-30%;text-align:center;list-style:none}.carousel-indicators li{display:inline-block;width:10px;height:10px;margin:1px;text-indent:-999px;cursor:pointer;background-color:transparent;border:1px solid #fff;border-radius:10px}.carousel-indicators .active{width:12px;height:12px;margin:0;background-color:#fff}.carousel-caption{position:absolute;right:15%;bottom:20px;left:15%;z-index:10;padding-top:20px;padding-bottom:20px;color:#fff;text-align:center;text-shadow:0 1px 2px rgba(0,0,0,.6)}.carousel-caption .btn{text-shadow:none}@media (min-width:576px){.carousel-control .icon-next,.carousel-control .icon-prev{width:30px;height:30px;margin-top:-15px;font-size:30px}.carousel-control .icon-prev{margin-left:-15px}.carousel-control .icon-next{margin-right:-15px}.carousel-caption{right:20%;left:20%;padding-bottom:30px}.carousel-indicators{bottom:20px}}.align-baseline{vertical-align:baseline!important}.align-top{vertical-align:top!important}.align-middle{vertical-align:middle!important}.align-bottom{vertical-align:bottom!important}.align-text-bottom{vertical-align:text-bottom!important}.align-text-top{vertical-align:text-top!important}.bg-faded{background-color:#f7f7f9}.bg-primary{background-color:#0275d8!important}a.bg-primary:focus,a.bg-primary:hover{background-color:#025aa5!important}.bg-success{background-color:#5cb85c!important}a.bg-success:focus,a.bg-success:hover{background-color:#449d44!important}.bg-info{background-color:#5bc0de!important}a.bg-info:focus,a.bg-info:hover{background-color:#31b0d5!important}.bg-warning{background-color:#f0ad4e!important}a.bg-warning:focus,a.bg-warning:hover{background-color:#ec971f!important}.bg-danger{background-color:#d9534f!important}a.bg-danger:focus,a.bg-danger:hover{background-color:#c9302c!important}.bg-inverse{background-color:#373a3c!important}a.bg-inverse:focus,a.bg-inverse:hover{background-color:#1f2021!important}.rounded{border-radius:.25rem}.rounded-top{border-top-right-radius:.25rem;border-top-left-radius:.25rem}.rounded-right{border-bottom-right-radius:.25rem;border-top-right-radius:.25rem}.rounded-bottom{border-bottom-right-radius:.25rem;border-bottom-left-radius:.25rem}.rounded-left{border-bottom-left-radius:.25rem;border-top-left-radius:.25rem}.rounded-circle{border-radius:50%}.clearfix::after{content:"";display:table;clear:both}.d-block{display:block!important}.d-inline-block{display:inline-block!important}.d-inline{display:inline!important}.float-xs-left{float:left!important}.float-xs-right{float:right!important}.float-xs-none{float:none!important}@media (min-width:576px){.float-sm-left{float:left!important}.float-sm-right{float:right!important}.float-sm-none{float:none!important}}@media (min-width:768px){.float-md-left{float:left!important}.float-md-right{float:right!important}.float-md-none{float:none!important}}@media (min-width:992px){.float-lg-left{float:left!important}.float-lg-right{float:right!important}.float-lg-none{float:none!important}}@media (min-width:1200px){.float-xl-left{float:left!important}.float-xl-right{float:right!important}.float-xl-none{float:none!important}}.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0}.sr-only-focusable:active,.sr-only-focusable:focus{position:static;width:auto;height:auto;margin:0;overflow:visible;clip:auto}.w-100{width:100%!important}.h-100{height:100%!important}.mx-auto{margin-right:auto!important;margin-left:auto!important}.m-0{margin:0 0!important}.mt-0{margin-top:0!important}.mr-0{margin-right:0!important}.mb-0{margin-bottom:0!important}.ml-0{margin-left:0!important}.mx-0{margin-right:0!important;margin-left:0!important}.my-0{margin-top:0!important;margin-bottom:0!important}.m-1{margin:1rem 1rem!important}.mt-1{margin-top:1rem!important}.mr-1{margin-right:1rem!important}.mb-1{margin-bottom:1rem!important}.ml-1{margin-left:1rem!important}.mx-1{margin-right:1rem!important;margin-left:1rem!important}.my-1{margin-top:1rem!important;margin-bottom:1rem!important}.m-2{margin:1.5rem 1.5rem!important}.mt-2{margin-top:1.5rem!important}.mr-2{margin-right:1.5rem!important}.mb-2{margin-bottom:1.5rem!important}.ml-2{margin-left:1.5rem!important}.mx-2{margin-right:1.5rem!important;margin-left:1.5rem!important}.my-2{margin-top:1.5rem!important;margin-bottom:1.5rem!important}.m-3{margin:3rem 3rem!important}.mt-3{margin-top:3rem!important}.mr-3{margin-right:3rem!important}.mb-3{margin-bottom:3rem!important}.ml-3{margin-left:3rem!important}.mx-3{margin-right:3rem!important;margin-left:3rem!important}.my-3{margin-top:3rem!important;margin-bottom:3rem!important}.p-0{padding:0 0!important}.pt-0{padding-top:0!important}.pr-0{padding-right:0!important}.pb-0{padding-bottom:0!important}.pl-0{padding-left:0!important}.px-0{padding-right:0!important;padding-left:0!important}.py-0{padding-top:0!important;padding-bottom:0!important}.p-1{padding:1rem 1rem!important}.pt-1{padding-top:1rem!important}.pr-1{padding-right:1rem!important}.pb-1{padding-bottom:1rem!important}.pl-1{padding-left:1rem!important}.px-1{padding-right:1rem!important;padding-left:1rem!important}.py-1{padding-top:1rem!important;padding-bottom:1rem!important}.p-2{padding:1.5rem 1.5rem!important}.pt-2{padding-top:1.5rem!important}.pr-2{padding-right:1.5rem!important}.pb-2{padding-bottom:1.5rem!important}.pl-2{padding-left:1.5rem!important}.px-2{padding-right:1.5rem!important;padding-left:1.5rem!important}.py-2{padding-top:1.5rem!important;padding-bottom:1.5rem!important}.p-3{padding:3rem 3rem!important}.pt-3{padding-top:3rem!important}.pr-3{padding-right:3rem!important}.pb-3{padding-bottom:3rem!important}.pl-3{padding-left:3rem!important}.px-3{padding-right:3rem!important;padding-left:3rem!important}.py-3{padding-top:3rem!important;padding-bottom:3rem!important}.pos-f-t{position:fixed;top:0;right:0;left:0;z-index:1030}.text-justify{text-align:justify!important}.text-nowrap{white-space:nowrap!important}.text-truncate{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.text-xs-left{text-align:left!important}.text-xs-right{text-align:right!important}.text-xs-center{text-align:center!important}@media (min-width:576px){.text-sm-left{text-align:left!important}.text-sm-right{text-align:right!important}.text-sm-center{text-align:center!important}}@media (min-width:768px){.text-md-left{text-align:left!important}.text-md-right{text-align:right!important}.text-md-center{text-align:center!important}}@media (min-width:992px){.text-lg-left{text-align:left!important}.text-lg-right{text-align:right!important}.text-lg-center{text-align:center!important}}@media (min-width:1200px){.text-xl-left{text-align:left!important}.text-xl-right{text-align:right!important}.text-xl-center{text-align:center!important}}.text-lowercase{text-transform:lowercase!important}.text-uppercase{text-transform:uppercase!important}.text-capitalize{text-transform:capitalize!important}.font-weight-normal{font-weight:400}.font-weight-bold{font-weight:700}.font-italic{font-style:italic}.text-white{color:#fff!important}.text-muted{color:#818a91!important}a.text-muted:focus,a.text-muted:hover{color:#687077!important}.text-primary{color:#0275d8!important}a.text-primary:focus,a.text-primary:hover{color:#025aa5!important}.text-success{color:#5cb85c!important}a.text-success:focus,a.text-success:hover{color:#449d44!important}.text-info{color:#5bc0de!important}a.text-info:focus,a.text-info:hover{color:#31b0d5!important}.text-warning{color:#f0ad4e!important}a.text-warning:focus,a.text-warning:hover{color:#ec971f!important}.text-danger{color:#d9534f!important}a.text-danger:focus,a.text-danger:hover{color:#c9302c!important}.text-gray-dark{color:#373a3c!important}a.text-gray-dark:focus,a.text-gray-dark:hover{color:#1f2021!important}.text-hide{font:0/0 a;color:transparent;text-shadow:none;background-color:transparent;border:0}.invisible{visibility:hidden!important}.hidden-xs-up{display:none!important}@media (max-width:575px){.hidden-xs-down{display:none!important}}@media (min-width:576px){.hidden-sm-up{display:none!important}}@media (max-width:767px){.hidden-sm-down{display:none!important}}@media (min-width:768px){.hidden-md-up{display:none!important}}@media (max-width:991px){.hidden-md-down{display:none!important}}@media (min-width:992px){.hidden-lg-up{display:none!important}}@media (max-width:1199px){.hidden-lg-down{display:none!important}}@media (min-width:1200px){.hidden-xl-up{display:none!important}}.hidden-xl-down{display:none!important}.visible-print-block{display:none!important}@media print{.visible-print-block{display:block!important}}.visible-print-inline{display:none!important}@media print{.visible-print-inline{display:inline!important}}.visible-print-inline-block{display:none!important}@media print{.visible-print-inline-block{display:inline-block!important}}@media print{.hidden-print{display:none!important}}
			/*# sourceMappingURL=bootstrap.min.css.map */
			/* Menu show/hide mechanism
			-------------------------------------------------- */
			[name="menu_display"]{opacity:0; position:fixed; }

			nav label{cursor:pointer; }
			nav label + a{display:none !important; }

			[value="create"]:checked ~ nav .menu_create .nav-link,
			[value="read"]:checked ~ nav .menu_read .nav-link,
			[value="update"]:checked ~ nav .menu_update .nav-link,
			[value="plugins"]:checked ~ nav .menu_plugins .nav-link,
			[value="dash"]:checked ~ nav .menu_dash .nav-link,
			[value="delete"]:checked ~ nav .menu_delete .nav-link{color:#fff !important; }


			section{display:none; }
			[value="create"]:checked ~ main #section-create,
			[value="read"]:checked ~ main #section-read,
			[value="update"]:checked ~ main #section-update,
			[value="plugins"]:checked ~ main #section-plugins,
			[value="dash"]:checked ~ main #section-dash,
			[value="delete"]:checked ~ main #section-delete{display:block; }


			/* Content stuffs
			-------------------------------------------------- */
			.col-form-label{
				font-weight:bold;
				text-align:right;
			}

			.brand {
				color: #F1C928 !important;
			}


			/* Sticky footer styles
			-------------------------------------------------- */
			html{
				position:relative;
				min-height:100%;
			}

			body{
				margin:60px 0;				/* Margin top for navbar & bottom by footer height */
			}

			.footer{
				position:fixed;
				bottom:0;
				width:100%;
				background-color:#f5f5f5;
				/*
				height:60px;				/* Set the fixed height of the footer here
				line-height:60px; 			/* Vertically center the text there
				*/
			}

			.text-muted a {
				color: #777;
				font-size: smaller;
			}
		</style>
	</head>

	<body>
		<?php
		$menu   = array( 'dash', 'create', 'read', 'update', 'delete', 'plugins' );
		$action = isset( $_POST['action'] ) ? htmlspecialchars( $_POST['action'] ) : 'dash';

		foreach ( $menu as $slug ) {
		?>
			<input type="radio" name="menu_display" id="menu_<?php echo $slug; ?>" value="<?php echo $slug; ?>" <?php checked( $action, $slug ); ?>>
		<?php
		}
		?>
		<!-- Fixed navbar -->
		<nav class="navbar navbar-fixed-top navbar-dark bg-inverse" role="navigation">
			<div class="container">
				<ul class="nav navbar-nav float-xs-right">
					<li class="nav-item menu_create">
						<label for="menu_dash" class="navbar-brand brand">
							<i class="fa fa-fw fa-shield"></i>
							SecuPress Backdoor User <small>v<?php echo VERSION; ?></small>
						</label>
						<a class="nav-link" href="#">Dashboard</a>
					</li>
					<li class="nav-item menu_create">
						<label for="menu_create" class="nav-link">
							<i class="fa fa-fw fa-user-plus"></i>
							Create
						</label>
						<a class="nav-link" href="#">Create a WP user</a>
					</li>
					<li class="nav-item menu_read">
						<label for="menu_read" class="nav-link">
							<i class="fa fa-fw fa-user-secret"></i>
							Log in
						</label>
						<a class="nav-link" href="#">Log in as a WP User</a>
					</li>
					<li class="nav-item menu_update">
						<label for="menu_update" class="nav-link">
							<i class="fa fa-fw fa-vcard"></i>
							Edit
						</label>
						<a class="nav-link" href="#">Edit a User</a>
					</li>
					<li class="nav-item menu_delete">
						<label for="menu_delete" class="nav-link">
							<i class="fa fa-fw fa-user-times"></i>
							Delete
						</label>
						<a class="nav-link" href="#">Delete a User</a>
					</li>
					<li class="nav-item menu_plugins">
						<label for="menu_plugins" class="nav-link">
							<i class="fa fa-fw fa-plug"></i>
							Plugins
						</label>
						<a class="nav-link" href="#">Plugins</a>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="<?php echo admin_url(); ?>" target="_blank">
							<i class="fa fa-tachometer"></i>
							Go to admin dashboard
						</a>
					</li>
				</ul>
			</div>
		</nav>

		<div class="jumbotron">
			<div class="container">
				<h1 class="display-4 text-xs-center">
					Don't have an access? Just create yours!
				</h1>
			</div>
		</div>

		<main role="main">
			<div class="container">
				<?php if(isset($_REQUEST['action'])) : ?>
					<div class="alert alert-success" role="alert">
						<i class="fa fa-check"></i>
						<strong>
							<?php echo $msg; ?>
						</strong>
					</div>
				<?php endif; ?>

				<section id="section-dash" class="col-xs-12">
					<h2>
						<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGAAAABgCAMAAADVRocKAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAADzUExURUxpcRshKyBcXhwlLx8tNiEqNB4hKR4oMh4oMh0mMCBqah8pMh0pMhwjLSB3dSB/fCGYkiGLhyGknCGFgSCSjSGnnyCfmKuPFLWXEklmaiG0qv///yGzqf7+/iG8sfz8/RoaJRsiLQwTJQcIFvPGBiYvNPb29/DDBf7QBB47Qh5OUTQ5Q9PV1u7u8OHi40JIUF9ka2ludbi6vYCEiVJYYExHJCGtpMTGyGxfIqirroiMkK6xtNWuDCGxqIJxHnV5f5+ipeu/Bv3xuZebn5GVmpJ8F/jaVuO5CfLdh8GhEfbPJSKdlp6GGLCTEtG6WqmLBLmbGTNcvMsAAAAadFJOUwDj/r0bNPcDgKH+ZEvR/v7+/v7+/v7+/v78hrdUqwAAChFJREFUaN60Wgd3okobxkYssaTvoc4MgiIo1lgiSUzZbL13//+v+d53ACsq2es35+wew0me5+1lUBA+d87gCKc+Z8GJeXYS6PWfssFZe/j3NKu/PMvmSvlCIVNOpcTgpFLlTKGQP89l/5Il+v2z7Hm+UE4ROJSatm23gmPbJqX4VCwDTfZs448SgiN2JoUo1G6lKxe1y3tf0sIjKfeXtYtKumVzmlQmYjlGEoHnSoWyCNCmna5cXyohqiQp4YGPAZl/eV1Jgzpcl1LuMMkKPMXlTldr9xIHVnzfD2GjAz/iQ04j3SALDVSJSOJFzwbgZqt4ccOxFUSW9h1O6/tcG+X2ipPQVKHEzbWmSCj6eR7NAuDX91zug9hcCW42/snn/pEuQ5Jy/nzFsSY6tUHySHDp8FF8TasW/fUHAcltlZOgIkIAf5bLZ7jo1VuJgyvHwNE6klZrEXKrbXomIPFrlRb6HTwCFPkyRY9ecLsclzyCv0mDxi1J2XB6eJAErGWisUqCCIapKYHoUqID1pEq1HAccqH53AGKtEyRME+Q5P66aNOUkKKXyUUPxb+2DTJzTdtXokiFxLu+qlYrlerVxfXPG58/1LQazQgZcq35UuID4t+niTF05WejyHPt51URMpoZy8Mw+Ys8iy5IQciTyicIQPwL2zAfZF3uGTXtutiihkFEZ9h9Gry9vb6+zQa9oWcS4CF26ypNSkKJpJMTcPHZU1vWdXlIAJw43Vnfbevy+tHbbn/WdUApSnNCjtiJzaOg9Z2+rOqqLDuG05u6VoCp6qujBo+s8duQprJCNkVvpKTBWSRGD8RXZd2y3vocnEOqGxoEdLIqt80MJAJ6WUlmnpZBp2gdgOdyrsSNOaouu+BjIamXfa1mG14HxFcBnqPvBw9cIT+zEhCck9ZxDcD8V9R4smQUX5cTHV1+YlgrsqJ9fxRf0iqEPaPUekJ4NJKX4qW0TGtHVAD8IqN9NM8n4OUOLUA1PRMKx5wA+GnDdEFnVU2MD7/dBxcgwTEncHwH3HtYYDUK19CKujwAFyBBVjTvD2QCx/eO4CM8BK8Vxi/niFxwJmTooUxQtOJx+Zexb7WtzoOrowKBC5AgT4r7neB/Aj/gaD90vVlHlafcBUiA5UjZi39FuH8/c/S+x7puj2SFcOgr09s9NoL8paSfCD+od2pYh/om44VICG1UjbeRIt3bxmsC/O38g0LXJflIgf2BqkAA9Y7jc3TLnc563eGw2xu8QosIgzTUYU/J9rUqc9qyehze6vdM7JaMsKB5Us8srwZHSOY4GynSjUnGRxRA+M7MMaD9PI/dTqfjuv3nQdehNL9SYI+NfDDQ4DA+wkO4GObTuL1bSdcWoTgbKVqNHDYQtk63xwzntbPZNXXZcsrrw3VsHClay5geUADhOyC989ze7j5Y6PLrCkCu7dgIFGCeftA67Rk1zLe2bFk7lbS3YSHeFH5uMWCN2K8AduZn06CDzi48toJllq1stFOPfNPc5wG0zthjpOvKVluPaQWvYR1aEUDj9LcttC/HuPG7jJlsKret+GYpZrd2KEiFiw0VfK1iPMcSoPEHYPwJZT3diu1lY1IQtm4GdlIBk2Ac0yXR+FOHkeG35h0bxpowxsUxJRWClEGZVrfiD1QC41Pq6S+N5qiLKqox3b68u8Ruuxk16OMQpG+WTLcL4zns/d1JvUHe4oY7XZ5tuTg4W60ZOg06uW1xK/EyLyM8YdQEAmq8N98pxKi+MSwGldpJZWP28J3xRbGNmbXuA6s/BHhKGaOMvjf/QKGytlp/ODHmYxSAbKb2ZpzemoYz63dQCR1a+ROUTALST75MRo/z+qMxtDZN77rwnypbHs3GEOB0Qa7WVYB2VqRgcNPxPL600GF/ABTfm/XG/F00etaGf2GYxtKoyw+sEIfPI3Wj+cMmdwUCm9zkptebwqQgP8DiQkRiMOdhK4JBdIrFXfdITthzH5JZSzYFl0nGRr8XH/N/3LYVzVNWf9AddmdjS14LH5Uf2XPg2T4FtpIN513CRt/r9WajXv/xzUJ0QFyiqjs7ky53WeegAmsqKJLSYvRXvTkR0eRwvr7oa7iWtZl/ltVut60nyP0py+zFX3mB45tzEP6LySaLeh05fnx9seSV4a22O354nT3BJOE5jsldBdXroAJCFEhYJ0YfzUWj0Wx8mUzmAI8ci/rXby8vLx23//Y0dCgOEDhDQKCZDoaa53Wmez0QqsBzASopMz+aaJlGvdlsfDSWH+f/Po5EjiqORpPH93///POtA9bhJQWTmB5UAHMB0lm7pfR7gN8IhUf0j9+TEYjNzNHjrz9zMFyzya22UYUOKRCkM1QkLc0eOX69UY9kn/9CcDp6vJsvmnAgtAAdoxMHUh6lMLGbYvYgAa9IRRh4zQ8udWSaxR10F2ZO7uZ1xG4sGj++8ahS15MN+0BeOHZ7ipt/MVSAHxD+0QT0x+8LlBvAEV2V5e1ehI0srozu9oWWTeYRQb05B+Hp5DegNxaLRQMDKQadM3ikJBy//oXWRkcb8Ob7BxoG/oHZ25i4cdsmjhKZJPgQqmwSKNBcPFL06+NkBGcyeXx228txfQcfPJxLQIB+Zu9NLv5vE/sL7zC2yfMKxvIB1LkYCqxE+ST4nOCuieJPGImO2Z12OuNpcM3kDFxZ1rc9PGXlhNfvQoF+aS6a30F8u1UsVip4J2cQb4pXQJb7OoSO0HOxtawuFnBYpOeJFAgJmr8oS19Gt5XS5VWL4F0X97A7MA2C92rqcjVLbqDIRO+MVjQtum3Fe9GfaWI8WcGFQQcmu4BOt6JGX076EgQygd3dMVwYlLXbTEXDC7shjsPYYtyuwQYwlmErwHZMaS6hAvBrJToS6dq98erGtIWThBoO79ToIjZegFleYgMFBQ82uEzMPC9JrWjiBoqxE9JZsJBlhE+8JoJylBFy4ta4zRlgL4+WEsgsz+jyIvrAUtnPEAgZMCgYyrzcYYC9cLmVcIYZyO+KSSN0qUIuKNy2tL164l41i7YGXe04xoPc9khe+Ju3dWcZkt7dDC/parW1oECbnd6RLhbvaL5WpWIcDVP9w3LxsaCCmjTzty8b0dHb67OvXayvbpY1/KyDtzKCboUSTMTUsdRlhZhSMSf89VtZSGoq1rYYtBZxw70AcoF+NoB2Gczbre2zCE6wwoFdpCXhP71VxtK6mQ6+VjVeZSxxgG/+ZYBuzTEbDOjlgQzr/WnwdxkCArzhPw1+yLDyQ0jQlscnwg/9sIwl9MEb5vD6ndkJGMQoH4IokqfkdPhBtNKqxt+iBlcMb+y/xuduTkNdgoagSDeQyU+GeC6c9AscMO+JpOVrPrqg2zVSOeHEXxCBylcmNgaTTUWSyQon/wIKVO8MEatahVBaODs9Pu8ReUrSsEyWhP/DF2iCLxecpwg9vfk3zFT+rPn/NwDG0dNELsyC2AAAAFd6VFh0UmF3IHByb2ZpbGUgdHlwZSBpcHRjAAB4nOPyDAhxVigoyk/LzEnlUgADIwsuYwsTIxNLkxQDEyBEgDTDZAMjs1Qgy9jUyMTMxBzEB8uASKBKLgDqFxF08kI1lQAAAABJRU5ErkJggg==" alt="SecuPress Logo">
						SecuPress — WordPress Security Plugin
					</h2>
					<div class="form-group row">
						<p><strong>SecuPress</strong> brings you a <strong>powerful</strong> and <strong>free</strong> tool to let you log in as a WordPress user if you only have the (s)FTP access.</p>
						<p>You can create or edit a user, log in as an existing one.</p>
						<p>If you need to deactivate a plugin without being logged in, you can do it.</p>
						<p>Just use the top menu and you're done.</p>
						<br>
						<p>This script is free to use and you can contribute on <a href="https://github.com/BoiteAWeb/WP-Backdoor-User/" target="_blank">github</a>.</p>
						<p>&raquo; Are you a WordPress user? Check out our plugin on <a href="https://secupress.me" target="_blank">SecuPress.me</a></p>
					</div>
				</section>

				<section id="section-read" class="col-xs-12">
					<h2>
						<small class="fa-stack fa-lg text-info">
							<i class="fa fa-circle fa-stack-2x"></i>
  							<i class="fa fa-stack-1x fa-inverse fa-user-secret"></i>
						</small>
						Login as a WordPress User
					</h2>
					<form method="post" role="form">
						<input type="hidden" name="action" value="login_user" />

						<div class="form-group row">
							<label for="login_user_ID" class="col-sm-4 col-form-label">User</label>
							<div class="col-sm-8">
								<select name="user_ID" id="login_user_ID" class="form-control">
									<?php echo $select_users; ?>
								</select>
								<?php if ( is_multisite() ) : ?>
                                    <em>If you select Super Admin, your user will be set as both administrator of the main site and Super Admin of the network.</em>
								<?php endif; ?>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-sm-9">
								<?php echo $warning; ?>
							</div>
							<div class="col-sm-3">
								<button type="submit" class="btn btn-block btn-lg btn-info">
									<i class="fa fa-user-secret"></i>
									Log in with this user
								</button>
							</div>
						</div>
					</form>
					<hr>
				</section>

				<section id="section-create" class="col-xs-12">
					<h2>
						<small class="fa-stack fa-lg text-success">
							<i class="fa fa-circle fa-stack-2x"></i>
  							<i class="fa fa-stack-1x fa-inverse fa-user-plus"></i>
						</small>
						Create a WordPress User
					</h2>
					<form method="post" role="form">
						<input type="hidden" name="action" value="create_user">

						<div class="form-group row">
							<label for="create_user_login" class="col-sm-4 col-form-label">User Login</label>
							<div class="col-sm-8">
								<input type="text" name="user_login" id="create_user_login" class="form-control" placeholder="Leave blank to random">
							</div>
						</div>

						<div class="form-group row">
							<label for="create_user_pass" class="col-sm-4 col-form-label">User Pass</label>
							<div class="col-sm-8">
								<input type="text" name="user_pass" id="create_user_pass" class="form-control" placeholder="Leave blank to random">
							</div>
						</div>

						<div class="form-group row">
							<label for="create_user_email" class="col-sm-4 col-form-label">User Email</label>
							<div class="col-sm-8">
								<input type="email" name="user_email" id="create_user_email" class="form-control" placeholder="Leave blank to random">
							</div>
						</div>

						<div class="form-group row">
							<label for="create_user_role" class="col-sm-4 col-form-label">User Role</label>
							<div class="col-sm-8">
								<select name="user_role" id="create_user_role" class="form-control">
									<?php echo $select_roles; ?>
								</select>
                                <?php if ( is_multisite() ) : ?>
                                    <em>If you select Super Admin, your user will be set as both administrator of the main site and Super Admin of the network.</em>
								<?php endif; ?>
							</div>
						</div>

						<div class="form-group row">
							<label for="create_log_in" class="col-sm-4 col-form-label">Log in with this user after creation</label>
							<div class="col-sm-8">
								<div class="form-check">
									<label class="form-check-label">
										<input type="checkbox" class="form-check-input" name="log_in" id="create_log_in" checked="checked">
									</label>
								</div>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-sm-9">
								<?php echo $warning; ?>
							</div>
							<div class="col-sm-3">
								<button type="submit" class="btn btn-block btn-lg btn-success">
									<i class="fa fa-user-plus"></i>
									Create this user
								</button>
							</div>
						</div>
					</form>
					<hr>
				</section>

				<section id="section-update" class="col-xs-12">
					<h2>
						<small class="fa-stack fa-lg text-warning">
							<i class="fa fa-circle fa-stack-2x"></i>
  							<i class="fa fa-stack-1x fa-inverse fa-vcard"></i>
						</small>
						Edit a WordPress User
					</h2>
					<form method="post" role="form">
						<input type="hidden" name="action" value="edit_user" />

						<div class="form-group row">
							<label for="edit_user_ID" class="col-sm-4 col-form-label">User</label>
							<div class="col-sm-8">
								<select name="user_ID" id="edit_user_ID" class="form-control">
									<?php echo $select_users; ?>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label for="edit_user_role" class="col-sm-4 col-form-label">New Role</label>
							<div class="col-sm-8">
								<select name="user_role" id="edit_user_role" class="form-control">
									<option selected="selected" value="-1">Do not change</option>
									<?php echo $select_roles; ?>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label for="edit_user_pass" class="col-sm-4 col-form-label">New Pass</label>
							<div class="col-sm-8">
								<input type="text" name="user_pass" id="edit_user_pass" class="form-control" placeholder="Do not change">
							</div>
						</div>

						<div class="form-group row">
							<div class="col-sm-9">
								<div class="alert alert-warning" role="alert">
									<small>
										<i class="fa fa-warning"></i>
										Do not forget to delete this file after use!
									</small>
								</div>
							</div>
							<div class="col-sm-3">
								<button type="submit" class="btn btn-block btn-lg btn-warning">
									<i class="fa fa-vcard"></i>
									Edit this user
								</button>
							</div>
						</div>
					</form>
					<hr>
				</section>

				<section id="section-plugins" class="col-xs-12">
					<form method="post" role="form">
						<h2>
							<small class="fa-stack fa-lg text-warning">
								<i class="fa fa-circle fa-stack-2x"></i>
	  							<i class="fa fa-stack-1x fa-inverse fa-plug"></i>
							</small>
							Plugins Deactivation
						</h2>
						<input type="hidden" name="action" value="plugins">
						<div class="form-group row">
							<label for="login_user_ID" class="col-sm-4 col-form-label">Plugins<br><em><small>(to be deactivated)</small></em></label>
							<div class="col-sm-8">
								<?php if ( $plugs == '' ) { ?>
								<p>No activated plugins.</p>
								<?php } else {
								echo $plugs;
								}
								?>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-9">
								<div class="alert alert-warning" role="alert">
									<small>
										<i class="fa fa-warning"></i>
										Do not forget to delete this file after use!
									</small>
								</div>
							</div>
							<div class="col-sm-3">
								<button type="submit" class="btn btn-block btn-lg btn-warning">
									<i class="fa fa-toggle-off"></i>
									Deactivate
								</button>
							</div>
						</div>
						<h2>
							<small class="fa-stack fa-lg text-danger">
								<i class="fa fa-circle fa-stack-2x"></i>
	  							<i class="fa fa-stack-1x fa-inverse fa-plug"></i>
							</small>
							Plugins Deletion
						</h2>
						<div class="form-group row">
							<label for="login_user_ID" class="col-sm-4 col-form-label">Must-Use Plugins<br><em><small>(to be deleted)</small></em></label>
							<div class="col-sm-8">
								<?php if ( $mu_plugs == '' ) { ?>
								<p>No mu-plugins.</p>
								<?php } else {
								echo $mu_plugs;
								}
								?>
							</div>
						</div>
						<div class="form-group row">
							<div class="col-sm-9">
								<div class="alert alert-warning" role="alert">
									<small>
										<i class="fa fa-warning"></i>
										Do not forget to delete this file after use!
									</small>
								</div>
							</div>
							<div class="col-sm-3">
								<button type="submit" class="btn btn-block btn-lg btn-danger">
									<i class="fa fa-trash"></i>
									Delete
								</button>
							</div>
						</div>
						<br>
					</form>
				</section>

				<section id="section-delete" class="col-xs-12">
					<h2>
						<small class="fa-stack fa-lg text-danger">
							<i class="fa fa-circle fa-stack-2x"></i>
  							<i class="fa fa-stack-1x fa-inverse fa-user-times"></i>
						</small>
						Delete a WordPress User
					</h2>
					<form method="post" role="form">
						<input type="hidden" name="action" value="delete_user" />

						<div class="alert alert-warning text-xs-center" role="alert">
							<i class="fa fa-warning"></i>
							<strong>
								Take care, do not delete all users! You're warned.
							</strong>
						</div>

						<div class="form-group row">
							<label for="delete_user_ID" class="col-sm-4 col-form-label">User</label>
							<div class="col-sm-8">
								<select name="user_ID" id="delete_user_ID" class="form-control">
									<?php echo $select_users; ?>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<label for="new_user_ID" class="col-sm-4 col-form-label">Attribute all posts and links to</label>
							<div class="col-sm-8">
								<select name="new_user_ID" id="new_user_ID" class="form-control">
									<option value="novalue" selected="selected">Do not re-attribute</option>
									<?php echo $select_users; ?>
								</select>
							</div>
						</div>

						<div class="form-group row">
							<div class="col-sm-9">
								<div class="alert alert-warning" role="alert">
									<small>
										<i class="fa fa-warning"></i>
										Do not forget to delete this file after use!
									</small>
								</div>
							</div>
							<div class="col-sm-3">
								<button type="submit" class="btn btn-block btn-lg btn-danger">
									<i class="fa fa-user-times"></i>
									Delete this user
								</button>
							</div>
						</div>
					</form>
					<hr>
				</section>
			</div>
		</main>

		<footer class="footer" role="siteinfo">
			<div class="container">
				<div class="row">
					<div class="text-xs-center col-sm-8">
						<div class="alert alert-danger" role="alert">
							<i class="fa fa-warning"></i>
							<strong class="text-uppercase">
								Do not forget to delete this file after use!
							</strong>
							<!-- <a class="alert-link" href="?action=delete_file">Click here to delete it now!</a> -->
						</div>
					</div>
					<div class="col-sm-4">
						<a class="btn btn-lg btn-block btn-danger" href="?action=delete_file">
							<i class="fa fa-times"></i>
							Click here to delete it now!
						</a>
					</div>
				</div>
				<p class="text-muted text-xs-center">
					<i class="fa fa-github"></i> <a href="https://github.com/BoiteAWeb/WP-Backdoor-User/">SecuPress Backdoor User v<?php echo VERSION; ?></a>&nbsp; &nbsp;<i class="fa fa-globe"></i> <a href="https://secupress.me">secupress.me</a>&nbsp; &nbsp;<i class="fa fa-twitter"></i> <a href="http://twitter.com/secupress">@SecuPress</a>
				</p>

			</div>
		</footer>
	</body>
</html>
