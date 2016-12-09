<?php

/*---------------------------------------------------------------------------

=== WP Backdoor User ===

Script Name: WP Backdoor User
Script URI: http://boiteaweb.fr/plugin-backdoor-user-3311.html (fr)
Author URI: http://boiteaweb.fr
Author: Julio Potier
Version: 3.0
Contributors: Kévin (@DarkLG), Fanchy (fanchy@hotmail.fr)
Tags: security, admin, user
License: GPLv3


== Description ==

This script is used to create, delete, login, edit a user in a WordPress
installation when you do not have dashboard access but only FTP access.
Just rename, upload, surf it and read.


== Installation ==

1. Rename this file
2. Upload it in any folder, even at WordPress root install
3. Go to this file in your favorite browser
4. 4 choices, create user, delete user, log in with a user, edit role or password for a user
5. Do not forget to delete the file after use, it will be automatically deleted for user creation and login.


== Usage ==

1. User creation:
	- Fill the fields for login, pass, email,
	- Choose a role,
	- Check or not the login box, if yes, you'll be logged in with this user,
	- Click "Create".
	- For each missing fields, a random value will be created.
		* Example for a random editor user : "editor_2j1p12"
		* Example for a random pass : mmm really random, change it or lose this account ;)
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


---------------------------------------------------------------------------*/

define( 'VERSION', '3.0' );
// Optional deleting file after use
$delete_file = true;

// Force the file to be renamed for security reasons
if ( 'wp-backdoor-user.php' === strtolower( basename( __FILE__ ) ) ) {
	die("Please rename the file before use!");
}

// Load WordPress
while ( ! is_file( 'wp-load.php' ) ) {
	if ( is_dir( '..' ) ){
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

			// Problem on creation ? Stop script
			if ( is_wp_error( $my_user_id ) ) {
				wp_die( new WP_Error( 'registerfail', sprintf( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !' ), esc_attr( get_option( 'admin_email' ) ) ) );
			}
			// Set admin role to this user
			$user = new WP_User( $my_user_id );
			$user->set_role( $new_user_role );

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
			do_action( 'wp_login', $user_data->user_login );

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

		// edit user action
		case 'edit_user':
			// If a role change is needed
			if ( $_REQUEST['user_role'] != '-1' ) {
				// Get the user
				$user = new WP_User( $_REQUEST['user_ID'] );

				// Set his role
				$user->set_role( $_REQUEST['user_role'] );
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
		<link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.0.0-alpha.5/css/bootstrap.min.css" rel="stylesheet">

		<style>
			/* Menu show/hide mechanism
			-------------------------------------------------- */
			[name="menu_display"]{opacity:0; position:fixed; }

			nav label{cursor:pointer; }
			nav label + a{display:none !important; }

			[value="create"]:checked ~ nav .menu_create .nav-link,
			[value="read"]:checked ~ nav .menu_read .nav-link,
			[value="update"]:checked ~ nav .menu_update .nav-link,
			[value="delete"]:checked ~ nav .menu_delete .nav-link{color:#fff !important; }


			section{display:none; }
			[value="create"]:checked ~ main #section-create,
			[value="read"]:checked ~ main #section-read,
			[value="update"]:checked ~ main #section-update,
			[value="delete"]:checked ~ main #section-delete{display:block; }


			/* Content stuffs
			-------------------------------------------------- */
			.col-form-label{
				font-weight:bold;
				text-align:right;
			}

			a:visited.navbar-brand, a:hover.navbar-brand, a:focus.navbar-brand, a:link.navbar-brand {
				color: #F1C928;
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
		<input type="radio" name="menu_display" id="menu_create" value="create" checked>
		<input type="radio" name="menu_display" id="menu_read" value="read">
		<input type="radio" name="menu_display" id="menu_update" value="update">
		<input type="radio" name="menu_display" id="menu_delete" value="delete">

		<!-- Fixed navbar -->
		<nav class="navbar navbar-fixed-top navbar-dark bg-inverse" role="navigation">
			<div class="container">
				<a class="navbar-brand" href="<?php echo $_SERVER['REQUEST_URI']; ?>">
					<i class="fa fa-home"></i>
					WP Backdoor User v<?php echo VERSION; ?>
				</a>
				<ul class="nav navbar-nav float-xs-right">
					<li class="nav-item menu_create">
						<label for="menu_create" class="nav-link">
							<i class="fa fa-fw fa-user-plus"></i>
							Create WP user
						</label>
						<a class="nav-link" href="#">Create WP user <span class="sr-only">(current)</span></a>
					</li>
					<li class="nav-item menu_read">
						<label for="menu_read" class="nav-link">
							<i class="fa fa-fw fa-user-secret"></i>
							Log in w. WP User
						</label>
						<a class="nav-link" href="#">Log in w. WP User</a>
					</li>
					<li class="nav-item menu_update">
						<label for="menu_update" class="nav-link">
							<i class="fa fa-fw fa-vcard"></i>
							Edit WP User
						</label>
						<a class="nav-link" href="#">Edit WP User</a>
					</li>
					<li class="nav-item menu_delete">
						<label for="menu_delete" class="nav-link">
							<i class="fa fa-fw fa-user-times"></i>
							Delete WP User
						</label>
						<a class="nav-link" href="#">Delete WP User</a>
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

				<section id="section-read" class="col-xs-12">
					<h2>
						<small class="fa-stack fa-lg text-info">
							<i class="fa fa-circle fa-stack-2x"></i>
  							<i class="fa fa-stack-1x fa-inverse fa-user-secret"></i>
						</small>
						Log in w. WP User
					</h2>
					<form method="post" role="form">
						<input type="hidden" name="action" value="login_user" />

						<div class="form-group row">
							<label for="login_user_ID" class="col-sm-4 col-form-label">User</label>
							<div class="col-sm-8">
								<select name="user_ID" id="login_user_ID" class="form-control">
									<?php echo $select_users; ?>
								</select>
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
						Create WP User
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
						Edit WP User
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

				<section id="section-delete" class="col-xs-12">
					<h2>
						<small class="fa-stack fa-lg text-danger">
							<i class="fa fa-circle fa-stack-2x"></i>
  							<i class="fa fa-stack-1x fa-inverse fa-user-times"></i>
						</small>
						Delete WP User
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
					<i class="fa fa-github"></i> <a href="https://github.com/BoiteAWeb/WP-Backdoor-User/">WP Backdoor User v<?php echo VERSION; ?></a>&nbsp; &nbsp;<i class="fa fa-globe"></i> <a href="http://boiteaweb.fr">boiteaweb.fr</a>&nbsp; &nbsp;<i class="fa fa-twitter"></i> <a href="http://twitter.com/boiteaweb">@BoiteAWeb</a>
				</p>

			</div>
		</footer>
	</body>
</html>
