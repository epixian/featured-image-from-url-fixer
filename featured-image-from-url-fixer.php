<?php
/*
Plugin Name: Featured Image From URL Fixer
Description: Imports featured images hosted at external URLs and removes any associated Featured Image From URL metadata from your Wordpress posts.
Version: 0.2
Author: EPIXIAN
Author URI: https://epixian.com/
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

/*
 * The actual meat and potatoes of the plugin.  Displays a list of FIFU affected posts and a button to fix them.
 */
function epix_fifuf_tools_html() { 
?>
	<div class="wrap">
		<h1>Featured Image From URL Fixer</h1>
		<form method="POST" action="options-general.php?page=fifuf&execute=true">
<?php 
	$site_url = parse_url(get_site_url());
	$domain_name = $site_url['host'];
	// generate the query arguments
	$args = array(
		'post_type' => 'post',	                          // get all posts
		'meta_query' => array(  
			array(
				'key' => 'fifu_image_url',                // that have a matching meta key
				'value' => 'mealsheelsandcocktails.com',  // not matching this website
//				'value' => $domain_name,                  // not matching this website
				'compare' => 'NOT LIKE',
			),
		),
		'cache_results' => false,
		'update_post_meta_cache' => false,
	);
	$the_query = new WP_Query( $args );

	// process FIFU entries
	if ($_REQUEST['execute']) {
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		if ( $the_query->have_posts() ) {
			echo '<p>The following images were imported:</p>';
			echo '<ul>';
			while( $the_query->have_posts() ) {
				$the_query->the_post();

				// get URL of external image
				$image_url = get_post_meta( get_the_ID(), 'fifu_image_url', true );

				// extract filename portion, remove unnecessary characters
				$filename = preg_replace( '/\.[^.]+$/', '', urldecode(urldecode(basename($image_url))));
				$filename = preg_replace_callback("/(&#[0-9]+;)/", function($m) { return mb_convert_encoding($m[1], "UTF-8", "HTML-ENTITIES"); }, $filename); 

				// get Wordpress uploads directory and set save location
				$upload_dir = wp_upload_dir();
				$new_file_path = $upload_dir['path'] . '/' . $filename;

				// get URL of save location (for setting GUID)
				$new_file_url = $upload_dir['url'] . '/' . $filename;

				// download the image and get its mime type (required for adding to Media Library)
				file_put_contents($new_file_path, file_get_contents($image_url));
				$file_mime = mime_content_type($new_file_path);

				// if can't store this file in Media Library, otherwise display new filename and mime type
				if( !in_array( $file_mime, get_allowed_mime_types() ) )
					die( 'WordPress doesn\'t allow this type of file.' );
				else 
					echo '<li><a href="' . $new_file_url . '">' . $file_mime . ': ' . $new_file_path . '</a></li>';

				// required for image attachments
				require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// add image to media library and attach to post
				$upload_id = wp_insert_attachment( array(
					'guid'           => $new_file_url, 
					'post_mime_type' => $file_mime,
					'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				), $new_file_path, get_the_ID() );

				// generate meta for the image
				wp_update_attachment_metadata( $upload_id, wp_generate_attachment_metadata( $upload_id, $new_file_path ) );
				
				// set the featured image
				set_post_thumbnail( get_the_ID(), $upload_id );

				// remove FIFU meta
				delete_post_meta( get_the_ID(), 'fifu_image_url' );

			}
			echo '</ul>';
			echo '<button onClick="window.location=\'options-general.php?page=fifuf&execute=false\'">Rescan for External Images</button>';

			wp_reset_postdata();

		} else {
			echo '<p>No posts to fix!</p>';
		}
	}

	// otherwise just display the entries
	else {
		if ( $the_query->have_posts() ) {
			echo '<p>Posts with external featured images:</p>';
			echo '<ul>';
			while ( $the_query->have_posts() ) {
				$the_query->the_post();	
				$url = get_post_meta( get_the_ID(), 'fifu_image_url', true );

				// truncate the URL if necessary (for display purposes)
				if (strlen($url) > 65)
					$disp_url = substr($url, 0, 25) . ' ... ' . substr($url, strlen($url) - 40);
				else
					$disp_url = $url;
				echo '<li><a href="/?p=' . get_the_ID() . '">' . get_the_title() . '</a> - <a href="' . $url . '">' . $disp_url . '</a></li>';
			}
			echo '</ul>';
			echo '<button type="submit">Import External Featured Images</button>';
			wp_reset_postdata();

		} else {
			echo '<p>No posts to fix!</p>';
		}
	}
?>
		</form>
	</div>
<?php 
}

/*
 * Add submenu item to the Settings menu
 */
function epix_fifuf_add_menu() {
	add_options_page(
		'Featured Images From URL Fixer',
		'FIFU Fixer',
		'manage_options',
		'fifuf',
		'epix_fifuf_tools_html'
	);
}
add_action('admin_menu', 'epix_fifuf_add_menu');	

/*
 * Add a direct link to the Settings page from the list of installed plugins
 */
function epix_fifuf_add_settings_to_plugins_page( $links ) {
    $settings_link = '<a href="options-general.php?page=fifuf">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
  	return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'epix_fifuf_add_settings_to_plugins_page' );