<?php defined( 'App' ) or die( 'BoidCMS' );
/**
 *
 * API - an easy way to integrate other systems with your site
 *
 * @package BoidCMS
 * @subpackage API
 * @author Shoaiyb Sysa
 * @version 1.0.0
 */

global $App;
$App->set_action( 'install', 'api_init' );
$App->set_action( 'uninstall', 'api_shut' );
$App->set_action( 'slug_taken', 'api_taken' );
$App->set_action( 'render', 'api_render' );
$App->set_action( 'admin', 'api_admin' );

/**
 * Initiate API, first time install
 * @param string $plugin
 * @return void
 */
function api_init( string $plugin ): void {
  global $App;
  if ( 'api' === $plugin ) {
    $token = bin2hex( random_bytes(32) );
    $App->set( $token, 'api_token' );
  }
}

/**
 * Free database space, while uninstalled
 * @param string $plugin
 * @return void
 */
function api_shut( string $plugin ): void {
  global $App;
  if ( 'api' === $plugin ) {
    $App->unset( 'api_token' );
  }
}

/**
 * API endpoints
 * @return string
 */
function api_taken(): string {
  return ',
  api/pages,
  api/medias,
  api/themes,
  api/plugins,
  api/settings,';
}

/**
 * API route
 * @return string
 */
function api_render(): void {
  global $App;
  switch ( $App->page ) {
    case 'api/pages':
      api_auth();
      api_pages();
      api_inv();
      break;
    case 'api/medias':
      api_auth();
      api_medias();
      api_inv();
      break;
    case 'api/themes':
      api_auth();
      api_themes();
      api_inv();
      break;
    case 'api/plugins':
      api_auth();
      api_plugins();
      api_inv();
      break;
    case 'api/settings':
      api_auth();
      api_settings();
      api_inv();
      break;
  }
}

/**
 * Admin settings
 * @return void
 */
function api_admin(): void {
  global $App, $layout, $page;
  switch ( $page ) {
    case 'api':
      $layout[ 'title' ] = 'API';
      $layout[ 'content' ] = '
      <form action="' . $App->admin_url( '?page=api', true ) . '" method="post">
        <div class="ss-alert ss-info ss-mobile ss-w-5 ss-mx-auto">
          <h4 class="ss-monospace">Endpoint URL</h4>
          <p class="sss-alert ss-small ss-ovf-scroll">' . $App->url( 'api/{endpoint}' ) . '</p>
          <small class="ss-label">Leave empty to regenerate new token.</small>
        </div>
        <label for="api_token" class="ss-label">Token</label>
        <input type="text" id="api_token" name="api_token" value="' . $App->esc( $App->get( 'api_token' ) ) . '" class="ss-input ss-mobile ss-w-6 ss-mx-auto">
        <input type="hidden" name="token" value="' . $App->token() . '">
        <input type="submit" name="save" value="Save" class="ss-btn ss-mobile ss-w-5">
      </form>';
      if ( isset( $_POST[ 'save' ] ) ) {
        $App->auth();
        $token = ( $_POST[ 'api_token' ] ?? '' );
        $token = ( ! empty( $token ) ? $token : bin2hex( random_bytes(32) ) );
        if ( $App->set( $token, 'api_token' ) ) {
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
  $page = ( $inputs[ 'page' ] ?? '' );
  unset( $inputs[ 'token' ], $inputs[ 'page' ] );
  if ( 'GET' === $method ) {
    if ( isset( $data[ 'pages' ][ $page ] ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'Page',
          'result' => $data[ 'pages' ][ $page ]
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Pages',
        'result' => api_paginate(
          $data[ 'pages' ]
        )
      )
    );
  } else if ( 'POST' === $method ) {
    $inputs[ 'pub' ] ??= false;
    $inputs[ 'type' ] ??= 'page';
    $inputs[ 'title' ] ??= 'Page';
    $inputs[ 'descr' ] ??= '';
    $inputs[ 'keywords' ] ??= '';
    $inputs[ 'content' ] ??= '';
    $inputs[ 'thumb' ] ??= '';
    $inputs[ 'date' ] = date( 'Y-m-d\TH:i:s' );
    $inputs[ 'tpl' ] ??= '';
    $inputs[ 'pub' ] = filter_var( $inputs[ 'pub' ], FILTER_VALIDATE_BOOL );
    unset( $inputs[ 'offset' ], $inputs[ 'limit' ] );
    $slug = $App->slugify( $inputs[ 'title' ] );
    if ( $App->create_page( $slug, $inputs ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'Page created',
          'result' => array(
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
        'result' => array()
      )
    );
  } else if ( 'PUT' === $method ) {
    $slug = ( $inputs[ 'slug' ] ?? $page );
    if ( ! isset( $data[ 'pages' ][ $page ] ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Page does not exist',
          'result' => array()
        )
      );
    } else {
      unset( $inputs[ 'slug' ], $inputs[ 'offset' ], $inputs[ 'limit' ] );
      if ( $App->update_page( $page, $slug, $inputs ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Page updated',
            'result' => array(
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
          'result' => array()
        )
      );
    }
  } else if ( 'DELETE' === $method ) {
    if ( ! isset( $data[ 'pages' ][ $page ] ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Page does not exist',
          'result' => array()
        )
      );
    } else {
      if ( $App->delete_page( $page ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Page deleted',
            'result' => array()
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Page not deleted',
          'result' => array()
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
  $inputs = api_inputs();
  unset( $inputs[ 'token' ] );
  if ( 'GET' === $method ) {
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Media files',
        'result' => api_paginate(
          $App->medias
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
        'result' => array(
          'file' => $file
        )
      )
    );
  } else if ( 'DELETE' === $method ) {
    $file = ( $inputs[ 'file' ] ?? '' );
    if ( ! isset( $App->medias[ $file ] ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'File does not exist',
          'result' => array()
        )
      );
    } else {
      if ( $App->delete_media( $file ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Media file deleted',
            'result' => array()
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Media file not deleted',
          'result' => array()
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
  $inputs = api_inputs();
  unset( $inputs[ 'token' ] );
  if ( 'GET' === $method ) {
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Themes',
        'result' => api_paginate(
          $App->themes
        )
      )
    );
  }
}

/**
 * API plugins handler
 * @return void
 */
function api_plugins(): void {
  global $App;
  $method = api_method();
  $inputs = api_inputs();
  unset( $inputs[ 'token' ] );
  $plugin = ( $inputs[ 'plugin' ] ?? '' );
  if ( 'GET' === $method ) {
    $plugins = $App->plugins;
    $status = array_map( array( $App, 'installed' ), $plugins );
    $plugins = array_combine( $plugins, $status );
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Plugins',
        'result' => api_paginate(
          $plugins
        )
      )
    );
  } else if ( 'POST' === $method ) {
    if ( ! in_array( $plugin, $App->plugins ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Plugin does not exist',
          'result' => array()
        )
      );
    } else {
      if ( $App->install( $plugin ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Plugin installed',
            'result' => array()
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Plugin not installed',
          'result' => array()
        )
      );
    }
  } else if ( 'DELETE' === $method ) {
    if ( ! $App->installed( $plugin ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Plugin not installed',
          'result' => array()
        )
      );
    } else {
      if ( $App->uninstall( $plugin ) ) {
        api_response(
          array(
            'code' => 200,
            'status' => true,
            'message' => 'Plugin uninstalled',
            'result' => array()
          )
        );
      }
      api_response(
        array(
          'code' => 200,
          'status' => false,
          'message' => 'Plugin not uninstalled',
          'result' => array()
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
  $inputs = api_inputs();
  unset( $inputs[ 'token' ] );
  if ( 'GET' === $method ) {
    api_response(
      array(
        'code' => 200,
        'status' => true,
        'message' => 'Settings',
        'result' => $data[ 'site' ]
      )
    );
  } else if ( 'PUT' === $method ) {
    unset( $inputs[ 'offset' ], $inputs[ 'limit' ] );
    $data[ 'site' ] = array_merge( $data[ 'site' ], $inputs );
    if ( $App->save( $data ) ) {
      api_response(
        array(
          'code' => 200,
          'status' => true,
          'message' => 'Settings updated',
          'result' => array()
        )
      );
    }
    api_response(
      array(
        'code' => 200,
        'status' => false,
        'message' => 'Settings not updated',
        'result' => array()
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
  $App->get_action( 'api_response', $data );
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
 * Paginate result
 * @param array $data
 * @return array
 */
function api_paginate( array $data ): array {
  $inputs = api_inputs();
  $offset = intval( $inputs[ 'offset' ] ?? 0 );
  $limit = intval( $inputs[ 'limit' ] ?? 20 );
  return array_slice( $data, $offset, $limit, true );
}

/**
 * Input parameters
 * @return array
 */
function api_inputs(): array {
  switch( api_method() ) {
    case 'GET':
    case 'DELETE':
      return $_GET;
      break;
    case 'POST':
      return $_POST;
      break;
    case 'PUT':
      parse_str( file_get_contents( 'php://input' ), $_PUT );
      return $_PUT;
      break;
    default:
      return array();
      break;
  }
}

/**
 * Authorization
 * @return void
 */
function api_auth(): void {
  global $App;
  $inputs = api_inputs();
  $inputs[ 'token' ] ??= '';
  $token = $App->get( 'api_token' );
  if ( ! hash_equals( $token, $inputs[ 'token' ] ) ) {
    api_response(
      array(
        'code' => 401,
        'status' => false,
        'message' => 'Invalid token',
        'result' => array()
      )
    );
  }
}

/**
 * Invalid method
 * @return void
 */
function api_inv(): void {
  api_response(
    array(
      'code' => 403,
      'status' => false,
      'message' => 'Invalid method',
      'result' => array()
    )
  );
}
?>
