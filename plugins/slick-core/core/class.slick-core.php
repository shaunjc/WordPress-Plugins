<?php
/**
 * Slick collection of modules.
 */
namespace Slick;

use Slick\Base_2_8_2 as BaseFramework;
use DOMDocument;
use Exception;

// Require Plugin Base
require_once SLICK_CORE_PLUGIN_CORE_DIR . '/class.slick.base-2.php';

/**
 * Slick Core
 * 
 * @version 1.0
 * @requires PluginBase Version 2.8.2
 * 
 * Primary Slick Core plugin class for providing
 * additional functionality to a WordPress site.
 * 
 * Stores and obtains settings automatically on
 * initialisation. A public instance is stored by
 * default as a static variable SlickCore::$inst.
 */
class Core extends BaseFramework {
    /** @var SlickCore Publicly available instance **/
    public static $inst;
    
    /** @var array plugin Settings loaded on construct. @see PluginBase:$settings **/
    protected $settings = array(
        'enable_svg' => true,
        'embed_svg'  => true,
        'extra_body_classes'  => true,
        'automatic_shortcodes' => true,
        'pdf_previews' => true,
    );
    
    /**
     * Register actions.
     */
    protected function register_actions() {
        
    }
    
    /**
     * Register filters.
     */
    protected function register_filters() {
        parent::register_filters();
        
        // SVG filters
        if ( $this->enable_svg ) {
            $this->svg_filters();
        }
        
        if ( $this->embed_svg ) {
            $this->add_filter( 'the_content', 'embed_svg', 10, 1 );
        }
        
        // Body classes
        if ( $this->extra_body_classes ) {
            $this->add_filter( 'body_class', null, 10, 2 );
        }
        
        // Slick shortcodes using %%notation%%
        if ( $this->automatic_shortcodes ) {
            // Replace shortcodes within content
            $this->add_filter( 'the_content'   , 'slick_shortcode', 10, 1 );
            // Replace shortcodes within post and page titles (includes allowances when creating slugs etc)
            $this->add_filter( 'the_title'     , 'slick_shortcode', 10, 2 );
            $this->add_filter( 'wp_title'      , 'slick_shortcode', 10, 3 );
            $this->add_filter( 'sanitize_title', 'slick_shortcode', 10, 3 );
        }
        
        // Default shortcodes provided by this plugin
        // Display the requested date for the shortcodes %%date%%, %%days%%, and %%time%%
        $this->add_filter( 'slick_shortcode_date', 'slick_shortcode_date', 10, 5 );
        $this->add_filter( 'slick_shortcode_days', 'slick_shortcode_date', 10, 5 );
        $this->add_filter( 'slick_shortcode_time', 'slick_shortcode_date', 10, 5 );
        
        $this->add_filter( 'slick_shortcode_siteurl', 'siteurl', 10, 5 );
    }
    
    /**
     * Determine main plugin file for use with Activation/Deactivation
     * @return string file path of plugin
     */
    protected function get_plugin_file_path() {
        return SLICK_CORE_PLUGIN_FILE;
    }
    
    /**
     * Additional filters for use with SVGs
     */
    protected function svg_filters() {
        $this->add_filter( 'upload_mimes' );
        $this->add_filter( 'getimagesize_mimes_to_exts' );
        $this->add_filter( 'wp_get_attachment_metadata', null, 10, 2 );
    }
    
    /**
     * Add SVG as a mime type for uploads
     * 
     * @filter upload_mimes called in functions.php get_allowed_mime_types().
     * 
     * @param array $mimes original mime types
     * @return array updated mime types
     */
    public function upload_mimes( $mimes = array() ) {
        $mimes['svg'] = 'image/svg+xml';
        return $mimes;
    }
    
    /**
     * Function to trick Wordpress into accepting 'application/octet-stream' mime
     * type as an svg when a file with .svg extension is now being uploaded.
     * 
     * @filter getimagesize_mimes_to_exts called in function.php wp_check_filetype_and_ext().
     * @uses version_compare to determine if this type of mime type check is required.
     * 
     * @param $mime_to_ext current mime types.
     * @return array updated mime types.
     */
    public function getimagesize_mimes_to_exts( $mime_to_ext = array() ) {
        if ( version_compare( get_bloginfo('version'), '4.6.1', '>' ) // WP Version 4.6.2+
        && isset( $_REQUEST['name'] ) // File needs to be uploaded
        && ( $file_name = strtolower( $_REQUEST['name'] ) )
        && 4 === strlen( $file_name) - strpos( $file_name, '.svg' ) ) { // File needs svg extension
            $svg = new DOMDocument;
            // Load SVG into a DOM document as XML and ensure the root tag is an SVG element
            if ( $svg->load( $file_name ) && strcasecmp( $svg->documentElement->tagName, 'svg' ) === 0 ) {
                // Add mime type
                $mime_to_ext['application/octet-stream'] = 'svg';
            }
        }
        return $mime_to_ext;
    }
    
    /**
     * Add file sizes to image meta, to assist with displaying svg within library
     * 
     * @filter wp_get_attachment_metadata called in post.php wp_get_attachment_metadata().
     * 
     * @param $data attachment meta data.
     * @param $id attachment or id.
     * @return updated attachment meta data.
     */
    public function wp_get_attachment_metadata( $data, $id ) {
        // Only populate attachment data if SVG and data not supplied
        if ( ! $data && 'image/svg+xml' === get_post_mime_type( $id ) ) {
            // Get attachment meta data
            $filename = get_post_meta( $id, '_wp_attached_file', true );
            $sizes = array( 0, 0, 0, 0 );
            if ( ( $uploads = wp_get_upload_dir() ) && false === $uploads['error'] ) {
                $path = trailingslashit( $uploads["basedir"] ) . $filename;
                $dom = new DOMDocument();
                if ( $dom->load( $path ) ) {
                    // Load SVG element as a DOMDocument and get width/height/viewBox attributes
                    $svgs = $dom->getElementsByTagName('svg');
                    if ( 1 === $svgs->length ) {
                        $width  = $svgs->item(0)->getAttribute('width');
                        $height = $svgs->item(0)->getAttribute('height');
                        $sizes  = explode( " ", $svgs->item(0)->getAttribute('viewBox') );
                        if ( $width ) {
                            $sizes[0] = 0;
                            $sizes[2] = $width;
                        }
                        if ( $height ) {
                            $sizes[1] = 0;
                            $sizes[3] = $height;
                        }
                    }
                }
            }
            // Initialise attachment data
            $file = basename( wp_get_attachment_url( $id ) );
            $data = array(
                "file"      => $file,
                "sizes"     => array(
                    "thumbnail" => array(
                        "file"      => $file,
                        "mime-type" => 'image/svg+xml'
                    )
                ),
                "mime-type" => 'image/svg+xml',
                "mime_type" => 'image/svg+xml'
            );
            // Only include sizes if they're present and both are not equat to 0
            if ( 4 === count( $sizes ) && ( $width = (float) $sizes[2] - (float) $sizes[0] ) && ( $height = (float) $sizes[3] - (float) $sizes[1] ) ) {
                $data["width"]  = $data["sizes"]["thumbnail"]["width"]  = $width;
                $data["height"] = $data["sizes"]["thumbnail"]["height"] = $height;
            }
        }
        return $data;
    }
    
    /**
     * Adds extra classes to body element to match
     * the current visitors' user agent / browser.
     * 
     * @action body_class Current Action
     * 
     * @param array $classes current list of body classes.
     * @param array|string $class additional classes to add
     * - should already be included.
     * @return array updated class list.
     */
    public function body_class( $classes, $class ) {
        // Get current determination of browser status
        global $is_lynx, $is_gecko, $is_IE, $is_opera, $is_NS4, $is_safari, $is_chrome, $is_iphone;
        $ua = $_SERVER['HTTP_USER_AGENT'];

        // Fix for Chrome on iOS devices
        if ( stripos( $ua, 'CriOS' ) !== false ) {
            $is_chrome = true;
            $is_iphone = $is_iphone || stripos( $ua, 'Mobile' ) !== false;
        }

        // Append specific browser classes using else/if statements
             if ( $is_lynx   ) $classes[] = 'browser_lynx';
        else if ( $is_gecko  ) $classes[] = 'browser_gecko';
        else if ( $is_opera  ) $classes[] = 'browser_opera';
        else if ( $is_NS4    ) $classes[] = 'browser_ns4';
        else if ( $is_chrome ) $classes[] = 'browser_chrome';
        else if ( $is_safari ) $classes[] = 'browser_safari';
        else if ( $is_IE     ) $classes[] = 'browser_ie';
        else                   $classes[] = 'browser_unknown';

        // Add specific IE version if available (IE 6 - 9)
        if ( $is_IE ) {
                 if ( strpos( $ua, 'MSIE 6' ) !== false ) $classes[] = 'browser_ie6';
            else if ( strpos( $ua, 'MSIE 7' ) !== false ) $classes[] = 'browser_ie7';
                 if ( strpos( $ua, 'MSIE 8' ) !== false ) $classes[] = 'browser_ie8';
                 if ( strpos( $ua, 'MSIE 9' ) !== false ) $classes[] = 'browser_ie9';
        }

        // Special class for iPhone
        if ( $is_iphone ) $classes[] = 'browser_iphone';

        return $classes;
    }
    
    /**
     * Process Slick Shortcodes using %%notation%%
     * 
     * Passes each tag through a filter. If the content
     * changes, then the whole tag is replaced with the
     * new content. If the content does not change, then
     * the tag is treated as though it was not a valid
     * shortcode, and remains unchanged.
     * 
     * May be called automatically for certain filters.
     * 
     * Can be called manually as required.
     * Default usage: echo SlickCore::$inst->slick_shortcode( $post->post_content );
     * 
     * @param string $content
     * @return string
     */
    public function slick_shortcode( $content = '' ) {
        $filter = current_filter();
        // Sanitize_title destroys shortcodes, so process the raw title first, and pass it through sanitize_title again as necessary
        if ( $filter === 'sanitize_title' ) $content = func_get_arg( 1 );
        
        // "#%%([^\r\n\s]+?)\b(:[^\r\n]*?)*?%%(.*?)%%/\1%%#" // Should match %%tag%% %%/tag%% or %%tag:attr%% %%/tag%% shortcodes - would need to do this first and ensure current content shortcodes are not affected. Not currently implemented
        // Locate shortcodes: must be of the format %%shortcode_callback:attr1:attr2::attr4%%
        if ( preg_match_all( "/%%[^\r\n\s]+?(:[^\r\n]*?)*?%%/", $content, $matches ) ) {
            // We're going to be processing the entire string
            while ( is_array( $matches ) && is_array( reset( $matches ) ) ) $matches = reset( $matches );
            $args = func_get_args();
            // Pass each through an anymous function - include both $content and $args as filter variables.
            $values = array_map( function( $match ) use ( $content, $args, $filter ) {
                /**
                 *  Replace colons within quotes with their html entity.
                 *  - Matching quotes should surround the full attribute.
                 *    i.e. %%date:"Y-m-d H:i:s"%% not %%date:Y-m-d "H:i:s"%%
                 *  - Matching quotes at either end will be trimmed. One
                 *    quote at either end, and all quotes in the middle,
                 *    will be used literally. Double the Quotes when surrounding
                 *    a string if they need to be taken literally.
                 *  Examples:
                 *    %%date:Y-m-d%%     => 2000-12-31
                 *    %%date:"Y-m-d"%%   => 2000-12-31
                 *    %%date:""Y-m-d""%% => "2000-12-31"
                 *    %%date:""Y"-m-d"%% => "2000"-12-31
                 *    %%date:"Y"-m-d%%   => "2000"-12-31
                 *    %%date:""Y-m-d%%   => ""2000-12-31
                 *  - In cases where literal quotes are between colons, use the
                 *    alternate quote around the whole string, or html/url
                 *    encode each entity instead.
                 *  Examples:
                 *    %%date:"H:i:s"%%     => 23:59:59
                 *    %%date:'H:"i":s'%%   => 23:"59":59
                 *    %%date:"H:"i%22:s"%% => 23:"59":59
                 *    %%date:"H:i&#34;:s%%  => "23:59":59
                 */
                $match = preg_replace_callback( "/(?<=:|%%)([\"\']).*?\1(?=:|%%)/", function( $match ){
                    while ( is_array( $match ) ) $match = reset( $match );
                    $match = str_replace( ':', '&#58;', $match );
                    return substr( $match, 1, strlen( $match ) - 2 ); // Trim quotes
                }, $match );
                // Trim percentage signs and separate into an array( callback, attr1, attr2, ... )
                $attrs = explode( ':', trim( $match, '%' ) );
                $attrs = array_map( 'urldecode', $attrs );
                $callback = array_shift( $attrs );
                // Pass each callback through a custom filter. Arguments: Callback name, supplied attributes, raw content, original function arguments, and current filter name.
                $value = apply_filters( 'slick_shortcode_' . $callback, $callback, $attrs, $content, $args, $filter );
                // If no content supplied by filter, then treat it as not a valid shortcode
                return $value === $callback ? $match : $value;
            }, $matches );
            
            $content = str_replace( $matches, $values, $content );
        }
        
        if ( $filter === 'sanitize_title' ) {
            // Raw title did not have a valid shortcode replacement
            if ( $content === func_get_arg( 1 ) ) {
                // Return original sanitized text
                return func_get_arg( 0 );
            }
            // Sanitize updated raw title - sanitize_title and slick_shortcode may be called up to two times.
            return sanitize_title( $content );
        }
        
        return $content;
    }
    
    /**
     * Return the current date using a shortcode.
     * 
     * Default usage: Copyright &copy; %%date:Y%%
     * Returns: Copyright © 2000
     * 
     * Additional Example formats:
     * '%%date:Y-m-d:post_date%%' => '2000-12-31',
     * '%%days:jS F%20Y:1495678388%%' => '25th May 2017',
     * '%%time:"H:i:s":now%%' => '23:59:59',
     * 
     * Url encode the date format as necessary to
     * include spaces or symbols. Also possible to wrap
     * the whole string in matching quotation marks to
     * prevent times from being split at colons.
     * 
     * @param type $callback unused
     * @param type $attrs array
     * [0] => string $format. Optional, can be URL encoded.
     *        Defaults to WordPress default date format setting.
     * [1] => mixed $timestamp. Optional, can be URL encoded.
     *        Defaults to now. Set to 'post_date' for the published date.
     *        Requires $format to be set, which can be an empty value.
     * @return string Date
     */
    public function slick_shortcode_date( $callback = 'date', $attrs = array(), $content = '', $args = array(), $filter = '' ) {
        // Collect format and timestamp - default to WordPress date format and now respectively.
        $format = urldecode( array_shift( $attrs ) ) ?: get_option( 'date_format' );
        $timestamp = urldecode( array_shift( $attrs ) ) ?: time();
        
        // Get post timestamp as necessary
        global $post;
        if ( $post ) {
            switch ( $timestamp ) {
                case 'post_date':
                    $timestamp = strtotime( $post->post_date );
                    break;
                default :
                    if ( function_exists( 'get_field' ) && ( $time = get_field( $timestamp, $post ) ) ) {
                        $timestamp = strtotime( $time );
                    }
            }
        }
        
        // Convert any non-numberical times into timestamps - assume parseable date or time.
        if ( ! filter_var( $timestamp, FILTER_VALIDATE_INT ) ) {
            $timestamp = strtotime( $timestamp );
        }
        
        // Remove time or date elements based on current callback - adjust white space and symbols as required
        switch( $callback ) {
            case 'days':
                // Dates only
                $format = preg_replace( "/(?<!\\)[aAbgGhHisuveIOPTZcrU]+/", "", $format );
                break;
            case 'time':
                // Time only
                $format = preg_replace( "/(?<!\\)[dDjlNSwzWFmMntLoYycrU]+/", "", $format );
                break;
        }
        
        return date( $format, $timestamp );
    }
    
    public function siteurl() {
        return bloginfo('siteurl');
    }
    
    /**
     * Filters Content to replace image tags with svg elements. May replace 
     * 
     * Identifies image tags where the src ends with '.svg'. The ID is used if
     * it is found via a traditional WordPress CSS class name: 'wp-image-$ID'.
     * 
     * @filter the_content Typically used by the WordPress filter 'the_content'.
     * 
     * @param string $content Content being filtered.
     * @return string Updated content string with new SVG and/or IMG tags.
     */
    public function embed_svg( $content = null ) {
        // Matches img tag, bounding src quotation marks and url.
        if ( preg_match_all( '#<img\b[^>]*[\s\t\r\n]src=(["\'])((?:(?!\1).)+\.svg)\1[^>]*>#', $content, $matches ) ) {
            $replace = array();
            foreach ( $matches[0] as $index => $tag ) {
                // Get the ID if supplied as part of a WordPress CSS class name.
                preg_match( '#[\s\t\r\n]class=(["\'])(?:(?!\1).)*\bwp-image-(\d+)\b(?:(?!\1).)*\1#', $tag, $id );
                $replace[ $tag ] = isset( $replace[ $tag ] ) ? $replace[ $tag ]
                    // Obtain the SVG tag from the load_image function.
                    : ( static::load_image( $id ? $id[2] : $matches[2][ $index ] ) ?: $tag );
            }
            // Ignore tags that generated empty values.
            $replace = array_filter( $replace );
            // Hard replace all IMG tags with their new matching SVG or IMG tag.
            $content = str_replace( array_keys( $replace ), array_values( $replace ), $content );
        }
        
        return $content;
    }
    
    /**
     * Loads an IMG or SVG tag from an attachment or URL.
     * 
     * We're sanitizing the output by loading a DOM document, pulling out an SVG
     * tag and returning it as a string. There should not be any issue with code
     * injection as we're not directly including any files. IMG tags should not
     * generate unless the attachment data is valid and the image is not an SVG.
     * 
     * @param string|int|array|WP_Post $image The attachment to load as either
     * a supplied attachment url, id array or post.
     * @param string|array $size Used during fallback.
     * 
     * @return string IMG or SVG HTML tag.
     */
    public static function load_image( $image, $size = 'large' ) {
        // Initialise path and identify if an attachment id was supplied.
        $path = false;
        $image_id =
            // $image may be a post object.
            is_a( $image, 'WP_Post' ) ? $image->ID : (
            // $image may be an image array.
            is_array( $image ) ? $image['id'] : (
            // $image may be an attachment ID - double check the number matches, i.e. (string) 123 != '123acb.png'.
            (string) ( $id = filter_var( $image, FILTER_VALIDATE_INT ) ) == $image ? $id : false ) );
        
        if ( $image_id ) {
            // Get filepath of the attachment.
            $path = get_attached_file( $image_id );
            if ( ! $path || ! file_exists( $path ) || ! is_file( $path ) || ! filesize( $path ) ) {
                // File does not exist? Default back to normal image tag.
                return wp_get_attachment_image( $image_id, $size );
            }
            // Double check that we're working with an SVG.
            $meta = is_array( $image ) ? $image : wp_get_attachment_metadata( $image_id );
            if ( 'image/svg+xml' !== $meta['mime_type'] ) {
                return wp_get_attachment_image( $image_id, $size );
            }
        }
        else if ( is_string( $image ) ) {
            // Assume the path or URL was supplied instead of an image object.
            $path = realpath( static::url_to_dir( $image ) );
            if ( 'image/svg+xml' !== mime_content_type( $path ) ) {
                // Mime type is invalid. Default back to normal image tag.
                return wp_get_attachment_image( $image, $size );
            }
        }
        
        if ( ! $path ) {
            // Path is invalid. Default back to normal image tag.
            return wp_get_attachment_image( $image_id ?: $image, $size );
        }
        
        // Try loading as a DOMDocument
        try {
            $svg = new DOMDocument;
            set_error_handler(function(){/*Disable Warnings and Notices*/});
            $loaded = $svg->load($path);
            restore_error_handler();
            if ($loaded) {
                if ('svg' === strtolower($svg->documentElement->tagName)) {
                    // Its document element is an SVG. Return as string to template.
                    return $svg->saveHTML($svg->documentElement);
                }
                $svgs = $svg->getElementsByTagName('svg');
                if ($svgs->length) {
                    // Return the first SVG found.
                    return $svg->saveHTML($svgs[0]);
                }
            }
        }
        catch( Exception $e ) {/*Do nothing on error*/}
        return wp_get_attachment_image( $image_id ?: $image, $size );
    }
    
    /**
     * Allow PDF media files to be chosen wherever an image can be selected in
     * the media library.
     * 
     * @param array $query
     * @return array
     */
    public function _filter_ajax_query_attachments_args( $query ) {
        if ( $this->pdf_previews && ! empty( $query['post_mime_type'] ) && in_array( 'image', (array) $query['post_mime_type'] ) ) {
            $query['post_mime_type'] = array_unique( array_merge( (array) $query['post_mime_type'], array( 'application/pdf' ) ) );
        }
        
        return $query;
    }
    
}

// Initialize class and store as a publicly available instance
Core::inst();
