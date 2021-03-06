<?php

namespace Automattic\VIP\Search;

class Health_Test extends \WP_UnitTestCase {
	public function setUp() {
		require_once __DIR__ . '/../../../../search/search.php';
		require_once __DIR__ . '/../../../../search/includes/classes/class-health.php';
	}

	public function test_get_missing_docs_or_posts_diff() {
		$found_post_ids = array( 1, 3, 5 );
		$found_document_ids = array( 1, 3, 7 );

		$diff = \Automattic\VIP\Search\Health::get_missing_docs_or_posts_diff( $found_post_ids, $found_document_ids );

		$expected_diff = array(
			'post_5' => array(
				'existence' => array(
					'expected' => sprintf( 'Post %d to be indexed', 5 ),
					'actual' => null,
				),
			),
			'post_7' => array(
				'existence' => array(
					'expected' => null,
					'actual' => sprintf( 'Post %d is currently indexed', 7 ),
				),
			),
		);

		$this->assertEquals( $expected_diff, $diff );
	}

	public function test_filter_expected_post_rows() {
		add_filter( 'ep_post_sync_kill', function( $skip, $post_id ) {
			return 2 === $post_id;
		}, 10, 2 );
	
		$rows = array(
			// Indexed
			(object) array(
				'ID' => 1,
				'post_type' => 'post',
				'post_status' => 'publish',
			),

			// Filtered out by ep_post_sync_kill
			(object) array(
				'ID' => 2,
				'post_type' => 'post',
				'post_status' => 'publish',
			),

			// Un-indexed post_type
			(object) array(
				'ID' => 3,
				'post_type' => 'unindexed',
				'post_status' => 'publish',
			),

			// Un-indexed post_status
			(object) array(
				'ID' => 4,
				'post_type' => 'post',
				'post_status' => 'unindexed',
			),

			// Indexed
			(object) array(
				'ID' => 5,
				'post_type' => 'post',
				'post_status' => 'publish',
			),
		);

		$indexed_post_types = array( 'post' );
		$indexed_post_statuses = array( 'publish' );

		$filtered = \Automattic\VIP\Search\Health::filter_expected_post_rows( $rows, $indexed_post_types, $indexed_post_statuses );

		// Grab just the IDs to make validation simpler
		$filtered_ids = array_values( wp_list_pluck( $filtered, 'ID' ) );

		$expected_ids = array( 1, 5 );

		$this->assertEquals( $expected_ids, $filtered_ids );
	}

	public function get_diff_document_and_prepared_document_data() {
		return array(
			// Simple diff
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(
					'post_title' => 'bar',
				),

				// Expected diff
				array(
					'post_title' => array(
						'expected' => 'foo',
						'actual' => 'bar',
					),
				),
			),

			// Nested props
			array(
				// Expected
				array(
					'post_title' => 'foo',
					'meta' => array(
						'somemeta' => array(
							'raw' => 'somemeta_raw',
							'value' => 'somemeta_value',
							'date' => '1970-01-01',
						),
					),
				),

				// Indexed
				array(
					'post_title' => 'bar',
					'meta' => array(
						'somemeta' => array(
							'raw' => 'somemeta_raw_other',
							'value' => 'somemeta_value_other',
							'date' => '1970-12-31', // Should not be validated
						),
					),
				),

				// Expected diff
				array(
					'post_title' => array(
						'expected' => 'foo',
						'actual' => 'bar',
					),
					'meta' => array(
						'somemeta' => array(
							'raw' => array(
								'expected' => 'somemeta_raw',
								'actual' => 'somemeta_raw_other',
							),
							'value' => array(
								'expected' => 'somemeta_value',
								'actual' => 'somemeta_value_other',
							),
						),
					),
				),
			),

			// No diff
			array(
				// Expected
				array(
					'post_title' => 'foo',
				),

				// Indexed
				array(
					'post_title' => 'foo',
				),

				// Expected diff
				null,
			),
		);
	}

	/**
	 * @dataProvider get_diff_document_and_prepared_document_data
	 */
	public function test_diff_document_and_prepared_document( $prepared_document, $document, $expected_diff ) {
		$diff = \Automattic\VIP\Search\Health::diff_document_and_prepared_document( $document, $prepared_document );

		$this->assertEquals( $expected_diff, $diff );
	}

	public function test_get_document_ids_for_batch() {
		$ids = \Automattic\VIP\Search\Health::get_document_ids_for_batch( 1, 5 );

		$expected_ids = array( 1, 2, 3, 4, 5 );

		$this->assertEquals( $expected_ids, $ids );
	}

	public function test_get_last_post_id() {
		$post = $this->factory->post->create_and_get( [ 'post_status' => 'draft' ] );

		$last_post_id = \Automattic\VIP\Search\Health::get_last_post_id();

		$this->assertEquals( $post->ID, $last_post_id );
	}
}
