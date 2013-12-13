<?php
/**
 * Plugin Name: JSON Mobile
 * Description: A json post API for mobile team
 * Version: 0.1.0
 * Author: Yuri Victor
 * Author URI: http://yurivictor.com
*/

if ( ! class_exists( 'JSON_Mobile' ) ):

class JSON_Mobile {

	/** Constants *************************************************************/

	const version    = '0.1.0';
	const name       = 'JSON Mobile';  // human-readable name of plugin
	const key        = 'JSON-Mobile';  // plugin slug, generally base filename and url endpoint
	const key_       = 'JSON_Mobile';  // slug with underscores (PHP/JS safe)
	const prefix     = 'json_mobile_'; // prefix to append to all options, API calls
	const nonce_key  = 'json_mobile_nonce';

	/** Variables *************************************************************/

	private static $instance;

	/** Load Methods **********************************************************/

	/**
	 * Start functions WordPress needs to run
	 * @return $instance
	 */
	public static function init() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new JSON_Mobile;
			self::add_actions();
		}
		return self::$instance;
	}

	/**
	 * Hook actions in that run on every page-load
	 * @uses add_action()
	 */
	private static function add_actions() {
		add_action( 'init', array( __CLASS__, 'register_hooks' ) );
		add_action( 'init', array( __CLASS__, 'add_endpoints' ) );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
	}

	/** Public Methods ********************************************************/


	/**
	 * Register json mobile endpoints
	 * @uses register_activation_hook()
	 * @uses register_deactivation_hook
	 */
	public static function register_hooks() {
		register_activation_hook( __FILE__, 'endpoints_activate' );     
		register_deactivation_hook( __FILE__, 'endpoints_deactivate' );
	}

	/**
	 * Redirect post to json API page
	 * @return bool, false if not an article or query
	 */
	public static function template_redirect() {		
		global $wp_query;
		if ( ! isset( $wp_query->query_vars['json-mobile'] ) || ! is_singular() ) {
			return false;
		}
		self::show_json();
		exit;
	}

	/**
	 * Show json encoded post per mobile spec
	 */
	public static function show_json() {
		header( 'Content-Type: application/json' );
		$json = self::format_post_as_array();
		echo json_encode( $json );
	}

	public static function format_post_as_array() {
		global $post;

		$ray = array();
		$ray = self::get_default_fields( $post );
		
		$paragraphs = preg_split( "/\\r\\n|\\r|\\n/", $post->post_content );

		// run through each paragraph 
		foreach ( $paragraphs as $paragraph ) {
			if ( self::is_image( $paragraph ) ) {
				$ray['items'][] = self::get_image( $paragraph );
			} elseif ( self::is_tweet( $paragraph ) ) {
				$ray['items'][] = self::get_tweet( $paragraph );
			} elseif( self::is_video( $paragraph ) ) {
				$ray['items'][] = self::get_video( $paragraph );
			} elseif( self::is_other( $paragraph ) ) {
				$ray['items'][] = self::get_other();
			} elseif ( ! empty ( $paragraph ) ) {
				$ray['items'][] = self::get_html( $paragraph );
			}
			
		}
		return $ray;
	}

	/** Endpoint Methods ********************************************************/

	/**
	 * Adds json-mobile endpoint
	 * @uses add_rewrite_endpoint()
	 */
	public static function add_endpoints() {
		add_rewrite_endpoint( 'json-mobile', EP_PERMALINK );
	}

	/**
	 * Activates json endpoint
	 * @uses flush_rewrite_rules()
	 */
	public static function endpoints_activate() {
		self::add_endpoints();
		flush_rewrite_rules();
	}
	

	/**
	 * Flush rules on deactivate as well
	 * so they're not left hanging around uselessly
	 * @uses flush_rewrite_rules()
	 */ 
	public static function endpoints_deactivate() {
		flush_rewrite_rules();
	}

	/** API Methods ***********************************************************/

	/**
	 * Check if paragraph contains a WordPress caption image
	 * @param string $paragraph, the post paragraph to check	 
	 * @return bool, true if contains an image
	 */
	public static function is_image( $paragraph ) {
		if ( strpos( $paragraph, '[caption' ) !== false ) { 
			return true;
		}
	}

	/**
	 * Check if paragraph contains an embedded tweet
	 * @param string $paragraph, the post paragraph to check	 
	 * @return bool, true if contains a tweet
	 */
	public static function is_tweet( $paragraph ) {
		if ( strpos( $paragraph, '/twitter.com/' ) !== false ) {
			return true;
		}
	}

	/**
	 * Check if paragraph contains a video from a known host
	 * Currently supports:
	 * • Youtube
	 * • Vimeo
	 * • PostTV
	 * @param string $paragraph, the post paragraph to check	 
	 * @return bool, true if contains a video
	 */
	public static function is_video( $paragraph ) {
		$is_youtube = strpos( $paragraph, 'youtube.com' );
		$is_vimeo   = strpos( $paragraph, 'vimeo.com' );
		$is_posttv  = strpos( $paragraph, '/posttv/' );
		if ( $is_youtube || $is_vimeo || $is_posttv ) {
			return true;
		}
	}

	/**
	 * Check if a string contains a url
	 * @param string $paragraph, the post paragraph to check	 
	 * @return bool, true if contains a url
	 */
	public static function is_url( $paragraph ) {
		$regex_url = '/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/';
		if ( preg_match( $regex_url, $paragraph, $url ) ) {
			return true;
		}
	}

	/**
	 * Checks paragraph content that doesn't translate
	 * from desktop to mobile apps such as js and flash embeds. 
	 * Here is the full list:
	 * • Galleries // Need to be updated when gallery builder is finished
	 * • (Flash) Objects and embeds
	 * • Script tags
	 * • CSS links
	 * @param string $paragraph, the post paragraph to check
	 * @return bool, true if contains unwanted content
	 */
	public static function is_other( $paragraph ) {
		$unwanted_items = array( 
			'gallery-container', 
			'gallery-caption',
			'<object',
			'<embed',
			'<script',
			'<link',
		);
		foreach ( $unwanted_items as $item ) {
			if ( stristr( $paragraph, $item ) ) {
				return true;
			}
		}
	}

	/**
	 * Sets up default fields for posts
	 * @param object $post, the current post
	 * @return array $default, the default fields:
	 * id: int, the current post ID
	 * title: string, the current post title
	 * author: string, the current post author(s)
	 * published: string, the original post published date in UTC format
	 * lmt: string, the last time the post was modified in UTC format
	 * lead_image: string, the designated lead image url, or first image, in the post
	 */
	public static function get_default_fields( $post ) {
		$default = array();
		$default['id']         = $post->ID;
		$default['title']      = $post->post_title;
		$default['author']     = get_the_author_meta( 'display_name', $post->post_author );
		$default['published']  = mysql2date( 'c', $post->post_date );
		$default['lmt']        = get_the_modified_time( 'c' );
		$default['lead_image'] = self::get_thumbnail( $post->ID );
		if ( ! $default['lead_image'] ) {
			$default['lead_image'] = null;
		}
		return $default;
	}

	/**
	 * Get a post's thumbnail
	 * In place to handle backwards compatibility with 'thumbnail_image' field.
	 * @param int $post_id, the post id to check for a thumbnail
	 * @uses has_post_thumbnail()
	 * @uses get_post_meta()
	 * @uses get_post_thumbnail_id()
	 * @uses wp_get_attachment_image_src()
	 * @return string thumbnail, the url to the thumbnail
	 */
	public static function get_thumbnail( $post_id ) {
		if ( has_post_thumbnail( $post_id ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post_id );
			$thumbnail = wp_get_attachment_image_src( $thumbnail_id );
			$thumbnail = $thumbnail[0];
		} else {
			$thumbnail = get_post_meta( $post_id, 'thumbnail_image', true );
		}
		return trim( $thumbnail );
	}

	/**
	 * Breaks image embeds into json elements
	 * @param $paragraph, string the current post paragraph
	 * @return array $image, the src and caption of the image
	 */
	public static function get_image( $paragraph ) {
		$image = array();
		$image['type'] = 'image';
		$image['caption'] = trim( preg_replace( array( "/<img[^>]+\>/i", "/<a[^>]*>(.*)<\/a>/iU", '%(\\[caption.*])(.*)(\\[/caption\\])%'), array( '','','$2' ), $paragraph ) );
		$output = preg_match_all( '/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $paragraph, $src );
		$image['src'] = trim( $src[1][0] );
		return $image;
	}

	/**
	 * Breaks tweet embeds into json elements
	 * @param $paragraph, string the current post paragraph
	 * @uses wp_remote_get()
	 * @return array $tweet, the id, url, author and content of the tweet
	 */
	public static function get_tweet( $paragraph ) {
		$embedded_tweet = false;
		$tweet = array();
		$tweet['type'] = 'tweet';
		if ( strpos( $paragraph, 'blockquote' ) ) {
			$embedded_tweet = true;
			$tweet['url'] = self::get_url( $paragraph );
		} else {
			$tweet['url'] = $paragraph;
		}
		$tweet_id = explode( '/', $tweet['url'] ); 
		$tweet['id'] = $tweet_id[5]; // For tweets like /status/id
		$get_tweet = wp_remote_get( 'https://api.twitter.com/1/statuses/oembed.json?id=' . $tweet['id'] );
		$tweet_body = json_decode( $get_tweet['body'] );
		$tweet['author'] = $tweet_body->author_name;
		$tweet['content'] = strip_tags( $tweet_body->html );

		return $tweet;
	}

	/**
	 * Gets the first url from a string of text
	 * @param $paragraph, string the current post paragraph
	 * @return string the url if found
	 */
	public static function get_url( $paragraph ) {
		$pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
		preg_match_all( "#$pattern#i", $paragraph, $matches );
		return $matches[1][0];
	}

	/**
	 * Response for unsupported content
	 * @return array $other, the response for unsupported content
	 */
	public static function get_other() {
		$other            = array();
		$other['type']    = 'unsupported';
		$other['content'] = 'This embedded element is not supported in mobile applications. Sorry.';
		return $other;
	}

	/**
	 * Breaks video embeds into json elements
	 * Currently supports
	 * • PostTV
	 * • Vimeo
	 * • Youtube
	 * @param $paragraph, string the current post paragraph
	 * @uses wp_remote_get()
	 * @return array $video, the url, host, id and thumbnail of the video
	 */
	public static function get_video( $paragraph ) {
		$video = array();
		$video['type'] = 'video';
		$video['url']  = self::get_url( $paragraph );

		// Set up video network checks
		$is_youtube = strpos( $video['url'], 'youtube.com' );
		$is_vimeo   = strpos( $video['url'], 'vimeo.com' );
		$is_posttv  = strpos( $video['url'], '/posttv/' );

		if ( $is_youtube ) {
			$video['host'] = 'youtube';

			$video_id = explode( '?v=', $video['url'] ); // For videos like watch?v=...
			
			if ( empty( $video_id[1] ) ) {
				$video_id = explode( '&v=', $video['url'] ); // For videos like watch?player&v=
				if ( empty( $video_id[1] ) ) {
					$video_id = explode( '/v/', $video['url'] ); // For videos like watch/v/
				}
			}

			if ( empty( $video_id[1] ) ) { 
				$video_id = explode( '?', $video['url'] ); // Get rid of all params
				$video_id = explode( '/', $video_id[0] );  // For video embeds /embed/
				$video['id'] = $video_id[2];
			} else {
				$video_id = explode( '&', $video_id[1] ); // Deleting any other params
				$video['id'] = $video_id[0];
			}

			$video['embed'] = '<iframe id="ytplayer" type="text/html" width="960" height="720" src="http://www.youtube.com/embed/' . $video['id'] . '?autoplay=0&enablejsapi=1&wmode=transparent" frameborder="0"></iframe>';
			$video['thumbnail'] = 'http://img.youtube.com/vi/' . $video['id'] . '/0.jpg';
		} elseif( $is_vimeo ) {
			$video['host'] = 'vimeo';
			$video_id    = explode( '/', $paragraph );
			$video['id'] = $video_id[3];
			if ( ! $video['id'] ) {
				$video['error'] = 'Unknown vimeo embed';
			} else {
				$video['embed'] = '<iframe src="//player.vimeo.com/video/' . $video['id'] . '?title=0&byline=0&portrait=0" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>';					
				$video_thumbnail = wp_remote_get( 'http://vimeo.com/api/v2/video/' . $video['id'] . '.json' );
				if ( $video_thumbnail === FALSE ) { 
					$video['thumbnail'] = 'null';
				} else {
					$video_body = json_decode( $video_thumbnail['body'] );
					$video['thumbnail'] = $video_body[0]->thumbnail_large;
				}
			}		
		} elseif ( $is_posttv ) {
			$video['host'] = 'posttv';
			$video_id = explode( '/', $video['url'] );
			$video['id'] = $video_id[6];
			$video['embed'] = '<iframe width="480" height="290" scrolling="no" src="http://www.washingtonpost.com/posttv/c/embed/' . $video['id'] . '" frameborder="0" allowfullscreen></iframe>';
			$video_thumbnail = wp_remote_get( 'http://www.washingtonpost.com/posttv/video/' . $video['id'] . '_json.html' );
			if ( $video_thumbnail === FALSE ) {
				$video['thumbnail'] = 'null';
			} else {
				$video_body = json_decode( $video_thumbnail['body'] );
				$video['thumbnail'] = $video_body->promoImage->fullscreen->url;
			}
		}

		return $video;
	}

	/**
	 * Formats html into array
	 * @param $paragraph, string the current post paragraph
	 * @return array $sanatized_html, the stripped down paragraph
	 */
	public static function get_html( $paragraph ) {
		$sanitized_html = array();
		$sanitized_html['type'] = 'sanitized_html';
		$sanitized_html['content'] = trim( $paragraph );
		return $sanitized_html;
	}

}

JSON_Mobile::init();

endif;