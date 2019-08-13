<?php
/**
 * Slick collection of modules.
 */
namespace Slick;

// Core
use ArrayAccess;
use Exception;
use Iterator;
use ReflectionClass;
use ReflectionException;

// Traits
use Slick\Base_2_8_2\actions_and_filters;
use Slick\Base_2_8_2\automatic_hooks;
use Slick\Base_2_8_2\class_helper;
use Slick\Base_2_8_2\custom_filters;
use Slick\Base_2_8_2\plugin_addon;
use Slick\Base_2_8_2\reflection_cache;
use Slick\Base_2_8_2\settings;
use Slick\Base_2_8_2\templates;
use Slick\Base_2_8_2\transient;

if ( ! class_exists( 'Slick\Base_2_x' ) ) :
/**
 * Basic class for recording initialised classes.
 */
abstract class Base_2_x {
    /**
     * @var array Group of objects which have been initialised.
     * Prevents multiples of the same object from being created and each hook
     * from being registered multiple times.
     * 
     * Example:
     * <pre>
     *  static::$instances[ get_called_class() ] = new static;
     * </pre>
     */
    protected static $instances = array(
        
    );
    
    /**
     * Check to ensure the called class does not directly extend Base_2_x.
     * 
     * @throws Warning when a class which extends Base_2_x is instantiated.
     */
    public function __constuct() {
        if ( get_parent_class( $this ) === get_class() ) {
            user_error( sprintf( 'The class %s should only be extended by other abstract classes.', get_class() ), E_USER_WARNING );
        }
    }
} endif;

if ( ! class_exists( 'Slick\Base_2_8_2' ) ) :
    
if ( ! trait_exists( 'Slick\Base_2_8_2\actions_and_filters' ) ) {
    // Load trait functions necessary for class.
    require_once 'traits.php';
}

/**
 * Base Class for registering plugin and theme modules.
 */
abstract class Base_2_8_2 extends Base_2_x implements ArrayAccess, Iterator {
    /**
     * Traits necessary to load settings and register all necessary hooks.
     * Some of these traits will load other traits automatically, however,
     * the full list of trats are here for completeness.
     */
    use actions_and_filters;
    use automatic_hooks;
    use class_helper;
    use custom_filters;
    use plugin_addon;
    use reflection_cache;
    use settings;
    use templates;
    use transient;
    
    //** Constructors **//
    
    /**
     * Allows any instantiable class to be initialised and cached for the
     * current request, as long as the construct arguments are not required.
     * 
     * @uses ReflectionClass to determine if the class is instantiable.
     * 
     * @throws Warning when instances cannot be created.
     * 
     * @param string $classname Name of class to instaniate.
     * Defaults to currently called class.
     * @param mixed $_ Any number of additional arguments to pass on construct.
     * @return static|object|boolean The original instance of any class created by
     * this constructor or false if the class is not instantiable.
     */
    public static function inst( $classname = '', $_ = null /* ...$args /* PHP 5.6+ Only */ ) {
        if ( ! $classname ) { $classname = get_called_class(); }
        if ( ! array_key_exists( $classname, static::$instances ) ) {
            static::$instances[ $classname ] = false;
            try {
                $reflectionClass = new ReflectionClass( $classname );
                if ( $reflectionClass->isInstantiable() ) {
                    $params = PHP_INT_MAX;
                    // Use additional arguments supplied to static::inst when creating a new instance.
                    $args = array_slice( func_get_args(), 1 );
                    if (
                        ! ( $constuctor = $reflectionClass->getConstructor() ) ||
                        ! ( $params = $constuctor->getNumberOfRequiredParameters() ) ||
                        count( $args ) >= $params
                    ) {
                        static::$instances[ $classname ] = $reflectionClass->newInstanceArgs( $args );
                    }
                    else {
                        user_error( sprintf( 'Class %1$s cannot be instantiated using the method %2$s::inst(). Insufficient arguments supplied.', $classname, get_called_class() ), E_USER_WARNING );
                    }
                }
                else {
                    user_error( sprintf( 'Class %1$s cannot be instantiated using the method %2$s::inst(). It may have a private constructor, or it is either abstract, an interface or a trait.', $classname, get_called_class() ), E_USER_WARNING );
                }
            }
            catch ( Exception $e ) {
                user_error( sprintf( 'Error attempting to instantiate class %1$s using the method %2$s::inst(): %3$s', $classname, get_called_class(), $e->getMessage() ), E_USER_WARNING );
            }
            catch ( ReflectionException $e ) {
                user_error( sprintf( 'Error attempting to instantiate class %1$s using the method %2$s::inst(): %3$s', $classname, get_called_class(), $e->getMessage() ), E_USER_WARNING );
            }
        }
        return static::$instances[ $classname ];
    }
    
    /**
     * Loads settings, and registers hooks.
     * 
     * Prevents hooks from being called multiple times
     * by checking to ensure that it's the only instance.
     * 
     * @return void
     */
    public function __construct() {
        // Always load settings so that they're accessibly from the current instance.
        $this->load_settings();
        
        // Perform additional initialisation for the first instance.
        $class = static::className( false, false );
        if ( ! array_key_exists( $class, static::$instances ) || static::$instances[ $class ] === false ) {
            static::$instances[ $class ] = $this;
            
            $this->register_hooks();
            $this->register_widgets();
            $this->register_activation_deactivation();
            if ( $this->settings ) {
                $this->register_settings_page();
            }
        }
    }
    
    /**
     * Register widgets as required.
     * 
     * Standard process is to put the widgets into a separate file and then load
     * them here using require_once. The file should also ensure the widget does
     * not yet exist and register the widget using the 'widgets_init' action.
     * Alternatively, you can create a new method called '_action_widgets_init',
     * in order to ensure the widgets are only registered at the correct time.
     */
    protected function register_widgets() {
        // require_once $path_to_widget; $widget::inst();
    }
    
    /**
     * Register Activation and Deactivation hooks.
     * 
     * Use 'after_switch_theme' and 'switch_theme', for
     * classes that are part of a theme, instead of the
     * the standard plugin activation/deactivation hooks.
     */
    protected function register_activation_deactivation() {
        // Get main plugin file path
        if ( ( $plugin_file_path = $this->get_plugin_file_path() ) ) {
            // Register Plugin Hooks
            register_activation_hook( $plugin_file_path, array( $this, 'register_activation_hook' ) );
            register_deactivation_hook( $plugin_file_path, array( $this, 'register_deactivation_hook' ) );
        }
        
        // Register Theme Hooks
        $this->add_action( 'after_switch_theme' );
        $this->add_action( 'switch_theme' );
    }
    
    /**
     * Plugin Hook: Register Activation
     * 
     * Will currently upgrade option names to new naming convention. Ensure you
     * include parent::register_activation_hook(), as the activation function
     * may be updated in future.
     */
    public function register_activation_hook() {
        $oldnames = array(
            'plugin_settings_' . get_called_class(),
        );
        foreach ( $oldnames as $oldname ) {
            // Simple switch over - should be no issues if no settings were saved with old naming conventions.
            $this->update_settings( (array) maybe_unserialize( get_option( $oldname ) ) );
            // Remove old settings as they would now be redundant.
            delete_option( $oldname );
        }
        // Ignore any new import logic from overloaded functions - simple 'update_option' call.
        self::save_settings();
    }
    
    /**
     * Plugin Hook: Register Deactivation
     * 
     * Remove any scheduled cron tasks for this plugin.
     * Ensure to run parent::register_deactivation_hook() when overloading.
     */
    public function register_deactivation_hook() {
        $class = static::className( false, '_' );
        // Get list of cron schedule frequency/periods
        $periods = array_keys( (array) wp_get_schedules() );
        foreach ( $periods as $period ) {
            // Unregister all scheduled cron tasks for each possible combination.
            wp_clear_scheduled_hook( "{$class}_{$period}_cron" );
        }
    }
    
    /**
     * Theme Hook: Activation
     */
    public function after_switch_theme() {
        // Overwrite function to trigger on theme activation.
    }
    
    /**
     * Theme Hook: Deactivation
     */
    public function switch_theme() {
        // Overwrite function to trigger on theme deactivation.
    }
    
} endif;

/**
 * Initialise plugin and store as a public static variable.
 * Recommended but not required.
 * 
 * The first instance of any class which extends the \Slick\Base_2_x classes
 * should be saved automatically on construct. Additional instances will load
 * the settings, but they will not be registered as the primary instance or
 * re-register additional hooks and actions.
 * 
 * See below for examples.
 */
# // The first instance will be automatically saved on construct.
# // Additional instances will not.
# assert( new $classname === $classname::inst() && new $classname !== $classname::inst() );
# 
# // Preferred method:
# // The classname is optional when inst called from the class directly.
# $instance = $classname::inst( ( $_classname = '' ) );
# 
# // Alternate method:
# // Allows an instance of almost any class. Checks are performed to match args.
# $instance = \Slick\Base_2_8::inst( $classname, ...( $_constructor_args = [] ) );
