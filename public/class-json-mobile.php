<?php
/**
 * JSON_Mobile_Feed
 *
 * @package   JSON_Mobile_Feed
 * @author    Yuri Victor <yurivictor@gmail.com>
 * @license   GPL-2.0+
 * @link      http://www.washingtonpost.com/
 * @copyright 2013 Yuri Victor
 */

/**
 * JSON_Mobile_Feed class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * @package JSON_Mobile_Feed
 * @author  Yuri Victor <yurivictor@gmail.com>
 */
class JSON_Mobile_Feed {

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 * @since   1.0.0
	 * @var     string
	 */
	const VERSION = '0.0.1';

	/**
	 * Unique identifier for plugin.
	 * @since    1.0.0
	 * @var      string
	 */
	protected $plugin_slug = 'JSON_Mobile';

	/**
	 * Instance of this class.
	 * @since    1.0.0
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin
	 * @since     1.0.0
	 */
	private function __construct() {
		/**
		 * Define custom functionality.
		 * Refer To http://codex.wordpress.org/Plugin_API#Hooks.2C_Actions_and_Filters
		 */
		$this->add_actions();
		// Include additional classes
		// $this->load_subclasses();
	}

	/**
	 * Return the plugin slug.
	 * @since    1.0.0
	 *@return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 * @since     1.0.0
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 * @since    1.0.0
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide  ) {
				// Get all blog ids
				$blog_ids = self::get_blog_ids();
				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_activate();
				}
				restore_current_blog();
			} else {
				self::single_activate();
			}
		} else {
			self::single_activate();
		}
	}

	/**
	 * Fired when the plugin is deactivated.
	 * @since    1.0.0
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			if ( $network_wide ) {
				// Get all blog ids
				$blog_ids = self::get_blog_ids();
				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_deactivate();
				}
				restore_current_blog();
			} else {
				self::single_deactivate();
			}
		} else {
			self::single_deactivate();
		}
	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 * @since    1.0.0
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {
		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}
		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 * @since    1.0.0
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {
		global $wpdb;
		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";
		return $wpdb->get_col( $sql );
	}

	/**
	 * Fired for each blog when the plugin is activated.
	 * @since    1.0.0
	 */
	private static function single_activate() {
		// Add ?json-mobile endpoint
		self::add_json_endpoints();
		flush_rewrite_rules();
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 * @since    1.0.0
	 */
	private static function single_deactivate() {
		// Flush json endpoint so it's not hanging around endlessly
		flush_rewrite_rules();
	}

	/**
	 * NOTE:  Actions are points in the execution of a page or process
	 *        lifecycle that WordPress fires.
	 *        Actions:    http://codex.wordpress.org/Plugin_API#Actions
	 *        Reference:  http://codex.wordpress.org/Plugin_API/Action_Reference
	 *
	 * @since    1.0.0
	 */
	public function add_actions() {
		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );
		// Add ?json-mobile endpoint on init
		add_action( 'init', array( __CLASS__, 'add_json_endpoints' ) );
		// Redirect article to json feed
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
	}

	/**
	 * Loads and substantiates all classes in the includes folders
	 * Classes should be named in the form of JSON_Mobile_{Class_Name}
	 * Files should be the name of the class name e.g. class-name.php
	 * Classes will be autoloaded as $object->{class_name}
	 * @since    1.0.0
	 */
	public function load_subclasses() {
		// load all core classes
		$files = glob( dirname( __FILE__ ) . '/includes/*.php' ) ;
		foreach ( $files as $file ) {
			//don't include self
			if ( basename( $file ) == basename( __FILE__ ) ) {
				continue;
			}
			//the name of this particular class, e.g., API or Options
			$include = $this->get_include_object( $file );
			if ( ! apply_filters( "{$this->plugin_slug}load_{$include->object_name}", true, $include ) ) {
				continue;
			}
			if ( ! class_exists( $include->class ) ) {
				@require_once $include->file;
			}
			if ( ! class_exists( $include->class ) ) {
				trigger_error( "{$this->plugin_slug} -- Unable to load class {$include->class}. see the readme for class and file naming conventions" );
			}
			continue;
		}
		$this->{$include->object_name} = new $include->class( $this );
		$this->classes[ $include->object_name ] = $include->class;
	}

	/**
	 * Returns an object with all information about a file to include
	 * Fields:
	 * file - path to file
	 * name - Title case name of class
	 * object_name - lowercase name that will become $this->{object_name}
	 * native - whether this is a native boilerplate class
	 * base - the base of the class name (either Plugin_Boilerplate or the parent class name)
	 * class - The name of the class
	 *
	 * @param string $file the file to include
	 * @return object the file object
	 */
	function get_include_object( $file ) {
		$class = new stdClass();
		$class->file = $file;
		$name = basename( $file, '.php' );
		$name = str_replace( '-', '_', $name );
		$name = str_replace( '_', ' ', $name );
		$class->name = str_replace( ' ', '_', ucwords( $name ) );
		$class->object_name = str_replace( ' ', '_', $name );

		//base, either Plugin class or Plugin_Boilerplate
		$class->native = ( dirname( $file ) == dirname( __FILE__ ) . '/includes' );
		$class->base = ( $class->native ) ? 'Plugin_' : get_class( $this );
		$class->class = $class->base . '_' . $class->name;

		return $class;
	}
	
	/**
	 * Add ?json-mobile endpoint to articles
	 * @since    1.0.0
	 */
	public static function add_json_endpoints() {
		add_rewrite_endpoint( 'json-mobile', EP_PERMALINK );
	}

	/** NEED TO EDIT ********************************************************/

	/**
	 * Redirect post to json API page
	 * @return bool, false if not an article or ?json-mobile query
	 * @since    1.0.0
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
		global $post;
		header( 'Content-Type: application/json' );
		header( 'Last-Modified: ' . get_the_modified_time( "D, d M Y H:i:s" ) );
		$json = self::format_post_as_array( $post );
		echo json_encode( $json );
	}

	public static function format_post_as_array( $post ) {

		$ray = array();
		$ray = self::get_default_fields( $post );

		// Activate shortcodes
		$content = do_shortcode( $post->post_content );

		// Split content by line breaks
		$paragraphs = self::pre_process( $content );
		$paragraphs = preg_split( "/\\r\\n|\\r|\\n/", $paragraphs );
		$ray['items'] = self::parse_paragraphs( $paragraphs );
		return $ray;
	}

	public static function pre_process( $post ) {
		// strip crap
		$post = preg_replace( 
			array( 
				'@<style[^>]*?>.*?</style>@siu', // CSS
				'@<script[^>]*?>.*?</script>@siu', // Scripts
				'[wapoad type="inline"]', // fucking inline ads
				'@"[script]"@siu', '@"[/script]"@siu', // Script shortcode
				'<img title="More..." alt="" src="http://www.washingtonpost.com/blogs/post-politics/wp-includes/js/tinymce/plugins/wordpress/img/trans.gif" />', // WTF
			), array( '' ), $post );
		// convert html entities to special characters
		$post = str_replace( '&amp;', '&', $post ); // Ampersands
		// Remove line breaks for certain cases, can cause problems
		// if for example an embedded tweet starts in one paragraph
		// and doesn't end until another paragraph
		if ( strpos( $post, 'blockquote' ) != false ) {
			$post = preg_replace( '/(<blockquote class="twitter-tweet"[^>]*>)(.*?)(\\r\\n|\\r|\\n)(\\r\\n|\\r|\\n)(.*?)(<\/blockquote>)/si', '$1$2 $5$6', $post );
		}
		return $post;
	}

	public static function parse_paragraphs( $paragraphs, $items = array(), $multiples = false ) {
		foreach ( $paragraphs as $paragraph ) {
			if ( self::is_image( $paragraph ) ) {
				$items[] = self::get_image( $paragraph );
				// Strip caption so it doesn't get confused with text later
				$paragraph = preg_replace( '/<p class="wp-caption-text">(.*?)<\/p>/i', '', $paragraph );
				// If this is not a Wordpress image, 
				// it's probably going to be in a pargraph
				// and we'll need to grab that paragraph
				if ( strpos( $paragraph, '[caption' ) === false ) {
					$html = self::get_html( $paragraph );
					if ( ! self::is_empty( $html['content'] ) ) {
						$items[] = $html;
					}
				}
			} elseif ( self::is_instagram( $paragraph ) ) {
				$items[] = self::get_instagram( $paragraph );
			} elseif ( self::is_tweet( $paragraph ) ) {
				$items[] = self::get_tweet( $paragraph );
			} elseif( self::is_video( $paragraph ) ) {
				$items[] = self::get_video( $paragraph );
			} elseif( self:: is_graphic( $paragraph ) ) {
				$items[] = self::get_graphic( $paragraph );
			} elseif( self::is_other( $paragraph ) ) {
				$items[] = self::get_other();
			} elseif( self::is_blockquote( $paragraph ) || $multiples === true ) {
				$values    = self::get_blockquote( $paragraph );
				$quote     = $values['blockquote'];
				if ( self::is_empty( $quote['content'] ) == false ) {
					$items[]   = $quote;
				}
				$multiples = $values['multiples'];
			} elseif ( self::is_empty( $paragraph ) == false ) {
				$html = self::get_html( $paragraph );
				if ( ! self::is_empty( $html['content'] ) ) {
					$items[] = $html;
				}
			}
		}
		return $items;
	}

	/** API Methods ***********************************************************/

	public static function is_empty( $paragraph, $value = false ) {
		$paragraph = trim( $paragraph );
		if ( 
			empty ( $paragraph ) 
			|| $paragraph == '&nbsp;' 
			|| $paragraph == '[]'
			|| $paragraph == '<em>'
			|| $paragraph == '<strong>'
			|| $paragraph == '</em>'
			|| $paragraph == '</strong>'
			|| $paragraph == '< >'			
		) {
			$value = true;
		}
		return $value;
	}

	public static function is_graphic( $paragraph ) {
		if ( strpos( $paragraph, 'post-embedded-graphic' ) !== false ) {
			return true;
		}
	}

	/**
	 * Check if paragraph contains a WordPress caption image
	 * @param string $paragraph, the post paragraph to check	 
	 * @return bool, true if contains an image
	 */
	public static function is_image( $paragraph ) {
		if ( strpos( $paragraph, '[caption' ) !== false || strpos( $paragraph, '<img' ) !== false ) { 
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
			// Need to account for links vs embeds, which look similar
			if ( strpos( $paragraph, 'href' ) !== false ) {
				if ( strpos( $paragraph, '<blockquote' ) !== false ) {
					return true;
				} else {
					return false;
				}
			}
			return true;
		}
	}

	/**
	 * Check if paragraph contains an embedded instagram
	 * @param string $paragraph, the post paragraph to check
	 * @return bool, true if contains an instagram image
	 */
	public static function is_instagram( $paragraph ) {
		if ( strpos( $paragraph, '/instagram.com/' ) !== false ) {
			// Need to account for links vs embeds, which look similar
			if ( strpos( $paragraph, 'href' ) !== false ) {
				if ( strpos( $paragraph, '<iframe' ) !== false ) {
					return true;
				} else {
					return false;
				}
			}
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
		$is_youtube = strpos( $paragraph, 'youtube.com' ) || strpos( $paragraph, 'youtu.be' );
		$is_vimeo   = strpos( $paragraph, 'vimeo.com' );
		$is_posttv  = strpos( $paragraph, '/posttv/' ) || strpos( $paragraph, 'posttv-video-embed' );
		if ( $is_youtube || $is_vimeo || $is_posttv ) {
			// Need to account for links vs embeds, which look similar
			if ( strpos( $paragraph, 'href' ) !== false ) {
				if ( strpos( $paragraph, '<embed' ) !== false ) {
					return true;
				} else {
					return false;
				}
			}
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
			'[script',
			'<style',
			'<link',
			'iframe',
			'< async',
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
	 * id: int, the WordPress post id
	 * uri: string, the post path
	 * title: string, the current post title
	 * author: string, the current post author(s)
	 * published: string, the original post published date in UTC format
	 * lmt: string, the last time the post was modified in UTC format
	 * lead_image: string, the designated lead image url, or first image, in the post
	 */
	public static function get_default_fields( $post ) {
		$default = array();
		$default['type']       = 'wordpress_story';
		$default['id']         = get_the_ID();
		$default['uri']        = strtok( $_SERVER['REQUEST_URI'], '?' );
		$default['title']      = $post->post_title;
		$default['authors']    = self::set_authors();
		// $default['published']  = mysql2date( "Y-m-d\TH:i:sO", $post->post_date );
		// $default['lmt']        = get_the_modified_time( 'Y-m-d\TH:i:sO' );
		$default['published']  = intval( mysql2date( "U", $post->post_date ) . '000' );
		$default['lmt']        = intval( get_the_modified_time( 'U' ) . '000' );
		$default['lead_image'] = self::get_thumbnail( $post->ID );
		$default['shareurl']   = WaPo::bitly();
		$default['contenturl'] = get_permalink();
		$blog_id      = get_option( 'blog_id' );
		$blog_section = get_option( 'blog_section' );
		$default['adkey']      = $blog_section . '/blog/' . str_replace( '-', '_', $blog_id );
		$default['omniture']   = self::get_omniture( $post );
		if ( ! $default['lead_image'] ) {
			$default['lead_image'] = null;
		}
		return $default;
	}

	/**
	 *
	 */
	public static function is_blockquote( $paragraph ) {
		if ( strpos( $paragraph, '<blockquote' ) !== false ) {
			return true;
		}
	}

	/**
	 *
	 */
	public static function get_blockquote( $paragraph, $blockquote = array(), $multiple = false ) {
		$blockquote['type']    = 'sanitized_html';
		$blockquote['subtype'] = 'blockquote';
		$blockquote['content'] = trim( strip_tags( $paragraph ) );
		if ( strpos( $paragraph, '</blockquote>' ) === false ) {
			$multiples = true;
		}
		return array( "blockquote" => $blockquote, "multiples" => $multiples );
	}

	/**
	 *
	 */
	public static function return_next( $paragraph ) {
		return false;
	}

	/**
	 *
	 */
	public static function set_authors() {
		$authors = array();
		if ( function_exists( 'get_coauthors' ) ) {
			if ( $wp_authors = get_coauthors() ) {
				foreach ( $wp_authors as $wp_author ) {
					$authors[] = self::get_authors( $wp_author->ID );
				}
			}
		} else {
			global $post;
			$authors[] = self::get_authors( $post->post_author );
		}
		return $authors;
	}

	public static function get_authors( $id = null, $ray = array() ) {
		$ray['name']  = get_the_author_meta( 'display_name', $id );
		if ( ! empty( $ray['name'] ) ) {
			$ray['email'] = get_the_author_meta( 'email', $id );
		} else {
			$ray['name']  = 'Guest';
			$ray['email'] = null;
		}
		return $ray;
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
	public static function get_image( $paragraph, $image = array() ) {
		$image['type']    = 'image';
		// Get image caption
		$image['caption'] = self::get_image_caption( $paragraph );
		// Get image source
		$image['src']     = self::get_image_src( $paragraph );
		// Get image attributes

		$image_attrs      = self::get_image_attrs( $image['src'] );
		$image['width']   = $image_attrs[0];
		$image['height']  = $image_attrs[1];
		$image['mime']    = $image_attrs['mime'];

		return $image;
	}

	public static function get_image_attrs( $image, $attrs = array() ) {
		// Start memcache
		global $memcache;
		if ( ! isset( $memcache ) ) {
			start_memcache();
		}
		// Get image from memcache
		$key   = 'wapo_image_url_' . $image;
		$attrs = $memcache->get( $key );
		// If no image exists in memcache, get image
		if ( empty( $attrs ) ) {
			$attrs = getimagesize( str_replace( ' ', '%20', $image ) );
			// Save image to memcache
			if ( ! empty( $attrs ) ) {
				$memcache->add( $key, $attrs, false, 86400 );
			}
		}
		return $attrs;
	}


	public static function get_image_id( $paragraph ) {
		preg_match( '/"(attachment_.*?)"/i', $paragraph, $id );
		return substr( $id[1], 11 );
	}

	public static function get_image_caption( $paragraph ) {
		preg_match( '/<p class="wp-caption-text">(.*?)<\/p>/i', $paragraph, $caption );
		// Leaving this commented out because it will be back
		// if ( empty( $caption ) ) {
		// 	preg_match( '/alt="(.*?)"/i', $paragraph, $caption );
		// }
		$caption = trim( strip_tags( html_entity_decode( $caption[1] ), '<strong><a><em>' ) );
		if ( strlen( $caption ) > 3 ) { // Why 3? No idea.
			return $caption;
		} else {
			return null;
		}
	}

	public static function get_image_src( $paragraph ) {
		$source = preg_match_all( '/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $paragraph, $src );
		return trim( $src[1][0] );
	}

	public static function get_omniture( $post, $ray = array() ) {
		$blog_id      = get_option( 'blog_id' );
		$blog_section = get_option( 'blog_section' );

		$ray['pageName']          = $blog_section . ':blog:' . $blog_id . ' - ' . $post->ID . ' - ' . get_the_date( 'Ymd' ) . ' - ' . sanitize_title_with_dashes( $post->post_name );
		$ray['channel']           = 'wp - ' . $blog_section;
		$ray['contentSubsection'] = $blog_section;
		$ray['contentType']       = 'blog';
		$ray['contentAuthor']     = get_the_author_meta( 'display_name', $post->post_author );
		$ray['searchKeywords']    = null;
		$ray['pageFormat']        = null;
		$ray['DBPath']            = null;
		$ray['blogName']          = $blog_id;
		$ray['contentSource']     = 'the washington post';
		return $ray;
	}


	/**
	 * Breaks tweet embeds into json elements
	 * @param $paragraph, string the current post paragraph
	 * @return array $tweet, the id, url, author and content of the tweet
	 */
	public static function get_tweet( $paragraph, $tweet = array(), $embedded_tweet = false ) {
		$tweet['type'] = 'tweet';
		if ( strpos( $paragraph, 'blockquote' ) ) {
			$embedded_tweet = true;
			$tweet_url = self::get_url( $paragraph );
		} else {
			$tweet_url = $paragraph;
		}		
		$tweet_id = explode( '/', $tweet_url ); 
		$tweet_id = $tweet_id[5]; // For tweets like /status/id
		$tweet_id = explode( "\">", $tweet_id ); // Get rid of anything after id
		$get_tweet = get_json_tweet( $tweet_id[0] );
		$tweet_body = json_decode( $get_tweet );
		$tweet['content'] = $tweet_body;
		return $tweet;
	}

	/**
	 * Breaks instagram embeds into json elements
	 * @param $paragraph, string the current post paragraph
	 * @return array $instagram, the id, url, author and content of the tweet
	 */
	public static function get_instagram( $paragraph, $instagram = array() ) {
		$instagram['type'] = 'instagram';
		$instagram['url']  = $paragraph;
		// Need a special case for embeds
		if ( strpos( $paragraph, 'iframe' ) ) {
			$url = 'http://' . self::get_url( $paragraph );
			$url = str_replace( 'embed/', '', $url );
			$instagram['url'] = $url;
		}
		$content = get_json_instagram( $instagram['url'] );
		$content = json_decode( $content['body'] );
		$instagram['content'] = $content;
		return $instagram;
	}

	/**
	 * Gets the first url from a string of text
	 * @param $paragraph, string the current post paragraph
	 * @return string the url if found
	 */
	public static function get_url( $paragraph ) {
		$pattern = '(?xi)\b((?:https?://|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:\'".,<>?«»“”‘’]))';
		preg_match_all( "#$pattern#i", $paragraph, $matches );
		$matches = $matches[1]; 
		return end( $matches );
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

	public static function get_graphic( $paragraph, $graphic = array() ) {
		$graphic['type'] = 'embedded_graphic';
		// Get image
		preg_match( '/promo-image="(.*?)"/i', $paragraph, $imageURL );
		$graphic['imageURL']    = $imageURL[1];
		$imageURL_attrs         = self::get_image_attrs( $graphic['imageURL'] );
		$graphic['imageWidth']  = $imageURL_attrs[0];
		$graphic['imageHeight'] = $imageURL_attrs[1];
		// Get link
		preg_match( '/promo-link="(.*?)"/i', $paragraph, $linkURL );
		$graphic['linkURL'] = $linkURL[1];
		// Get caption
		preg_match( '/promo-caption="(.*?)"/i', $paragraph, $caption );
		$graphic['caption'] = $caption[1];

		return $graphic;
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
		$is_youtube = strpos( $paragraph, 'youtube.com' ) || strpos( $video['url'], 'youtu.be' );;
		$is_vimeo   = strpos( $video['url'], 'vimeo.com' ); 
		$is_posttv  = strpos( $paragraph, '/posttv/' ) || strpos( $paragraph, 'posttv-video-embed' );

		// Start memcache
		global $memcache;
		if ( ! isset( $memcache ) ) {
			start_memcache();
		}

		if ( $is_youtube ) {
			$video['host'] = 'youtube';
			// Add http
			if ( strpos( $video['url'], 'http' ) === false ) {
				$video['url'] = 'http://' . $video['url']; // Adds http
			}
			// Embeds suck and have many use cases
			if ( strpos( $video['url'], 'embed' ) !== false ) {
				$video_url    = explode( '?', $video['url'] ); // Gets rid of extra crap after ?
				$video['url'] = $video_url[0];
				$video['url'] = str_replace( 'embed/', 'watch?v=', $video['url'] ); // Replaces embed with watch
			} elseif ( strpos( $video['url'], '/v/' ) !== false ) {
				$video_url    = explode( '/v/', $video['url'] ); // For videos like /v/id 
				$video_url    = explode( '?',   $video_url[1] ); // Gets rid of extra crap after ?
				$video['url'] = 'http://www.youtube.com/watch?v=' . $video_url[0];
			}
			// Get video from memcache
			$key       = 'wapo_youtube_id_' . $video['url'];
			$get_video = $memcache->get( $key );
			// If no video exists in memcache, get video
			if ( empty( $get_video ) ) {
				$get_video = wp_remote_get( 'http://www.youtube.com/oembed?url=' . $video['url'] . '&format=json' );
				$get_video = $get_video['body'];
				// Save video to memcache
				if ( ! empty( $get_video ) ) {
					$memcache->add( $key, $get_video, false, 86400 );
				}
			}
			$content           = json_decode( $get_video );
			$video['mediaURL'] = $video['url'];
			$video['imageURL'] = $content->thumbnail_url;
			$video['imageHeight'] = $content->thumbnail_height;
			$video['imageWidth']  = $content->thumbnail_width;
			$video['caption']     = $content->title;
			$video['content']  = $content;
		} elseif( $is_vimeo ) {
			$video['host'] = 'vimeo';
			$video_id = explode( '/', $paragraph );
			if ( strpos( $paragraph, 'iframe' ) === false ) {
				// For oembeds
				$video_id = $video_id[3];
			} else {
				// For embeds
				$video_id = explode( '?', $video_id[4] ); // Get rid of all the extra crap after ?
				$video_id = $video_id[0];
			}

			if ( ! $video_id ) {
				$video['error'] = 'Unknown vimeo embed';
			} else {
				// Get video from memcache
				$key       = 'wapo_vimeo_id_' . $video_id;
				$get_vimeo = $memcache->get( $key );
				$response  = true;
				// If no video exists in memcache, get video
				if ( empty( $get_vimeo ) ) {
					$response = wp_remote_get( 'http://vimeo.com/api/v2/video/' . $video_id . '.json' );

					if ( ! is_wp_error( $response ) ) {
						// Get body
						$get_vimeo = $response['body'];
						$get_vimeo = json_decode( $get_vimeo );
						// Save video to memcache
						$memcache->add( $key, $get_vimeo, false, 86400 );
					} else {
						$response = false;
					}
				}
				$content   = $get_vimeo[0];
				$video['mediaURL'] = $content->mobile_url;
				$video['imageURL'] = $content->thumbnail_large;
				$video['imageHeight'] = null;
				$video['imageWidth']  = null;
				$video['caption']     = $content->title;
				if ( $response == true ) {
					$video['content'] = $content;
				} else {
					$video['content'] = $response->get_error_message();
				}
			}
		} elseif ( $is_posttv ) {
			$video['host'] = 'posttv';
			if ( strpos(  $paragraph, 'iframe' ) === false ) {
				preg_match( '/data-uuid="(.*?)"/i', $paragraph, $video_id );
				$video_id = $video_id[1];
				$video['id'] = $video_id;
			} else {
				$video_id = explode( '/', $video['url'] );
				$video['id'] = $video_id[6];
			}
			if ( ! $video_id ) {
				$video['error'] = 'Unknown posttv embed';
			} else {
				// Get video from memcache
				$key       = 'wapo_posttv_id_' . $video_id;
				$get_video = $memcache->get( $key );
				// If no video exists in memcache, get video
				if ( empty( $get_video ) ) {
					$get_video = wp_remote_get( 'http://www.washingtonpost.com/posttv/c/videojson/' . $video['id'] . '?resType=jsonp' );
					$get_video = self::jsonp_decode( $get_video['body'] );
					
					// Save video to memcache
					if ( ! empty( $get_video ) ) {
						$memcache->add( $key, $get_video, false, 86400 );
					}
				}
				$video['embedCode']   = $get_video[0]->contentConfig->videoContentId;
				$video['imageURL']    = $get_video[0]->promoImage->image->url;
				$video['imageWidth']  = $get_video[0]->promoImage->image->width;
				$video['imageHeight'] = $get_video[0]->promoImage->image->height;
				$video['caption']     = $get_video[0]->contentConfig->blurb;
				
				// Find the media url based on the best quality MP4
				$streams = $get_video[0]->contentConfig->streams;
				// This is disgusting and it makes me feel bad
				foreach ( $streams as $stream ) {
					if ( $stream->type == 'MP4' ) {
						$mp4s[] =  $stream;
					}
				}
				$mp4s = self::sort_arrays_in_object( $mp4s, 'bitrate','desc' );
				$video['mediaURL'] = $mp4s[0]->url;
			}
		}

		return $video;
	}

	public static function jsonp_decode( $jsonp, $assoc = false ) { 
		if( $jsonp[0] !== '[' && $jsonp[0] !== '{' ) { // we have JSONP, cry
			$jsonp = substr( $jsonp, strpos( $jsonp, '(') );
		}
		return json_decode( trim( $jsonp,'();' ), $assoc );
	}
	/**
	 * Formats html into array
	 * @param $paragraph, string the current post paragraph
	 * @return array $sanatized_html, the stripped down paragraph
	 */
	public static function get_html( $paragraph ) {
		$sanitized_html = array();
		$sanitized_html['type'] = 'sanitized_html';
		if (   strpos( $paragraph, '<h4' ) !== false 
			|| strpos( $paragraph, '<h2' ) !== false
			|| strpos( $paragraph, '<h1' ) !== false
			|| strpos( $paragraph, '<h3' ) !== false
			|| strpos( $paragraph, '<h5' ) !== false
			|| strpos( $paragraph, '<h6' ) !== false
		   ) {
			$sanitized_html['subtype'] = 'subhead';
		}
		$paragraph = self::strip_html_tags( html_entity_decode( $paragraph ) );
		$sanitized_html['content'] = trim( $paragraph );
		return $sanitized_html;
	}

	public static function strip_html_tags( $text ) {
		$text = preg_replace(
			array(
				// Remove invisible content
				'@<head[^>]*?>.*?</head>@siu',
				'@<style[^>]*?>.*?</style>@siu',
				'@<script[^>]*?.*?</script>@siu',
				'@<object[^>]*?.*?</object>@siu',
				'@<embed[^>]*?.*?</embed>@siu',
				'@<applet[^>]*?.*?</applet>@siu',
				'@<noframes[^>]*?.*?</noframes>@siu',
				'@<noscript[^>]*?.*?</noscript>@siu',
				'@<noembed[^>]*?.*?</noembed>@siu',
				// Add line breaks before and after blocks
				'@</?((address)|(blockquote)|(center)|(del))@iu',
				'@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
				'@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
				'@</?((table)|(th)|(td)|(caption))@iu',
				'@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
				'@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
				'@</?((frameset)|(frame)|(iframe))@iu',
			),
			array(
			' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',"$0", "$0", "$0", "$0", "$0", "$0","$0", "$0",), $text );

		// Exclude some html tags
		return strip_tags( $text , '<strong><a><em>' );
	}

	/**
	 * Sorts arrays in object by key
	 * @param $array, array of objects
	 * @param $sortby, the object-key to sort by
	 * @param $direction, 'asc' = ascending
	 * @return $sorted, the newly sorted array
	 */
	function sort_arrays_in_object( $array, $sortby, $direction = 'asc' ) {

		$sorted_array = array();
		$temp_array   = array();

		foreach ( $array as $key => $value ) {
			$temp_array[] = strtolower( $value->$sortby );
		}

		if ( $direction == 'asc' ) {
			asort( $temp_array );
		} else {
			arsort( $temp_array );
		}

		foreach( $temp_array as $key=>$temp ) {
			$sorted_array[] = $array[$key];
		}

		return $sorted_array;

	}	

}

function get_json_instagram( $url, $instagram = null ) {
	// Start memcache
	global $memcache;
	if ( ! isset( $memcache ) ) {
		start_memcache();
	}
	// Get tweet from memcache
	$key       = 'wapo_instagram_id_' . $url;
	$instagram = $memcache->get( $key );
	// If no tweet exists in memcache, get tweet
	if ( empty( $instagram ) ) {
		// Grab oembed
		$instagram = wp_remote_get( 'http://api.instagram.com/oembed?url=' . $url );
		// Save to memcache
		if ( ! empty( $instagram ) ) {
			$memcache->add( $key, $instagram, false, 86400 );
		}
	}
	return $instagram;
}

function get_json_tweet( $id, $tweet = null ) {

	// Start memcache
	global $memcache;
	if ( ! isset( $memcache ) ) {
		start_memcache();
	}

	// Get tweet from memcache
	$key   = 'wapo_tweet_id_' . $id;
	$tweet = $memcache->get( $key );

	// If no tweet exists in memcache, get tweet
	if ( empty( $tweet ) ) {
		require_once( 'includes/class-json-twitter.php' );
		/** Set access tokens here - see: https://dev.twitter.com/apps/ **/
		$settings = array(
			'oauth_access_token' => "5511932-9NxYzwQLpfYKFCydnY3L6AmRyFSrjR6NzlKa218eBm",
			'oauth_access_token_secret' => "8LhvfmXPEDPr7H8YF3d8RmPX4B9ufUHJQ2Kc3QeT7B3Jt",
			'consumer_key' => "mocGBNwEiWX3hjRR4L8xg",
			'consumer_secret' => "VB8dA0RPqHrqT8ukGsVSl6O2K7GNQDOBabBO0TAHeyE"
		);

		/** URL for REST request, see: https://dev.twitter.com/docs/api/1.1/ **/
		$url           = 'https://api.twitter.com/1.1/statuses/show.json';
		$getfield      = '?id=' . $id;
		$requestMethod = 'GET';

		$twitter = new JSON_Mobile_Twitter( $settings );
		$tweet = $twitter->setGetfield( $getfield )
		                 ->buildOauth( $url, $requestMethod )
		                 ->performRequest();

		// Save tweet to memcache
		if ( ! empty( $tweet ) ) {
			$memcache->add( $key, $tweet, false, 86400 );
		}
	}
	return $tweet;
}

function start_memcache() {
	global $memcache;
	if ( ! isset( $memcache ) ) {
		$memcache = new Memcache;
		if ( ! defined( 'MEMCACHED_HOST_1' ) ) {
			$memcache->addServer( 'localhost', 11211 );
		} else {
			$memcache->addServer( MEMCACHED_HOST_1, 11211 );
		}
		if ( defined( 'MEMCACHED_HOST_2' ) ) {
			$memcache->addServer(MEMCACHED_HOST_2, 11211);
		}
	}
}