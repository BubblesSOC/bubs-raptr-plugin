<?php
class BRP_Widget extends WP_Widget {
  
  function __construct() {
    parent::__construct( 'raptr', 'Raptr', array( 'description' => 'Raptr "Games I\'m Playing" widget' ) );
  }
  
  function form( $instance ) {
    $title = isset( $instance['title'] ) ? esc_attr( $instance['title'] ) : 'Raptr';
?>
<p>
	<label for="<?php echo $this->get_field_id( 'title' ); ?>">Title:</label> 
	<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo $title; ?>" />
</p>
<?php
  }
  
  function update( $new_instance, $old_instance ) {
    $instance = array();
    $instance['title'] = strip_tags( $new_instance['title'] );
    return $instance;
  }
  
  function widget( $args, $instance ) {
    extract( $args );
    $title = apply_filters( 'widget_title', empty($instance['title']) ? 'Raptr' : $instance['title'] );
    
    echo str_replace( $widget_id, 'raptr', $before_widget ) . "\n";
    echo $before_title . $title . $after_title . "\n";
?>
<ul>
  <li><img src="<?php bloginfo('template_directory') ?>/images/ajax-loader.gif" alt="Loading..." /></li>
</ul>
<?php
    echo $after_widget . "\n\n";
  }
}
?>