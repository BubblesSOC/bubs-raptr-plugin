<?php
/*
Plugin Name: Bubs' Raptr Plugin
Plugin URI: http://bubblessoc.net
Description: Parses the content of the "Games I'm Playing" <a href="http://raptr.com/dashboard/widgets">Raptr widget</a> for display on your WordPress blog. Uses the <a href="http://simplehtmldom.sourceforge.net/">PHP Simple HTML DOM Parser</a>.
Version: 1.0
Author: Bubs
Author URI: http://bubblessoc.net
*/

require_once('includes/simple_html_dom.php');
define('BRP_PLUGIN_SLUG', "bubs-raptr-plugin");

class BubsRaptrPlugin {
  private $_cache;
  private $_source; // for debugging
  
  function __construct() {
    $this->_cache = get_option( 'brp_cache', array( 'timestamp' => 0, 'items' => array() ) );
    
    add_action( 'admin_menu', array($this, 'adminMenu') );
    add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array($this, 'actionLinks') );
    add_action( 'wp_ajax_brp-refresh-cache', array($this, 'refreshCache') );
    add_action( 'wp_ajax_brp-cache-images', array($this, 'cacheImages') );
    
    add_action( 'wp_ajax_nopriv_brp-print-games', array($this, 'printGames') );
    add_action( 'wp_ajax_brp-print-games', array($this, 'printGames') );
  }
  
  private function _updateCache( $items ) {
    $this->_cache['timestamp'] = time();
    $this->_cache['items'] = $items;
    update_option( 'brp_cache', $this->_cache );
  }

  function enqueueAdminJS() {
    wp_enqueue_script( 'brp_admin_js', plugins_url('/includes/brp-admin.js', __FILE__), array('jquery') );
  }
  
  function enqueueAdminCSS() {
    wp_enqueue_style( 'brp_admin_css', plugins_url('/includes/brp-admin.css', __FILE__) );
  }
  
  function adminMenu() {
    $page_hook = add_options_page("Bubs' Raptr Plugin", "Bubs' Raptr Plugin", 'manage_options', BRP_PLUGIN_SLUG, array($this, 'optionsPage'));
    add_action('admin_print_scripts-' . $page_hook, array($this, 'enqueueAdminJS') );
    add_action('admin_print_styles-' . $page_hook, array($this, 'enqueueAdminCSS') );
  }
  
  function actionLinks( $actions ) {
    $actions[] = '<a href="options-general.php?page='. BRP_PLUGIN_SLUG .'">Settings</a>';
    return $actions;
  }
  
  function optionsPage() {
    if (!current_user_can('manage_options'))  {
  		wp_die( __('You do not have sufficient permissions to access this page.') );
  	}
?>
<div class="wrap">
  <div id="icon-options-general" class="icon32"><br></div>
	<h2>Bubs' Raptr Plugin Settings</h2>
	<br />
	<div id="message" class="updated raptr"></div>
  <p class="alignleft">
    <a class="button" id="brp-refresh-cache-button" href="#" data-nonce="<?php echo wp_create_nonce('brp-refresh-cache'); ?>">Refresh Cache</a>
    <img src="<?php echo admin_url('images/wpspin_light.gif'); ?>" class="ajax-loading" id="brp-refresh-cache-spinner" alt="" />
  </p>
  <p class="alignright">
    <strong>Last Cached:</strong> <span id="brp-cache-timestamp"><?php echo date( get_option('date_format') . ' ' . get_option('time_format'), $this->_cache['timestamp'] ); ?></span>
  </p>
  <br class='clear' />
	<table class="widefat fixed" id="brp-cache-table" cellspacing="0">
	  <thead>
	    <tr>
	      <th scope="col">Game</th>
	      <th scope="col">Local Images</th>
	      <th scope="col">Last Played</th>
	      <th scope="col" class="col-center">Action</th>
	    </tr>
	  </thead>
	  <tbody>
<?php $this->_optionsPage_tbody(); ?>
	  </tbody>
	</table>
</div>
<?php
  }
  
  private function _optionsPage_tbody() {
    $nonce = wp_create_nonce('brp-cache-images');
    $spinner = admin_url('images/wpspin_light.gif');
    $i = 0;
    foreach ( $this->_cache['items'] as $item ) {
      $class = ($i % 2 != 0 ? 'alt' : '');
      echo <<<EOD
<tr class="$class">
  <td><a href="{$item['link']}">{$item['title']}</a></td>
  <td>
    <img src="{$item['img_med']['local']}" id="raptr-boxart-md-$i" width="{$item['img_med']['width']}" height="{$item['img_med']['height']}" />
    <img src="{$item['img_small']['local']}" id="raptr-boxart-sm-$i" width="{$item['img_small']['width']}" height="{$item['img_small']['height']}" />
  </td>
  <td>{$item['last_played']}</td>
  <td class="col-center">
    <a class="button brp-cache-images-button" href="#" data-nonce="$nonce" data-index="$i">Cache Images</a>
    <img src="$spinner" class="ajax-loading brp-cache-images-spinner" alt="" />
  </td>
</tr>      
EOD;
      $i++;
    }
  }
  
  function refreshCache() {
    $response = array(
      'status' => 'error',
      'data'   => null
    );
    header("Content-Type: application/json");
    if ( !check_ajax_referer('brp-refresh-cache', 'brpNonce', false) || !current_user_can('manage_options') ) {
      $response['data'] = 'You do not have sufficient permissions to access this page.';
      echo json_encode($response);
      exit;  
    }
    $result = $this->_fetchGames(true);
    if ( is_wp_error($result) ) {
      $response['data'] = $result->get_error_message();
    }
    else {
      $response['status'] = 'success';
      $response['data']['timestamp'] = date( get_option('date_format') . ' ' . get_option('time_format'), $this->_cache['timestamp'] );
    }
    echo json_encode($response);
    exit;
  }
  
  function cacheImages() {
    $response = array(
      'status' => 'error',
      'data'   => null
    );
    header("Content-Type: application/json");
    
    if ( !check_ajax_referer('brp-cache-images', 'brpNonce', false) || !current_user_can('manage_options') ) {
      $response['data'] = 'You do not have sufficient permissions to access this page.';
      echo json_encode($response);
      exit;  
    }
    
    $index = $_POST['cacheIndex'];
    $items = $this->_cache['items'];
    list( $items[$index]['img_small'], $items[$index]['img_med'] ) = $this->_getImageUrls( $items[$index]['img_small']['remote'], $items[$index]['link'] );
    
    if ( is_null($items[$index]['img_small']['local']) || is_null($items[$index]['img_med']['local']) ) {
      $response['data'] = 'Cache failed. Please try again.';
    }
    else {
      $this->_updateCache($items);
      $response['status'] = 'success';
      $response['data']['timestamp'] = date( get_option('date_format') . ' ' . get_option('time_format'), $this->_cache['timestamp'] );
      $response['data']['img_small'] = $items[$index]['img_small']['local'];
      $response['data']['img_med'] = $items[$index]['img_med']['local'];
    }
    echo json_encode($response);
    exit;
  }
  
  function printGames() {
    $this->_fetchGames();
?>
<!-- Last Cached: <?php echo date('F j, Y h:i a', $this->_cache['timestamp']); ?> -->
<!-- Source: <?php echo $this->_source; ?> -->
<?php
    foreach ( $this->_cache['items'] as $item ) {
      if ( !is_null($item['img_med']['local']) ) {
        $src = $item['img_med']['local'];
      }
      else {
        $src = $item['img_med']['remote'];
      }
      $game = '<a href="'. $item['link'] .'"><img src="'. $src .'" alt="'. $item['title_escaped'] .'" width="'. $item['img_med']['width'] .'" height="'. $item['img_med']['height'] .'" /></a>';
      echo "<li>$game</li>\n";
    }
    exit;
  }
  
  private function _fetchGames( $refresh = false ) {
    $time_diff = time() - $this->_cache['timestamp'];
    
    if ( $time_diff > (60 * 60 * 24) || $refresh ) {
      // Fetch from Raptr
      $response = wp_remote_get('http://raptr.com/badges/playing/bubblessoc');
      if ( is_wp_error($response) ) {
        $this->_source = 'Cache (Error)';
        return $response;
      }
      $items = $this->_parseWidget($response['body']);
      if ( !$items ) {
        $this->_source = 'Cache (Error)';
        return new WP_Error( 'brp_parse_error', 'Could not parse Raptr widget' );
      }
      else {
        $this->_updateCache($items);
        $this->_source = 'Raptr';
      }
    }
    else {
      // Use Cache
      $this->_source = 'Cache';
    }
    return true;
  }
  
  private function _parseWidget( $contents ) {
    $html = new simple_html_dom();
    $html->load($contents);
    $ul = $html->find('ul', 0);
    if ( is_null($ul) )
      return false;
      
    $items = array();
    foreach ( $ul->find('li') as $li ) {
      $a = $li->find('a', 0);
      $img = $li->find('img', 0);
      $p = $li->find('p', 0);
      $strong = $li->find('strong', 0);
      
      if ( !is_null($a) && !is_null($img) && !is_null($p) && !is_null($strong) ) {
        $item['link'] = 'http://raptr.com' . $a->href;
        list( $item['img_small'], $item['img_med'] ) = $this->_getImageUrls( $img->src, $a->href );
        $img->outertext = '';
        $item['title'] = trim($a->innertext);
        $item['title_escaped'] = esc_attr($item['title']);
        $strong->outertext = '';
        $item['last_played'] = trim($p->innertext);
        array_push($items, $item);
      }
    }
    
    if ( empty($items) )
      return false;
    else
      return $items;
  }
  
  private function _getImageUrls( $src, $href ) {
    list( $image['path'], $image['filename'], $image['extension'] ) = preg_split('/(boxart-small)/i', $src, 0, PREG_SPLIT_DELIM_CAPTURE);
    $slug = strtolower( preg_replace('/^(.+)?\/game\/[^\/]+\/(.+)$/i', "$2", $href) );
    
    $img_small = array(
      'remote' => $image['path'] . $image['filename'] . $image['extension'],
      'local'  => null,
      'width'  => '32',
      'height' => '32'
    );
    $img_med = array(
      'remote' => $image['path'] . 'boxart-medium' . $image['extension'],
      'local'  => null,
      'width'  => '64',
      'height' => '64'
    );
    
    $cached_file_sm = 'boxart/' . $slug . '-small' . $image['extension'];
    $cached_file_sm_path = plugin_dir_path(__FILE__) . $cached_file_sm;
    if ( ( file_exists($cached_file_sm_path) && @getimagesize($cached_file_sm_path) ) || $this->_cacheImage( $img_small['remote'], $cached_file_sm_path ) ) {
      $img_small['local'] = plugin_dir_url(__FILE__) . $cached_file_sm;
    }
    
    $cached_file_md = 'boxart/' . $slug . '-medium' . $image['extension'];
    $cached_file_md_path = plugin_dir_path(__FILE__) . $cached_file_md;
    if ( ( file_exists($cached_file_md_path) && @getimagesize($cached_file_md_path) ) || $this->_cacheImage( $img_med['remote'], $cached_file_md_path ) ) {
      $img_med['local'] = plugin_dir_url(__FILE__) . $cached_file_md;
    }
    
    return array( $img_small, $img_med );
  }
  
  private function _getImageSize( $remote ) {
    $info = @getimagesize($remote);
    if ( is_array($info) ) {
      return array($info[0], $info[1]);
    }
    else {
      return array(0, 0);
    }
  }
  
  private function _cacheImage( $remote, $local ) {
    $ch = curl_init( $remote );
    $fp = fopen( $local, 'wb' );
    $options = array(
     CURLOPT_FILE => $fp,
     CURLOPT_HEADER => false,
     CURLOPT_CONNECTTIMEOUT => 1
    );
    curl_setopt_array($ch, $options);
    $result = curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    return $result;
  }
}

$brp = new BubsRaptrPlugin();
?>