<?php
/**
 * @package ZON_Recent_Posts_Widget
 *
 * Plugin Name:       ZEIT ONLINE Recent Posts By Author Widget
 * Plugin URI:        https://github.com/ZeitOnline/zon-recent-posts-widget
 * Description:       Wordpress widget to display recent posts of the current author on single post pages
 * Version:           1.0.0
 * Author:            Moritz Stoltenburg
 * Author URI:        http://slomo.de/
 * License:           GPL-3.0+
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
 * GitHub Plugin URI: https://github.com/ZeitOnline/zon-recent-posts-widget
*/

/**
 * Recent_Posts widget class
 */
class ZON_Recent_Posts_Widget extends WP_Widget {
	// enable persistent caching
	private $_use_cache = false;

	// default parameters
	private $_defaults = array(
		'number' => 2,
	);

	function __construct() {
		parent::__construct(
			'zon-recent-posts',
			'ZON Letzte Beiträge des Autors',
			array('description' => 'Die aktuellsten Beiträge eines Autoren.')
		);

		if ( $this->_use_cache ) {
			add_action( 'save_post', array($this, 'flush_widget_cache') );
			add_action( 'deleted_post', array($this, 'flush_widget_cache') );
			add_action( 'switch_theme', array($this, 'flush_widget_cache') );
		}
	}

	function widget($args, $instance) {
		if ( ! is_single() ) {
			return;
		}

		$author_id = get_the_author_meta( 'ID' );
		$post_id = get_the_ID();

		// current post is excluded from result, for that reason cache per post
		// otherwise we would cache per author
		$cache_id = $post_id;

		if ( $this->_use_cache ) {
			$cache = get_transient('widget_recent_posts_by_author');

			if ( ! is_array( $cache ) )
				$cache = array();

			if ( ! isset( $args['widget_id'] ) )
				$args['widget_id'] = $this->id;

			if ( isset( $cache[ $args['widget_id'] ][ $cache_id ] ) ) {
				echo $cache[ $args['widget_id'] ][ $cache_id ];
				return;
			}

			ob_start();
		}

		$title = sprintf( 'Letzte Blogposts von %s', get_the_author_meta( 'display_name', $author_id ) );
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );
		$number = ( ! empty( $instance['number'] ) ) ? absint( $instance['number'] ) : $this->_defaults['number'];
		if ( ! $number )
 			$number = $this->_defaults['number'];

		$r = new WP_Query( array(
			'author'              => $author_id,
			'posts_per_page'      => $number,
			'no_found_rows'       => true,
			'order'               => 'DESC',
			'orderby'             => 'date',
			'post_status'         => 'publish',
			'post__not_in'        => array( $post_id ),
			'ignore_sticky_posts' => true
		) );

		if ($r->have_posts()) :
?>
		<?php echo $args['before_widget']; ?>
		<?php if ( $title ) echo $args['before_title'] . $title . $args['after_title']; ?>
		<ul class="widget-posts">
		<?php while ( $r->have_posts() ) : $r->the_post(); ?>
			<li>
				<a href="<?php the_permalink(); ?>">
					<span class="entry-kicker"><?php echo get_post_meta( get_the_ID(), 'zon-kicker', true); ?></span>
					<h3 class="widget-item"><?php get_the_title() ? the_title() : the_ID(); ?></h3>
					<p><?php echo get_the_excerpt(); ?></p>
				</a>
			</li>
		<?php endwhile; ?>
		</ul>
		<div class="widget-footer">
			<a href="<?php echo get_author_posts_url( $author_id ); ?>">Alle Posts</a>
		</div>
<?php

		echo $args['after_widget'];

		// Reset the global $the_post as this query will have stomped on it
		wp_reset_postdata();

		endif;

		if ( $this->_use_cache ) {
			$cache[ $args['widget_id'] ][ $cache_id ] = ob_get_flush();
			set_transient('widget_recent_posts_by_author', $cache, 60 * 60 * 24 * 30);
		}
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['number'] = (int) $new_instance['number'];

		if ( $this->_use_cache ) {
			$this->flush_widget_cache();
		}

		return $instance;
	}

	function form( $instance ) {
		$number    = isset( $instance['number'] ) ? absint( $instance['number'] ) : $this->_defaults['number'];
?>
		<p>
			<label for="<?php echo $this->get_field_id( 'number' ); ?>"><?php _e( 'Number of posts to show:' ); ?></label>
			<input id="<?php echo $this->get_field_id( 'number' ); ?>" name="<?php echo $this->get_field_name( 'number' ); ?>" type="number" value="<?php echo $number; ?>" min="1" max="9" step="1" size="1" />
		</p>
<?php
	}

	function flush_widget_cache() {
		delete_transient('widget_recent_posts_by_author');
	}
}

// register widget
function register_zon_recent_posts_widget() {
	register_widget('ZON_Recent_Posts_Widget');
}
add_action( 'widgets_init', 'register_zon_recent_posts_widget' );

// customize excerpt length
function custom_excerpt_length( $length ) {
	return 24;
}
add_filter( 'excerpt_length', 'custom_excerpt_length', 999 );

// customize excerpt ellipsis
function custom_excerpt_more( $more ) {
	return ' […]';
}
add_filter( 'excerpt_more', 'custom_excerpt_more' );
