<?php
/*
Plugin Name: WP PDF Thumbnails
Version: 1.0.0
Author: Dave Long
Description: Creates a thumbnail from the first page of uploaded PDF(s).
*/

if ( !defined( 'ABSPATH' ) ) {
	die( 'Direct access not allowed.' );
}

$pdf_thumbnails = new WP_PDF_Thumbnails;

/**
 * PDF Thumbnails Class
 *
 * Create thumbnail from first page of uploaded PDF(s).
 */
class WP_PDF_Thumbnails {

	private $imagick_available;
	
	/**
	 * Constructor - Set up action hooks
	 *
	 * @return WP_PDF_Thumbnails
	 */
	public function __construct() {
		// Check for ImageMagick extension & disable thumbnail creation if not found
		if( !class_exists( 'Imagick' ) ) {
			$this->imagick_available = false;
		}
		else {
			$this->imagick_available = true;
		}

		/* WordPress Admin Actions */
		add_action( 'admin_head', array( &$this, 'check_plugin_dependencies' ) );
		add_action( 'delete_attachment', array( &$this, 'action_delete_attachment' ) );
		add_action( 'edit_attachment', array( &$this, 'update_attachment_meta' ) );

		/* WordPress Filters */
		add_filter( 'add_attachment', array( &$this, 'add_attachment' ), 10, 2 );
		add_filter( 'icon_dir' , array( &$this, 'filter_icon_directory' ), 10, 1 );
		add_filter( 'media_send_to_editor', array( &$this, 'wp_editor_thumbnail' ), 100, 3 );
		add_filter( 'wp_mime_type_icon', array( &$this, 'change_attachment_icon' ), 10, 3 );
	}

	/**********    PUBLIC FUNCTIONS    **********/

	/**
	 * Delete the associated thumbnail when a PDF is deleted from the Media Libray
	 *
	 * @param int $attachment_id
	 * @return void
	 */
	public function action_delete_attachment( $attachment_id ) {
		// If this is a PDF, delete the thumbnail too
		if( 'application/pdf' == get_post_mime_type ( $attachment_id ) ){
			$thumbnail_id = get_post_meta( $attachment_id, '_thumbnail_id', true );
			if( isset ( $thumbnail_id ) ){
				wp_delete_post( $thumbnail_id );
			}
		}
	}

	/**
	 * Generate thumbnail from PDF attachment
	 *
	 * @param int $attachment_id
	 * @return int $attachment_id
	 */
	public function add_attachment( $attachment_id ) {
		if( $this->imagick_available && 'application/pdf' == get_post_mime_type ( $attachment_id ) ) {
			$file = get_attached_file( $attachment_id );
			$file_name = esc_attr( get_the_title( $attachment_id ) );
			$thumbnail_url = $this->generate_pdf_thumbnail( $file );
			if( file_exists( $thumbnail_url ) ){
				$dir_url = wp_get_attachment_url( $attachment_id );
				$attachment = array(
					'post_type' => 'attachment',
					'post_mime_type' => 'image/jpeg',
					'post_title' => $file_name,
					'post_parent' => $attachment_id
				);
				$thumbnail_id = wp_insert_attachment( $attachment, $thumbnail_url );
				$thumbnail_metadata = wp_generate_attachment_metadata( $thumbnail_id, $thumbnail_url );
				wp_update_attachment_metadata( $thumbnail_id, $thumbnail_metadata );
				update_post_meta( $attachment_id, '_thumbnail_id', $thumbnail_id );
			}
		}
		return $attachment_id;
	}

	/**
	 * Display thumbnail instead of document.png for PDF attachments
	 *
	 * @param string $icon - Path to the mime type icon.
	 * @param string $mime - Mime type.
	 * @param int $attachment_id
	 * @return string $icon - link to thumbnail
	 */
	public function change_attachment_icon ( $icon, $mime, $attachment_id ) {
		if( 'application/pdf' == $mime ){
			$metadata = wp_get_attachment_metadata ( $attachment_id );
			$thumbnail_id = get_post_meta( $attachment_id, '_thumbnail_id', true );
			if( $thumbnail_id ){
				$thumbnail = wp_get_attachment_image_src ( $thumbnail_id, 'medium' );
				$icon = $thumbnail[0];
			}
		}
		return $icon;
	}

	/**
	 * Check that ImageMagick available
	 * If no ImageMagick, display warning
	 *
	 * @return void
	 */
	public function check_plugin_dependencies() {
		// Display warning if ImageMagick extension not found
		if( !$this->imagick_available ) {
			echo( '<div class="error"><strong>WARNING: ImageMagick not found.  PDF thumbnail generation will not be available.</strong></div>' );
		}
	}

	/**
	 * If the attachment is a PDF, look in the upload directory for the thumbnail
	 *
	 * @global object $post - WP Attachment
	 * @param string $path - Default icon directory
	 * @return string $path
	 */
	public function filter_icon_directory( $path ) {
		global $post;

		if( 'application/pdf' == get_post_mime_type ( $attachment_id ) ) {
			$attachment_path = get_post_meta( $post->ID, '_wp_attached_file', true );
			$file_name = basename( $attachment_path );
			$attachment_path = str_replace( '/' . $file_name, '', $attachment_path );
			$upload_dir = wp_upload_dir( $attachment_path );
			$path = $upload_dir['path'];
		}
		return $path;
	}

	/**
	 * Copy attachment categories to thumbnails of PDF attachments
	 *
	 * @param int $parent_id - WordPress post ID of attachment
	 * @return void
	 */
	public function update_attachment_meta( $parent_id ) {
		if( 'application/pdf' == get_post_mime_type( $parent_id ) ) {
			$thumb_id = get_post_meta( $parent_id, '_thumbnail_id', true );
			$parent_cats = wp_get_post_terms( $parent_id, 'media_category' );

			// Only check categories if thumb_id and parent categories are valid
			if( $thumb_id && !is_wp_error( $parent_cats ) ) {
				$new_cats = array();

				// Walk through parent categories; pulling out IDs
				foreach( $parent_cats as $cat ){
					array_push( $new_cats, $cat->term_id );
				}

				// Replace child categories with copy of parent categories
				if( $new_cats ) {
					wp_set_post_terms( $thumb_id, $new_cats, 'media_category' );
				}
			}
		}
	}

	/**
	 * When adding a PDF in the WP Editor, show thumbnail linked to PDF
	 *
	 * @param string $html - HTML send to the editor by WordPress
	 * @param int $send_id - ID of the PDF attachment
	 * @param array $attachment -  Array of attachment attributes
	 * @return void
	 */
	public function wp_editor_thumbnail( $html, $send_id, $attachment ) {
		if( 'application/pdf' == get_post_mime_type ( $attachment['id'] ) ) {
			$thumbnail_id = get_post_meta( $attachment['id'], '_thumbnail_id', true );
			if( isset ( $thumbnail_id ) ){
				if ( !$attach_title = $attachment['post_title'] ) {
					$attach_title = 'PDF';
				}
				$thumbnail = wp_get_attachment_image_src ( $thumbnail_id, 'medium' );
				$thumbnail_link = '
					<a class="link-to-pdf" href="%1$s" title="%2$s" target="_blank">
						<img class="size-medium wp-image-%3$s" src="%4$s" alt="thumbnail-of-%2$s" width="%5$s" height="%6$s"/>
					</a>';
				$html = sprintf( $thumbnail_link, $attachment['url'], $attach_title, $thumbnail_id, $thumbnail[0], $thumbnail[1], $thumbnail[2] );
			}
		}
		return $html;
	}

	/**********    PRIVATE FUNCTIONS    **********/

	/**
	 * Generate thumbnail from $page of PDF $file
	 *
	 * @param string $file - Path to file
	 * @param int $page - Optional page of PDF to convert; defaults to first page
	 * @return string - $thumbnail_url
	 */
	private function generate_pdf_thumbnail( $file, $page = 0 ) {
		$thumbnail_url = $file . '.jpg';

		$im = new imagick();
		$im->readimage( $file . "[$page]" );
		$im->setImageBackgroundColor( 'white' );
		$im->setImageAlphaChannel( imagick::ALPHACHANNEL_REMOVE );
		$im = $im->mergeImageLayers( imagick::LAYERMETHOD_FLATTEN );
		$im->resizeImage( 150, 194, Imagick::FILTER_LANCZOS, 1 );
		$im->writeImage( $thumbnail_url );
		$im->clear();
		$im->destroy();

		return $thumbnail_url;
	}
}