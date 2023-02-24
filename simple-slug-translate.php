<?php
/**
 * Plugin Name:     Simple Slug Translate
 * Plugin URI:      https://github.com/ko31/simple-slug-translate
 * Description:     Simple Slug Translate can translate the post, page, category and taxonomy slugs to English automatically.
 * Version:         2.7.3
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

	function __construct() {
		$data                  = get_file_data(
			__FILE__,
			array(
				'ver'         => 'Version',
				'langs'       => 'Domain Path',
				'text_domain' => 'Text Domain'
			)
		);
		$this->version         = $data['ver'];
		$this->text_domain     = $data['text_domain'];
		$this->langs           = $data['langs'];
		$this->plugin_slug     = basename( dirname( __FILE__ ) );
		$this->option_name     = basename( dirname( __FILE__ ) );
		$this->has_mbfunctions = $this->mbfunctions_exist();
	}

	public function register() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
		add_action( $this->plugin_slug . '_scheduled_event', array( $this, 'call_scheduled_event' ) );
		register_activation_hook( __FILE__, array( $this, 'register_activation_hook' ) );
		register_deactivation_hook( __FILE__, array( $this, 'register_deactivation_hook' ) );
	}

	public function register_activation_hook() {
		if ( ! $this->has_mbfunctions ) {
			deactivate_plugins( __FILE__ );
			exit( __( 'Sorry, Simple Slug Translate requires <a href="http://www.php.net/manual/en/mbstring.installation.php" target="_blank">mbstring</a> functions.', $this->text_domain ) );
		}
		$options = get_option( $this->option_name );
		if ( empty( $options ) ) {
			add_option( $this->option_name, array(
				'source'     => $this->get_default_source(),
				'post_types' => array( 'post', 'page' ),
			) );
		}
		if ( ! wp_next_scheduled( $this->plugin_slug . '_scheduled_event' ) ) {
			wp_schedule_event( time(), 'daily', $this->plugin_slug . '_scheduled_event' );
		}
	}

	public function register_deactivation_hook() {
		wp_clear_scheduled_hook( $this->plugin_slug . '_scheduled_event' );
	}

	public function call_scheduled_event() {
		$this->translate( 'test' );
	}

	public function plugins_loaded() {
		$this->options = get_option( $this->option_name );

		load_plugin_textdomain(
			$this->text_domain,
			false,
			dirname( plugin_basename( __FILE__ ) ) . $this->langs
		);

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_filter( 'name_save_pre', array( $this, 'name_save_pre' ) );
		add_filter( 'wp_insert_term_data', array( $this, 'wp_insert_term_data' ), 10, 3 );

		$this->activate_post_type();
	}

	public function activate_post_type() {
		if ( empty( $this->options['post_types'] ) ) {
			return false;
		}

		foreach ( $this->options['post_types'] as $post_type ) {
			add_filter( 'rest_insert_' . $post_type, array( $this, 'rest_insert_post' ), 10, 2 );
		}
	}

	public function rest_insert_post( $post, $request ) {

		if (
			empty( $this->options['overwrite'] )
			&& ! empty( $post->post_name )
			&& ( strtolower( $post->post_name ) !== strtolower( urlencode( $post->post_title ) ) ) /* Except when publishing immediately */
		) {
			return;
		}

		if ( ! $this->is_post_type( $post->post_type ) ) {
			return;
		}

		if ( ! $this->is_post_status( $post->post_status ) ) {
			return;
		}

		if ( empty( $post->post_title ) ) {
			return;
		}

		$post_name = $this->call_translate( $post->post_title );
		$post_name = wp_unique_post_slug( $post_name, $post->ID, $post->post_status, $post->post_type, $post->post_parent );

		wp_update_post( array(
			'ID'        => $post->ID,
			'post_name' => $post_name,
		) );
	}

	public function name_save_pre( $post_name ) {
		global $post;

		// Do nothing when API is called.
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return $post_name;
		}

		if ( empty( $this->options['overwrite'] ) && $post_name ) {
			return $post_name;
		}

		if ( empty( $post ) ) {
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

	public function is_post_type( $post_type ) {
		if ( empty( $this->options['post_types'] ) ) {
			return false;
		}

		foreach ( $this->options['post_types'] as $enabled_post_type ) {
			if ( $enabled_post_type == $post_type ) {
				return true;
			}
		}

		return false;
	}

	public function is_taxonomy( $taxonomy ) {
		if ( empty( $this->options['taxonomies'] ) ) {
			return false;
		}

		foreach ( $this->options['taxonomies'] as $enabled_taxonomy ) {
			if ( $enabled_taxonomy == $taxonomy ) {
				return true;
			}
		}

		return false;
	}

	public function is_post_status( $post_status ) {
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

	public function wp_insert_term_data( $data, $taxonomy, $args ) {
		if ( ! $this->is_taxonomy( $taxonomy ) ) {
			return $data;
		}

		if ( ! empty( $data ) && empty( $args['slug'] ) ) {
			$slug         = $this->call_translate( $data['name'] );
			$slug         = wp_unique_term_slug( $slug, (object) $args );
			$data['slug'] = $slug;
		}

		return $data;
	}

	public function call_translate( $text ) {
		if ( ! $this->has_mbfunctions ) {
			return $text;
		}

		if ( strlen( $text ) == mb_strlen( $text, 'UTF-8' ) ) {
			return $text;
		}

		$result = $this->translate( $text );

		return ( ! empty( $result['text'] ) ) ? $result['text'] : $text;
	}

	public function translate( $text ) {
		if ( empty( $this->options['apikey'] ) ||
		     empty( $this->options['source'] ) ) {
			return array(
				'code' => '0',
				'text' => $text,
			);
		}
		$auth = base64_encode( 'apikey:' . $this->options['apikey'] );

		$location_endpoint = ( $this->options['endpoint'] ) ?: $this->get_default_endpoint();
		$endpoint          = $location_endpoint . '/v3/translate?version=2018-05-01';

		$response = wp_remote_post( $endpoint,
			array(
				'timeout' => 10,
				'method'  => 'POST',
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'Authorization' => 'Basic ' . $auth,
				),
				'body'    => json_encode( array(
					'text'   => $text,
					'source' => $this->options['source'],
					'target' => 'en',
				) ),
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
			$body = json_decode( $response['body'] );
			$text = sanitize_title( $body->translations[0]->translation );
		}

		/**
		 * Filters the translated results
		 *
		 * @param array $results
		 */
		$results = apply_filters( 'simple_slug_translate_results', array(
			'code' => $code,
			'text' => $text,
		) );

		return $results;
	}

	public function admin_menu() {
		add_options_page(
			__( 'Simple Slug Translate', $this->text_domain ),
			__( 'Simple Slug Translate', $this->text_domain ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'options_page' )
		);
	}

	public function admin_init() {
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
			'apikey',
			__( 'API key', $this->text_domain ),
			array( $this, 'apikey_callback' ),
			$this->plugin_slug,
			'api_settings'
		);

		add_settings_field(
			'endpoint',
			__( 'Endpoint URL', $this->text_domain ),
			array( $this, 'endpoint_callback' ),
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

		add_settings_section(
			'permission_settings',
			__( 'Permission settings', $this->text_domain ),
			array( $this, 'permission_section_callback' ),
			$this->plugin_slug
		);

		add_settings_field(
			'source',
			__( 'Enabled post types', $this->text_domain ),
			array( $this, 'post_types_callback' ),
			$this->plugin_slug,
			'permission_settings'
		);

		add_settings_field(
			'taxonomies',
			__( 'Enabled taxonomies', $this->text_domain ),
			array( $this, 'taxonomies_callback' ),
			$this->plugin_slug,
			'permission_settings'
		);

		add_settings_field(
			'overwrite',
			__( 'Overwrite', $this->text_domain ),
			array( $this, 'overwrite_callback' ),
			$this->plugin_slug,
			'permission_settings'
		);
	}

	public function sanitize_callback( $input ) {

		if ( ! is_array( $input ) ) {
			$input = (array) $input;
		}

		if ( ! $input['apikey'] ) {
			add_settings_error( $this->plugin_slug, 'empty_apikey', __( 'Please input API key', $this->text_domain ) );
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
		echo '<p>' . __( 'Input your own API key, Endpoint URL for Watson Language Translator API ( <a href="https://console.ng.bluemix.net/registration/free" target="_blank">Register</a> )', $this->text_domain ) . '</p>';
	}

	public function translation_section_callback() {
		return;
	}

	public function permission_section_callback() {
		return;
	}

	public function apikey_callback() {
		$apikey = isset( $this->options['apikey'] ) ? $this->options['apikey'] : '';
		?>
        <input name="<?php echo $this->option_name; ?>[apikey]" type="text" id="apikey" value="<?php echo esc_html( $apikey ); ?>"
               class="regular-text">
		<?php
	}

	public function endpoint_callback() {
		$endpoint = isset( $this->options['endpoint'] ) ? $this->options['endpoint'] : '';
		?>
        <input name="<?php echo $this->option_name; ?>[endpoint]" type="text" id="endpoint" value="<?php echo esc_html( $endpoint ); ?>"
               placeholder="<?php echo esc_attr( $this->get_default_endpoint() ); ?>" class="regular-text">
		<?php
	}

	public function checker_callback() {
		$apikey = isset( $this->options['apikey'] ) ? $this->options['apikey'] : '';
		if ( $apikey ) :
			$result = $this->translate( 'test' );
			if ( $result['code'] == 200 ) {
				echo '<p class="description">' . sprintf( __( 'API settings is valid', $this->text_domain ), esc_html( $result['code'] ) ) . '</p>';
			} else {
				echo '<p class="description">' . sprintf( __( 'API settings is Invalid ! (status: %s)', $this->text_domain ), esc_html( $result['code'] ) ) . '</p>';
			}
		endif;
	}

	public function source_callback() {
		$source = isset( $this->options['source'] ) ? $this->options['source'] : 'en';
		?>
        <select name="<?php echo $this->option_name; ?>[source]" id="source">
			<?php
			foreach ( $this->get_supported_sources() as $k => $v ) {
				echo '<option value="' . esc_attr( $k ) . '" ' . ( ( $source == $k ) ? 'selected="selected"' : '' ) . '>' . esc_html( $v ) . '</option>';
			}
			?>
        </select>
		<?php
	}

	public function post_types_callback() {
		$post_types = get_post_types( array(
			'show_ui' => true
		), 'objects' );
		foreach ( $post_types as $post_type ) :
			if ( $post_type->name == 'attachment' || $post_type->name == 'wp_block' ) :
				continue;
			endif;
			?>
            <label>
                <input
                        type="checkbox"
                        name="<?php echo esc_attr( $this->option_name ); ?>[post_types][]"
                        value="<?php echo esc_attr( $post_type->name ); ?>"
					<?php if ( $this->is_post_type( $post_type->name ) ) : ?>
                        checked="checked"
					<?php endif; ?>
                />
				<?php echo esc_html( $post_type->labels->name ); ?>
            </label>
		<?php
		endforeach;
	}

	public function taxonomies_callback() {
		$taxonomies = get_taxonomies( array(
			'show_ui' => true
		), 'objects' );
		foreach ( $taxonomies as $taxonomy ) :
			?>
            <label>
                <input
                        type="checkbox"
                        name="<?php echo esc_attr( $this->option_name ); ?>[taxonomies][]"
                        value="<?php echo esc_attr( $taxonomy->name ); ?>"
	                <?php if ( $this->is_taxonomy( $taxonomy->name ) ) : ?>
                        checked="checked"
	                <?php endif; ?>
                />
				<?php echo esc_html( $taxonomy->labels->name ); ?>
            </label>
		<?php
		endforeach;
	}

	public function overwrite_callback() {
		$overwrite = isset( $this->options['overwrite'] ) ? $this->options['overwrite'] : '';
		?>
        <label>
            <input
                    type="checkbox"
                    name="<?php echo esc_attr( $this->option_name ); ?>[overwrite]"
                    value="1"
				<?php if ( $overwrite ) : ?>
                    checked="checked"
				<?php endif; ?>
            />
			<?php _e( 'Check if you want to overwrite the slug', $this->text_domain ); ?>
        </label>
		<?php
	}

	public function options_page() {
		?>
        <form action='options.php' method='post'>
            <h1><?php echo __( 'Simple Slug Translate', $this->text_domain ); ?></h1>
			<?php
			settings_fields( $this->plugin_slug );
			do_settings_sections( $this->plugin_slug );
			submit_button();
			?>
        </form>
		<?php
	}

	public function get_default_source() {
		$language = substr( get_bloginfo( 'language' ), 0, 2 );
		if ( $this->is_supported_source( $language ) ) {
			return $language;
		} else {
			return 'en';
		}
	}

	public function is_supported_source( $source ) {
		$sources = $this->get_supported_sources();

		return ( isset( $sources[ $source ] ) ) ? true : false;
	}

	public function get_supported_sources() {
		return array(
			'ar' => 'ar - Arabic',
			'bg' => 'bg - Bulgarian',
			'bn' => 'bn - Bengali',
			'cs' => 'cs - Czech',
			'da' => 'da - Danish',
			'de' => 'de - German',
			'el' => 'el - Greek',
			'en' => 'en - English',
			'es' => 'es - Spanish',
			'fi' => 'fi - Finnish',
			'fr' => 'fr - French',
			'gu' => 'gu - Gujarati',
			'he' => 'he - Hebrew',
			'hi' => 'hi - Hindi',
			'hu' => 'hu - Hungarian',
			'it' => 'it - Italian',
			'ja' => 'ja - Japanese',
			'ko' => 'ko - Korean',
			'lv' => 'lv - Latvian',
			'ml' => 'ml - Malayalam',
			'nb' => 'nb - Norwegian Bokmal',
			'ne' => 'ne - Nepali',
			'nl' => 'nl - Dutch',
			'pl' => 'pl - Polish',
			'pt' => 'pt - Portuguese',
			'ro' => 'ro - Romanian',
			'ru' => 'ru - Russian',
			'si' => 'si - Sinhala',
			'sk' => 'sk - Slovakian',
			'sl' => 'sl - Slovenian',
			'sr' => 'sr - Serbian',
			'sv' => 'sv - Swedish',
			'th' => 'th - Thai',
			'tr' => 'tr - Turkish',
			'uk' => 'uk - Ukrainian',
			'ur' => 'ur - Urdu',
			'vi' => 'vi - Vietnamese',
			'zh' => 'zh - Simplified Chinese',
			'zh-TW' => 'zh-TW - Traditional Chinese',
		);
	}

	public function get_default_endpoint() {
		return 'https://gateway.watsonplatform.net/language-translator/api';
	}

	public function mbfunctions_exist() {
		return ( function_exists( 'mb_strlen' ) ) ? true : false;
	}

} // end class simple_slug_translate

// EOF
