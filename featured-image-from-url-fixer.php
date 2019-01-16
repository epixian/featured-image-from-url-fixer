<?php
/*
Plugin Name: Featured Image From URL Fixer
Description: Imports featured images hosted at external URLs and removes any associated Featured Image From URL metadata from your Wordpress posts.
Version: 0.1
Author: EPIXIAN
Author URI: https://epixian.com/
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

function epix_fifuf_tools_html() { 
?>
	<div class="wrap">
		<h1>Featured Image From URL Fixer</h1>
		<form method="POST" action="options-general.php?page=fifuf&execute=true">
			<button type="submit">Import External Featured Images</button>
<?php 
	$args = array(
		'post_type' => 'post',
		'meta_query' => array(
			array(
				'key' => 'fifu_image_url',
				'value' => 'mealsheelsandcocktails.com',
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
			echo '<ul>';
			while( $the_query->have_posts() ) {
				$the_query->the_post();

				$image_url = get_post_meta( get_the_ID(), 'fifu_image_url', true );

				$filename = basename($image_url);
				$upload_dir = wp_upload_dir();
				$new_file_path = $upload_dir['path'] . '/' . $filename;
				$new_file_url = $upload_dir['url'] . '/' . $filename;
				unlink($new_file_path);
				file_put_contents($new_file_path, file_get_contents($image_url));
				$file_mime = mime_content_type($new_file_path);
				echo '<li>' . $file_mime . ': ' . $new_file_path . '</li>';

				if( !in_array( $file_mime, get_allowed_mime_types() ) )
					die( 'WordPress doesn\'t allow this type of file.' );

				$upload_id = wp_insert_attachment( array(
					'guid'           => $new_file_url, 
					'post_mime_type' => $file_mime,
					'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
					'post_content'   => '',
					'post_status'    => 'inherit'
				), $new_file_path, get_the_ID() );

				// required for image attachments
				require_once( ABSPATH . 'wp-admin/includes/image.php' );

				// generate attachment meta
				wp_update_attachment_metadata( $upload_id, wp_generate_attachment_metadata( $upload_id, $new_file_path ) );
				
				// set the featured image
				set_post_thumbnail( get_the_ID(), $upload_id );

				// remove FIFU meta
				delete_post_meta( get_the_ID(), 'fifu_image_url' );

			}
			echo '</ul>';

			wp_reset_postdata();

		} else {
			echo '<p>No posts to fix!</p>';
		}
	}

	// otherwise just display the entries
	else {
		if ( $the_query->have_posts() ) {

			echo '<ul>';
			while ( $the_query->have_posts() ) {
				$the_query->the_post();	
				$url = get_post_meta( get_the_ID(), 'fifu_image_url', true );
				if (strlen($url) > 65) {
					$url = substr($url, 0, 25) . ' ... ' . substr($url, strlen($url) - 40);
				}
				echo '<li>' . get_the_title() . ' - ' . $url . '</li>';
			}
			echo '</ul>';

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

function epix_fifuf_add_menu() {
	add_options_page(
		'Featured Images From URL Fixer',
		'FIFU Fixer',
		'manage_options',
		'fifuf',
		'epix_fifuf_tools_html'
	);
}

function epix_fifuf_add_settings_to_plugins_page( $links ) {
    $settings_link = '<a href="options-general.php?page=fifuf">' . __( 'Settings' ) . '</a>';
    array_unshift( $links, $settings_link );
  	return $links;
}
$plugin = plugin_basename( __FILE__ );
add_filter( "plugin_action_links_$plugin", 'epix_fifuf_add_settings_to_plugins_page' );

add_action('admin_menu', 'epix_fifuf_add_menu');	