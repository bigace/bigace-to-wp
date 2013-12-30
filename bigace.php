<?php
/*
Plugin Name: Bigace to Wordpress importer
Description: Import content from a Bigace powered site into WordPress
License: Public Domain or CC0 ( http://creativecommons.org/publicdomain/zero/1.0/ )
Version: 0.1
Changelog: Please see README.md
Kudos: Code was forked from the s9y (serendipity) importer and adjusted to work with Bigace, see https://raw.github.com/ShakataGaNai/s9y-to-wp/
*/

define('BIGACE_IMPORTER_PASSWORD', 'password123');
define('BIGACE_IMPORTER_RICHEDIT', 'false');

set_time_limit(0);
ini_set('display_errors', true);

/* borrowed from movable type importer plugin */
if ( !defined('WP_LOAD_IMPORTERS') )
	return;


// Load Importer API
require_once ABSPATH . 'wp-admin/includes/import.php';

if ( !class_exists( 'WP_Importer' ) ) {
	$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
	if ( file_exists( $class_wp_importer ) )
		require_once $class_wp_importer;
}
/* End borrowed */

/**
	Add These Functions to make our lives easier
**/


if(!function_exists('get_catbynicename')) {
    function get_catbynicename($category_nicename) {
        global $wpdb;
	    $name = $wpdb->get_var('SELECT cat_ID FROM '.$wpdb->categories.' WHERE category_nicename="'.$category_nicename.'"');
	    return $name;
    }
}

if(!function_exists('get_comment_count'))
{
	function get_comment_count($post_ID)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT count(*) FROM '.$wpdb->comments.' WHERE comment_post_ID = '.$post_ID);
	}
}

if(!function_exists('link_exists'))
{
	function link_exists($linkname)
	{
		global $wpdb;
		return $wpdb->get_var('SELECT link_id FROM '.$wpdb->links.' WHERE link_name = "'.$wpdb->escape($linkname).'"');
	}
}

if(!function_exists('find_comment_parent'))
{
	function find_comment_parent($haystack, $needle)
	{
		ini_set('display_errors', true);
		foreach($haystack as $h)
		{
			if($h[0] == $needle)
				return $h[1];
		}
	}
}

if(!function_exists('get_cat_by_name'))
{
	function get_cat_by_name($cat_name)
	{
		global $wpdb;
		return $wpdb->get_var("SELECT term_id from ".$wpdb->terms." WHERE name = '$cat_name'");
	}
}

// --

/**
	The Main Importer Class
**/
class Bigace_Import extends WP_Importer
{

    function connect_bigacedb()
    {
        $bigacedb = new wpdb(
            get_option('bigaceuser'),
            get_option('bigacepass'),
            get_option('bigacename'),
            get_option('bigacehost')
        );
        $bigacedb->set_charset($bigacedb->dbh, get_option('bigacecharset'));
 		set_magic_quotes_runtime(0);
		return $bigacedb;
    }

	function header() 
	{
		echo '<div class="wrap">';
		echo '<h2>'.__('Import from Bigace').'</h2>';
		echo '<p>'.__('Steps may take a few minutes depending on the size of your database. Please be patient.').'</p>';
	}

	function footer() 
	{
		echo '</div>';
	}
	
	function greet() 
	{
		echo '<p>'.__('Howdy! This importer allows you to extract posts from any Bigace v2.7.8 site into your blog. This has not been tested on previous versions of Wordpress. Mileage may vary.').'</p>';
		echo '<p>'.__('Your Bigace configuration settings are as follows:').'</p>';
		echo '<form action="admin.php?import=bigace&amp;step=1" method="post">';
		$this->db_form();
		echo '<input type="submit" name="submit" value="'.__('Import Categories').'" />';
		echo '</form>';
	}

	function get_bigace_cats()
	{
		global $wpdb;
		// General Housekeeping
		$bigacedb = $this->connect_bigacedb(); 
		$prefix = get_option('spre');
		$cid = get_option('bigacecommunityid');

		// Get Categories
		return $bigacedb->get_results('SELECT A.name as category_name, A.id as categoryid, B.name AS parentname 
									FROM '.$prefix.'category A
									LEFT JOIN '.$prefix.'category B ON A.parentid = B.id
									WHERE a.cid='.$cid.' and b.cid='.$cid.'
									ORDER BY A.parentid, A.id',
									 ARRAY_A);
	}
	
	function get_bigace_users()
	{
		global $wpdb;
		// General Housekeeping
		$bigacedb = $this->connect_bigacedb(); 
		$prefix = get_option('spre');
		$cid = get_option('bigacecommunityid');
		
		// Get Users
		
		return $bigacedb->get_results('SELECT a.id as userid, a.username,
							b.attribute_value as realname,
							a.email,
							"author" as userlevel
							FROM '.$prefix.'user a
							LEFT JOIN '.$prefix.'user_attributes b ON a.id = b.userid
					    WHERE a.cid = '.$cid.' and b.cid='.$cid.' and b.attribute_name="firstname"', ARRAY_A);
	}
	
	function get_bigace_posts($start=0)
	{
		// General Housekeeping
		$bigacedb = $this->connect_bigacedb(); 
		$prefix = get_option('spre');
		$cid = get_option('bigacecommunityid');
		
		$posts = $bigacedb->get_results('SELECT 
							id,
							createdate as timestamp,
							createby as authorid,
							modifieddate as last_modified,
							name as title,
							text_1 as filename,
							description as body,
							catchwords as tags, 
							unique_name as permalink,
							viewed
 			   		      FROM '.$prefix.'item_1 WHERE cid='.$cid.' LIMIT '.$start.',100', ARRAY_A);
		
		return $posts;
	}

	function get_bigace_comments()
	{
		global $wpdb;
		// General Housekeeping
		$bigacedb = $this->connect_bigacedb(); 
		$prefix = get_option('spre');
		$cid = get_option('bigacecommunityid');
		
		// Get Comments
		return $bigacedb->get_results('SELECT * FROM '.$prefix.'comments', ARRAY_A);
	}
	
	/**
	 * FIXME
	 */
	function get_bigace_cat_assoc($post_id)
	{
		global $wpdb;
		// General Housekeeping
		$bigacedb = $this->connect_bigacedb(); 
		$prefix = get_option('spre');
		$cid = get_option('bigacecommunityid');
		
		return $bigacedb->get_results("select * from ".$prefix."category c, ".$prefix."item_category ic, where ".$prefix."entries.id = ".$prefix."entrycat.entryid and ".$prefix."category.categoryid=".$prefix."entrycat.categoryid and ".$prefix."entries.id =$post_id;");
	}
	
	// ------------------------------------------------------------------------------------------------------------------------
	
	function cat2wp($categories='') 
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$bigacecat2wpcat = array();
		// Do the Magic
		if(is_array($categories))
		{
			echo '<p>'.__('Importing Categories...').'<br /><br /></p>';
			
			foreach ($categories as $category) 
			{
				$count++;
				extract($category);
				
				
				// Make Nice Variables
				$title = $wpdb->escape($category_name);
				$slug = sanitize_title($title);
				//$slug = $categoryid . '-' . sanitize_title($title);
				$name = $wpdb->escape($title);
				$parent = $bigacecat2wpcat[ $parentid ];
				
				$args = array('category_nicename' => $slug, 'cat_name' => $name );
				
				if( !empty( $parentid ))
					$args[ 'category_parent' ] = $parent;

				$ret_id = wp_insert_category($args);
				$bigacecat2wpcat[$categoryid] = $ret_id;
			}
			
			// Store category translation for future use
			add_option('bigacecat2wpcat', $bigacecat2wpcat);
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> categories imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Categories to Import!');
		return false;
	}
	
	function users2wp($users='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$bigaceid2wpid = array();
		
		// Midnight Mojo
		if(is_array($users))
		{
			echo '<p>'.__('Importing Users...').'<br /><br /></p>';
			foreach($users as $user)
			{
				extract($user);
				
				// anonymous
				if ($userid == 2) {
					continue;
				}

				// administrator
				if ($userid == 1) {
					//continue;
				}

                $count++;

                // Make Nice Variables
				$user_login = $wpdb->escape($username);
				$user_name = $wpdb->escape($realname);
				
				if($uinfo = get_user_by('login', $user_login))
				{
					
					wp_insert_user(array(
								'ID'			=> $uinfo->ID,
								'user_login'	=> $user_login,
								'user_nicename'	=> $user_name,
								'user_email'	=> $email,
								'user_url'		=> 'http://',
								'display_name'	=> $user_login)
								);
					$ret_id = $uinfo->ID;
				}
				else 
				{
					$ret_id = wp_insert_user(array(
								'user_login'	=> $user_login,
								'user_pass'     => BIGACE_IMPORTER_PASSWORD,
								'user_nicename'	=> $user_name,
								'user_email'	=> $email,
								'user_url'	=> 'http://',
								'display_name'	=> $user_name)
								);
				}
				$bigaceid2wpid[$user_id] = $ret_id;
				
				// Update Usermeta Data
				$user = new WP_User($ret_id);
				if($userid == 1) { 
					$user->set_role('administrator'); 
					update_user_meta( $ret_id, 'wp_user_level', '10' );
				} else {
					$user->set_role('editor');
					update_user_meta( $ret_id, 'wp_user_level', '5' );
				}
				//$user->set_role('contributor');
				//update_user_meta( $ret_id, 'wp_user_level', '2' );
				
				update_user_meta( $ret_id, 'rich_editing', BIGACE_IMPORTER_RICHEDIT); // ???

			}// End foreach($users as $user)
			
			// Store id translation array for future use
			add_option('bigaceid2wpid', $bigaceid2wpid);
			
			
			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> users imported.'), $count).'<br /><br /></p>';
			return true;
		}// End if(is_array($users)
		
		echo __('No Users to Import!');
		return false;
	}
	
	/**
	 * FIXME
	 */
	function posts2wp($posts='')
	{
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$bigaceposts2wpposts = get_option('bigaceposts2wpposts');
		if ( !$bigaceposts2wpposts ) $bigaceposts2wpposts = array();

		$cats = array();

		// Do the Magic
		if(is_array($posts))
		{
			echo '<p>'.__('Importing Posts...').'<br /><br /></p>';
			foreach($posts as $post)
			{
				$count++;
				if(!is_array($post))
					$post = (array) $post;

				extract($post);
				
				$post_id = $id;
				
				$uinfo = ( get_user_by( 'login', $author ) ) ? get_user_by( 'login', $author ) : 1;
				$authorid = ( is_object( $uinfo ) ) ? $uinfo->ID : $uinfo ;

				$post_title = $wpdb->escape($title);

				// dobschat - 2008/11/08 - split body and extended text in wp with <!--more-->
				if ($wpdb->escape($extended) != "") {
					$post_body = $wpdb->escape($body)."<!--more-->".$wpdb->escape($extended);
				} else {
					$post_body = $wpdb->escape($body);
				}
				//
				$post_time = date('Y-m-d H:i:s', $timestamp);
				$post_modified = date('Y-m-d H:i:s', $last_modified);
				
				// Import Post data into WordPress
				
				if($pinfo = post_exists($post_title,$post_body))
				{
					$ret_id = wp_insert_post(
                        array(
							'ID'				=> $pinfo,
							'post_date'			=> $post_time,
							'post_date_gmt'		=> $post_time,
							'post_author'		=> $authorid,
							'post_modified'		=> $post_modified,
							'post_modified_gmt' => $post_modified_gmt,
							'post_title'		=> $post_title,
							'post_content'		=> $post_body,
							'post_status'		=> $post_status,
							'post_name'			=> sanitize_title($post_title)
						)
					);
				}
				else 
				{
					$ret_id = wp_insert_post(
                        array(
							'post_date'			=> $post_time,
							'post_date_gmt'		=> $post_time,
							'post_author'		=> $authorid,
							'post_modified'		=> $post_modified,
							'post_modified_gmt' => $post_modified,
							'post_title'		=> $post_title,
							'post_content'		=> $post_body,
							'post_status'		=> $post_status,
							'menu_order'		=> $post_id,
							'post_name'			=> sanitize_title($post_title)
                        )
					);
				}
				$bigaceposts2wpposts[] = array($id, $ret_id);
				
				// Make Post-to-Category associations
				$cats = $this->get_bigace_cat_assoc($id);
				
				$wpcats = array();
				if ( is_array($cats) )
				foreach($cats as $cat)
				{
					$c = get_category_by_slug($cat->categoryid . '-' . sanitize_title($cat->category_name));
					$wpcats[] = $c->term_id;
				}
				$cats = (is_array($wpcats)) ? $wpcats : (array) $wpcats;
				
				if(!empty($cats)) { wp_set_post_categories( $ret_id, $cats); }
				else { wp_set_post_categories( $ret_id, get_option('default_category')); }
			}
		}
		// Store ID translation for later use
		update_option('bigaceposts2wpposts', $bigaceposts2wpposts);
		
		echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> posts imported.'), $count).'<br /><br /></p>';
		return true;	
	}
	
	/**
	 * FIXME
	 */
	function comments2wp($comments='')
	{
		ini_set('display_errors', true);
		// General Housekeeping
		global $wpdb;
		$count = 0;
		$bigacecm2wpcm = array();
		$postarr = get_option('bigaceposts2wpposts');
		
		
		// Magic Mojo
		if(is_array($comments))
		{
			echo '<p>'.__('Importing Comments...').'<br /><br /></p>';
			foreach($comments as $comment)
			{
				$count++;
				extract($comment);
				// WordPressify Data
				$comment_ID = (int) $id;
				//$comment_post_ID = find_comment_parent($postarr, $id);
				$comment_approved = ($status == 'approved') ? 1 : 0;
				//parent comment not parent post;
				$comment_parent=$parent_id;
				$name = $wpdb->escape(($author));
				$email = $wpdb->escape($email);
				$web = $wpdb->escape($url);
				$message = $wpdb->escape($body);
				$wpdb->show_errors();
				$posted = date('Y-m-d H:i:s', $timestamp);
				if(comment_exists($name, $posted) > 0)
				{
					//$cinfo = comment_exists($name, $posted);
					// Update comments

					$ret_id = wp_update_comment(array(
							'comment_ID'			=> $comment_ID,
							'comment_post_ID'		=> find_comment_parent($postarr, $entry_id),
							'comment_author'		=> $name,
							'comment_parent'		=> $comment_parent,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'		=> $ip,
							'comment_date'			=> $posted,
							'comment_content'		=> $message,
							'comment_approved'		=> $comment_approved)
							);
				}
				else 
				{
					// Insert comments
					$ret_id = wp_insert_comment(array(
							'comment_ID'			=> $comment_ID,
							'comment_post_ID'		=> find_comment_parent($postarr, $entry_id),
							'comment_author'		=> $name,
							'comment_author_email'	=> $email,
							'comment_author_url'	=> $web,
							'comment_author_IP'		=> $ip,
							'comment_date'			=> $posted,
							'comment_content'		=> $message,
							'comment_parent'		=> $comment_parent,
							'comment_approved'		=> $comment_approved)

							);
				$wpdb->query("UPDATE $wpdb->comments SET comment_ID=$comment_ID WHERE comment_ID=$ret_id");
				}
				//$bigacecm2wpcm[$comment_ID] = $ret_id;
			}

			// Store Comment ID translation for future use
			//add_option('bigacecm2wpcm', $bigacecm2wpcm);		

			// Associate newly formed categories with posts
			//get_comment_count($ret_id);

			echo '<p>'.sprintf(__('Done! <strong>%1$s</strong> comments imported.'), $count).'<br /><br /></p>';
			return true;
		}
		echo __('No Comments to Import!');
		return false;
	}

    // Category Import
	function import_categories()
	{	
		$cats = $this->get_bigace_cats();
		$this->cat2wp($cats);
		add_option('bigace_cats', $cats);
			
		echo '<form action="admin.php?import=bigace&amp;step=2" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Users'));
		echo '</form>';

	}

    // User Import
	function import_users()
	{
		$users = $this->get_bigace_users();
		$this->users2wp($users);
		
		echo '<form action="admin.php?import=bigace&amp;step=3" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Posts'));
		echo '</form>';
	}

    /**
     * FIXME
     * Import posts
     */
    function import_posts()
	{
		// Process 100 posts per load, and reload between runs
		$start = $_REQUEST["start"];
		if ( !$start ) $start = 0;
		$posts = $this->get_bigace_posts($start);
		
		if ( count($posts) != 0 ) {
			$this->posts2wp($posts);
        }
			
		if ( count($posts) >= 100 ) 
		{
			echo "Reloading: More work to do.";
			$url = "admin.php?import=bigace&step=3&start=".($start+100);
			?>
			<script type="text/javascript">
			window.location = '<?php echo $url; ?>';
			</script>
			<?php
			return;
		}
		
		echo '<form action="admin.php?import=bigace&amp;step=4" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Import Comments'));
		echo '</form>';
	}

    /**
     * Comment Import
     * FIXME
     */
	function import_comments()
	{
		$comments = $this->get_bigace_comments();
		$this->comments2wp($comments);
		
		echo '<form action="admin.php?import=bigace&amp;step=5" method="post">';
		printf('<input type="submit" name="submit" value="%s" />', __('Finish'));
		echo '</form>';
	}	
	
	function cleanup_bigaceimport()
	{
		delete_option('spre');
		delete_option('bigace_cats');
		delete_option('bigaceid2wpid');
		delete_option('bigacecat2wpcat');
		delete_option('bigaceposts2wpposts');
		delete_option('bigacecm2wpcm');
		delete_option('bigacelinks2wplinks');
		delete_option('bigaceuser');
		delete_option('bigacepass');
		delete_option('bigacename');
		delete_option('bigacehost');
		$this->tips();
	}
	
	function tips()
	{
		echo '<p>'.__('Welcome to WordPress.  We hope (and expect!) that you will find this platform incredibly rewarding!  As a new WordPress user coming from Bigace, there are some things that we would like to point out.  Hopefully, they will help your transition go as smoothly as possible.').'</p>';
		echo '<h3>'.__('Users').'</h3>';
		echo '<p>'.__('You have already setup WordPress and have been assigned an administrative login and password.  Forget it.  You didn\'t have that login in Bigace, why should you have it here?  Instead we have taken care to import all of your users into our system.  Unfortunately there is one downside.  Because both WordPress and Bigace uses a strong encryption hash with passwords, it is impossible to decrypt it and we are forced to assign temporary passwords to all your users.') . ' <strong>' . __( 'Every user has the same username, but their passwords are reset to "'.BIGACE_IMPORTER_PASSWORD.'". It is strongly recommended that you change passwords for all users immediately.') .'</strong></p>';
		echo '<h3>'.__('Preserving Authors').'</h3>';
		echo '<p>'.__('Secondly, we have attempted to preserve post authors.  If you are the only author or contributor to your blog, then you are safe.  In most cases, we are successful in this preservation endeavor.  However, if we cannot ascertain the name of the writer due to discrepancies between database tables, we assign it to you, the administrative user.').'</p>';
			echo '<h3>'.__('WordPress Resources').'</h3>';
		echo '<p>'.__('Finally, there are numerous WordPress resources around the internet.  Some of them are:').'</p>';
		echo '<ul>';
		echo '<li>'.__('<a href="http://www.wordpress.org">The official WordPress site</a>').'</li>';
		echo '<li>'.__('<a href="http://wordpress.org/support/">The WordPress support forums').'</li>';
		echo '<li>'.__('<a href="http://codex.wordpress.org">The Codex (In other words, the WordPress Bible)</a>').'</li>';
		echo '</ul>';
		echo '<p>'.sprintf(__('That\'s it! What are you waiting for? Go <a href="%1$s">login</a>!'), '/wp-login.php').'</p>';
	}
	
	function db_form()
	{
		echo '<ul>';
		printf('<li><label for="dbuser">%s</label> <input type="text" name="dbuser" id="dbuser" /></li>', __('bigace Database User:'));
		printf('<li><label for="dbpass">%s</label> <input type="password" name="dbpass" id="dbpass" /></li>', __('bigace Database Password:'));
		printf('<li><label for="dbname">%s</label> <input type="text" id="dbname" name="dbname" /></li>', __('bigace Database Name:'));
		printf('<li><label for="dbhost">%s</label> <input type="text" id="dbhost" name="dbhost" value="localhost" /></li>', __('bigace Database Host:'));
		printf('<li><label for="dbprefix">%s</label> <input type="text" name="dbprefix" id="dbprefix"  value="cms_" /></li>', __('bigace Table prefix (if any):'));
		printf('<li><label for="dbcharset">%s</label> <input type="text" name="dbcharset" id="dbcharset" value="utf8" /></li>', __('bigace Table charset:'));
		printf('<li><label for="dbcid">%s</label> <input type="text" name="dbcid" id="dbcid" value="1" /></li>', __('bigace Community ID:'));
		echo '</ul>';
	}
	
	function dispatch() 
	{
		if (empty ($_GET['step'])) {
			$step = 0;
        } else {
			$step = (int) $_GET['step'];
        }
		$this->header();
		
		if ( $step > 0 ) 
		{
			if($_POST['dbuser'])
			{
				if(get_option('bigaceuser'))
					delete_option('bigaceuser');	
				add_option('bigaceuser',$_POST['dbuser']);
			}
			if($_POST['dbpass'])
			{
				if(get_option('bigacepass'))
					delete_option('bigacepass');	
				add_option('bigacepass',$_POST['dbpass']);
			}
			
			if($_POST['dbname'])
			{
				if(get_option('bigacename'))
					delete_option('bigacename');	
				add_option('bigacename',$_POST['dbname']);
			}
			if($_POST['dbhost'])
			{
				if(get_option('bigacehost'))
					delete_option('bigacehost');
				add_option('bigacehost',$_POST['dbhost']); 
			}
			if($_POST['dbprefix'])
			{
				if(get_option('spre'))
					delete_option('spre');
				add_option('spre',$_POST['dbprefix']); 
			}			
			if($_POST['dbcharset'])
			{
				if(get_option('bigacecharset'))
					delete_option('bigacecharset');
				add_option('bigacecharset',$_POST['dbcharset']); 
			}			
			if($_POST['dbcid'])
			{
				if(get_option('bigacecommunityid'))
					delete_option('bigacecommunityid');
				add_option('bigacecommunityid',$_POST['dbcid']); 
			}
		}

		switch ($step) 
		{
			default:
			case 0 :
				$this->greet();
				break;
			case 1 :
				$this->import_categories();
				break;
			case 2 :
				$this->import_users();
				break;
			case 3 :
				$this->import_posts();
				break;
			case 4 :
				$this->import_comments();
				break;
			case 5 :
				$this->cleanup_bigaceimport();
				break;
		}
		
		$this->footer();
	}
}

$bigace_import = new Bigace_Import();
register_importer('bigace', 'Bigace', __('Import data from a Bigace site'), array ($bigace_import, 'dispatch'));

