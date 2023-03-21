<?php defined( 'App' ) or die( 'BoidCMS' );
/**
 *
 * The API plugin is an easy and efficient way to implement API functionality into your site, allowing for seamless integration with other systems.
 *
 * @package Plugin_API
 * @author Shuaib Yusuf Shuaib
 * @version 0.1.0
 */

global $App;
$App->set_action( 'install', 'api_install' );
$App->set_action( 'uninstall', 'api_uninstall' );
$App->set_action( 'slug_taken', 'api_routes' );
$App->set_action( 'render', 'api_render', 0 );
$App->set_action( 'site_head', 'api_head' );
$App->set_action( 'admin', 'api_admin' );

/**
 * Initiate API, first time install
 * @param string $plugin
 * @return void
 */
function api_install( string $plugin ): void {
  global $App;
  if ( 'api' === $plugin ) {
    $api = array();
    $api[ 'rate' ] = 10;
    $api[ 'limit' ] = 20;
    $api[ 'read' ] = bin2hex( random_bytes(32) );
    $api[ 'write' ] = bin2hex( random_bytes(32) );
    $App->set( $api, 'api' );
  }
}

/**
 * Free database space, while uninstalled
 * @param string $plugin
 * @return void
 */
function api_uninstall( string $plugin ): void {
  global $App;
  if ( 'api' === $plugin ) {
    $App->unset( 'api' );
  }
}

/**
 * API routes
 * @return string
 */
function api_routes(): string {
  return ',
  api/v1/,
  api/v1/pages,
  api/v1/pages/,
  api/v1/medias,
  api/v1/medias/,
  api/v1/themes,
  api/v1/themes/,
  api/v1/plugins,
  api/v1/plugins/,
  api/v1/settings,
  api/v1/settings/,
  api/v1/slugify,
  api/v1/slugify/,';
}

/**
 * API router
 * @return string
 */
function api_render(): void {
  global $App;
  switch ( $App->page ) {
    case 'api/v1/':
      api_rate_limit();
      api_reference();
      api_inv();
      break;
    case 'api/v1/pages':
    case 'api/v1/pages/':
      api_rate_limit();
      api_auth();
      api_pages();
      api_inv();
      break;
    case 'api/v1/medias':
    case 'api/v1/medias/':
      api_rate_limit();
      api_auth();
      api_medias();
      api_inv();
      break;
    case 'api/v1/themes':
    case 'api/v1/themes/':
      api_rate_limit();
      api_auth();
      api_themes();
      api_inv();
      break;
    case 'api/v1/plugins':
    case 'api/v1/plugins/':
      api_rate_limit();
      api_auth();
      api_plugins();
      api_inv();
      break;
    case 'api/v1/settings':
    case 'api/v1/settings/':
      api_rate_limit();
      api_auth();
      api_settings();
      api_inv();
      break;
    case 'api/v1/slugify':
    case 'api/v1/slugify/':
      api_rate_limit();
      api_auth();
      api_slugify();
      api_inv();
      break;
    default:
      $regexp = '/^(?<endpoint>api\/(?<version>v[1-9]+)\/(?<handle>.+?)\/?)$/';
      if ( preg_match( $regexp, $App->page, $match ) ) {
        $match = array_filter( $match, 'is_string', ARRAY_FILTER_USE_KEY );
        api_rate_limit();
        api_response(
          array(
            'code' => 404,
            'status' => false,
            'message' => 'Invalid endpoint',
            'data' => $match
          )
        );
        api_inv();
      }
      break;
  }
}

/**
 * API discovery link
 * @return string
 */
function api_head(): string {
  global $App;
  $base = $App->url( 'api/v1/' );
  $data = ( $App->data()[ 'pages' ][ $App->page ] ?? array() );
  $endpoint = ( ( empty( $data ) || $App->page === '404' ) ? '' : 'pages?page=' . $App->page );
  return sprintf( '<link rel="alternate api" type="application/json" href="%s%s">', $base, $endpoint );
}

/**
 * Admin settings
 * @return void
 */
function api_admin(): void {
  global $App, $layout, $page;
  switch ( $page ) {
    case 'api':
      $api = $App->get( 'api' );
      $layout[ 'title' ] = 'API';
      $layout[ 'content' ] = '
      <form action="' . $App->admin_url( '?page=api', true ) . '" method="post">
        <label for="read" class="ss-label">Read-only Token</label>
        <input type="text" id="read" name="api[read]" value="' . $App->esc( $api[ 'read' ] ) . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <p class="ss-small ss-gray ss-mb-5">This is the API readonly token.<br>Leave empty or set to "0" (zero) to generate new token.</p>
        <label for="write" class="ss-label">Write Access Token</label>
        <input type="text" id="write" name="api[write]" value="' . $App->esc( $api[ 'write' ] ) . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <p class="ss-small ss-gray ss-mb-5">This is the API write access token, <b>must be protected like a password</b>.<br>Leave empty or set to "0" (zero) to generate new token.</p>
        <label for="rate" class="ss-label">Rate Limit</label>
        <input type="number" id="rate" name="api[rate]" value="' . $api[ 'rate' ] . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <p class="ss-small ss-gray ss-mb-5">This is the maximum number of requests allowed per minute.<br>Leave empty or set to "0" (zero) to disable rate limiting.</p>
        <label for="limit" class="ss-label">Pagination Limit</label>
        <input type="number" id="limit" name="api[limit]" min="1" value="' . $api[ 'limit' ] . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto" required>
        <p class="ss-small ss-gray ss-mb-5">This is the maximum number of items to return per pagination.</p>
        <input type="hidden" name="token" value="' . $App->token() . '">
        <input type="submit" name="save" value="Save" class="ss-btn ss-mobile ss-w-5">
      </form>';
      if ( isset( $_POST[ 'save' ] ) ) {
        $App->auth();
        $api = ( $_POST[ 'api' ] ?? $api );
        $api[ 'rate' ] = ( empty( $api[ 'rate' ] ) ? 0 : $api[ 'rate' ] );
        $api[ 'limit' ] = ( empty( $api[ 'limit' ] ) ? 100 : $api[ 'limit' ] );
        $api[ 'read' ] = ( empty( $api[ 'read' ] ) ? bin2hex( random_bytes(32) ) : $api[ 'read' ] );
        $api[ 'write' ] = ( empty( $api[ 'write' ] ) ? bin2hex( random_bytes(32) ) : $api[ 'write' ] );
        if ( $App->set( $api, 'api' ) ) {
          $App->alert( 'Settings saved successfully.', 'success' );
          $App->go( $App->admin_url( '?page=api' ) );
        }
        $App->alert( 'Failed to save settings, please try again.', 'error' );
        $App->go( $App->admin_url( '?page=api' ) );
        break;
      }
      require_once $App->root( 'app/layout.php' );
      break;
  }
}

/**
 * API pages handler
 * @return void
 */
function api_pages(): void {
  global $App;
  $data = $App->data();
  $method = api_method();
  $inputs = api_inputs();
  $page = api_input_string( 'page' );
  if ( 'GET' === $method ) {
    if ( isset( $data[ 'pages' ][ $page ] ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'Page',
          'data' => $data[ 'pages' ][ $page ]
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Pages',
        'data' => api_paginate(
          $data[ 'pages' ]
        )
      )
    );
  } else if ( 'POST' === $method ) {
    $inputs[ 'data' ] = api_input_array( 'data' );
    $inputs[ 'data' ][ 'type' ] = api_data_input_string( 'type', 'page' );
    $inputs[ 'data' ][ 'title' ] = api_data_input_string( 'title', 'Page' );
    $inputs[ 'data' ][ 'descr' ] = api_data_input_string( 'descr' );
    $inputs[ 'data' ][ 'keywords' ] = api_data_input_string( 'keywords' );
    $inputs[ 'data' ][ 'content' ] = api_data_input_string( 'content' );
    $inputs[ 'data' ][ 'thumb' ] = api_data_input_string( 'thumb' );
    $inputs[ 'data' ][ 'date' ] = api_data_input_string( 'date', date( 'Y-m-d\TH:i:s' ) );
    $inputs[ 'data' ][ 'tpl' ] = api_data_input_string( 'tpl' );
    $inputs[ 'data' ][ 'pub' ] = api_data_input_bool( 'pub' );
    $slug = $App->slugify( $inputs[ 'data' ][ 'title' ] );
    if ( $App->create_page( $slug, $inputs[ 'data' ] ) ) {
      api_response(
        array(
          'code' => 201,
          'status' => true,
          'message' => 'Page created',
          'data' => array(
            'slug' => $slug
          )
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => false,
        'message' => 'Page not created',
        'data' => $inputs[ 'data' ]
      )
    );
  } else if ( 'PUT' === $method ) {
    $slug = $App->esc_slug( $page );
    if ( ! isset( $data[ 'pages' ][ $page ] ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'Page does not exist',
          'data' => array()
        )
      );
    } else {
      $inputs[ 'data' ] = api_input_array( 'data' );
      $inputs[ 'data' ][ 'pub' ] = api_data_input_bool( 'pub' );
      if ( $App->update_page( $page, $slug, $inputs[ 'data' ] ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Page updated',
            'data' => array(
              'slug' => $slug
            )
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Page not updated',
          'data' => array()
        )
      );
    }
  } else if ( 'DELETE' === $method ) {
    if ( ! isset( $data[ 'pages' ][ $page ] ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'Page does not exist',
          'data' => array()
        )
      );
    } else {
      if ( $App->delete_page( $page ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Page deleted',
            'data' => array()
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Page not deleted',
          'data' => array()
        )
      );
    }
  }
}

/**
 * API media handler
 * @return void
 */
function api_medias(): void {
  global $App;
  $method = api_method();
  if ( 'GET' === $method ) {
    $files = $App->medias;
    $file = api_input_string( 'file' );
    if ( in_array( $file, $files ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'File',
          'data' => api_media_info( $file )
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Media files',
        'data' => api_paginate(
          array_map(
            'api_media_info',
            array_values( $files )
          )
        )
      )
    );
  } else if ( 'POST' === $method ) {
    $upld = $App->upload_media( $msg, $file );
    api_response(
      array(
        'code' => 200,
        'status' => $upld,
        'message' => $msg,
        'data' => array(
          'file' => $file
        )
      )
    );
  } else if ( 'DELETE' === $method ) {
    $files = $App->medias;
    $file = api_input_string( 'file' );
    if ( ! in_array( $file, $files ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'File does not exist',
          'data' => array()
        )
      );
    } else {
      if ( $App->delete_media( $file ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'File deleted',
            'data' => array()
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'File not deleted',
          'data' => array()
        )
      );
    }
  }
}

/**
 * API themes handler
 * @return void
 */
function api_themes(): void {
  global $App;
  $method = api_method();
  $themes = $App->themes;
  $theme = api_input_string( 'theme' );
  if ( 'GET' === $method ) {
    if ( in_array( $theme, $themes ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'Theme',
          'data' => api_theme_info( $theme )
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Themes',
        'data' => api_paginate(
          array_map(
            'api_theme_info',
            $themes
          )
        )
      )
    );
  } else if ( 'PUT' === $method ) {
    if ( ! in_array( $theme, $themes ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'Theme does not exist',
          'data' => array()
        )
      );
    } else {
      if ( $App->set( $theme, 'theme' ) ) {
        $App->get_action( 'change_theme', $theme, 'api' );
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Theme activated',
            'data' => array()
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Theme not activated',
          'data' => array()
        )
      );
    }
  }
}

/**
 * API plugins handler
 * @return void
 */
function api_plugins(): void {
  global $App;
  $method = api_method();
  $plugin = api_input_string( 'plugin' );
  if ( 'GET' === $method ) {
    $plugins = $App->plugins;
    if ( in_array( $plugin, $plugins ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'Plugin',
          'data' => api_plugin_info( $plugin )
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Plugins',
        'data' => api_paginate(
          array_map(
            'api_plugin_info',
            $plugins
          )
        )
      )
    );
  } else if ( 'PUT' === $method ) {
    if ( ! in_array( $plugin, $App->plugins ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'Plugin does not exist',
          'data' => array(
            'plugin' => $plugin
          )
        )
      );
    } else {
      if ( $App->install( $plugin ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Plugin installed',
            'data' => array(
              'plugin' => $plugin
            )
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Plugin not installed' . ( $App->installed( $plugin ) ? ', already installed' : '' ),
          'data' => array(
            'plugin' => $plugin
          )
        )
      );
    }
  } else if ( 'DELETE' === $method ) {
    $plugin = api_input_string( 'plugin' );
    if ( ! $App->installed( $plugin ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Plugin not already installed',
          'data' => array(
            'plugin' => $plugin
          )
        )
      );
    } else {
      if ( $App->uninstall( $plugin ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Plugin uninstalled',
            'data' => array(
              'plugin' => $plugin
            )
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Plugin not uninstalled',
          'data' => array(
            'plugin' => $plugin
          )
        )
      );
    }
  }
}

/**
 * API settings handler
 * @return void
 */
function api_settings(): void {
  global $App;
  $data = $App->data();
  $method = api_method();
  if ( 'GET' === $method ) {
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Settings',
        'data' => $data[ 'site' ]
      )
    );
  } else if ( 'PUT' === $method ) {
    $inputs = api_inputs();
    $inputs[ 'data' ] = api_input_array( 'data' );
    $data[ 'site' ] = array_merge( $data[ 'site' ], $inputs[ 'data' ] );
    if ( $App->save( $data ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'Settings updated',
          'data' => array()
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => false,
        'message' => 'Settings not updated',
        'data' => array()
      )
    );
  }
}

/**
 * API slugify handler
 * @return void
 */
function api_slugify(): void {
  global $App;
  if ( 'GET' === api_method() ) {
    $text = api_input_string( 'text' );
    $slug = $App->slugify( $text );
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Slugify',
        'data' => array(
          'text' => $text,
          'slug' => trim( $slug, '-' )
        )
      )
    );
  }
}

/**
 * API links reference handler
 * @return void
 */
function api_reference(): void {
  global $App;
  if ( 'GET' === api_method() ) {
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Routes',
        'data' => array(
          'self' => array(
            'href' => $App->url( 'api/v1/' ),
            'methods' => [ 'GET' ]
          ),
          'api/v1/pages' => array(
            'href' => $App->url( 'api/v1/pages' ),
            'methods' => [ 'GET', 'POST', 'PUT', 'DELETE' ]
          ),
          'api/v1/medias' => array(
            'href' => $App->url( 'api/v1/medias' ),
            'methods' => [ 'GET', 'POST', 'DELETE' ]
          ),
          'api/v1/themes' => array(
            'href' => $App->url( 'api/v1/themes' ),
            'methods' => [ 'GET', 'PUT' ]
          ),
          'api/v1/plugins' => array(
            'href' => $App->url( 'api/v1/plugins' ),
            'methods' => [ 'GET', 'PUT', 'DELETE' ]
          ),
          'api/v1/settings' => array(
            'href' => $App->url( 'api/v1/settings' ),
            'methods' => [ 'GET', 'PUT' ]
          ),
          'api/v1/slugify' => array(
            'href' => $App->url( 'api/v1/slugify' ),
            'methods' => [ 'GET' ]
          )
        )
      )
    );
  }
}

/**
 * JSON Response
 * @param array $data
 * @return void
 */
function api_response( array $data ): void {
  global $App;
  http_response_code( $data[ 'code' ] );
  header( 'Access-Control-Allow-Origin: *' );
  header( 'Content-Type: application/json' );
  $data = $App->_( $data, 'api_response' );
  $json = json_encode( $data );
  exit( $json );
}

/**
 * Request method
 * @return string
 */
function api_method(): string {
  return $_SERVER[ 'REQUEST_METHOD' ];
}

/**
 * Input parameters
 * @return array
 */
function api_inputs(): array {
  $input   = file_get_contents( 'php://input' );
  $data    =        json_decode( $input, true );
  if ( json_last_error() !== JSON_ERROR_NONE ) {
    parse_str( $input, $data );
  }
  switch ( api_method() ) {
    case 'GET':
    case 'DELETE':
      return array_merge( $_GET, $data );
      break;
    case 'POST':
      return array_merge( $_POST, $data );
      break;
    case 'PUT':
      return $data;
      break;
  }
  return array();
}

/**
 * Media file info
 * @param string $file
 * @return array
 */
function api_media_info( string $file ): array {
  global $App;
  $link = $App->url( 'media/' . $file );
  $path = $App->root( 'media/' . $file );
  $finfo = new finfo( FILEINFO_MIME_TYPE );
  return array(
    'file' => $file,
    'href' => $link,
    'date' => date( DATE_W3C, filemtime( $path ) ),
    'mime' => $finfo->file( $path )
  );
}

/**
 * Theme info
 * @param string $theme
 * @return array
 */
function api_theme_info( string $theme ): array {
  global $App;
  $name = str_replace( '-', ' ', $theme );
  $current = $App->get( 'theme' );
  return array(
    'name' => ucwords( $name ),
    'active' => ( $current === $theme )
  );
}

/**
 * Plugin info
 * @param string $plugin
 * @return array
 */
function api_plugin_info( string $plugin ): array {
  global $App;
  $name = str_replace( '-', ' ', $plugin );
  $installed = $App->installed( $plugin );
  return array(
    'name' => ucwords( $name ),
    'active' => $installed
  );
}

/**
 * Paginate result
 * @param array $data
 * @return array
 */
function api_paginate( array $data ): array {
  global $App;
  $max = $App->get( 'api' )[ 'limit' ];
  $offset  = api_input_integer( 'offset', 0 );
  $limit = api_input_integer( 'limit', $max );
  $limit  = ( $limit > $max ? $max : $limit );
  $args = [ $data, $offset, $limit, true ];
  return array_slice( ...$args );
}

/**
 * API string input
 * @param string $index
 * @param string $def
 * @return mixed
 */
function api_input_string( string $index, string $default = '', ?string $key = null ): string {
  $input = ( api_inputs()[ $index ] ?? $default );
  return ( is_string( $input ) ? $input : $default );
}

/**
 * API integer input
 * @param string $index
 * @param int $default
 * @return int
 */
function api_input_integer( string $index, int $default = 0 ): int {
  $input = ( api_inputs()[ $index ] ?? $default );
  return ( is_numeric( $input ) ? $input : $default );
}

/**
 * API array input
 * @param string $index
 * @param array $default
 * @return array
 */
function api_input_array( string $index, array $default = array() ): array {
  $input = ( api_inputs()[ $index ] ?? $default );
  return ( is_array( $input ) ? $input : $default );
}

/**
 * API boolean input
 * @param string $index
 * @param bool $default
 * @return bool
 */
function api_input_bool( string $index, bool $default = false ): bool {
  return filter_var( api_inputs()[ $index ] ?? $default, FILTER_VALIDATE_BOOL );
}

/**
 * API string data input
 * @param string $index
 * @param string $default
 * @return string
 */
function api_data_input_string( string $index, string $default = '' ): string {
  $input = ( api_input_array( 'data' )[ $index ] ?? $default );
  return ( is_string( $input ) ? $input : $default );
}

/**
 * API integer data input
 * @param string $index
 * @param int $default
 * @return int
 */
function api_data_input_integer( string $index, int $default = 0 ): int {
  $input = ( api_input_array( 'data' )[ $index ] ?? $default );
  return ( is_numeric( $input ) ? $input : $default );
}

/**
 * API array data input
 * @param string $index
 * @param array $default
 * @return array
 */
function api_data_input_array( string $index, array $default = array() ): array {
  $input = ( api_input_array( 'data' )[ $index ] ?? $default );
  return ( is_array( $input ) ? $input : $default );
}

/**
 * API boolean data input
 * @param string $index
 * @param bool $default
 * @return bool
 */
function api_data_input_bool( string $index, bool $default = false ): bool {
  return filter_var( api_input_array( 'data' )[ $index ] ?? $default, FILTER_VALIDATE_BOOL );
}

/**
 * Rate limiting
 * @return void
 */
function api_rate_limit(): void {
  global $App;
  $conf = $App->get( 'api' );
  if ( 0 === $conf[ 'rate' ] ) {
    return;
  }
  $limit = $conf[ 'rate' ];
  $data = ( $_SESSION[ 'api' ][ $App->page ] ?? null );
  $data = ( $data ?? array( 'hits' => 0, 'time' => time() ) );
  $elapsed = ( time() - $data[ 'time' ] );
  if ( $elapsed > 60 ) {
    $data[ 'hits' ] = 1;
    $data[ 'time' ] = time();
  } else {
    $data[ 'hits' ]++;
    if ( $data[ 'hits' ] > $limit ) {
      $retry = 60 - $elapsed;
      header( 'Retry-After: ' . $retry );
      api_response(
        array(
          'code' => 429,
          'status' => false,
          'message' => sprintf( 'Rate limit exceeded, please wait "%d" seconds before making another request', $retry ),
          'data' => array(
            'retry' => $retry
          )
        )
      );
    }
  }
  $_SESSION[ 'api' ][ $App->page ] = $data;
}

/**
 * Authentication
 * @return void
 */
function api_auth(): void {
  global $App;
  $token = $App->get( 'api' )[ 'read' ];
  $user_token = api_input_string( 'token' );
  if ( ! hash_equals( $token, $user_token ) ) {
    api_response(
      array(
        'code' => 401,
        'status' => false,
        'message' => 'Invalid token',
        'data' => array()
      )
    );
  } else if ( 'GET' !== api_method() ) {
    $auth = $App->get( 'api' )[ 'write' ];
    $user_auth = api_input_string( 'auth' );
    if ( ! hash_equals( $auth, $user_auth ) ) {
      api_response(
        array(
          'code' => 401,
          'status' => false,
          'message' => 'Invalid "auth" token',
          'data' => array()
        )
      );
    }
  }
}

/**
 * Method not allowed
 * @return void
 */
function api_inv(): void {
  api_response(
    array(
      'code' => 403,
      'status' => false,
      'message' => 'Method not allowed',
      'data' => array()
    )
  );
}
?>
