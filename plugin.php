<?php defined( 'App' ) or die( 'BoidCMS' );
/**
 *
 * API – A RESTful API for your website
 *
 * @package Plugin_API
 * @author Shuaib Yusuf Shuaib
 * @version 0.1.0
 */

// Ensure the plugin is installed properly
if ( 'api' !== basename( __DIR__ ) ) return;

global $App;
define( 'API_VERSION', 'v1' );
define( 'API_PLUGIN_VERSION', '0.1.0' );
$App->set_action( 'install', 'api_install' );
$App->set_action( 'uninstall', 'api_uninstall' );
$App->set_action( 'slug_taken', 'api_routes' );
$App->set_action( 'render', 'api_render', 0 );
$App->set_action( 'site_head', 'api_head' );
$App->set_action( 'admin', 'api_admin' );

/**
 * Initialize API, first time install
 * @param string $plugin
 * @return void
 */
function api_install( string $plugin ): void {
  global $App;
  if ( 'api' === $plugin ) {
    $config = array();
    $config[ 'rate' ] = 10;
    $config[ 'limit' ] = 20;
    $config[ 'read' ] = bin2hex( random_bytes(32) );
    $config[ 'write' ] = bin2hex( random_bytes(32) );
    $App->set( $config, 'api' );
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
  $routes[] = 'api/' . API_VERSION . '/';
  $routes[] = 'api/' . API_VERSION . '/pages';
  $routes[] = 'api/' . API_VERSION . '/pages/';
  $routes[] = 'api/' . API_VERSION . '/medias';
  $routes[] = 'api/' . API_VERSION . '/medias/';
  $routes[] = 'api/' . API_VERSION . '/themes';
  $routes[] = 'api/' . API_VERSION . '/themes/';
  $routes[] = 'api/' . API_VERSION . '/plugins';
  $routes[] = 'api/' . API_VERSION . '/plugins/';
  $routes[] = 'api/' . API_VERSION . '/settings';
  $routes[] = 'api/' . API_VERSION . '/settings/';
  $routes[] = 'api/' . API_VERSION . '/slugify';
  $routes[] = 'api/' . API_VERSION . '/slugify/';
  return sprintf( ',%s,', join( ',', $routes ) );
}

/**
 * API routing
 * @return void
 */
function api_render(): void {
  if ( is_api( null, $match ) ) {
    api_rate_limit();
    api_authenticate();
    switch ( $match[ 'endpoint' ] ) {
      case 'api/' . API_VERSION . '/':
        api_reference();
        break;
      case 'api/' . API_VERSION . '/pages':
      case 'api/' . API_VERSION . '/pages/':
        api_pages();
        break;
      case 'api/' . API_VERSION . '/medias':
      case 'api/' . API_VERSION . '/medias/':
        api_medias();
        break;
      case 'api/' . API_VERSION . '/themes':
      case 'api/' . API_VERSION . '/themes/':
        api_themes();
        break;
      case 'api/' . API_VERSION . '/plugins':
      case 'api/' . API_VERSION . '/plugins/':
        api_plugins();
        break;
      case 'api/' . API_VERSION . '/settings':
      case 'api/' . API_VERSION . '/settings/':
        api_settings();
        break;
      case 'api/' . API_VERSION . '/slugify':
      case 'api/' . API_VERSION . '/slugify/':
        api_slugify();
        break;
      default:
        api_invalid( $match );
        break;
    }
    api_not_allowed();
  }
}

/**
 * API discovery link
 * @return string
 */
function api_head(): string {
  global $App;
  $base = $App->url( 'api/' . API_VERSION . '/' );
  $data = ( $App->data()[ 'pages' ][ $App->page ] ?? array() );
  $endpoint = ( empty( $data ) ? '' : 'pages?slug=' . $App->page );
  $format = '<link rel="alternate api" type="application/json" href="%s%s">';
  return sprintf( $format, $base, $endpoint );
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
        <p class="ss-small ss-mb-5">This is the API read-only token.<br> If left empty or set to "0" (zero), a new token will be generated.</p>
        <label for="write" class="ss-label">Write Access Token</label>
        <input type="text" id="write" name="api[write]" value="' . $App->esc( $api[ 'write' ] ) . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <p class="ss-small ss-mb-5">This is the API write access token, which provides full access to modify your data.<br> <b class="ss-red">Treat it like a password and keep it safe.</b><br> If left empty or set to "0" (zero), a new token will be generated.</p>
        <label for="rate" class="ss-label">Rate Limit</label>
        <input type="number" id="rate" name="api[rate]" value="' . $api[ 'rate' ] . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <p class="ss-small ss-mb-5">Prevent API abuse by setting the maximum number of requests allowed per minute.<br> If you don\'t want to limit the usage, simply leave this field empty or set it to "0" (zero).</p>
        <label for="limit" class="ss-label">Pagination Limit</label>
        <input type="number" id="limit" name="api[limit]" min="1" value="' . $api[ 'limit' ] . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <p class="ss-small ss-mb-5">This option sets the maximum number of items to be displayed per page during pagination.<br> Adjust it to your desired limit.</p>
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
  $inputs = api_request();
  $slug = api_input_string( 'slug' );
  $slug = $App->esc_slug( $slug );
  if ( 'GET' === $method ) {
    $raw = api_input_bool( 'raw' );
    if ( isset( $data[ 'pages' ][ $slug ] ) ) {
      if ( $raw ) $fields = $data[ 'pages' ][ $slug ];
      $fields ??= api_page_fields( $slug );
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => sprintf( 'Page "%s"', $slug ),
          'data' => api_field_selection( $fields )
        )
      );
    }
    if ( ! $raw ) {
      $slugs = array_keys( $data[ 'pages' ] );
      $fields = array_map( 'api_page_fields', $slugs );
      $fields = array_combine( $slugs, $fields );
    }
    $fields ??= $data[ 'pages' ];
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Pages',
        'data' => api_pagination(
          $fields
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
    if ( ! isset( $data[ 'pages' ][ $slug ] ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'Page does not exist',
          'data' => array(
            'slug' => $slug
          )
        )
      );
    } else {
      $pub = $data[ 'pages' ][ $slug ][ 'pub' ];
      $inputs[ 'data' ] = api_input_array( 'data' );
      $inputs[ 'data' ][ 'pub' ] = api_data_input_bool( 'pub', $pub );
      if ( $App->update_page( $slug, $slug, $inputs[ 'data' ] ) ) {
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
          'data' => array(
            'slug' => $slug
          )
        )
      );
    }
  } else if ( 'DELETE' === $method ) {
    if ( ! isset( $data[ 'pages' ][ $slug ] ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'Page does not exist',
          'data' => array(
            'slug' => $slug
          )
        )
      );
    } else {
      if ( $App->delete_page( $slug ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Page deleted',
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
          'message' => 'Page not deleted',
          'data' => array(
            'slug' => $slug
          )
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
  $files = $App->medias;
  $method = api_method();
  if ( 'GET' === $method ) {
    $file = api_input_string( 'file' );
    if ( in_array( $file, $files ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => sprintf( 'File "%s"', $file ),
          'data' => api_field_selection(
            api_media_info( $file )
          )
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Media files',
        'data' => api_pagination(
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
    $file = api_input_string( 'file' );
    if ( ! in_array( $file, $files ) ) {
      api_response(
        array(
          'code' => 404,
          'status' => false,
          'message' => 'File does not exist',
          'data' => array(
            'file' => $file
          )
        )
      );
    } else {
      if ( $App->delete_media( $file ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'File deleted',
            'data' => array(
              'file' => $file
            )
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'File not deleted',
          'data' => array(
            'file' => $file
          )
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
          'message' => sprintf( 'Theme "%s"', $theme ),
          'data' => api_field_selection(
            api_theme_info( $theme )
          )
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Themes',
        'data' => api_pagination(
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
          'data' => array(
            'theme' => $theme
          )
        )
      );
    } else {
      if ( $App->set( $theme, 'theme' ) ) {
        $App->get_action( 'change_theme', $theme );
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Theme activated',
            'data' => array(
              'theme' => $theme
            )
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Theme not activated',
          'data' => array(
            'theme' => $theme
          )
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
  $plugins = $App->plugins;
  $plugin = api_input_string( 'plugin' );
  if ( 'GET' === $method ) {
    if ( in_array( $plugin, $plugins ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => sprintf( 'Plugin "%s"', $plugin ),
          'data' => api_field_selection(
            api_plugin_info( $plugin )
          )
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Plugins',
        'data' => api_pagination(
          array_map(
            'api_plugin_info',
            $plugins
          )
        )
      )
    );
  } else if ( 'PUT' === $method ) {
    if ( ! in_array( $plugin, $plugins ) ) {
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
          'message' => 'Plugin' . ( $App->installed( $plugin ) ? ' already installed' : ' not installed' ),
          'data' => array(
            'plugin' => $plugin
          )
        )
      );
    }
  } else if ( 'DELETE' === $method ) {
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
        'data' => api_field_selection(
          $data[ 'site' ]
        )
      )
    );
  } else if ( 'PUT' === $method ) {
    $inputs = api_request();
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
    $esc = api_input_bool( 'esc' );
    $text = api_input_string( 'text' );
    if ( $esc ) $slug = $App->esc_slug( $text );
    $slug ??= $App->slugify( $text );
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
            'href' => $App->url( 'api/' . API_VERSION . '/' ),
            'methods' => [ 'GET' ]
          ),
          'api/' . API_VERSION . '/pages' => array(
            'href' => $App->url( 'api/' . API_VERSION . '/pages' ),
            'methods' => [ 'GET', 'POST', 'PUT', 'DELETE' ]
          ),
          'api/' . API_VERSION . '/medias' => array(
            'href' => $App->url( 'api/' . API_VERSION . '/medias' ),
            'methods' => [ 'GET', 'POST', 'DELETE' ]
          ),
          'api/' . API_VERSION . '/themes' => array(
            'href' => $App->url( 'api/' . API_VERSION . '/themes' ),
            'methods' => [ 'GET', 'PUT' ]
          ),
          'api/' . API_VERSION . '/plugins' => array(
            'href' => $App->url( 'api/' . API_VERSION . '/plugins' ),
            'methods' => [ 'GET', 'PUT', 'DELETE' ]
          ),
          'api/' . API_VERSION . '/settings' => array(
            'href' => $App->url( 'api/' . API_VERSION . '/settings' ),
            'methods' => [ 'GET', 'PUT' ]
          ),
          'api/' . API_VERSION . '/slugify' => array(
            'href' => $App->url( 'api/' . API_VERSION . '/slugify' ),
            'methods' => [ 'GET' ]
          )
        )
      )
    );
  }
}

/**
 * Alias of "api_route_match"
 * @param ?string $route
 * @param ?array $match
 * @return bool
 */
function is_api( ?string $route = null, ?array &$match = null ): bool {
  global $App;
  $route ??= $App->page;
  return api_route_match( $route, $match );
}

/**
 * Request method
 * @return string
 */
function api_method(): string {
  return $_SERVER[ 'REQUEST_METHOD' ];
}

/**
 * Tells whether route matches API
 * @param string $route
 * @param ?array &$match
 * @return bool
 */
function api_route_match( string $route, ?array &$match = null ) {
  $regex = '|^(?<endpoint>api/(?<version>v[0-9\.]+)/(?<slug>.*?)/?)$|';
  $result = preg_match( $regex, $route, $match );
  $match = array_filter( $match, 'is_string', ARRAY_FILTER_USE_KEY );
  return ( bool ) $result;
}

/**
 * API Request
 * @return array
 */
function api_request(): array {
  $raw_request = file_get_contents( 'php://input' );
  $request     = json_decode( $raw_request, true );
  if ( json_last_error() !== JSON_ERROR_NONE ) {
    parse_str( $raw_request, $request );
  }
  switch ( api_method() ) {
    case 'GET':
    case 'DELETE':
      $request = array_merge( $_GET, $request );
      break;
    case 'POST':
      $request = array_merge( $_POST, $request );
      break;
    case 'PUT':
      break;
    default:
      $request = array();
      break;
  }
  return api_exchange_filter( $request, true );
}

/**
 * API JSON Response
 * @param array $response
 * @return void
 */
function api_response( array $response ): void {
  $resp = api_exchange_filter(  $response  );
  http_response_code(   $resp[  'code'  ]  );
  header( 'Access-Control-Allow-Origin: *' );
  header( 'Content-Type: application/json' );
  exit(     json_encode(    $resp    )     );
}

/**
 * Request and Response Filter
 * @param array $req_or_resp
 * @param string $is_request
 * @return array
 */
function api_exchange_filter( array $req_or_resp, bool $is_req = false ): array {
  global $App;
  $action = ( $is_req ? 'api_request' : 'api_response' );
  return $App->get_filter( $req_or_resp, $action );
}

/**
 * Pages filtered values
 * @param string $slug
 * @return array
 */
function api_page_fields( string $slug ): array {
  global $App;
  $fields = array();
  $pages = $App->data()[ 'pages' ];
  foreach ( $pages[ $slug ] as $index => $value ) {
    $fields[ $index ] = $App->page( $index, $slug );
  }
  return $fields;
}

/**
 * Media file info
 * @param string $file
 * @return array
 */
function api_media_info( string $file ): array {
  global $App;
  $path = $App->root( 'media/' . $file );
  $finfo = new finfo( FILEINFO_MIME_TYPE );
  return array(
    'file' => $file,
    'href' => $App->url( 'media/' . $file ),
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
    'active' => ( $current === $theme ),
    'folder' => $theme
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
    'active' => $installed,
    'folder' => $plugin
  );
}

/**
 * API pagination
 * @param array $data
 * @return array
 */
function api_pagination( array $data ): array {
  global $App;
  $max = $App->get( 'api' )[ 'limit' ];
  $offset  = api_input_integer( 'offset', 0 );
  $limit = api_input_integer( 'limit', $max );
  $limit  = ( $limit > $max ? $max : $limit );
  $args = [ $data, $offset, $limit, true ];
  return array_slice( ...$args );
}

/**
 * API field selection
 * @param array $data
 * @return array
 */
function api_field_selection( array $data ): array {
  $fields = api_input_array( 'fields' );
  if ( empty( $fields ) )  return $data;
  foreach ( $data as $index => $value ) {
    if ( ! in_array( $index, $fields ) ) {
      unset( $data[ $index ] );
    }
  }
  return $data;
}

/**
 * API string input
 * @param string $index
 * @param string $def
 * @return mixed
 */
function api_input_string( string $index, string $default = '', ?string $key = null ): string {
  $input = ( api_request()[ $index ] ?? $default );
  return ( is_string( $input ) ? $input : $default );
}

/**
 * API integer input
 * @param string $index
 * @param int $default
 * @return int
 */
function api_input_integer( string $index, int $default = 0 ): int {
  $input = ( api_request()[ $index ] ?? $default );
  return ( is_numeric( $input ) ? $input : $default );
}

/**
 * API array input
 * @param string $index
 * @param array $default
 * @return array
 */
function api_input_array( string $index, array $default = array() ): array {
  $input = ( api_request()[ $index ] ?? $default );
  return ( is_array( $input ) ? $input : $default );
}

/**
 * API boolean input
 * @param string $index
 * @param bool $default
 * @return bool
 */
function api_input_bool( string $index, bool $default = false ): bool {
  return filter_var( api_request()[ $index ] ?? $default, FILTER_VALIDATE_BOOL );
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
 * API rate limiting
 * @return void
 */
function api_rate_limit(): void {
  global $App;
  if ( 0 === ( $limit = $App->get( 'api' )[ 'rate' ] ) ) return;
  $data = ( $_SESSION[ 'api' ][ $App->page ] ?? null );
  $data = ( $data ?? [ 'hits' => 0, 'time' => time() ] );
  $elapsed = ( time() - $data[ 'time' ] );
  if ( $elapsed > 60 ) {
    $data[ 'hits' ] = 1;
    $data[ 'time' ] = time();
  } else {
    $data[ 'hits' ]++;
    if ( $data[ 'hits' ] > $limit ) {
      $retry = ( 60 - $elapsed );
      header( 'Retry-After: ' . $retry );
      api_response(
        array(
          'code' => 429,
          'status' => false,
          'message' => 'Rate limit exceeded',
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
function api_authenticate(): void {
  global $App;
  $api = $App->get( 'api' );
  $token = api_input_string( 'token' );
  if ( ! hash_equals( $api[ 'read' ], $token ) ) {
    api_response(
      array(
        'code' => 401,
        'status' => false,
        'message' => 'Invalid "token" token',
        'data' => array()
      )
    );
  } else if ( 'GET' !== api_method() ) {
    $auth = api_input_string( 'auth' );
    if ( ! hash_equals( $api[ 'write' ], $auth ) ) {
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
 * Unhandled request
 * @param array $data
 * @return void
 */
function api_invalid( array $data ): void {
  api_response(
    array(
      'code' => 404,
      'status' => false,
      'message' => 'Invalid endpoint',
      'data' => $data
    )
  );
}

/**
 * Not allowed
 * @return void
 */
function api_not_allowed(): void {
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
