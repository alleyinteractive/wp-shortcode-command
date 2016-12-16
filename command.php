<?php

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

class Shortcode_CLI_Command extends WP_CLI_Command {

	/**
	 * List all shortcodes in post_content.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp shortcode list-all --format=json
	 *
	 * @subcommand list-all
	 */
	public function list_all( $args, $assoc_args ) {
		global $wpdb;

		$assoc_args = wp_parse_args( $assoc_args, array(
			'format' => 'table'
		) );

		\WP_CLI::line( 'Looking for posts with shortcodes...' );
		$ids = $wpdb->get_col( "SELECT `ID` FROM {$wpdb->posts} WHERE `post_content` LIKE '%[%]%' AND post_type NOT IN ('revision') AND post_status NOT IN ('auto-draft')" );

		$count = count( $ids );
		if ( ! $count ) {
			\WP_CLI::error( 'No posts found with shortcodes' );
		}

		$shortcodes = array();
		$regex = get_shortcode_regex();
		$progress = \WP_CLI\Utils\make_progress_bar( 'Collecting shortcodes', $count );
		$per_page = 100;

		for ( $i = 0; $i < $count; $i += $per_page ) {
			$ids_slice = array_slice( $ids, $i, $per_page );
			$contents = $wpdb->get_col( "SELECT `post_content` FROM {$wpdb->posts} WHERE `ID` in (" . implode( ',', $ids_slice ) . ')' );
			foreach ( $contents as $content ) {
				if ( preg_match_all( "/$regex/s", $content, $matches ) ) {
					foreach ( $matches[2] as $shortcode ) {
						if ( ! isset( $shortcodes[ $shortcode ] ) ) {
							$shortcodes[ $shortcode ] = 1;
						} else {
							$shortcodes[ $shortcode ]++;
						}
					}
				}
				$progress->tick();
			}
		}
		$progress->finish();

		$rows = array();
		foreach ( $shortcodes as $shortcode => $count ) {
			$rows[] = array( 'Shortcode' => $shortcode, 'Number of Instances' => $count );
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'], $rows, array( 'Shortcode', 'Number of Instances' ) );
	}

}
WP_CLI::add_command( 'shortcode', 'Shortcode_CLI_Command' );
