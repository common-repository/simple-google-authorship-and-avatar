<?php
/*
Plugin Name: Simple Google Authorship and Avatar
Plugin URI: http://eng.marksw.com/2013/01/11/simple-google-authorship-and-avatar-plugin/
Description: Enables users to easily claim google authorship on their content and use their google profile picture as avatar
Author: Mark Kaplun
Version: 1.0
Requires at least: 3.2
Author URI: http://eng.marksw.com/

*/

add_action('init','mk_gplus_gapi_token');


function mk_gplus_admin_notice_success(){
	printf('<div class="updated"><p>%s.</p></div>',__('User info retrived from G+','mk_gplus'));
}

function mk_gplus_admin_notice_error(){
	printf('<div class="error"><p>%s.</p></div>',__('User info retrivale from G+ had failed','mk_gplus'));
}

function mk_gplus_remove_token() {
    $url = remove_query_arg(array('gapi_token','gapi_error'));
?>
	<script type="text/javascript" charset="utf-8">
		if (typeof history.replaceState === 'function') {
		  data = {dummy:true};
		  history.replaceState(data,'','<? echo $url?>');
		}
	</script>
<?
}
		
function mk_gplus_gapi_token() {
  if (is_user_logged_in())
    if (isset($_GET['gapi_token'])) {
		$response = wp_remote_get('http://wpproxy.marksw.com/gapi/me.php?gapi_token='.stripslashes($_GET['gapi_token']));
		if( !is_wp_error( $response ) ) {
		  if ($response['response']['code'] == 200) {
			$me = json_decode($response['body']);
			$user = wp_get_current_user();
			delete_user_meta($user->id,'mk_gplus_gapi_me');
			add_user_meta($user->id,'mk_gplus_gapi_me',$me,true);

			add_action('admin_notices', 'mk_gplus_admin_notice_success');
		  } else {
			add_action('admin_notices', 'mk_gplus_admin_notice_error');
		  }
		} else {
		  add_action('admin_notices', 'mk_gplus_admin_notice_error');
		}
		add_action('admin_footer','mk_gplus_remove_token');	
  } else if (isset($_GET['gapi_error'])) {
    add_action('admin_notices', 'mk_gplus_admin_notice_error');
    add_action('admin_footer','mk_gplus_remove_token');	
  }
}

add_action( 'show_user_profile', 'mk_gplus_fields' );
add_action( 'edit_user_profile', 'mk_gplus_fields' );
		
add_action( 'personal_options_update', 'mk_gplus_fields_save' );
add_action( 'edit_user_profile_update', 'mk_gplus_fields_save' );		

	
/**
 * Displays the profile-field in the Authors Profile Page
 * 
 */
function mk_gplus_fields( $user ) { 
  if ($_SERVER['HTTPS'])
    $prot = 'https://';
  else 
    $prot = 'http://';
  $gapi_cb = $prot.$_SERVER["HTTP_HOST"].$_SERVER["REQUEST_URI"];
  $cuser = wp_get_current_user();
  $data = get_user_meta($user->id,'mk_gplus_gapi_me',true);
  $opts = get_user_meta($user->id,'mk_gplus_link',true);
?>

	<h3><?_e('Google profile','mk_gplus')?></h3>

	<table class="form-table">
		<tr>

			<th><label><?php _e('Current info','mk_gplus')?></label></th>

			<td>
				<?php if ($data) { ?>
					<a href="<?php echo $data->url;?>"><img width="50" hright="50" src="<?php echo $data->image->url;?>"></a><br>
					<?php echo $data->displayName;?>
				<?php } else { 
					_e('Not authenticated','mk_gplus');
					}
				?>	
			</td>
		</tr>
	    <?php if ($user->ID == $cuser->ID) { ?>
		<tr>
		    <?php
			  if ($data) {
			    $text=__('Refresh info','mk_gplus');
			  } else {
			    $text=__('Get info','mk_gplus');
			  }
			?>
			<th><label><?php echo $text; ?></label></th>

			<td>
			    <a href="http://wpproxy.marksw.com/gapi/me.php?callback=<?php echo $gapi_cb;?>"><?php _e('Get info','mk_gplus')?></a>
			</td>
		</tr>
        <?php } ?>
        <?php if ($data) { ?>
		<tr>
			<th><label><?php _e('Delete info','mk_gplus')?></label></th>

			<td>
			    <label for="mk_gplus_delete"><input id="mk_gplus_delete" type="checkbox" value="1" name="mk_gplus_delete"> <?php _e('Delete info','mk_gplus')?></label>
			</td>
		</tr>
		<tr>
			<th><label><?php _e('Use info for','mk_gplus')?></label></th>

			<td>
				<?php 
				   $u1=parse_url(get_option('siteurl'));
				   $authenticate = __('. You might need to add this site to the "Contributor to" section of your profile','mk_gplus');
				   foreach ($data->urls as $url) {
				     $u2=parse_url($url->value);					 
				     if ($u1['host']==$u2['host']) {
					   $authenticate = '';
					   break;
					   }
				   }
				?>
			    <label for="mk_gplus_authorship"><input id="mk_gplus_authorship" type="checkbox" value="1" name="mk_gplus_authorship" <?php checked($opts['authorship'])?> > <?php echo __('Claim authorship','mk_gplus').$authenticate?></label><br>
			    <label for="mk_gplus_avatar"><input id="mk_gplus_avatar" type="checkbox" value="1" name="mk_gplus_avatar" <?php checked($opts['avatar'])?> > <?php _e('Avatar','mk_gplus')?></label><br>
			</td>
		</tr>
		<?php } // end data ?>

	</table>
<?php }

function mk_gplus_fields_save( $user_id ) {

	if ( !current_user_can( 'edit_user', $user_id ) )
		return false;
	$opts = array();
	if (isset($_POST['mk_gplus_authorship']))
	  $opts['authorship'] = true;
	if (isset($_POST['mk_gplus_avatar']))
	  $opts['avatar'] = true;
	if (isset($_POST['mk_gplus_delete'])) {
	  delete_user_meta($user_id, 'mk_gplus_link');
	  delete_user_meta($user_id, 'mk_gplus_gapi_me');
	} else {
	  if (empty($opts))
	    delete_user_meta($user_id, 'mk_gplus_link');
	  else 
	    update_user_meta( $user_id, 'mk_gplus_link', $opts );
	}
}

add_action('wp_head','mk_gplus_head');

function mk_gplus_head() {
  global $post;
  
  if (is_singular()) {
    $m = get_user_meta($post->post_author, 'mk_gplus_link',true);
	if ($m) {
	  if ($m['authorship']) {
	    $data = get_user_meta($post->post_author,'mk_gplus_gapi_me',true);
	    echo '<link href="'.$data->url.'" rel="author">';
	  }
	}
  }
  if (is_author()) {
    $m = get_user_meta($post->post_author, 'mk_gplus_link',true);
	if ($m) {
	  if ($m['authorship']) {
	    $data = get_user_meta($post->post_author,'mk_gplus_gapi_me',true);
	    echo '<link href="'.$data->url.'" rel="me">';
	  }
	}
  }
}

add_filter('get_avatar','mk_gplus_avatar',10,5); 

function mk_gplus_avatar($avatar, $id_or_email, $size, $default, $alt) {
  global $post;

  if ( is_object($id_or_email) ) { // it is a comment
    $id_or_email = (int) $id_or_email->user_id;
  } else if (is_string($id_or_email)) { // maybe email
    $user = get_user_by('email',$id_or_email);
	if ($user)
	  $id_or_email = $user->ID;
  }
  if ( is_numeric($id_or_email) ) {
    $m = get_user_meta($post->post_author, 'mk_gplus_link',true);
	if ($m) {
	  if ($m['avatar']) {
	    $data = get_user_meta($id_or_email,'mk_gplus_gapi_me',true);
	    $img = $data->image->url;
		$img = str_replace('sz=50','sz='.$size,$img);
		$avatar = preg_replace('/src=([^\s]+)\s/','src="'.$img.'"',$avatar);
	  }
	}
  }
  return $avatar;
}

function mk_gplus_l10n() {
  load_plugin_textdomain( 'mk_gplus_gapi_me', false, dirname( plugin_basename( __FILE__ ) ).'/languages/' ); 
}
add_action('admin_init', 'mk_gplus_l10n');
?>