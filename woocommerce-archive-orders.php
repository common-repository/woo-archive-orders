<?php
/*
  Plugin Name: WooCommerce Archive Orders
  Plugin URI: https://wordpress.org/plugins/woo-archive-orders
  Description: Let old Woocommerce orders be archived
  Version: 1.0
  Author: N.O.U.S. Ouvert Utile et Simple
  Contributors: bastho
  Author URI: https://avecnous.eu
  License: GPLv2
  Text Domain: woo-archive-orders
  Tags: Woocommerce, Archive, orders, performances
 */
if ( ! defined( 'ABSPATH' ) ) exit;

global $WcAOO;
$WcAOO = new WooCommerceArchiveOrders;


class WooCommerceArchiveOrders{
  private $legacy_statuses = array();
  private $archived_statuses = array();
  private $textdomain = 'woo-archive-orders';

  public function __construct() {
    add_filter( 'woocommerce_register_shop_order_post_statuses', array(&$this,'register_custom_order_status'), 100);
    add_filter( 'wc_order_statuses', array(&$this,'add_custom_statuses_to_list') );

    // filter on edit-shop_order page
    add_filter( 'views_edit-shop_order', array( $this, 'shop_order_filters' ), 10, 1 );
    add_action('restrict_manage_posts', array($this, 'restrict_manage_orders'), 5);
    add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ), 10, 1 );

    // Add settings
    add_filter('woocommerce_get_sections_advanced',  array( $this, 'woocommerce_get_sections_advanced' ), 10, 1 );
    add_filter('woocommerce_get_settings_advanced',  array( $this, 'woocommerce_get_settings_advanced' ), 10, 2 );

    add_filter('plugin_action_links_woocommerce-archive-orders/woocommerce-archive-orders.php', array( &$this, 'settings_link' ) );

    // Manage cron tasks
    $this->manage_schedules();
    add_action( 'woocommerce_archive_orders_cron_tasks', array($this, 'cron_tasks') );
  }

  public function register_custom_order_status($order_statuses){
    $this->legacy_statuses = $order_statuses ;
    foreach ( $order_statuses as $order_status => $values ) {
      $values['label'] = sprintf(__('%s (archived)', $this->textdomain), $values['label']);
      $values['label_count'] = _n_noop( $values['label'].' <span class="count">(%s)</span>', $values['label'].' <span class="count">(%s)</span>');
      $values['show_in_admin_all_list'] = false;
      $values['show_in_admin_status_list'] = false;
      $order_statuses[$order_status.'-a'] = $values;
      $this->archived_statuses[$order_status.'-a'] = $values['label'];
    }
    return $order_statuses;
  }

  function add_custom_statuses_to_list( $order_statuses ) {
      foreach ( $order_statuses as $order_status => $label ) {
        $order_statuses[$order_status.'-a'] = sprintf(__('%s (archived)', $this->textdomain), $label);
      }
      return $order_statuses;
  }

  /**
   * Order admin filter links
   * @param  array $views
   * @return array
   */
  public function shop_order_filters( $views ) {
    global $wpdb;
    $is_archeolog = (filter_input(INPUT_GET, 'archived') == 'yes');

    $sql = "SELECT
      count(DISTINCT p.ID)
    FROM
      $wpdb->posts p
    WHERE
      p.post_status LIKE 'wc-%-a'
      AND p.post_type = 'shop_order'";

    $count = $wpdb->get_var($sql);
    $query_string = add_query_arg( array('post_type' => 'shop_order', 'archived' => 'yes'), admin_url('edit.php'));
    $views['archived']   = '<a href="'. $query_string . '" class="' . ( $is_archeolog ? 'current' : '' ) . '">' . __('Archived', $this->textdomain) . ' <span class="count">(' . number_format_i18n( $count ) . ')</a>';

    if ( $is_archeolog ) {
      $views['all'] = str_replace( 'class="current"', '', $views['all'] );
    }
    return $views;
  }

  function restrict_manage_orders($value = ''){
      global $woocommerce, $typenow;
      if ('shop_order' != $typenow) {
          return;
      }
      ?>
      <input type="hidden" name="archived" value="<?php echo esc_attr(filter_input(INPUT_GET, 'archived')); ?>">
      <?php
    }

  /**
   * Order admin filter
   * @param $query
   */
  function pre_get_posts( $query ) {

    if ( is_admin() && filter_input(INPUT_GET, 'post_type') == 'shop_order' && filter_input(INPUT_GET, 'archived') == 'yes' ) {
      $query->set( 'post_status', implode(',', array_keys($this->archived_statuses)) );
    }

  }


  function woocommerce_get_sections_advanced($sections){
    $sections['archives'] = __('Archives', $this->textdomain);
    return $sections;
  }

  function woocommerce_get_settings_advanced($settings, $current_section){
    if ( 'archives' === $current_section ) {
      $settings = apply_filters(
        'woocommerce_settings_archives', array(
          array(
            'title' => __( 'Archives', $this->textdomain ),
            'desc'    => __( 'Automatically archive orders older than X days', $this->textdomain ),
            'type'  => 'title',
            'id'    => 'archives',
          ),
          array(
            'title'   => __( 'Max orders age (in days)', $this->textdomain ),
            'desc'    => __( '0 disables automatic archives', $this->textdomain ),
            'id'      => 'woocommerce_archive_orders_older_than_days',
            'type'    => 'text',
            'default' => '0',
            'desc_tip' => true,
          ),
          array(
            'type' => 'sectionend',
            'id'   => 'archives',
          ),
        )
      );
    }
    return $settings;
  }

  /**
   *  Settings link on the plugins page
   */
  public function settings_link( $links ) {
    $setting_url = add_query_arg(
        array(
          'page' => 'wc-settings',
          '&tab' => 'advanced',
          'section' => 'archives',
        ),
        admin_url('admin.php')
      );
      $settings_link = '<a href="'.$setting_url.'">' . __( 'Settings', $this->textdomain ) . '</a>';
      array_unshift( $links, $settings_link );
      return $links;
  }

  /**
   *
   */
  function manage_schedules() {
    if (!wp_next_scheduled('woocommerce_archive_orders_cron_tasks')) {
      wp_schedule_event(strtotime('+1 day'), 'daily', 'woocommerce_archive_orders_cron_tasks');
    }
  }

  function add_order_note($order_id, $note){
    $commentdata = apply_filters( 'woocommerce_new_order_note_data',
        array(
            'comment_post_ID'      => $order_id,
            'comment_author'       => __( 'WooCommerce', $this->textdomain ),
            'comment_author_email' => strtolower(__( 'WooCommerce', $this->textdomain )). '@' .site_url(),
            'comment_author_url'   => '',
            'comment_content'      => $note,
            'comment_agent'        => 'WooCommerceArchiveOrder',
            'comment_type'         => 'order_note',
            'comment_parent'       => 0,
            'comment_approved'     => 1,
        ),
        array(
            'order_id'         => $order_id,
            'is_customer_note' => false,
        )
    );
    return wp_insert_comment( $commentdata );
  }

  function cron_tasks(){
    $max_age = get_option('woocommerce_archive_orders_older_than_days');
    if(!$max_age){
      return;
    }
    global $wpdb;
    $order_statuses = implode("','", array_keys($this->legacy_statuses));
    $floor = date('Y-m-d H:i:s', strtotime("-$max_age days"));
    $post_ids = array();

    $sql_get = "SELECT
      p.ID,
      p.post_status
    FROM
      $wpdb->posts p
    WHERE
      p.post_status IN ('$order_statuses')
    AND p.post_type = 'shop_order'
    AND p.post_date < '$floor'
    ";
    $posts = $wpdb->get_results($sql_get);

    foreach($posts as $post){
      $post_ids[] = $post->ID;
      $this->add_order_note($post->ID, sprintf(__('Order has been archived with status «%s»', $this->textdomain), $this->archived_statuses[$post->post_status.'-a']));
    }
    if(!count($post_ids)){
      return;
    }
    $ids_in = implode(',', $post_ids);

    $sql_set = "UPDATE
      $wpdb->posts p
    SET
      p.post_status = REPLACE(p.post_status, p.post_status, CONCAT(p.post_status, '-a'))
    WHERE
      p.ID IN ($ids_in)
    ";

    $wpdb->query($sql_set);

  }
}
