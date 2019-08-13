<?php

namespace Slick\Base_2_8_2;

use Exception;
use ReflectionClass;
use ReflectionMethod;

use Cake\Utility\Inflector;

/**
 * Used in most traits and classes.
 * 
 * It is used to determine the names of classes, hooks, functions and settings.
 */
trait class_helper {
    //** Helpers and Formatting Functions **//
    
    /**
     * Helper function to return either a standardised class naming system which
     * does not contain any backslashes, or a human readable string where words
     * are capitalised and separated by spaces.
     * 
     * Interprets classes whose names either use_underscores, or are either
     * camelCase or PascalCase. It will also ensure Acronyms are separated from
     * other words when they are all capitalised. Namespaces may also change in
     * a similar fashion. Digits will be separated from letters with a space.
     * 
     * Default Usages:
     * <pre>
     * $escaped_class = static::className( false, '.' );
     * $readable_name = static::className( true );
     * $callable = array( static::className(), $static_method_name );
     * </pre>
     * Examples:
     * <pre>
     * Slick\Base_2_x::className(      ) === 'Slick\Base_2_x'; // No escaping.
     * Slick\Base_2_x::className( true ) === 'Slick Base 2 X'; // Readable format
     * Slick\Base_2_x::className( true, false, 'SlickAPIClass' ) === 'Slick API Class; // Acronyms
     * </pre>
     * 
     * @param boolean $formatted Determines whether to convert symbols to spaces
     * and separate words that are camelCase or PascelCase. Default: False.
     * @param string $escaped Delimiter used to convert backslashes when denoting
     * fully qualified namespaces. Default '.' [full stop]. Set to a value which
     * equates to false to prevent backslases from being replaced.
     * @param string $unformatted_title Optional string to format instead of the
     * current fully qualified class name.
     * @return string The formatted class name.
     */
    public static function className( $formatted = false, $escaped = false, $unformatted_title = false ) {
        $formatted_title = $unformatted_title ?: get_called_class();
        if ( $escaped ) {
            $formatted_title = str_replace( '\\', $escaped, $formatted_title );
        }
        if ( $formatted ) {
            $formatted_title = ucwords( preg_replace( "/[_\/\\\\]+|([a-z])([A-Z])|([A-Z])([A-Z][a-z])|(\d)(\D)|(\D)(\d)/", "$1$3$5$7 $2$4$6$8", $formatted_title ) );
        }
        return $formatted_title;
    }
}

/**
 * Static classes designed to add or apply unique filters based on the called
 * class. Classes should use $this->filterVar( $filter, $value, ...$context ) to
 * apply filters. Invoke $inst::filter( $filter, $callback, $priority, $args )
 * from third party plugins/themes to add a new filter instead.
 */
trait custom_filters {
    // Dependent traits.
    #  use class_helper;
    
    /**
     * @var array List of filters used by the current class.
     * 
     * Can be used to display them in the help window on the settings page.
     * Add filters in the following format:
     * <pre>
     *  $filter_name => array(
     *      0 => __( ( $description = '' ), 'slick-base' ),
     *      1 => ( $number_of_args = 1 ),
     *      2 => ( $argument_list = '$arg1, $arg2' ),
     *      3 => ( $first_argument = '$arg1' ),
     *  ),
     * </pre>
     * Filters that are strings or just contain a single element will be shown
     * as just a paragraph / description. Keys do not need to be numeric, but
     * the filter items should be in the prescribed order. The first argument
     * will be collected from the argument list if it is missing, which will
     * default to '$arg' if that is missing also. Strings and arrays with less
     * than 2 elements will be translated and output without any examples.
     */
    protected static $custom_filter_list = array(
        
    );
    
    /**
     * Filter a variable using a WordPress filter. Arguments are in the same
     * order as they would appear when calling apply_filters directly.
     * 
     * @see self::filter_name(); for obtaining the full filter name.
     * 
     * Example:
     * <pre>
     * $filtered = $this->filterVar( static::filter_name( $filter ), $unfiltered );
     * </pre>
     * 
     * @param string $filter_name The short name of the filter. It will prepend
     * the sanitized class name automatically.
     * @param mixed $variable Variable to filter.
     * @param mixed $_ Any number of additional arguments to provide context.
     * 
     * @return mixed filtered variable.
     */
    protected static function filterVar( $filter_name, $variable, $_ = null ) {
        $args = func_get_args();
        $args[0] = static::filter_name( $filter_name );
        $args[1] = $variable; // Superflous, but it helps to see the order of arguments.
        return call_user_func_array( 'apply_filters', $args );
    }
    
    /**
     * Public static function used to add filters. For use with the filterVar()
     * function.
     * 
     * @param string $filter_name Name of the filter as passed to filterVar();
     * @param mixed $cb Function name or callable to run when filter is applied.
     * @param int $priority Priority of Filter. Default: 10.
     * @param int $arguments Number of arguments passed to filter. Default: 1.
     * 
     * @return boolean Should always be true when add_filter() is successful.
     */
    public static function filter( $filter_name, $cb, $priority = 10, $arguments = 1 ) {
        $args = func_get_args();
        $args[0] = static::filter_name( $filter_name );
        $args[1] = $cb;
        $args[2] = $priority;
        $args[3] = $arguments;
        return call_user_func_array( 'add_filter', $args );
    }
    
    /**
     * Generates a filter identifier based on the current class.
     * 
     * You should call it statically to prevent strict warnings, however it will
     * still work correctly if it's called directly as a method on the instance.
     * 
     * Example:
     * <pre>
     * if ( is_callable( $cb ) && is_callable( get_class( $plugin_inst ), 'filter_name' ) ) {
     *   add_filter( $plugin_inst::filter_name( $filter ), $cb );
     * }
     * </pre>
     * 
     * @param string $filter_name The short name of the filter. It will prepend
     * the sanitized class name automatically.
     * @return string The filter name combined with the class name.
     */
    public static function filter_name( $filter_name ) {
        return static::className( false, '_' ) . '_' . $filter_name;
    }
    
    /**
     * Generate a description to display filters in the help tab.
     * @param string $filter_name Name of the filter
     * @param array $filter Filter array.
     * @see self::$custom_filter_list for preferred format.
     * @return string
     */
    protected static function generate_filter_description( $filter_name, $filter ) {
        // $filter is not an array. Output string.
        if ( ! is_array( $filter ) ) {
            return wpautop( __( "{$filter}", 'slick-base' ) );
        }
        else {
            $filter = array_merge( (array) $filter_name, array_values( $filter ) );
        }
        // $filter does not even show number of arguments. Output description if found.
        if ( count( $filter ) < 3 ) {
            return wpautop( __( '' . reset( $filter ), 'slick-base' ) );
        }
        // $filter does not contain arguments. Add generic args.
        if ( count( $filter ) < 4 ) {
            $filter[3] = '$arg';
        }
        // $filter has it's arguments as a list. Format it as a string.
        if ( is_array( $filter[3] ) ) {
            $filter[3] = implode( ', ', array_walk( function( &$val, $key ) {
                if ( ! is_numeric( $key ) ) {
                    // Allow arguments to be in the format ['$arg' => '$default']
                    $val = "{$key} = {$val}";
                }
            }, $filter[3] ) );
        }
        // $filter does not separate first argument. Pull it out from argument list, including optional arguments.
        if ( count( $filter ) < 5 ) {
            $args = preg_split( '/[\s\t\r\n]*(,|=)[\s\t\r\n]*/', $filter[3], -1, PREG_SPLIT_NO_EMPTY );
            $filter[4] = reset( $args );
        }
        $filter[] = static::className( false, false );
        return wpautop( vsprintf(
            '<strong onclick="jQuery(this).closest(\'div\').find(\'pre\').slideToggle();" style="cursor:hand;">%1$s</strong><br />%2$s
<pre style="border:1px solid;display:none;padding:5px">\%6$s::inst()->filter( \'%1$s\', \'my_%1$s\', 10, %3$s );
function my_%1$s( %4$s ) {
    // ' . __( 'Do filtering and/or additional processing here', 'slick-base' ) . '
    return %5$s;
}</pre>',
            $filter
        ) );
    }
    
    /**
     * Generate the full content of the filters tab, based on filters that have
     * been documented in the <code>static::$custom_filter_list</code> array.
     * @param array $content Override the function to add additional description
     * fields to the content array as necessary.
     * @return string The full help tab content.
     */
    protected static function generate_filter_help_content( $content = array() ) {
        settype( $content, 'array' );
        foreach ( static::$custom_filter_list as $filter_name => $filter ) {
            $content[] = static::generate_filter_description( $filter_name, $filter );
        }
        if ( ( $content = array_filter( $content ) ) ) {
            return '<div>' . implode( '</div><div>', $content ) . '</div>';
        }
    }
}

/**
 * Sets up Reflection class and method getter and caching functions.
 * 
 * Required for instance progagation and hook generation.
 */
trait reflection_cache {
    /**
     * @var array Reflection class information.
     *  class   => ReflectionClass,
     *  methods => array
     *   (int) ReflectionMethod::const => array ReflectionMethod[]
     */
    protected $reflection = array(
        'class' => null,
        'methods' => array(
            
        )
    );
    
    //** Getters and Setters **//
    
    /**
     * Gets a caches a ReflectionClass of the current object.
     * 
     * @return ReflectionClass
     */
    protected function reflectionClass() {
        if ( empty( $this->reflection['class'] ) ) {
            $this->reflection['class'] = new ReflectionClass( get_called_class() );
        }
        return $this->reflection['class'];
    }
    
    /**
     * Gets and caches a list of reflectionMethods based on
     * the supplied filter.
     * 
     * @param int $filter A matching ReflectionMethod conditional constant or
     * bitwise combination. Default NULL for all methods.
     * @return array List of ReflectionMethods.
     */
    protected function reflectionMethods( $filter = null ) {
        if ( empty( $this->reflection['methods'][ $filter ] ) ) {
            $this->reflection['methods'][ $filter ] = $this->reflectionClass()->getMethods( $filter );
        }
        return $this->reflection['methods'][ $filter ];
    }
}

/**
 * Trait for wrapping WordPress actions and filters.
 */
trait actions_and_filters {
    // Dependent traits.
    # use reflection_cache;
    
    /**
     * Similar to core WordPress, add_action is just an alias of add_filter.
     * 
     * @uses self::add_filter();
     */
    protected function add_action( $action, $method = null, $priority = 10, $arguments = 1 ) {
        $this->add_filter( $action, $method, $priority, $arguments );
    }
    
    /**
     * Wrapper method for the WordPress function add_filter.
     * 
     * Method is optional and will be matched to a public method where possible.
     * Will not register any filters or actions if the method or function is not
     * callable.
     * 
     * @uses self::locateMethod(); to use a local method as necessary.
     * 
     * @param string $filter Required. Filter to add.
     * @param callable $method Optional Callable such as a string or an array.
     * @param int $priority Filter or Action priority.
     * @param int $arguments Number of arguments to pass to method.
     */
    protected function add_filter( $filter, $method = null, $priority = 10, $arguments = 1 ) {
        if ( ! function_exists( 'add_filter' ) ) { return; }
        if ( ( $method = $this->locateMethod( $method, $filter ) ) ) {
            add_filter( $filter, $method, $priority, $arguments );
        }
    }
    
    /**
     * Similar to core WordPress, remove_action is just an alias of remove_filter.
     * 
     * @uses self::remove_filter();
     */
    protected function remove_action( $action, $method = null, $priority = 10 ) {
        $this->remove_filter( $action, $method, $priority );
    }
    
    /**
     * Wrapper method for the WordPress function remove_filter.
     * 
     * Method is optional and will be matched to a public method where possible.
     * Will not register any filters or actions if the method or function is not
     * callable.
     * 
     * @uses self::locateMethod(); to use a local method as necessary.
     * 
     * @param string $filter Required. Filter to add.
     * @param callable $method Optional Callable such as a string or an array.
     * @param int $priority Filter or Action priority.
     */
    protected function remove_filter( $filter, $method = null, $priority = 10 ) {
        if ( ! function_exists( 'remove_filter' ) ) { return; }
        if ( ( $method = $this->locateMethod( $method, $filter ) ) ) {
            remove_filter( $filter, $method, $priority );
        }
    }
    
    /**
     * A method will be found for the current class.
     * 
     * When a string is supplied, if it matches a public method then it will be
     * converted to an array otherwise a function matching the method name will
     * be called instead. If the supplied method is empty, then the filter will
     * be used if it matches a public method, which may also be preceeded by an
     * underscore '_'.
     * 
     * It can be used to locate a method to call directly, for example:
     * <code>$method[0]->{"$method[1]"}();</code> however, it is primarily used
     * for the functions `call_user_func();` and `call_user_func_array();`
     * 
     * @param mixed $method Original function name or callable to process.
     * @param string $filter Filter name to check when $method is empty.
     * @param int $type The type of function to return. Pass a ReflectionMethod
     * constant, or multiple constants combined using bitwise operators. Please
     * note that any function which is not public may return false as it is not
     * callable. Public static functions should work as well as public methods.
     * @return mixed A method as an array or function name as a string. Returns
     * false when the final result is not callable.
     */
    protected function locateMethod( $method, $filter = '', $type = ReflectionMethod::IS_PUBLIC ) {
        // Compare $method and/or $filter to public method names.
        $methods = array_map( function( $method ) {
            return $method->name;
        }, $this->reflectionMethods( $type ) );
        // $method is empty - fall back to filter but only if it matches an actual method.
        if ( ! $method && $filter ) {
            if ( in_array( $filter, $methods ) ) {
                // Method $this->$filter(); exists and is public.
                $method = array( $this, $filter );
            }
            if ( in_array( "_$filter", $methods ) ) {
                // Method $this->{"_$filter"}(); exists and is public.
                $method = array( $this, "_$filter" );
            }
        }
        if ( is_string( $method ) && in_array( $method, $methods ) ) {
            // Method $this->$method(); exists and is public.
            $method = array( $this, $method );
        }
        if ( ! is_callable( $method ) ) {
            // Method does not exist or is not public, function does not exist,
            // or $method is an invalid variable.
            $method = false;
        }
        return $method;
    }
}

/**
 * Load hooks automatically based on function names.
 */
trait automatic_hooks {
    // Dependent traits.
    # use actions_and_filters;
    # use class_helper;
    # use reflection_cache;
    
    protected static $cron_tasks = array(
        
    );
    
    /**
     * Register Hooks based on function names for the current class.
     */
    protected function register_hooks() {
        $this->register_actions();
        $this->register_filters();
        $this->register_ajax();
        $this->register_shortcodes();
        $this->register_cron();
    }
    
    /**
     * Register actions simply by creating a public method with the name in the
     * following format:
     * <code>public function _action_{(string)$actionName}[_{(int)$actionPriority}][_{(int)$actionArgNum}]() {}</code>
     * 
     * $actionName must adhere to standard property regex.
     * If the action tag contains characters that cannot be used to define
     * functions, then overload the function and call those actions manually.
     * Example:
     * <pre>
     * protected function register_actions() {
     *  parent::register_actions();
     *  add_action( 'ACF\settings', array( $this, 'ACF_settings' ) );
     * }
     * </pre>
     * 
     * $actionPriority and $actionArgNum are optional, and if they are not
     * included, the preceeding underscore should also be removed. The
     * $actionPriority argument will default to 10, and $actionArgNum will
     * default to the total number of required and optional arguments for the
     * matching method. $actionPriority is required when $actionArgNum is
     * supplied, as per standard calls to add_action or add_filter. Example:
     * <pre>
     * // The following functions will add an action to 'init' with priotity 10 and 0 arguments.
     * public function _action_init() {}
     * public function _action_init_10() {}
     * public function _action_init_10_0() {}
     * </pre>
     * Priority and argument count should always be defined for actions or filters
     * that end with a number following an underscore to prevent ambiguity. Examples:
     * <pre>
     * // Adds an action for 'gform_after_submission' with priority 5 and argument count of 2.
     * public function _action_gform_after_submission_5( $entry, $form ) {}
     * // Adds an action for 'gform_after_submission' with priority 5 and argument count of 10.
     * public function _action_gform_after_submission_5_10( $entry, $form ) {}
     * // Adds an action for 'gform_after_submission_5' with priority 10 and argument count of 2.
     * public function _action_gform_after_submission_5_10_2( $entry, $form ) {}
     * </pre>
     * 
     * @see self::add_action(); for an additional method to register actions.
     */
    protected function register_actions() {
        if ( ! function_exists( 'add_action' ) ) { return; }
        $methods = $this->reflectionMethods( ReflectionMethod::IS_PUBLIC );
        foreach ( $methods as $method ) {
            // (?:_(\d+)){0,2} will not capture both the priority and arguments.
            if ( preg_match( '/^_action_(.+?)(?:_(\d+))?(?:_(\d+))?$/i', $method->name, $action ) ) {
                // Replace method name with a callable array as the second argument.
                array_shift( $action );
                array_splice( $action, 1, 0, array( array( $this, $method->name ) ) );
                // Append default priority and number of parameters if not already supplied
                array_pad( $action, 3, 10 );
                array_pad( $action, 4, $method->getNumberOfParameters() );
                // Add Action
                call_user_func_array( 'add_action', $action );
            }
        }
    }
    
    /**
     * Register filters simply by creating a public method with the name in the
     * following format:
     * <code>public function _filter_{(string)$filterName}[_{(int)$filterPriority}][_{(int)$filterArgNum}]( $_ ) {}</code>
     * 
     * @see self::register_actions() for additional examples and documentation.
     * @see self::add_action(); for an additional method to register filters.
     * 
     * @return void
     */
    protected function register_filters() {
        if ( ! function_exists( 'add_filter' ) ) { return; }
        $methods = $this->reflectionMethods( ReflectionMethod::IS_PUBLIC );
        foreach ( $methods as $method ) {
            if ( preg_match( '/^_filter_(.+?)(?:_(\d+))?(?:_(\d+))?$/i', $method->name, $filter ) ) {
                array_shift( $filter );
                array_splice( $filter, 1, 0, array( array( $this, $method->name ) ) );
                array_pad( $filter, 3, 10 );
                array_pad( $filter, 4, $method->getNumberOfParameters() );
                call_user_func_array( 'add_filter', $filter );
            }
        }
    }
    
    /**
     * Register ajax actions for both logged in users/admin pages and guests by
     * simply by creating a public method with the name in the following format:
     * <code>public function _ajax_{$tag}() { die( $ajax_output ); }</code>
     * 
     * Use the format necessary to register actions if you only want to register
     * ajax actions for either the backend or frontend. Examples:
     * <pre>public function _action_wp_ajax_{$tag}() {
     *  &nbsp; die( $admin_ajax );
     * }
     * public function _action_wp_ajax_nopriv_{$tag}() {
     *  &nbsp; die( $guest_ajax );
     * }</pre>
     * 
     * @see self::register_actions() for additional examples and documentation.
     */
    protected function register_ajax() {
        if ( ! function_exists( 'add_action' ) ) { return; }
        $methods = $this->reflectionMethods( ReflectionMethod::IS_PUBLIC );
        foreach ( $methods as $method ) {
            if ( preg_match( '/^_ajax_(.+?)$/i', $method->name, $ajax ) ) {
                while ( is_array( $ajax ) ) { $ajax = end( $ajax ); }
                // Add the ajax actions for both guests and logged in users.
                $this->add_action( "wp_ajax_{$ajax}", $method->name );
                $this->add_action( "wp_ajax_nopriv_{$ajax}", $method->name );
            }
        }
    }
    
    /**
     * Register shortcodes simply by creating a public method with the name in
     * the following format:
     * <code>public function _shortcode_{$tag}( $atts, $content = null ) {}</code>
     * 
     * @see self::register_actions() for additional examples and documentation if
     * the tag name needs to contain characters that can't be part of a function.
     */
    protected function register_shortcodes() {
        if ( ! function_exists( 'add_shortcode' ) ) { return; }
        $methods = $this->reflectionMethods( ReflectionMethod::IS_PUBLIC );
        foreach ( $methods as $method ) {
            if ( preg_match( '/^_shortcode_(.+?)$/i', $method->name, $shortcode ) ) {
                while ( is_array( $shortcode ) ) { $shortcode = end( $shortcode ); }
                // Add Shortcode
                add_shortcode( $shortcode, array( $this, $method->name ) );
            }
        }
    }
    
    /**
     * Register cron tasks by creating matching public period functions.
     * 
     * A matching period from wp_get_schedules() needs to exist in order to
     * appropriately locate and wueue the function.
     * 
     * Example:
     * <pre>
     *  public function _daily_cron() {
     *      // Runs once daily
     *  }
     * </pre>
     */
    protected function register_cron() {
        $class = static::className( false, '_' );
        if ( empty( static::$cron_tasks[ $class ] ) ) {
            static::$cron_tasks[ $class ] = array();
        }
        // Get list of cron schedule frequency/periods
        $periods = (array) wp_get_schedules();
        foreach ( $periods as $period => $data ) {
            // See if matching method exists and register cron task for the specified time.
            if ( method_exists( $this, ( $method = "_{$period}_cron" ) ) ) {
                $scheduled = wp_next_scheduled( ( $action = "{$class}_{$period}_cron" ) );
                static::$cron_tasks[ $class ][ $period ] = array_merge( array(
                    'action' => $action,
                    'method' => $method,
                    'scheduled' => $scheduled ?: time(),
                    'period' => $period,
                ), $data );
                $this->add_action( $action, $method );
                if ( ! $scheduled ) {
                    wp_schedule_event( static::$cron_tasks[ $class ][ $period ]['scheduled'], $period, $action );
                }
            }
        }
    }
}

/**
 * Used to locate the current plugin file path. Uses the file name of the called
 * class by default, as long as it is found within the plugin directory.
 */
trait plugin_addon {
    // Dependent traits.
    # use reflection_cache;
    
    /**
     * Obtain the plugin file path.
     * 
     * Should work as intended as long as the parent class was defined in the
     * main plugin file.
     * 
     * Override as required to set a new path, for instance, using a global
     * variable, static or non-static property / variable, or constant.
     * 
     * @return string Plugin filename. Return false to prevent plugin activation
     * /deactivation hooks and to prevent the settings link from being displayed.
     */
    protected function get_plugin_file_path() {
        $filename = $this->reflectionClass()->getFileName();
        if ( stristr( $filename, WP_PLUGIN_DIR ) === 0 ) {
            return $filename;
        }
    }
}

/**
 * Used to register settings and overrides.
 * 
 * This trait will need to be applied to a parent class when the protected
 * properties $settings, $enumerable and $multipart, or any of the methods,
 * need to be overwritten. Alternatively, the default settings can be set
 * inside the class constructor.
 * 
 * Any class which directly uses this trait should implement ArrayAccess and
 * Iterator for full functionality.
 * 
 * $this->register_settings_page() should be called the first time this class is
 * instantiated in order to autiomatically generate the appropriate settings
 * pages. $this->load_settings() should be called during construct.
 */
trait settings { # implements ArrayAccess, Iterator
    // Dependent traits.
    # use actions_and_filters;
    # use class_helper;
    # use custom_filters;
    # use plugin_addon;
    
    /**
     * @var array plugin Settings loaded on construct.
     * Can be accessed using magic getter. Cannot be set except during construct
     * or options update.
     * 
     * Stored in the database as an option with the name like the following:
     * <pre>
     * $name = 'plugin_settings_Slick.Base_2_x';
     * </pre>
     * 
     * If the default settings page is loaded, each field can be overwritten by
     * creating a public function with the name "{$setting}_field"; Underscores
     * are preferred over hyphens for setting keys/names for full compatibility
     * and Case should be made consistent where possible.
     * 
     * Settings will be type cast to the same type as the default values. NULL
     * should allows any type to be set but may cause issues with updating new
     * information. Resources should not be saved using this method.
     * 
     * Settings can be accessed externally using the keys as either object
     * properties or array keys. They do not update externally and cannot be
     * accessed using the settings array directly but can be looped through /
     * iterated like an array.
     * Example:
     * <pre>
     * foreach ( $this as $key => $setting ) {
     * &nbsp;   $this->settings[ $key ] === $setting;      // Iterator
     * &nbsp;   $this->settings[ $key ] === $this[ $key ]; // ArrayAccess
     * &nbsp;   $this->settings[ $key ] === $this->$key;   // self::__get( $key )
     * }
     * </pre>
     */
    protected $settings = array(
        
    );
    
    /**
     * @var array Allowable values for settings. Used for validation when saving
     * settings, as well as populating dropdowns. Apply setings in the following
     * format:
     * <pre>
     * $settings = array(
     * &nbsp;   'value1' => 'title1',
     * &nbsp;   'value2' => 'title2',
     * &nbsp;   'value3', // Title will be created automatically.
     * );
     * </pre>
     */
    protected static $enumerable = array(
        
    );
    
    /**
     * @var array Overridden settings.
     * 
     * Use in conjunction with other plugin addons, This array will at most only
     * ever have the same keys as $settings. If present and not null, this value
     * will be used when using getters.
     * 
     * Set overridden values using any of the standard setter functions, i.e:
     * <pre>
     * $this->override[ $name ] === ( $this->$name = $this[$name] = $setting );
     * </pre>
     * Or set overridden values using the override method, for example:
     * <pre>
     * // Preferred method: Single array with matching keys.
     * $this->override( [ $key => $value ] );
     * // Alternate method: Arguments in the same order as the settings array.
     * // Wrap arrays or other complex values inside an array with one element.
     * $this->override( $value1, $value2, [ $array_or_complex_value3 ] );
     * // Try to avoid (to prevent confusion): Mixing arrays and scalars
     * $this->override(
     * &nbsp;   [ $value1, $value2 ], // Same as passing as separate args.
     * &nbsp;   $value3,              // Treated as third argument.
     * &nbsp;   [ $value4 ],          // Numeric keys are renumbered when merged in.
     * &nbsp;   [ $key2 => $replaces_value2 ], // Keys are used over numeric indexes
     * &nbsp;   [ 2 => $value5 ],     // Again, numeric keys are renumbered.
     * &nbsp;   [ $key2 => null ]     // Removes $replaces_value2 and uses $value2.
     * );
     * </pre>
     * The override method will empty all overridden data before saving new info.
     */
    protected $override = array(
        
    );
    
    /**
     * @var boolean Set to True to enable file uploads on settings page.
     */
    protected $multipart = false;
    
    /**
     * Magic Getter: Gets values from settings array.
     * 
     * Gets current overridden value if present and not null.
     * 
     * @param string $name Settings array key.
     * @return mixed|null Matching setting value or NULL.
     */
    public function __get( $name ) {
        return $this->isOverridden( $name )
            ? $this->override[ $name ]
            : array_key_exists( $name, $this->settings ) ? $this->settings[ $name ] : null;
    }
    
    /**
     * Magic Setter: Prevents values from being overwritten externally.
     * 
     * Overrides value if setting name exists.
     * 
     * @param string $name Setting array key.
     * @param mixed $value New overridden value.
     * @return mixed|null Newly overridden value. Return ignored when using
     * language construct: <code>$a = $this->b = $c; $a === $c;</code>
     */
    public function __set( $name, $value ) {
        return array_key_exists( $name, $this->settings )
            ? $this->override[ $name ] = $value
            : null;
    }
    
    /**
     * Magic Un-Setter: Prevents values from being removed externally.
     * 
     * Unsets overridden values where applicable.
     * 
     * @param string $name Ignored.
     * @return null Return ignored when using language construct:
     * <code>unset( $this->a );</code>
     */
    public function __unset( $name ) {
        if ( $this->isOverridden( $name ) ) {
            unset( $this->override[ $name ] );
        }
    }
    
    /**
     * Magic Getter: Check to see if settings key exists.
     * 
     * @param string $name Settings array key.
     * @return boolean TRUE if key exists, even whan the value is NULL.
     * FALSE otherwise. <code>$this->a = null; isset( $this->a ) === true;</code>
     */
    public function __isset( $name ) {
        return array_key_exists( $name, $this->settings );
    }
    
    /**
     * Replace output when using var_dump to only show the settings.
     * Will merge in overridden values automatically.
     * 
     * Default output will show pre PHP 5.6 when instance is dumped.
     * 
     * @return array Settings array.
     */
    public function __debugInfo() {
        return array_merge( $this->settings, $this->override );
    }
    
    /**
     * Magic method: Call
     * 
     * Load settings fields dynamically.
     * @see self::_field( $key ) on how to override settings fields.
     * 
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call( $name, $arguments ) {
        if ( preg_match( "/^(.*)_field$/", $name, $name ) ) {
            while( is_array( $name ) ) { $name = end( $name ); }
            return $this->_field( $name, reset( $arguments ) );
        }
    }
    
    /**
     * Override Settings for the current request.
     * 
     * This method will remove all pre-existing overridden settings when called.
     * Use as either a separate call to self::override() to remove the current
     * overrides or use this function to also update overridden settings.
     * 
     * @see self::override() for full description and argument information.
     */
    public function cleanOverride( $settings = null, $_ = null ) {
        // Empty override array.
        $this->override = array();
        return call_user_func_array( array( $this, 'override' ), func_get_args() );
    }
    
    /**
     * Override select settings based on plugin addon settings.
     * 
     * Accepts any number of arguments, which can be any the following:
     * 1. A single array with keys which match the settings array. Preferred!
     * 2. A single array with numerical indexes which match the position of elem
     *    in the settings array. eg: reset( $this->settings ) === $settings[0];
     * 3. Each setting provided as a separate argument, either as a scalar or a
     *    numerical array with a single element: $_ = [ $some_complex_object ].
     * 4. A list of arrays which will be merged. Renumbered numerical indexes.
     * 
     * Passing both arrays and scalars as separate arguments is not recommended,
     * as the merging process can become overly complicated. In order to ensure
     * the correct information is overwritten, provide only a single array as
     * the first argument with keys that match the settings array.
     * 
     * Providing both keys and numerical indexes may also caused unexpected
     * issues. It is recommended to stick with keys for consistency.
     * 
     * @param scalar|array $settings Either an array with matching indexes/keys
     * or the first setting from the settings array.
     * @param scalar|array $_ Additional arguments typically matching the second
     * etc setting from the settings array.
     * @return type
     */
    public function override( $settings, $_ = null ) {
        // Treat all arguments as a list of overridden values.
        $settings = array_map(
            function( $a ) {
                // Convert all arguments to an array, if not already.
                return (array) $a;
            },
            func_get_args()
        );
        // Merge all arguments into single array. Numerical indexes will be
        // renumbered automatically to match the order of arguments.
#       $settings = array_merge( ...$settings ); // PHP 5.6+ only.
        $settings = array_reduce(
            $settings,
            'array_merge',
            array()
        );
        // Get and loop through keys - treat all as strings.
        $keys = array_map( 'strval', array_keys( $this->settings ) );
        foreach ( $keys as $i => $key ) {
            // Numerical or argument index should match up with settings array unless there is a matching numerical key.
            if ( isset( $settings[ $i ] ) && ! in_array( (string) $i, $keys ) ) {
                $this->override[ $key ] = $settings[ $i ];
            }
            // Keys have higher preference than numerical indexes.
            if ( isset( $settings[ $key ] ) ) {
                $this->override[ $key ] = $settings[ $key ];
            }
            // Ensure types match where possible.
            if ( isset( $this->override[ $key ] ) ) {
                switch( ( $type = gettype( $this->settings[ $key ] ) ) ) {
                    case 'null':
                        break;
                    default:
                        settype( $this->override[ $key ], $type );
                }
            }
        }
        // Return computed overrided settings for further processing.
        return $this->override;
    }
    
    /**
     * Checks to see if a setting has been overridden.
     * 
     * A setting is classed as being overridden as long as the key has been
     * set and the value is not null. Other empty values are considered valid.
     * 
     * Overload method to ignore empty values, or povide additional logic for
     * getting values using self::__get().
     * 
     * @param scalar $which Key or setting name to check. Pass null or do not
     * supply a key in order to check and see if any value has been overridden.
     * @return boolean
     */
    public function isOverridden( $which = null ) {
        if ( isset( $which ) ) {
            // Isset should check for both key and value not being null.
            return isset( $this->override[ $which ] );
        }
        return (bool) array_filter( $this->override, function( $setting ) {
            // Only filter out null values. False, 0 and empty values are valid.
            return isset( $setting );
        } );
    }
    
    //** Array Access **//
    
    /**
     * Array Access: Offset Exists
     * 
     * @param scalar $offset
     * @return boolean
     */
    public function offsetExists( $offset ) {
        return array_key_exists( $offset, $this->settings );
    }
    
    /**
     * Array Access: Offset Get
     * 
     * @param scalar $offset
     * @return mixed
     */
    public function offsetGet( $offset ) {
        return $this->$offset;
    }
    
    /**
     * Array Access: Offset Set.
     * Prevents settings from being changed
     * 
     * @param scalar $offset
     * @param mixed $value
     * @return null
     */
    public function offsetSet( $offset, $value ) {
        return $this->$offset = $value;
    }
    
    /**
     * Array Access: Offset Unset.
     * Prevents settings from being changed
     * 
     * @param scalar $offset
     * @return null
     */
    public function offsetUnset( $offset ) {
        unset( $this->$offset );
    }
    
    //** Iterator / Transversable **//
    
    /**
     * Iterator / Transversable: Current
     * @return mixed Current setting value from loop. Will return the matching
     * overridden setting if present and not null.
     */
    public function current() {
        return $this->isOverridden( $this->key() )
            ? $this->override[ $this->key() ]
            : current( $this->settings );
    }
    
    /**
     * Iterator / Transversable: Key
     * @return scalar Current setting key from loop.
     */
    public function key() {
        return key( $this->settings );
    }
    
    /**
     * Iterator / Transversable: Next
     * @return mixed Moves to next setting value. Return ignored during loop.
     */
    public function next() {
        return next( $this->settings );
    }
    
    /**
     * Iterator / Transversable: Rewind
     * @return mixed Returns to first setting value. Return ignored during loop.
     */
    public function rewind() {
        return reset( $this->settings );
    }
    
    /**
     * Iterator / Transversable: Valid
     * @return mixed Determines if loop is valid by comparing the key to null.
     */
    public function valid() {
        return $this->key() !== null;
    }
    
    /**
     * Loads settings from options.
     */
    protected function load_settings() {
        $class = static::className();
        // Clear settings cache before loading data (for long requests which may overlap).
        wp_cache_delete( 'plugin_settings_' . $class, 'options' );
        $this->update_settings( (array) get_option( 'plugin_settings_' . $class ) );
        foreach ( $this->settings as &$value ) {
            if ( is_string( $value ) ) {
                $value = stripslashes( $value );
            }
        }
    }
    
    /**
     * Saves settings to WordPress options.
     * 
     * @return mixed Boolean unless overwritten.
     * Returned values will determine which admin notice will be shown.
     */
    protected function save_settings() {
        return update_option( 'plugin_settings_' . static::className(), $this->settings );
    }
    
    /**
     * Updates current settings with matching setting values.
     * 
     * Will update all settings, so ensure all necessary keys are present to
     * prevent 'unchanged' values being set to an empty value.
     * 
     * @param array $settings
     */
    protected function update_settings( $settings ) {
        foreach ( $this->settings as $name => &$value ) {
            // Obtain the list of enumerable options.
            $enumerable = $this->enumerable( $name );
            $setting = array_key_exists( $name, $settings )
               // Extra validation when value needs to be a select dropdown.
               && ( ! $enumerable || array_key_exists( $value, $enumerable ) )
                ? $settings[ $name ] : $value; // Keep default value
            switch ( gettype( $value ) ) {
                case 'bool'   :
                case 'boolean':
                    $value = (bool)  $setting;
                    break;
                case 'string' :
                    $value = (string)$setting;
                    break;
                case 'integer':
                    $value = (int)   $setting;
                    break;
                case 'double' :
                case 'float'  :
                    $value = (float) $setting;
                    break;
                case 'array'  :
                    $value = (array) $setting;
                    break;
                case 'null'   :
                case 'object' :
                    $value = $setting;
                default:
                    // Ignore Resources, and other unknown types.
            }
        }
    }
    
    /**
     * Register actions to display a menu for the current class.
     */
    protected function register_settings_page() {
        $this->add_action( 'admin_menu', '_admin_menu' );
        $this->add_action( 'admin_init', '_admin_init' );
        if ( ( $plugin_file_path = $this->get_plugin_file_path() ) ) {
            // Link to Settings page from plugins page.
            $this->add_filter( 'plugin_action_links_' . plugin_basename( $plugin_file_path ), '_plugin_action_links' );
        }
    }
    
    /**
     * Add options page to admin menu.
     * 
     * Function starts with an underscore to prevent conflicts with other
     * add_action( 'admin_menu' ) calls in child classes. This function will be
     * used by default when <code>$this->add_action( 'admin_menu' );</code> is
     * called, unless a separate function `admin_menu` is also created.
     * 
     * Override to disable the default menu page. Invoke self::options_page() to
     * display these settings on a different page instead.
     */
    public function _admin_menu() {
        $class = static::className( false, '_' );
        $name = static::className( true );
        $options_page = add_options_page( $name, $name, 'manage_options', $class, array( $this, 'options_page' ) );
        $this->add_action( "load-{$options_page}", 'load_options_page' );
    }
    
    /**
     * Displays a help tab when content is supplied.
     * 
     * @action load-{$page}
     * 
     * Override this method and pass the help text to the parent method in order
     * to display it in a help tab on the settings page.
     * @param string $help_content The content to display. The help tab will not
     * display if no content is supplied, for instance using the default filter.
     * @param string $title optional tab title to show in the sidebar of the
     * help window and also sets the tab id. Defaults to 'Help'.
     */
    public function load_options_page( $help_content = '', $title = '' ) {
        $class = static::className( false, '_' );
        // Help tab content
        if ( $help_content && is_string( $help_content ) ) {
            get_current_screen()->add_help_tab( array(
                'id' => "{$class}-" . ( sanitize_title( $title ) ?: 'help' ),
                'title' => $title ?: __( 'Help', 'slick-base' ),
                'content' => wpautop( $help_content ),
            ) );
        }
        // Special help tab to list filters.
        if ( ( $filter_content = static::generate_filter_help_content() ) ) {
            get_current_screen()->add_help_tab( array(
                'id' => "{$class}-filters",
                'title' => __( 'Filters', 'slick-base' ),
                'content' => wpautop( $filter_content ),
            ) );
        }
    }
    
    /**
     * Output Options page html to browser.
     */
    public function options_page() {
        if ( ! $this->settings ) { return; }
        
        global $pagenow;
        $class = static::className( false, '_' );
        $name = static::className( true, false );
        $is_main_page = $pagenow === 'options-general.php' && $_GET['page'] === $class;
        ?>
        <div class="wrap">
            <?php if ( $is_main_page ) : ?>
                <h1><?php echo $name; ?></h1>
            <?php endif; ?>
            <form method="post" action="options.php" <?php if ( ! empty( $this->multipart ) ) { echo 'enctype="multipart/form-data"'; } ?>>
                <?php
                    settings_fields( $class );
                    do_settings_sections( $class );
                    submit_button( $is_main_page ? __( 'Save General Settings', 'slick-base' ) : sprintf( __( 'Save %s Settings', 'slick-base' ), $name ), 'primary', "save-{$class}" );
                ?>
            </form>
        </div>
        <?php
        
        $this->additional_options_pages();
    }
    
    /**
     * Overload additional_options_pages to display the options page for other
     * classes or to display anything else above the cron section.
     */
    public function additional_options_pages() {
        $this->cron_options();
    }
    
    /**
     * Output information relating to each cron task and give the option
     * to either process the task immediately, or reschedule it for ASAP.
     */
    public function cron_options() {
        $class = static::className( false, '_' );
        if ( isset( static::$cron_tasks[ $class ] ) && static::$cron_tasks[ $class ] ) : ?>
            <div class="wrap">
                <h2><?php _e( 'Recurring Tasks', 'slick-base' ); ?></h2>
                <?php foreach ( static::$cron_tasks[ $class ] as $task ) : ?>
                    <h3><?php echo $task['display']; ?></h3>
                    <?php
                        $action = $task['action'];
                        $cron   = $task['scheduled'];
                        $period = $task['period'];
                        // Check for and process or reschedule task.
                        if ( isset( $_REQUEST['period'] ) && (string) $_REQUEST['period'] === (string) $period   // Verify current period
                          && isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], $action ) // Verify nonce
                          && isset( $_REQUEST['action'] ) ) {
                            // Rescheulde task
                            if ( $_REQUEST['action'] === 'reschedule' ) {
                                // Check that supplied cron matches.
                                if ( isset( $_REQUEST['cron'] ) && (string) $_REQUEST['cron'] === (string) $cron ) {
                                    // Reschedule and output success message.
                                    $cron = time();
                                    wp_clear_scheduled_hook( $action );
                                    wp_schedule_event( $cron, $period, $action );
                                    echo wpautop( sprintf( __( '%s task rescheduled successfully.', 'slick-base' ), __( $period, 'slick-base' ) ) );
                                }
                                else {
                                    // Issue with supplied cron time. User may have refreshed the page.
                                    echo wpautop( sprintf( __( 'Please click the button again to reschedule the %s task.', 'slick-base' ), __( $period, 'slick-base' ) ) );
                                }
                            }
                            // Process task now
                            if ( $_REQUEST['action'] === 'process' ) {
                                $method = $task['method'];
                                try {
                                    $result = $this->$method();
                                    echo wpautop( sprintf( __( '%s task %s appears to have completed successfully.', 'slick-base' ), __( $period, 'slick-base' ), $action ) . "\n" . print_r( $result, 1 ) );
                                } catch ( Exception $ex ) {
                                    echo wpautop( sprintf( __( 'Error processing %s task: %s', 'slick-base' ), __( $period, 'slick-base' ), $action ) . "\n" . $ex->getMessage() );
                                }
                            }
                        }
                        // Display next estimated scheduled run.
                        echo wpautop( sprintf( __( 'Next scheduled run: %s', 'slick-base' ), date( __( 'd/F/Y H:i:s (P)', 'slick-base' ), $cron ) ) );
                        // Output buttons for rescheduling or processing tasks.
                        printf(
                            '<a href="%s" class="button">%s</a> ',
                            wp_nonce_url( add_query_arg( array( 'action' => 'reschedule', 'period' => $period, 'cron' => $cron ) ), $action ),
                            __( 'Reschedule for ASAP', 'slick-base' )
                        );
                        printf(
                            '<a href="%s" class="button button-primary">%s</a> ',
                            wp_nonce_url( add_query_arg( array( 'action' => 'process', 'period' => $period ) ), $action ),
                            __( 'Process task now', 'slick-base' )
                        );
                    
                endforeach; ?>
            </div>
        <?php endif;
    }
    
    /**
     * Register settings and take the opportunity to update current settings if
     * new ones were submitted.
     * 
     * Function starts with an underscore to prevent conflicts with other
     * add_action( 'admin_init' ) calls in child classes. This function will be
     * used by default when <code>$this->add_action( 'admin_init' );</code> is
     * called, unless a separate function `admin_init` is also created.
     * 
     * @see self::add_action(); and self::locateMethod();
     */
    public function _admin_init() {
        $class = static::className( false, '_' );
        if ( isset( $_POST['_wpnonce'] ) && ( $nonce = $_POST['_wpnonce'] ) && wp_verify_nonce( $nonce, $class . '-options' ) && isset( $_POST[ $class ] ) ) {
            $this->update_settings( $_POST[ $class ] );
            $saved = $this->save_settings();
            // Reload settings from options in case they did not save.
            $this->load_settings();
            // Display Admin Notice
            if ( is_bool( $saved ) || is_null( $saved ) ) {
                $saved = $saved ? 'success' : 'error';
            }
            $this->add_action( 'admin_notices', "admin_notices_$saved" );
        }
        
        $this->register_settings();
    }
    
    /**
     * Save successful admin notice.
     */
    public function admin_notices_success() {
        prinft( '<div class="notice notice-success"><p>%1$s</p></div>', __( 'Settings saved successfully.', 'slick-base' ) );
    }
    
    /**
     * Save unsuccessful admin notice.
     */
    public function admin_notices_error() {
        prinft( '<div class="notice notice-error"><p>%1$s</p></div>', __( 'Settings not saved. Please check your input and try again.', 'slick-base' ) );
    }
    
    /**
     * Register Settings for fields.
     * 
     * Override this function to adjust which fields are registered as settings,
     * as well as other features such as section titles.
     */
    protected function register_settings() {
        if ( ! $this->settings ) { return; }
        
        global $pagenow;
        $class = static::className( false, '_' );
        $name = static::className( true, false );
        
        /**
         * Add the section title. It'll use the plugin class name if it is an
         * additional settings form, or 'General Settings' when the form is on
         * the automatically generated class settings page.
         * @filter static::className( false, '_' ) . '_settings_section_title';
         * 
         * Add Filter Example:
         * <pre>
         * $plugin_inst::filter( 'settings_section_title', function( $name, $inst ) {
         *   // Do processing here.
         *   return $name;
         * }, ( $priority = 10 ), 2 );
         * </pre>
         */
        add_settings_section(
            '_field',
            $this->filterVar(
                'settings_section_title',
                $pagenow === 'options-general.php' && ( $_GET['page'] === $class ) ? __( 'General Settings', 'slick-base' ) : $name,
                $this
            ),
            array( $this, '_field' ),
            $class
        );
        
        // Registers a form field for each setting.
        foreach ( array_keys( $this->settings ) as $key ) {
            register_setting( $class, $key );
            add_settings_field(
                $key,
                ucwords( preg_replace( "/[_\-\.\/\\\\]+|([a-z])([A-Z])/", "$1 $2", $key ) ),
                array( $this, "{$key}_field" ),
                $class,
                '_field'
            );
        }
    }
    
    /**
     * Generate settings fields for current class
     * 
     * Override for individual settings by creating a function with the name in
     * the format <code>"{$setting}_field"</code>. Output the form field HTML
     * from within these functions, plus any further markup that os desirable,
     * such as a description.
     * 
     * @param string $key Field name.
     */
    public function _field( $key = '', $type = false, $attr = array() ) {
        $class = static::className( false, '_' );
        
        // Obtain the list of enumerable options.
        $enumerable = $this->enumerable( $key );
        // Allow dropdowns to be populated using static settings variables.
        if ( ! $type && $enumerable ) {
            $type = 'select';
        }
        // We can't populate a dropdown without any options...
        else if ( $type === 'select' && ! $enumerable ) {
            $type = false;
        }
        
        if ( ! $key || is_array( $key ) ) {
            // Key should always be a scalar, or more importantly a non-empty string.
        }
        else if ( array_key_exists( $key, $this->settings ) ) {
            $value = $this->settings[ $key ];
            if ( empty( $attr['id'] ) ) {
                $attr['id'] = "{$class}-{$key}";
            }
            if ( empty( $attr['name'] ) ) {
                $attr['name'] = "{$class}[{$key}]";
            }
            /**
             * Apply a unique type, such as password, by using this filter or by
             * defining the corresponding field function.
             * @filter static::className( false, '_' ) . '_settings_field_type';
             * 
             * Add Filter Example:
             * <pre>
             * $plugin_inst::filter( 'settings_field_type', function( $type, $original, $key, $value, $inst ) {
             *   // Do processing here.
             *   return $type;
             * }, ( $priority = 10 ), 5 );
             * </pre>
             */
            switch ( (string) ( $type = $this->filterVar( 'settings_field_type', $type ?: gettype( $value ), $type, $key, $value, $this ) ) ) {
                // Enumerable choices will be displayed as a select.
                case 'select':
                    printf(
                        '<select %1$s>',
                        static::_format_attr( $attr )
                    );
                    foreach ( $enumerable as $choice => $title ) {
                        printf(
                            '<option id="%1$s-%2$s-%3$s" value="%3$s" %5$s >%4$s</option>',
                            esc_attr( $class ),
                            esc_attr( $key ),
                            esc_attr( $choice ),
                            esc_html( $title ),
                            "{$choice}" === "{$value}" ? 'selected="selected"' : '' // Strict string comparison.
                        );
                    }
                    echo '</select>';
                    break;
                // Custom type: textarea.
				case 'textarea':
					printf(
						'<textarea %1$s>%2$s</textarea>',
                        static::_format_attr( $attr ),
						esc_html( $value )
					);
					break;
                // Booleans will display a checkbox by default.
                case 'bool':
                case 'boolean':
                case 'checkbox':
                    if ( isset( $attr['type'] ) ) {
                        unset( $attr['type'] );
                    }
                    $attr['value'] = 1;
                    if ( $value ) {
                        $attr['checked'] = 'checked';
                    }
                    printf(
                        // Hidden field is sent through to form when checkbox is unticked.
                        // Fix for when unticked checkboxes are the only settings being saved.
                        '<input type="hidden" id="%1$s-false" name="%2$s" value=0 />'
                      . '<input type="checkbox" %3$s />',
                        $attr['id'],
                        $attr['name'],
                        static::_format_attr( $attr )
                    );
                    break;
                // Numbers, strings, other types of variables, and custom types will show a textbox by default.
                case 'int':
                case 'integer':
                case 'double':
                case 'float':
                    $type = 'number';
                case 'str':
                case 'string':
                default :
                    if ( ! $type || ! is_string( $type ) || $type === 'str' || $type === 'string' ) {
                        $type = 'text';
                    }
                    if ( empty( $attr['type'] ) ) {
                        $attr['type'] = $type;
                    }
                    if ( ! isset( $attr['value'] ) ) {
                        $attr['value'] = $value;
                    }
                    printf(
                        '<input %1$s />',
                        static::_format_attr( $attr )
                    );
            }
        }
        else {
            // Key not found in current settings array.
        }
    }
    
    /**
     * Escapes a list of html attributes and converts them to the appropriate
     * attribute string.
     * @param array $attr List of attributes in the format: $attribute => $value
     * @return string Attributes in the following format: $attribute="$value"
     */
    protected static function _format_attr( $attr ) {
        return urldecode( http_build_query(
            array_map( function ( $attr ) {
                return stripcslashes( json_encode( $attr ) ); // Enclose in quotation marks
            }, array_filter(
                    array_map( 'esc_attr', // Escape attribute strings
                        $attr
                    )
                )
            ), '', ' '
        ) );
    }
    
    /**
     * Obtain a list of enumerable options, optionally filtered by the specified
     * key. Enumerable options can be further filtered using enumerable_options.
     * 
     * @param scalar $key Th setting option we're obtaining enumerable options.
     * @return array A list of enumerable options. It should be associative.
     */
    public static function enumerable( $key = '' ) {
        $enumerable = (array) static::$enumerable;
        
        if ( $key && is_scalar( $key ) ) {
            $enumerable = array_key_exists( $key, $enumerable )
                ? (array) $enumerable[ $key ]
                : array();
        }
        
        /**
         * Filter the list of available options for a setting.
         * @filter static::className( false, '_' ) . '_enumerable_options';
         * 
         * Add Filter Example:
         * <pre>
         * $plugin_inst::filter( 'enumerable_options', function( $enumerable, $key, $inst ) {
         *   // Do processing here.
         *   return $enumerable;
         * }, ( $priority = 10 ), 3 );
         */
        return static::filterVar( 'enumerable_options', $enumerable, $key, static::inst() );
    }
    
    /**
     * Add Link to Plugin Settings page in plugin action links.
     * 
     * Ensure to override this with the new link if the _admin_menu function is
     * changed.
     * 
     * @param array $links Default links: i.e. Deactivate and Edit.
     * @return array Updated list of plugin actions.
     */
    public function _plugin_action_links( $links ) {
        $links[ 'slick_base_2_settings' ] = sprintf(
            '<a href="%2$s">%1$s</a>',
            __( 'Settings', 'slick-base' ),
            admin_url( 'options-general.php?page=' . static::className( false, '_' ) )
        );
        return $links;
    }
}

/**
 * Helper hethods for transforming words from plural to singular and visa versa.
 * 
 * Cake\Utility\Inflector is used when it is available, but these methods do not
 * include this package by default. Simple string replacements are done instead,
 * such as trimming or appending the letter 's', when this package is not found.
 * 
 * Not all words can be transformed using simple string replacements. A common
 * example would be when the plural is completely different to the singular word
 * and another example is when the singular is meant to end with 'se'. As it is
 * normally passed to both methods, care should be taken to make sure the least
 * ambiguous word is used for generating both plurals and singular terms.
 * Examples:
 * <pre>
 * pluralise::plural('Mouse') === 'Mouses';
 * // Should be 'Mice', although 'Mouses' may be more appropriate than 'Mices'.
 * pluralise::singular('Horses') === 'Hors';
 * // Should be 'Horse'. Supplying 'Horse' would return 'Horse' and 'Horses'.
 * </pre>
 */
trait pluralise {
    // Dependent traits.
    # use class_helper;
    
    /**
     * Helper function: Pluralize a word.
     * 
     * This function is not foolproof, for instance, the word 'Mouse' would be
     * incorrectly changed to 'Mouses'. Typically the same word would be passed
     * to both the singular and plural helper functions, in which case the best
     * option is to choose the version which is lease ambiguous.
     * 
     * @uses \Cake\Utility\Inflector::pluralize() if this class exists,
     * otherwise it will fall back to standard regex replacements.
     * This plugin does NOT include a copy of this library.
     * 
     * @param string $word Word to pluralize.
     * @return string $word in its assumed plural form.
     */
    public static function plural( $word ) {
        // Sanitize word into an easily readable format. Symbols are converted
        // to spaces, and additional spaces added between capitalised words and/
        // or acronyms in all caps. Words will be capitalised automatically.
        $formatted = static::className( true, ' ', $word );
        // Use \Cake\Utility\Inflector::pluralize if possible.
        if ( class_exists( 'Cake\Utility\Inflector' ) ) {
            return Inflector::pluralize( $formatted );
        }
        // Append words ending with 's' (but not 'es') with an 'es'.
        // Convert words ending with 'y' to 'ies'.
        // Append an 's' to all other words which do not end with an 's'.
        $plural = preg_replace( array( '/([^e])s$/', '/y$/', '/([^s])$/' ), array( '$1ses', 'ies', '$1s' ), $formatted );
        // Apply additional filtering options.
        return static::filterVar( 'plural', $plural, $formatted, $word );
    }
    
    /**
     * Helper Function: Singularize a word.
     * 
     * This function is not foolproof, for instance, the word 'Horses' would be
     * incorrectly shortened to 'Hors'. Typically the same word would be passed
     * to both the singular and plural helper functions, in which case the best
     * option is to choose the version which is lease ambiguous.
     * 
     * @uses \Cake\Utility\Inflector::singularize() if this class exists,
     * otherwise it will fall back to standard regex replacements.
     * This plugin does NOT include a copy of this library.
     * 
     * @param string $word Word to singularize.
     * @return string $word in its assumed singluar form.
     */
    public static function singular( $word ) {
        // Sanitize word into an easily readable format. Symbols are converted
        // to spaces, and additional spaces added between capitalised words and/
        // or acronyms in all caps. Words will be capitalised automatically.
        $formatted = static::className( true, ' ', $word );
        // Use \Cake\Utility\Inflector::pluralize if possible.
        if ( class_exists( 'Cake\Utility\Inflector' ) ) {
            return Inflector::singularize( $formatted );
        }
        // Remove the trailing 's' if the previous letter is not an 'e'.
        // Convert words ending with 'ies' to 'y'.
        // Trims 'es' from the end of the word.
        $singular = preg_replace( array( '/([^e])s$/', '/ies$/', '/es$/' ), array( '$1', 'y', '' ), $formatted );
        // Apply additional filtering options.
        return static::filterVar( 'singular', $singular, $formatted, $word, class_exists( 'Cake\Utility\Inflector' ) );
    }
}

/**
 * Helper trait to get, set and cache transients, using a unique key.
 * 
 * Unique keys are generated by prefixing the class name to the supplied
 * $transient. The supplied $transient value should be consistent across
 * all three methods, and does not need to include a classname or prefix.
 * 
 * A database query should only fire once per request for each transient.
 */
trait transient {
    // Dependent traits.
    # use class_helper;
    
    /**
     * Cached list of transients. Each instance may have its own list, and can
     * remove items as necessary to pull in fresh transients from the database.
     * @var array [ $transient => $value ]
     * Transient keys stored in this array use the short hand notation, as sent
     * to the local get and set methods; there is no need to use transient_key.
     */
    protected $transients = [
        
    ];
    
    /**
     * Generate a transient key for saving or retrieving data from the database.
     * @param string $transient Original transient name/key.
     * @return string Transient name prepended with the sanitized class name.
     */
    protected function transient_key( $transient ) {
        settype( $transient, 'string' );
        $class = static::className( false, '.' );
        return "{$class}::{$transient}";
    }
    
    /**
     * Get a transient. Populates the cache if the transient key is not present.
     * @param string $transient Transient name/key.
     * @return mixed The value of the transient.
     */
    protected function get_transient( $transient ) {
        settype( $transient, 'string' );
        if ( ! array_key_exists( $transient, $this->transients ) ) {
            $this->transients[ $transient ] = get_transient( $this->transient_key( $transient ) );
        }
        return $this->transients[ $transient ];
    }
    
    /**
     * Set a transient and cache the value for the current request.
     * @param string $transient Transient name/key.
     * @param mixed $value Value to store.
     * @param int $expiration Expiration in seconds. Default 0 (no expiration).
     */
    protected function set_transient( $transient, $value, $expiration = 0 ) {
        settype( $transient, 'string' );
        $this->transients[ $transient ] = $value;
        return set_transient( $this->transient_key( $transient) , $value, $expiration );
    }
    
    /**
     * Delete a transient and remove it from the cache.
     * @param string $transient Transient name/key.
     */
    protected function delete_transient( $transient ) {
        settype( $transient, 'string' );
        $this->clear_transient( $transient );
        return delete_transient( $this->transient_key( $transient) );
    }
    
    /**
     * Clears the cache for a particular transient. Transients may still use
     * the WordPress cache.
     * @param string $transient Transient name/key.
     */
    protected function clear_transient( $transient ) {
        settype( $transient, 'string' );
        unset( $this->transients[ $transient ] );
    }
}

trait templates {
    # use reflection_cache;
    
    /**
     * Associative list of templates
     * @var array 
     */
    protected static $templates = array(
        
    );
    
    /**
     * Locate a template based on the supplied file or files.
     * @param type $template
     * @return type
     */
    public function locate_template_file( $template ) {
        $templates = static::$templates && array_key_exists( (string) $template, static::$templates )
            ? static::$templates[ (string) $template ]
            : $template;
        $the_template = locate_template( $templates, false )
            ?: $this->template_dir( (string) $template );
        return $the_template && is_file( $the_template ) ? $the_template : false;
    }
    
    /**
     * Use this function to return either the template directory or the path to
     * the supplied template file.
     * 
     * Override as necessary to return a different folder by default, Example:
     * <pre>
     * public function template_dir( $file = '', $dir = '../templates' ) {
     *    return parent::template_dir( $file, $dir );
     * }
     * </pre>
     * 
     * @param string $file Optional relative path to the template file.
     * @param string $dir Optional subdirectory to check. May be a relative path
     * using '..'
     * @return string|boolean The directory of the plugin file or real path of
     * the supplied template file. False if file or directory does not exist.
     */
    public function template_dir( $file = '', $dir = '' ) {
        $filepath = dirname( $this->reflectionClass()->getFileName() );
        $dirpath  = $dir  ? realpath( static::join_path( $filepath, static::relative_path( $dir, $filepath ) ) ) : $filepath;
        $filename = $file ? realpath( static::join_path( $dirpath,  static::relative_path( $file, $dirpath ) ) ) : $dirpath;
        // Final check for file_exists may be redundant.
        return $filename && file_exists( $filename ) ? $filename : false;
    }
    
    /**
     * Example filter for loading specific template file.
     */
    # public function _filter_template_include( $template ) {
    #     if ( is_post_type_archive( ( $post_type = '' ) ) ) {
    #         $template = $this->locate_template_file( ( $relative_template_path = '' ) )
    #             ?: $template;
    #     }
    #     
    #     return $template;
    # }
    
    /**
     * Converts file URLs to matching paths.
     * 
     * @param string $uri
     * @return string
     */
    public static function url_to_dir( $uri ) {
        return str_replace( get_site_url(), realpath( ABSPATH ), $uri );
    }
    
    /**
     * Converts file paths to matching URLs.
     * 
     * @param string $path
     * @return string
     */
    public static function dir_to_url( $path ) {
        return str_replace( realpath( ABSPATH ), get_site_url(), realpath( $path ) );
    }
    
    /**
     * Join URL or directory paths into a single path or URI.
     * @param string $_ Any number of directory paths as strings.
     * @return string Full path as a string.
     */
    public static function join_path( $_ = '' ) {
        return DIRECTORY_SEPARATOR . implode( DIRECTORY_SEPARATOR, array_map( function( $part ) {
            return trim( $part, DIRECTORY_SEPARATOR );
        }, func_get_args() ) );
    }
    
    /**
     * Obtain the relative path of a plugin.
     * @param string $path Original path.
     * @return string Relative path to plugin file. Does not include a leading
     * forward slash, in order to match the way they are stored in the database.
     */
    public static function relative_path( $path, $root = WP_PLUGIN_DIR ) {
        return trim(
            str_replace( $root, '', $path ),
            DIRECTORY_SEPARATOR
        );
    }
}