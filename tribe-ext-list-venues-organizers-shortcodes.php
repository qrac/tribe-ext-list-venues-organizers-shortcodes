<?php
/**
 * Plugin name:     The Events Calendar Extension: List Venues/Organizers Shortcodes
 * Description:     Adds the `[list_venues]` and `[list_organizers]` shortcodes to list Venues and Organizers. [Custom linked post types](https://theeventscalendar.com/knowledgebase/linked-post-types/) can be used as well, such as `[list_instructors]`.
 * Version:         2.1.0
 * Extension Class: Tribe__Extension__VenueOrganizer_List
 * Author:          Modern Tribe, Inc
 * Author URI:      http://theeventscalendar.com
 * License:         GPL version 3 or any later version
 * License URI:     https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:     tribe-ext-list-venues-organizers-shortcodes
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

// Do not load directly.
if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

if ( class_exists( 'Tribe__Extension' ) && ! class_exists( 'Tribe__Extension__VenueOrganizer_List' ) ) {
	class Tribe__Extension__VenueOrganizer_List extends Tribe__Extension {
		protected $atts = array();
		protected $query;
		protected $output = '';

		public function construct() {
			$this->add_required_plugin( 'Tribe__Events__Main', '4.3' );
			$this->set_url( 'https://theeventscalendar.com/extensions/list-venues-and-organizers-shortcodes/' );
			$this->set_version( '2.1.0' );
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			// Load plugin textdomain
			load_plugin_textdomain( 'tribe-ext-list-venues-organizers-shortcodes', false, basename( dirname( __FILE__ ) ) . '/languages/' );

			add_shortcode( 'list_venues', array( $this, 'tec_do_venue_shortcode' ) );
			add_shortcode( 'list_organizers', array( $this, 'tec_do_organizer_shortcode' ) );

			add_action( 'wp_enqueue_scripts', array( $this, 'load_styles' ) );
		}

		public function load_styles() {
			wp_register_style( 'tribe-list-venues-organizers-shortcodes', plugins_url( 'tribe-ext-list-venues-organizers-shortcodes/src/resources/css/tribe-list-venues-organizers-shortcodes.css' ) );
			wp_enqueue_style( 'tribe-list-venues-organizers-shortcodes' );
		}

		protected function query() {
			// remove all whitespaces
			$exclude = preg_replace( '/\s+/', '', $this->atts['exclude'] );
			$include = preg_replace( '/\s+/', '', $this->atts['include'] );
			$author  = preg_replace( '/\s+/', '', $this->atts['author'] );
			if ( 'current_user' == $author ) {
				$author = get_current_user_id();
			}
			$exclude = explode( ',', $exclude );
			$include = explode( ',', $include );

			$args = array(
				'post_type'      => $this->atts['post_type'],
				'posts_per_page' => $this->atts['limit'],
				'order'          => $this->atts['order'],
				'orderby'        => $this->atts['orderby'],
				'post__not_in'   => $exclude, // must be an array
			);

			if ( $this->atts['include'] ) {
				$args['post__in'] = $include;
			}
			if ( $this->atts['author'] ) {
				$args['author'] = $author;
			}

			$this->query = new WP_Query( apply_filters( 'tribe_ext_list_venues_organizers_shortcodes_args', $args, $this->atts ) );
		}

		protected function format() {
			$opening_tag  = '<ul class="tribe-venues-organizers-shortcode list ' . $this->atts['post_type'] . '">';
			$this->output = apply_filters( 'tribe_ext_list_venues_organizers_shortcodes_list_open', $opening_tag, $this->atts );

			// remove all whitespaces
			$thumb      = preg_replace( '/\s+/', '', $this->atts['thumb'] );
			$details    = preg_replace( '/\s+/', '', $this->atts['details'] );
			$count      = preg_replace( '/\s+/', '', $this->atts['count'] );
			$hide_empty = preg_replace( '/\s+/', '', $this->atts['hide_empty'] );

			while ( $this->query->have_posts() ) {
				$this->query->the_post();
				$shortcode_type = 'tribe_organizer' == $this->atts['post_type'] ? 'organizer' : 'venue';
				$id             = get_the_ID();

				// count upcoming events
				$args = array(
					'start_date' => date( 'Y-m-d H:i:s' ),
				);

				if ( 'organizer' == $shortcode_type ) {
					$args['organizer'] = $id;
				} else {
					$args['venue'] = $id;
				}

				$events       = tribe_get_events( $args );
				$events_count = count( $events );

				// generate HTML
				if ( 'yes' != $hide_empty || ( 'yes' == $hide_empty && $events_count > 0 ) ) :

					$item       = '<li id="' . esc_attr( $this->atts['post_type'] . '-' . $id ) . '" class="tec list ' . $this->atts['post_type'] . '">';
					$item_thumb = '';

					// get the post thumbnail (if there is one)
					if ( 'yes' == $thumb ) {
						if ( get_the_post_thumbnail( $id ) ) {
							$image      = '<div class="tribe-venues-organizers-image">' . get_the_post_thumbnail( $id, 'thumbnail' ) . '</div>';
							$item_thumb = $image;
						}
					}

					// get the title - if TEC PRO, link to Venue/Organizer, else just output title without link
					if ( defined( 'EVENTS_CALENDAR_PRO_DIR' ) ) {
						$link = '<a href="' . get_the_permalink() . '">' . get_the_title() . '</a>';
					} else {
						$link = get_the_title();
					}
					$items['link'] = $link;

					// get the upcoming event count string
					if ( 'yes' == $count ) {

						if ( 0 == $events_count ) {
							$count_string = __( 'No upcoming events', 'tribe-ext-list-venues-organizers-shortcodes' );
						} else {
							$count_string = sprintf( _n( '%s upcoming event', '%s upcoming events', $events_count, 'tribe-ext-list-venues-organizers-shortcodes' ), $events_count );
						}

						$count_string = '<span class="tribe-venues-organizers-event-count">' . $count_string . '</span>';
						apply_filters( 'tribe_venue_organizer_shortcode_count', $events_count, $count_string );
						$items['count_string'] = $count_string;
					}

					// get the details
					if ( 'yes' == $details ) {
						$item_details = '';

						if ( 'organizer' == $shortcode_type ) {
							$item_details = tribe_get_organizer_details();
							$item_details = '<span class="tribe-organizer-details">' . $item_details . '</span>';
							apply_filters( 'tribe_organizer_shortcode_details', $details );
						} else {
							$venue_details = tribe_get_venue_details();
							$item_details  = $venue_details['address'];
							$item_details  = '<p class="tribe-venue-details">' . $item_details . '</p>';
							apply_filters( 'tribe_venue_shortcode_details', $details );
						}

						$items['details'] = $item_details;
					}

					$item .= $item_thumb ? $item_thumb . '<div class="tribe-venues-organizers-info">' : '<div class="tribe-venues-organizers-info">';

					$item .= implode( '', $items ) . '</div></li>';

					$this->output .= apply_filters( 'tribe_ext_list_venues_organizers_shortcodes_list_item', $item, $this->atts );
				endif;
			}

			$this->output .= apply_filters( 'tribe_ext_list_venues_organizers_shortcodes_list_close', '</ul>', $this->atts );
			wp_reset_postdata();
		}

		public function tec_do_venue_shortcode( $atts ) {
			return $this->do_shortcode( Tribe__Events__Main::VENUE_POST_TYPE, $atts );
		}

		public function tec_do_organizer_shortcode( $atts ) {
			return $this->do_shortcode( Tribe__Events__Main::ORGANIZER_POST_TYPE, $atts );
		}

		private function do_shortcode( $post_type, $atts ) {
			$this->atts = shortcode_atts( array(
				'post_type'  => $post_type,
				'limit'      => -1,
				'order'      => 'ASC',
				'orderby'    => 'post_title',
				'exclude'    => '', // comma-separated list of Venue post IDs or Organizer post IDs
				'include'    => '', // comma-separated list of Venue post IDs or Organizer post IDs
				'author'     => '', // int || str Venue or Organizer author ID or str "current_user"
				'thumb'      => '', // str "yes" to include Venue or Organizer thumbnail
				'details'    => '', // str "yes" to include Venue or Organizer details
				'count'      => '', // str "yes" to include count of upcoming events for each Venue or Organizer
				'hide_empty' => '', // string "yes" to exclude Venues or Organizers without upcoming events
			), $atts );

			$this->query();
			$this->format();

			return $this->output;
		}
	}
}