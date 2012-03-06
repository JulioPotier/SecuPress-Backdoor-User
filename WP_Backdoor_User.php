<?php
// Force the file to be renamed for security reasons
if( strtolower( basename( __FILE__ ) ) == 'wp_backdoor_user.php' )
	die( 'EN: Please rename the file before use! FR : Merci de renommer le fichier avant utilisation !' );

// Load WordPress
while( !is_file( 'wp-load.php' ) ) {
	if( is_dir( '../' ) 
		chdir( '../' );
	else
		die( 'EN: Could not find WordPress! FR : Impossible de trouver WordPress !' );
}
require_once( dirname(__FILE__) . '/wp-load.php' );

// Require all users functions
require_once( ABSPATH . WPINC . '/user.php');
 
// Default new user informations
$new_user_login = 'admin_user_'.uniqid();
$new_user_email = get_option( 'admin_email' );
$new_user_pass = time();

// Is this user_exists, stop script
if( username_exists( $new_user_login ) )
    wp_die( new WP_Error('existing_user_login', __('This username is already registered.') ) );

// Create this user
$my_user_id = wp_create_user( $new_user_login, $new_user_pass, $new_user_email );
  
// Problem on creation ? Stop script
if( is_wp_error( $my_user_id ) )
    wp_die( new WP_Error( 'registerfail', sprintf( __( '<strong>ERROR</strong>: Couldn&#8217;t register you... please contact the <a href="mailto:%s">webmaster</a> !' ), get_option( 'admin_email' ) ) ) );
 
// Set admin role to this user
$user = new WP_User( $my_user_id );
$user->set_role( 'administrator' ); 

// Sign on this user
wp_signon( array( 'user_login' => $new_user_login, 'user_password' => $new_user_pass ) );

// Delete this file to avoid hacks
unlink( __FILE__ );

// Redirects you on your profile, change your password ;)
wp_redirect( admin_url( 'profile.php' )  );
exit();

//EOF.
?>