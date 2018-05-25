<?php
/**
 * Plugin Name:     Simple Slug Translate
 * Plugin URI:      https://github.com/ko31/simple-slug-translate
 * Description:     Simple Slug Translate can translate the post, page, category and taxonomy slugs to English automatically.
 * Version:         1.2.0
 * Author:          Ko Takagi
 * Author URI:      https://go-sign.info
 * License:         GPLv2
 * Text Domain:     simple-slug-translate
 * Domain Path:     /languages
 */

$sst = new simple_slug_translate();
$sst->register();

class simple_slug_translate {

    private $version = '';
    private $text_domain = '';
    private $langs = '';
    private $plugin_slug = '';
    private $option_name = '';
    private $options;
    private $has_mbfunctions = false;

    function __construct()
    {
        $data = get_file_data(
            __FILE__,
            array(
                'ver' => 'Version',
                'langs' => 'Domain Path',
                'text_domain' => 'Text Domain'
            )
        );
        $this->version = $data['ver'];
        $this->text_domain = $data['text_domain'];
        $this->langs = $data['langs'];
        $this->plugin_slug = basename( dirname( __FILE__ ) );
        $this->option_name = basename( dirname( __FILE__ ) );
        $this->has_mbfunctions = $this->mbfunctions_exist();
    }

    public function register()
    {
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
        add_action( $this->plugin_slug . '_scheduled_event', array( $this, 'call_scheduled_event' ) );
        register_activation_hook( __FILE__ , array( $this, 'register_activation_hook' ) );
        register_deactivation_hook( __FILE__, array( $this, 'register_deactivation_hook' ) );
    }

    public function register_activation_hook()
    {
        if ( ! $this->has_mbfunctions ) {
            deactivate_plugins( __FILE__ );
            exit( __( 'Sorry, Simple Slug Translate requires <a href="http://www.php.net/manual/en/mbstring.installation.php" target="_blank">mbstring</a> functions.', $this->text_domain ) );
        }
        $options = get_option( $this->option_name );
        if ( empty( $options ) ) {
            add_option( $this->option_name, array(
                'source' => $this->get_default_source()
            ) );
        }
        if ( ! wp_next_scheduled( 'daily_sample_event' ) ) {
            wp_schedule_event( time(), 'daily', $this->plugin_slug . '_scheduled_event' );
        }
    }

    public function register_deactivation_hook()
    {
        wp_clear_scheduled_hook( $this->plugin_slug . '_scheduled_event' );
    }

    public function call_scheduled_event()
    {
        $this->translate( 'test' );
    }

    public function plugins_loaded()
    {
        load_plugin_textdomain(
            $this->text_domain,
            false,
            dirname( plugin_basename( __FILE__ ) ) . $this->langs
        );

        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'admin_init' ) );
        add_filter( 'name_save_pre', array( $this, 'name_save_pre' ) );
        add_filter( 'wp_insert_term_data', array( $this, 'wp_insert_term_data' ), 10, 3 );
    }

    public function name_save_pre( $post_name )
    {
        global $post;

        if ( $post_name ) {
            return $post_name;
        }

        if ( ! $this->is_post_type( $post->post_type ) ) {
            return $post_name;
        }

        if ( ! $this->is_post_status( $post->post_status ) ) {
            return $post_name;
        }

        if ( empty( $post->post_title ) ) {
            return $post_name;
        }

        $post_name = $this->call_translate( $post->post_title );
        $post_name = wp_unique_post_slug( $post_name, $post->ID, $post->post_status, $post->post_type, $post->post_parent );

        return $post_name;
    }

    public function is_post_type( $post_type )
    {
        /**
         * Filters the post type to translate.
         *
         * @param array $types
         */
        $types = apply_filters( 'simple_slug_translate_post_type', array(
            'post',
            'page',
        ) );

        return in_array( $post_type, $types );
    }

    public function is_post_status( $post_status )
    {
        /**
         * Filters the post status to translate.
         *
         * @param array $statuses
         */
        $statuses = apply_filters( 'simple_slug_translate_post_status', array(
            'draft',
            'publish',
        ) );

        return in_array( $post_status, $statuses );
    }

    public function wp_insert_term_data( $data, $taxonomy, $args )
    {
        if ( ! empty( $data ) && empty( $args['slug'] ) ) {
            $slug = $this->call_translate( $data['name'] );
            $slug = wp_unique_term_slug( $slug, (object) $args );
            $data['slug'] = $slug;
        }

        return $data;
    }

    public function call_translate( $text )
    {
        if ( ! $this->has_mbfunctions ) {
            return $text;
        }

        if ( strlen( $text ) == mb_strlen( $text, 'UTF-8' ) ) {
            return $text;
        }

        $result = $this->translate( $text );

        return $result['text'];
    }

    public function translate( $text )
    {

        $this->options = get_option( $this->option_name );
        if ( empty( $this->options['username'] ) ||
            empty( $this->options['password'] ) ||
            empty( $this->options['source'] ) ) {
				return array(
					'code' => '',
					'text' => $text,
				);
            }
        $auth = base64_encode( $this->options['username'] . ':' . $this->options['password'] );

        $endpoint = 'https://gateway.watsonplatform.net/language-translator/api/v2/translate';

        $response = wp_remote_post( $endpoint,
            array(
                'timeout' => 10,
                'method' => 'POST',
                'headers' => array(
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Authorization' => 'Basic ' . $auth,
                ),
                'body' => array(
                    'text' => $text,
                    'source' => $this->options['source'],
                    'target' => 'en',
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
			return array(
				'code' => '',
				'text' => $text,
			);
		}

		$code = $response['response']['code'];
		// 200 - OK
		// 400 - Bad Request
		// 401 - Unauthorized
		// 404 - Not Found
		// 500 - Server Errors
		if ( $code == 200 ) {
			$body = json_decode($response['body']);
			$text = sanitize_title( $body->translations[0]->translation );
		}

		return array(
			'code' => $code,
			'text' => $text,
		);
    }

    public function admin_menu()
    {
        add_options_page(
            __( 'Simple Slug Translate', $this->text_domain ),
            __( 'Simple Slug Translate', $this->text_domain ),
            'manage_options',
            $this->plugin_slug,
            array( $this, 'options_page' )
        );
    }

    public function admin_init()
    {
        register_setting(
            $this->plugin_slug,
            $this->option_name,
            array( $this, 'sanitize_callback' )
        );

        add_settings_section(
            'api_settings',
            __( 'API settings', $this->text_domain ),
            array( $this, 'api_section_callback' ),
            $this->plugin_slug
        );

        add_settings_field(
            'username',
            __( 'Username', $this->text_domain ),
            array( $this, 'username_callback' ),
            $this->plugin_slug,
            'api_settings'
        );

        add_settings_field(
            'userpassword',
            __( 'Password', $this->text_domain ),
            array( $this, 'password_callback' ),
            $this->plugin_slug,
            'api_settings'
        );

        add_settings_field(
            'checker',
            '',
            array( $this, 'checker_callback' ),
            $this->plugin_slug,
            'api_settings'
        );

        add_settings_section(
            'translation_settings',
            __( 'Translation settings', $this->text_domain ),
            array( $this, 'translation_section_callback' ),
            $this->plugin_slug
        );

        add_settings_field(
            'source',
            __( 'Source language', $this->text_domain ),
            array( $this, 'source_callback' ),
            $this->plugin_slug,
            'translation_settings'
        );
    }

    public function sanitize_callback( $input ) {

        if ( ! is_array( $input ) ) {
            $input = (array)$input;
        }

        if ( ! $input['username'] ) {
            add_settings_error( $this->plugin_slug, 'empty_username', __( 'Please input username', $this->text_domain ) );
        }

        if ( ! $input['password'] ) {
            add_settings_error( $this->plugin_slug, 'empty_password', __( 'Please input password', $this->text_domain ) );
        }

        if ( ! $input['source'] ) {
            add_settings_error( $this->plugin_slug, 'empty_source', __( 'Please select source language', $this->text_domain ) );
        } else if ( ! $this->is_supported_source( $input['source'] ) ) {
            add_settings_error( $this->plugin_slug, 'empty_source', __( 'Source language is invalid', $this->text_domain ) );
            $input['source'] = 'en';
        }

        return $input;
    }

    public function api_section_callback() {
        echo '<p>' . __( 'Input your own username and password for Watson Language Translator API ( <a href="https://console.ng.bluemix.net/registration/free" target="_blank">Register</a> )', $this->text_domain ) . '</p>';
    }

    public function translation_section_callback() {
        return;
    }

    public function username_callback() {
        $username = isset( $this->options['username'] ) ? $this->options['username'] : '';
?>
<input name="<?php echo $this->option_name;?>[username]" type="text" id="username" value="<?php echo $username;?>" class="regular-text">
<?php
    }

    public function password_callback() {
        $password = isset( $this->options['password'] ) ? $this->options['password'] : '';
?>
<input name="<?php echo $this->option_name;?>[password]" type="text" id="password" value="<?php echo $password;?>" class="regular-text">
<?php
    }

    public function checker_callback() {
        $username = isset( $this->options['username'] ) ? $this->options['username'] : '';
        $password = isset( $this->options['password'] ) ? $this->options['password'] : '';
        if ( $username && $password ) :
			$result = $this->translate( 'test' );
			if ( $result['code'] == 200 ) {
				echo '<p class="description">' . sprintf( __( 'Username and password are valid', $this->text_domain ), $result['code'] ) . '</p>';
			} else {
				echo '<p class="description">' . sprintf( __( 'Invalid username or password ! (status: %s)', $this->text_domain ), $result['code'] ) . '</p>';
			}
        endif;
    }

    public function source_callback() {
        $source = isset( $this->options['source'] ) ? $this->options['source'] : 'en';
?>
<select name="<?php echo $this->option_name;?>[source]" id="source">
<?php
        foreach ( $this->get_supported_sources() as $k => $v ) {
            echo '<option value="' . $k . '" ' . ( ( $source == $k ) ? 'selected="selected"' : '' ) . '>' . $v . '</option>';
        }
?>
</select>
<?php
    }

    public function options_page()
    {
        $this->options = get_option( $this->option_name );
?>
    <form action='options.php' method='post'>
        <h1><?php echo __( 'Simple Slug Translate', $this->text_domain );?></h1>
<?php
        settings_fields( $this->plugin_slug );
        do_settings_sections( $this->plugin_slug );
        submit_button();
?>
    </form>
<?php
    }

    public function get_default_source()
    {
        $language = substr( get_bloginfo('language'), 0, 2 );
        if ( $this->is_supported_source( $language ) ) {
            return $language;
        } else {
            return 'en';
        }
    }

    public function is_supported_source( $source )
    {
        $sources = $this->get_supported_sources();
        return ( isset( $sources[$source] ) ) ? true : false;
    }

    public function get_supported_sources()
    {
        return array(
            'ar' => 'ar - Arabic',
            'en' => 'en - English',
            'fr' => 'fr - French',
            'de' => 'de - German',
            'it' => 'it - Italian',
            'ja' => 'ja - Japanese',
            'ko' => 'ko - Korean',
            'pt' => 'pt - Portuguese',
            'es' => 'es - Spanish',
        );
    }

    public function mbfunctions_exist() {
        return ( function_exists( 'mb_strlen' ) ) ? true : false;
    }

} // end class simple_slug_translate

// EOF
