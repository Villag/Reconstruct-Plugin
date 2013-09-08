<?php
/*
Plugin Name: Reconstruct
Description:
Version: 0.1
Author: Patrick Daly
*/

add_action( 'init', 'register_cpt_project' );
add_action( 'wp_enqueue_scripts', 'reconstruct_enqueue_scripts' );
add_action( 'wp_ajax_new-project', 'reconstruct_new_project' );
add_action( 'wp_ajax_nopriv_new-project', 'reconstruct_new_project' );
add_action( 'wp_ajax_fork', 'reconstruct_fork' );
add_action( 'wp_ajax_nopriv_fork', 'reconstruct_fork' );

/**
 * Queue static resources
 *
 * @since 0.1.0
 */
function reconstruct_enqueue_scripts() {

	// Queue JS
	wp_enqueue_script( 'app', plugin_dir_url( __FILE__ ) . 'app.js', array( 'jquery' ), false, true );
	wp_localize_script( 'app', 'Reconstruct', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );


}

function register_cpt_project() {

    $labels = array(
        'name' => _x( 'Projects', 'project' ),
        'singular_name' => _x( 'Project', 'project' ),
        'add_new' => _x( 'Add New', 'project' ),
        'add_new_item' => _x( 'Add New Project', 'project' ),
        'edit_item' => _x( 'Edit Project', 'project' ),
        'new_item' => _x( 'New Project', 'project' ),
        'view_item' => _x( 'View Project', 'project' ),
        'search_items' => _x( 'Search Projects', 'project' ),
        'not_found' => _x( 'No projects found', 'project' ),
        'not_found_in_trash' => _x( 'No projects found in Trash', 'project' ),
        'parent_item_colon' => _x( 'Parent Project:', 'project' ),
        'menu_name' => _x( 'Projects', 'project' ),
    );

    $args = array(
        'labels' => $labels,
        'hierarchical' => true,

        'supports' => array( 'title', 'editor', 'excerpt', 'author', 'custom-fields', 'comments', 'revisions', 'page-attributes', 'thumbnail' ),
        'taxonomies' => array( 'category' ),
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 5,

        'show_in_nav_menus' => true,
        'publicly_queryable' => true,
        'exclude_from_search' => false,
        'has_archive' => true,
        'query_var' => true,
        'can_export' => true,
        'rewrite' => true,
        'capability_type' => 'post'
    );

    register_post_type( 'project', $args );
}

/**
* Inserts project submitted via POST
*/
function reconstruct_new_project() {

    $current_user = wp_get_current_user();

	$message = array();

	$post_title = $_POST['post_title'];
    $post_title = $_POST['post_title'];

	// Create post object
	$args = array(
        'post_author'   => $current_user->ID,
		'post_type'		=> 'project',
		'post_title'    => $post_title,
        'post_status'   => 'publish'
	);

	// Insert the post into the database and add its meta
	$post_id = wp_insert_post( $args, false );

	// If there's an error or not
	if ( is_wp_error( $post_id ) ) {
		$status = 'error';
		$message[] = $id->get_error_messages();
	} else {
		$status = 'success';

		// Loop through the POST array and insert each key as custom meta
		foreach( $_POST as $key => $val ) {
			update_post_meta( $post_id, $key, $val );
		}

        // If the upload field has a file in it
        if(isset($_FILES['reconstruct_original_image']) && ($_FILES['reconstruct_original_image']['size'] > 0)) {

            // Get the type of the uploaded file. This is returned as "type/extension"
            $arr_file_type = wp_check_filetype(basename($_FILES['reconstruct_original_image']['name']));
            $uploaded_file_type = $arr_file_type['type'];

            // Set an array containing a list of acceptable formats
            $allowed_file_types = array('image/jpg','image/jpeg','image/gif','image/png');

            // If the uploaded file is the right format
            if(in_array($uploaded_file_type, $allowed_file_types)) {

                // Options array for the wp_handle_upload function. 'test_upload' => false
                $upload_overrides = array( 'test_form' => false );

                // Handle the upload using WP's wp_handle_upload function. Takes the posted file and an options array
                $uploaded_file = wp_handle_upload($_FILES['reconstruct_original_image'], $upload_overrides);

                // If the wp_handle_upload call returned a local path for the image
                if(isset($uploaded_file['file'])) {

                    // The wp_insert_attachment function needs the literal system path, which was passed back from wp_handle_upload
                    $file_name_and_location = $uploaded_file['file'];

                    // Generate a title for the image that'll be used in the media library
                    $file_title_for_media_library = 'your title here';

                    // Set up options array to add this file as an attachment
                    $attachment = array(
                        'post_mime_type' => $uploaded_file_type,
                        'post_title' => 'Uploaded image ' . addslashes($file_title_for_media_library),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    );

                    // Run the wp_insert_attachment function. This adds the file to the media library and generates the thumbnails. If you wanted to attch this image to a post, you could pass the post id as a third param and it'd magically happen.
                    $attach_id = wp_insert_attachment( $attachment, $file_name_and_location, $post_id );
                    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
                    wp_update_attachment_metadata($attach_id,  $attach_data);

                    // Before we update the post meta, trash any previously uploaded image for this post.
                    // You might not want this behavior, depending on how you're using the uploaded images.
                    $existing_uploaded_image = (int) get_post_meta($post_id,'_reconstruct_original_image', true);
                    if(is_numeric($existing_uploaded_image)) {
                        wp_delete_attachment($existing_uploaded_image);
                    }

                    // Now, update the post meta to associate the new image with the post
                    update_post_meta($post_id,'_reconstruct_original_image',$attach_id);
                    update_post_meta($post_id, '_thumbnail_id', $attach_id);

                    // Set the feedback flag to false, since the upload was successful
                    $message[] = 'Your original image was successfully updated';
                    $reconstruct_original_image_url = wp_get_attachment_url( $attach_id );


                } else { // wp_handle_upload returned some kind of error. the return does contain error details, so you can use it here if you want.

                    $message[] = 'There was a problem with your upload.';

                }

            } else { // wrong file type

                $message[] = 'Please upload only image files (jpg, gif or png).';

            }

        } else { // No file was passed

            $message[] = 'You did not specify an image';

        }

        // If the upload field has a file in it
        if(isset($_FILES['reconstruct_revised_image']) && ($_FILES['reconstruct_revised_image']['size'] > 0)) {

            // Get the type of the uploaded file. This is returned as "type/extension"
            $arr_file_type = wp_check_filetype(basename($_FILES['reconstruct_revised_image']['name']));
            $uploaded_file_type = $arr_file_type['type'];

            // Set an array containing a list of acceptable formats
            $allowed_file_types = array('image/jpg','image/jpeg','image/gif','image/png');

            // If the uploaded file is the right format
            if(in_array($uploaded_file_type, $allowed_file_types)) {

                // Options array for the wp_handle_upload function. 'test_upload' => false
                $upload_overrides = array( 'test_form' => false );

                // Handle the upload using WP's wp_handle_upload function. Takes the posted file and an options array
                $uploaded_file = wp_handle_upload($_FILES['reconstruct_revised_image'], $upload_overrides);

                // If the wp_handle_upload call returned a local path for the image
                if(isset($uploaded_file['file'])) {

                    // The wp_insert_attachment function needs the literal system path, which was passed back from wp_handle_upload
                    $file_name_and_location = $uploaded_file['file'];

                    // Generate a title for the image that'll be used in the media library
                    $file_title_for_media_library = 'your title here';

                    // Set up options array to add this file as an attachment
                    $attachment = array(
                        'post_mime_type' => $uploaded_file_type,
                        'post_title' => 'Uploaded image ' . addslashes($file_title_for_media_library),
                        'post_content' => '',
                        'post_status' => 'inherit'
                    );

                    // Run the wp_insert_attachment function. This adds the file to the media library and generates the thumbnails. If you wanted to attch this image to a post, you could pass the post id as a third param and it'd magically happen.
                    $attach_id = wp_insert_attachment( $attachment, $file_name_and_location, $post_id );
                    require_once(ABSPATH . "wp-admin" . '/includes/image.php');
                    $attach_data = wp_generate_attachment_metadata( $attach_id, $file_name_and_location );
                    wp_update_attachment_metadata($attach_id,  $attach_data);

                    // Before we update the post meta, trash any previously uploaded image for this post.
                    // You might not want this behavior, depending on how you're using the uploaded images.
                    $existing_uploaded_image = (int) get_post_meta($post_id,'_reconstruct_revised_image', true);
                    if(is_numeric($existing_uploaded_image)) {
                        wp_delete_attachment($existing_uploaded_image);
                    }

                    // Now, update the post meta to associate the new image with the post
                    update_post_meta($post_id,'_reconstruct_revised_image',$attach_id);
                    update_post_meta($post_id, '_thumbnail_id', $attach_id);

                    // Set the feedback flag to false, since the upload was successful
                    $message[] = 'Your original image was successfully updated';
                    $reconstruct_revised_image_url = wp_get_attachment_url( $attach_id );


                } else { // wp_handle_upload returned some kind of error. the return does contain error details, so you can use it here if you want.

                    $message[] = 'There was a problem with your upload.';

                }

            } else { // wrong file type

                $message[] = 'Please upload only image files (jpg, gif or png).';

            }

        } else { // No file was passed

            $message[] = 'You did not specify an image';

        }
	}

	$response = array (

		'status'	=> $status,
		'data'		=> array(
			'message'	=> $message,
            'reconstruct_original_image_url' => $reconstruct_original_image_url,
            'reconstruct_revised_image_url' => $reconstruct_revised_image_url
		)

	);

	echo json_encode( $response );

	die();

}

function reconstruct_fork() {

    $current_user = wp_get_current_user();

    $message = array();
    $status = '';

    $post_id = $_POST['post_id'];

    $fork = new Fork();
    $fork->fork( $post_id, $current_user->ID );

    $response = array (

        'status'    => $status,
        'data'      => array(
            'message'   => $message
        )

    );

    echo json_encode( $response );

    die();

}