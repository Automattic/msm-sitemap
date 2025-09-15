<?php
/**
 * SitemapStatsService
 *
 * @package Automattic\MSM_Sitemap\Application\Services
 */

declare( strict_types=1 );

namespace Automattic\MSM_Sitemap\Application\Services;

use Automattic\MSM_Sitemap\Domain\Contracts\SitemapRepositoryInterface;
use Automattic\MSM_Sitemap\Infrastructure\Repositories\PostRepository;
use Automattic\MSM_Sitemap\Infrastructure\WordPress\PostTypeRegistration;
use DateTime;
use DateInterval;

/**
 * Service for generating comprehensive sitemap statistics.
 */
class SitemapStatsService {

	/**
	 * The sitemap repository.
	 *
	 * @var SitemapRepositoryInterface
	 */
	private SitemapRepositoryInterface $repository;

	/**
	 * The post repository.
	 *
	 * @var PostRepository
	 */
	private PostRepository $post_repository;

	/**
	 * The post type registration service.
	 *
	 * @var PostTypeRegistration
	 */
	private PostTypeRegistration $post_type_registration;

	/**
	 * Constructor.
	 *
	 * @param SitemapRepositoryInterface $repository The sitemap repository.
	 * @param PostRepository|null $post_repository The post repository (optional, will create if not provided).
	 * @param PostTypeRegistration|null $post_type_registration The post type registration service (optional, will create if not provided).
	 */
	public function __construct( 
		SitemapRepositoryInterface $repository,
		?PostRepository $post_repository = null,
		?PostTypeRegistration $post_type_registration = null
	) {
		$this->repository             = $repository;
		$this->post_repository        = $post_repository ?? new PostRepository();
		$this->post_type_registration = $post_type_registration ?? new PostTypeRegistration();
	}

	/**
	 * Get comprehensive sitemap statistics.
	 *
	 * @param string $date_range Optional date range filter ('all', '7', '30', '90', '180', '365', 'custom', or 'year_YYYY').
	 * @param string $start_date Optional start date for custom range (Y-m-d format).
	 * @param string $end_date Optional end date for custom range (Y-m-d format).
	 * @return array Array containing various statistics about sitemaps.
	 */
	public function get_comprehensive_stats( string $date_range = 'all', string $start_date = '', string $end_date = '' ): array {
		$sitemap_dates = $this->repository->get_all_sitemap_dates();
		
		// Filter sitemap dates based on date range
		if ( 'all' !== $date_range ) {
			if ( 'custom' === $date_range && ! empty( $start_date ) && ! empty( $end_date ) ) {
				// Custom date range
				$sitemap_dates = array_filter(
					$sitemap_dates,
					function ( $date ) use ( $start_date, $end_date ) {
						return $date >= $start_date && $date <= $end_date;
					}
				);
			} elseif ( strpos( $date_range, 'year_' ) === 0 ) {
				// Year-specific range
				$year          = substr( $date_range, 5 ); // Remove 'year_' prefix
				$sitemap_dates = array_filter(
					$sitemap_dates,
					function ( $date ) use ( $year ) {
						return strpos( $date, $year ) === 0; // Date starts with the year
					}
				);
			} else {
				// Predefined ranges
				$days_ago      = (int) $date_range;
				$cutoff_date   = gmdate( 'Y-m-d', strtotime( "-{$days_ago} days" ) );
				$sitemap_dates = array_filter(
					$sitemap_dates,
					function ( $date ) use ( $cutoff_date ) {
						return $date >= $cutoff_date;
					}
				);
			}
		}
		
		$total_sitemaps = count( $sitemap_dates );

		if ( 0 === $total_sitemaps ) {
			return $this->get_empty_stats();
		}

		$stats = array(
			'overview'         => $this->get_overview_stats( $sitemap_dates ),
			'timeline'         => $this->get_timeline_stats( $sitemap_dates ),
			'url_counts'       => $this->get_url_count_stats( $sitemap_dates ),
			'performance'      => $this->get_performance_stats( $sitemap_dates ),
			'coverage'         => $this->get_coverage_stats( $sitemap_dates ),
			'storage'          => $this->get_storage_stats( $sitemap_dates ),
			'health'           => $this->get_health_stats( $sitemap_dates ),
			'content_analysis' => $this->get_content_analysis_stats( $sitemap_dates ),
		);

		return $stats;
	}

	/**
	 * Get basic overview statistics.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array Overview statistics.
	 */
	private function get_overview_stats( array $sitemap_dates ): array {
		$total_sitemaps = count( $sitemap_dates );
		$total_urls     = (int) get_option( 'msm_sitemap_indexed_url_count', 0 );
		
		// Find most recent sitemap
		$most_recent = null;
		$max_date    = '';
		
		foreach ( $sitemap_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( ! $post_id ) {
				continue;
			}
			
			$post = get_post( $post_id );
			if ( $post && ( '' === $max_date || $post->post_date_gmt > $max_date ) ) {
				$max_date    = $post->post_date_gmt;
				$most_recent = $post;
			}
		}

		// Find oldest sitemap
		$oldest   = null;
		$min_date = '';
		
		foreach ( $sitemap_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( ! $post_id ) {
				continue;
			}
			
			$post = get_post( $post_id );
			if ( $post && ( '' === $min_date || $post->post_date_gmt < $min_date ) ) {
				$min_date = $post->post_date_gmt;
				$oldest   = $post;
			}
		}

		return array(
			'total_sitemaps'           => $total_sitemaps,
			'total_urls'               => $total_urls,
			'most_recent'              => array(
				'date'      => $most_recent ? $most_recent->post_name : '',
				'id'        => $most_recent ? $most_recent->ID : 0,
				'created'   => $most_recent ? $most_recent->post_date_gmt : '',
				'url_count' => $most_recent ? (int) get_post_meta( $most_recent->ID, 'msm_indexed_url_count', true ) : 0,
			),
			'oldest'                   => array(
				'date'      => $oldest ? $oldest->post_name : '',
				'id'        => $oldest ? $oldest->ID : 0,
				'created'   => $oldest ? $oldest->post_date_gmt : '',
				'url_count' => $oldest ? (int) get_post_meta( $oldest->ID, 'msm_indexed_url_count', true ) : 0,
			),
			'average_urls_per_sitemap' => $total_sitemaps > 0 ? round( $total_urls / $total_sitemaps, 2 ) : 0,
		);
	}

	/**
	 * Get timeline statistics (yearly, monthly breakdowns).
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array Timeline statistics.
	 */
	private function get_timeline_stats( array $sitemap_dates ): array {
		$yearly_stats  = array();
		$monthly_stats = array();
		$daily_stats   = array();

		foreach ( $sitemap_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( ! $post_id ) {
				continue;
			}

			$url_count = (int) get_post_meta( $post_id, 'msm_indexed_url_count', true );
			
			// Parse date components
			$date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
			if ( ! $date_obj ) {
				continue;
			}

			$year  = $date_obj->format( 'Y' );
			$month = $date_obj->format( 'Y-m' );
			$day   = $date_obj->format( 'Y-m-d' );

			// Yearly stats
			if ( ! isset( $yearly_stats[ $year ] ) ) {
				$yearly_stats[ $year ] = array(
					'count' => 0,
					'urls'  => 0,
				);
			}
			++$yearly_stats[ $year ]['count'];
			$yearly_stats[ $year ]['urls'] += $url_count;

			// Monthly stats
			if ( ! isset( $monthly_stats[ $month ] ) ) {
				$monthly_stats[ $month ] = array(
					'count' => 0,
					'urls'  => 0,
				);
			}
			++$monthly_stats[ $month ]['count'];
			$monthly_stats[ $month ]['urls'] += $url_count;

			// Daily stats
			$daily_stats[ $day ] = $url_count;
		}

		// Sort by date
		krsort( $yearly_stats );
		krsort( $monthly_stats );
		krsort( $daily_stats );

		return array(
			'yearly'     => $yearly_stats,
			'monthly'    => $monthly_stats,
			'daily'      => $daily_stats,
			'date_range' => array(
				'start'      => reset( $sitemap_dates ),
				'end'        => end( $sitemap_dates ),
				'total_days' => count( array_unique( $sitemap_dates ) ),
			),
		);
	}

	/**
	 * Get URL count statistics.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array URL count statistics.
	 */
	private function get_url_count_stats( array $sitemap_dates ): array {
		$url_counts = array();
		$total_urls = 0;

		foreach ( $sitemap_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( ! $post_id ) {
				continue;
			}

			$url_count           = (int) get_post_meta( $post_id, 'msm_indexed_url_count', true );
			$url_counts[ $date ] = $url_count;
			$total_urls         += $url_count;
		}

		if ( empty( $url_counts ) ) {
			return array(
				'total_urls'       => 0,
				'average_urls'     => 0,
				'min_urls'         => 0,
				'max_urls'         => 0,
				'median_urls'      => 0,
				'url_distribution' => array(),
			);
		}

		$values = array_values( $url_counts );
		sort( $values );
		
		$count  = count( $values );
		$median = $count % 2 === 0 
			? ( $values[ $count / 2 - 1 ] + $values[ $count / 2 ] ) / 2 
			: $values[ ( $count - 1 ) / 2 ];

		// Create distribution buckets
		$distribution = array(
			'0-10'     => 0,
			'11-50'    => 0,
			'51-100'   => 0,
			'101-500'  => 0,
			'501-1000' => 0,
			'1000+'    => 0,
		);

		foreach ( $values as $count ) {
			if ( $count <= 10 ) {
				++$distribution['0-10'];
			} elseif ( $count <= 50 ) {
				++$distribution['11-50'];
			} elseif ( $count <= 100 ) {
				++$distribution['51-100'];
			} elseif ( $count <= 500 ) {
				++$distribution['101-500'];
			} elseif ( $count <= 1000 ) {
				++$distribution['501-1000'];
			} else {
				++$distribution['1000+'];
			}
		}

		return array(
			'total_urls'         => $total_urls,
			'average_urls'       => round( $total_urls / count( $url_counts ), 2 ),
			'min_urls'           => min( $values ),
			'max_urls'           => max( $values ),
			'median_urls'        => $median,
			'url_distribution'   => $distribution,
			'url_counts_by_date' => $url_counts,
		);
	}

	/**
	 * Get performance statistics.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array Performance statistics.
	 */
	private function get_performance_stats( array $sitemap_dates ): array {
		$recent_url_counts = array();

		foreach ( $sitemap_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( ! $post_id ) {
				continue;
			}

			$url_count                  = (int) get_post_meta( $post_id, 'msm_indexed_url_count', true );
			$recent_url_counts[ $date ] = $url_count;
		}

		$trend = 'stable';
		if ( count( $recent_url_counts ) >= 2 ) {
			$values      = array_values( $recent_url_counts );
			$first_half  = array_slice( $values, 0, (int) floor( count( $values ) / 2 ) );
			$second_half = array_slice( $values, (int) floor( count( $values ) / 2 ) );
			
			$first_avg  = array_sum( $first_half ) / count( $first_half );
			$second_avg = array_sum( $second_half ) / count( $second_half );
			
			if ( $second_avg > $first_avg * 1.1 ) {
				$trend = 'increasing';
			} elseif ( $second_avg < $first_avg * 0.9 ) {
				$trend = 'decreasing';
			}
		}

		return array(
			'recent_trend'       => $trend,
			'recent_average'     => count( $recent_url_counts ) > 0 ? round( array_sum( $recent_url_counts ) / count( $recent_url_counts ), 2 ) : 0,
			'recent_count'       => count( $recent_url_counts ),
			'last_updated'       => get_option( 'msm_sitemap_update_last_run', 0 ),
			'last_updated_human' => get_option( 'msm_sitemap_update_last_run', 0 ) ? human_time_diff( get_option( 'msm_sitemap_update_last_run', 0 ) ) . ' ago' : 'Never',
		);
	}

	/**
	 * Get coverage statistics.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array Coverage statistics.
	 */
	private function get_coverage_stats( array $sitemap_dates ): array {
		if ( empty( $sitemap_dates ) ) {
			return array(
				'date_coverage'      => 0,
				'gaps'               => array(),
				'continuous_streaks' => array(),
			);
		}

		// Sort dates
		sort( $sitemap_dates );
		
		$start_date = DateTime::createFromFormat( 'Y-m-d', $sitemap_dates[0] );
		$end_date   = DateTime::createFromFormat( 'Y-m-d', end( $sitemap_dates ) );
		
		if ( ! $start_date || ! $end_date ) {
			return array(
				'date_coverage'      => 0,
				'gaps'               => array(),
				'continuous_streaks' => array(),
			);
		}

		$total_days          = $start_date->diff( $end_date )->days + 1;
		$covered_days        = count( $sitemap_dates );
		$coverage_percentage = round( ( $covered_days / $total_days ) * 100, 2 );

		// Find gaps
		$gaps         = array();
		$current_date = clone $start_date;
		
		foreach ( $sitemap_dates as $date ) {
			$sitemap_date = DateTime::createFromFormat( 'Y-m-d', $date );
			if ( ! $sitemap_date ) {
				continue;
			}

			while ( $current_date < $sitemap_date ) {
				$gaps[] = $current_date->format( 'Y-m-d' );
				$current_date->add( new DateInterval( 'P1D' ) );
			}
			$current_date->add( new DateInterval( 'P1D' ) );
		}

		// Find continuous streaks
		$streaks        = array();
		$current_streak = array();
		
		foreach ( $sitemap_dates as $date ) {
			if ( empty( $current_streak ) ) {
				$current_streak[] = $date;
			} else {
				$last_date    = DateTime::createFromFormat( 'Y-m-d', end( $current_streak ) );
				$current_date = DateTime::createFromFormat( 'Y-m-d', $date );
				
				if ( $last_date && $current_date && $last_date->diff( $current_date )->days === 1 ) {
					$current_streak[] = $date;
				} else {
					if ( count( $current_streak ) > 1 ) {
						$streaks[] = array(
							'start'  => $current_streak[0],
							'end'    => end( $current_streak ),
							'length' => count( $current_streak ),
						);
					}
					$current_streak = array( $date );
				}
			}
		}
		
		// Add the last streak
		if ( count( $current_streak ) > 1 ) {
			$streaks[] = array(
				'start'  => $current_streak[0],
				'end'    => end( $current_streak ),
				'length' => count( $current_streak ),
			);
		}

		// Sort streaks by length (descending)
		usort(
			$streaks,
			function ( $a, $b ) {
				return $b['length'] <=> $a['length'];
			} 
		);

		// Calculate coverage quality (percentage of site content in sitemaps)
		$total_posts   = wp_count_posts( 'post' )->publish;
		$total_pages   = wp_count_posts( 'page' )->publish;
		$total_content = $total_posts + $total_pages;
		
		// Get total URLs from overview stats (we'll need to pass this in or calculate it here)
		$overview_stats = $this->get_overview_stats( $sitemap_dates );
		$sitemap_urls   = $overview_stats['total_urls'];
		
		$coverage_quality = $total_content > 0 ? round( ( $sitemap_urls / $total_content ) * 100, 1 ) : 0;
		
		// Get the posts per sitemap limit for context
		$posts_per_sitemap = apply_filters( 'msm_sitemap_entry_posts_per_page', 500 );

		return array(
			'date_coverage'           => $coverage_percentage,
			'total_days'              => $total_days,
			'covered_days'            => $covered_days,
			'gaps'                    => $gaps,
			'continuous_streaks'      => $streaks,
			'longest_streak'          => ! empty( $streaks ) ? $streaks[0] : null,
			'coverage_quality'        => $coverage_quality,
			'posts_per_sitemap_limit' => $posts_per_sitemap,
		);
	}

	/**
	 * Get storage statistics.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array Storage statistics.
	 */
	private function get_storage_stats( array $sitemap_dates ): array {
		global $wpdb;

		// For storage stats, we want to show the overall storage usage, not just the analyzed dates
		// This gives a better picture of the total database impact
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$sitemap_posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content, post_date_gmt 
			FROM $wpdb->posts 
			WHERE post_type = %s AND post_status = 'publish'
			ORDER BY post_date_gmt DESC",
				$this->post_type_registration->get_post_type()
			) 
		);

		$total_size        = 0;
		$largest_sitemap   = null;
		$smallest_sitemap  = null;
		$size_distribution = array(
			'0-1KB'     => 0,
			'1-10KB'    => 0,
			'10-100KB'  => 0,
			'100KB-1MB' => 0,
			'1MB+'      => 0,
		);



		foreach ( $sitemap_posts as $post ) {
			// Check both post_content and post meta for sitemap data
			$content_size = strlen( $post->post_content );
			$meta_size    = 0;
			
			// Get all post meta for this sitemap post
			$post_meta = get_post_meta( $post->ID );
			foreach ( $post_meta as $meta_key => $meta_values ) {
				foreach ( $meta_values as $meta_value ) {
					$meta_size += strlen( $meta_value );
				}
			}
			
			// Use the larger of content or meta size
			$size        = max( $content_size, $meta_size );
			$total_size += $size;

			// Track largest and smallest
			if ( ! $largest_sitemap || $size > $largest_sitemap['size'] ) {
				$largest_sitemap = array(
					'id'         => $post->ID,
					'date'       => $post->post_date_gmt,
					'size'       => $size,
					'size_human' => size_format( $size ),
				);
			}

			if ( ! $smallest_sitemap || $size < $smallest_sitemap['size'] ) {
				$smallest_sitemap = array(
					'id'         => $post->ID,
					'date'       => $post->post_date_gmt,
					'size'       => $size,
					'size_human' => size_format( $size ),
				);
			}

			// Categorize by size
			if ( $size <= 1024 ) {
				++$size_distribution['0-1KB'];
			} elseif ( $size <= 10240 ) {
				++$size_distribution['1-10KB'];
			} elseif ( $size <= 102400 ) {
				++$size_distribution['10-100KB'];
			} elseif ( $size <= 1048576 ) {
				++$size_distribution['100KB-1MB'];
			} else {
				++$size_distribution['1MB+'];
			}
		}

		return array(
			'total_size'         => $total_size,
			'total_size_human'   => size_format( $total_size ),
			'average_size'       => count( $sitemap_posts ) > 0 ? round( $total_size / count( $sitemap_posts ), 2 ) : 0,
			'average_size_human' => count( $sitemap_posts ) > 0 ? size_format( round( $total_size / count( $sitemap_posts ) ) ) : '0 B',
			'largest_sitemap'    => $largest_sitemap,
			'smallest_sitemap'   => $smallest_sitemap,
			'size_distribution'  => $size_distribution,

		);
	}

	/**
	 * Get recent URL counts for the last N days.
	 *
	 * @param int $days Number of days to retrieve counts for (default: 7).
	 * @return array Array of date => url_count pairs.
	 */
	public function get_recent_url_counts( int $days = 7 ): array {
		$stats = array();

		for ( $i = 0; $i < $days; $i++ ) {
			$date    = date( 'Y-m-d', strtotime( "-$i days" ) );
			$post_id = $this->repository->find_by_date( $date );
			
			if ( $post_id ) {
				$stats[ $date ] = (int) get_post_meta( $post_id, 'msm_indexed_url_count', true );
			} else {
				$stats[ $date ] = 0;
			}
		}

		return $stats;
	}

	/**
	 * Get empty statistics structure.
	 *
	 * @return array Empty statistics structure.
	 */
	private function get_empty_stats(): array {
		return array(
			'overview'         => array(
				'total_sitemaps'           => 0,
				'total_urls'               => 0,
				'most_recent'              => array(
					'date'      => '',
					'id'        => 0,
					'created'   => '',
					'url_count' => 0,
				),
				'oldest'                   => array(
					'date'      => '',
					'id'        => 0,
					'created'   => '',
					'url_count' => 0,
				),
				'average_urls_per_sitemap' => 0,
			),
			'timeline'         => array(
				'yearly'     => array(),
				'monthly'    => array(),
				'daily'      => array(),
				'date_range' => array(
					'start'      => '',
					'end'        => '',
					'total_days' => 0,
				),
			),
			'url_counts'       => array(
				'total_urls'       => 0,
				'average_urls'     => 0,
				'min_urls'         => 0,
				'max_urls'         => 0,
				'median_urls'      => 0,
				'url_distribution' => array(),
			),
			'performance'      => array(
				'recent_trend'       => 'stable',
				'recent_average'     => 0,
				'recent_count'       => 0,
				'last_updated'       => 0,
				'last_updated_human' => 'Never',
			),
			'coverage'         => array(
				'date_coverage'      => 0,
				'gaps'               => array(),
				'continuous_streaks' => array(),
			),
			'storage'          => array(
				'total_size'         => 0,
				'total_size_human'   => '0 B',
				'average_size'       => 0,
				'average_size_human' => '0 B',
				'largest_sitemap'    => null,
				'smallest_sitemap'   => null,
				'size_distribution'  => array(),
			),
			'health'           => array(
				'success_rate'            => 0,
				'last_error'              => null,
				'average_generation_time' => 0,
				'processing_queue'        => 0,
			),
			'content_analysis' => array(
				'url_types'         => array(),
				'post_type_counts'  => array(),
				'yearly_breakdown'  => array(),
				'content_freshness' => 0,
				'duplicate_urls'    => 0,
			),
		);
	}

	/**
	 * Get health and reliability statistics.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array Health statistics.
	 */
	private function get_health_stats( array $sitemap_dates ): array {
		// Calculate success rate based on recent activity (last 30 days)
		$recent_dates    = array_slice( $sitemap_dates, -30, null, true );
		$recent_actual   = count( $recent_dates );
		$recent_expected = min( 30, count( $sitemap_dates ) ); // Cap at 30 days or total available
		$success_rate    = $recent_expected > 0 ? round( ( $recent_actual / $recent_expected ) * 100, 1 ) : 100;

		// Get last error from options
		$last_error           = get_option( 'msm_sitemap_last_error', null );
		$last_error_formatted = null;
		if ( $last_error ) {
			$last_error_formatted = array(
				'timestamp'  => $last_error['timestamp'] ?? 0,
				'message'    => $last_error['message'] ?? 'Unknown error',
				'human_time' => $last_error['timestamp'] ? human_time_diff( $last_error['timestamp'] ) . ' ago' : 'Unknown',
			);
		}

		// Get average generation time from recent sitemaps
		$recent_dates     = array_slice( $sitemap_dates, -10, null, true );
		$generation_times = array();
		foreach ( $recent_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( $post_id ) {
				$generation_time = get_post_meta( $post_id, 'msm_generation_time', true );
				if ( $generation_time ) {
					$generation_times[] = (float) $generation_time;
				}
			}
		}
		$average_generation_time = ! empty( $generation_times ) ? round( array_sum( $generation_times ) / count( $generation_times ), 2 ) : 0;

		// Check processing queue
		$processing_queue = (bool) get_option( 'msm_generation_in_progress', false ) ? 1 : 0;

		return array(
			'success_rate'            => $success_rate,
			'last_error'              => $last_error_formatted,
			'average_generation_time' => $average_generation_time,
			'processing_queue'        => $processing_queue,
		);
	}

	/**
	 * Get content analysis statistics.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return array Content analysis statistics.
	 */
	private function get_content_analysis_stats( array $sitemap_dates ): array {
		global $wpdb;

		// Get URL types breakdown from recent sitemaps
		$url_types        = array();
		$post_type_counts = array();
		$recent_dates     = array_slice( $sitemap_dates, -5, null, true );
		
		foreach ( $recent_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( $post_id ) {
				$xml_content = get_post_meta( $post_id, 'msm_sitemap_xml', true );
				if ( $xml_content ) {
					// Parse XML to extract URLs
					libxml_use_internal_errors( true );
					$xml = simplexml_load_string( $xml_content );
					libxml_clear_errors();
					
					if ( $xml && isset( $xml->url ) ) {
						foreach ( $xml->url as $url_entry ) {
							$url = (string) $url_entry->loc;
							if ( $url ) {
								$type = $this->categorize_url_type( $url );
								if ( ! isset( $url_types[ $type ] ) ) {
									$url_types[ $type ] = 0;
								}
								++$url_types[ $type ];
								
								// Also track by post type for "Most Active Content"
								$post_id_from_url = url_to_postid( $url );
								if ( $post_id_from_url ) {
									$post = get_post( $post_id_from_url );
									if ( $post && $post->post_type ) {
										$post_type = $post->post_type;
										if ( ! isset( $post_type_counts[ $post_type ] ) ) {
											$post_type_counts[ $post_type ] = 0;
										}
										++$post_type_counts[ $post_type ];
									}
								}
							}
						}
					}
				}
			}
		}

		// Get yearly breakdown
		$yearly_breakdown = array();
		foreach ( $sitemap_dates as $date ) {
			$year = substr( $date, 0, 4 );
			if ( ! isset( $yearly_breakdown[ $year ] ) ) {
				$yearly_breakdown[ $year ] = 0;
			}
			++$yearly_breakdown[ $year ];
		}
		krsort( $yearly_breakdown );

		// Calculate content freshness (average age of posts in sitemaps)
		$content_freshness = $this->calculate_content_freshness( $sitemap_dates );

		// Count duplicate URLs
		$duplicate_urls = $this->count_duplicate_urls( $sitemap_dates );

		// Sort post types by count for "Most Active Content"
		arsort( $post_type_counts );

		return array(
			'url_types'         => $url_types,
			'post_type_counts'  => $post_type_counts,
			'yearly_breakdown'  => $yearly_breakdown,
			'content_freshness' => $content_freshness,
			'duplicate_urls'    => $duplicate_urls,
		);
	}

	/**
	 * Calculate expected number of sitemaps based on date range.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return int Expected number of sitemaps.
	 */
	private function calculate_expected_sitemaps( array $sitemap_dates ): int {
		if ( empty( $sitemap_dates ) ) {
			return 0;
		}

		sort( $sitemap_dates );
		$start_date = DateTime::createFromFormat( 'Y-m-d', $sitemap_dates[0] );
		$end_date   = DateTime::createFromFormat( 'Y-m-d', end( $sitemap_dates ) );
		
		if ( ! $start_date || ! $end_date ) {
			return count( $sitemap_dates );
		}

		$days_diff = $start_date->diff( $end_date )->days + 1;
		return $days_diff;
	}

	/**
	 * Categorize URL type based on URL structure.
	 *
	 * @param string $url The URL to categorize.
	 * @return string The URL type.
	 */
	private function categorize_url_type( string $url ): string {
		// Parse the URL to get the path
		$parsed_url = parse_url( $url );
		$path       = $parsed_url['path'] ?? '';
		
		// Check for common WordPress URL patterns
		if ( preg_match( '#/category/#', $path ) ) {
			return 'Categories';
		} elseif ( preg_match( '#/tag/#', $path ) ) {
			return 'Tags';
		} elseif ( preg_match( '#/author/#', $path ) ) {
			return 'Authors';
		} elseif ( preg_match( '#/page/#', $path ) || preg_match( '#/\?p=#', $url ) ) {
			return 'Pages';
		} elseif ( preg_match( '#/\d{4}/\d{2}/#', $path ) || preg_match( '#/\d{4}/#', $path ) ) {
			return 'Posts';
		} elseif ( preg_match( '#/\?p=\d+#', $url ) ) {
			// Check if it's a post by query parameter
			$post_id = url_to_postid( $url );
			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					if ( 'post' === $post->post_type ) {
						return 'Posts';
					} elseif ( 'page' === $post->post_type ) {
						return 'Pages';
					}
				}
			}
			return 'Posts'; // Default for ?p= URLs
		} elseif ( '/' === $path || '' === $path ) {
			return 'Home';
		} else {
			// Try to identify by post ID lookup
			$post_id = url_to_postid( $url );
			if ( $post_id ) {
				$post = get_post( $post_id );
				if ( $post ) {
					if ( 'post' === $post->post_type ) {
						return 'Posts';
					} elseif ( 'page' === $post->post_type ) {
						return 'Pages';
					} else {
						return ucfirst( $post->post_type );
					}
				}
			}
			return 'Other';
		}
	}

	/**
	 * Calculate content freshness (average age of posts).
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return int Average age in days.
	 */
	private function calculate_content_freshness( array $sitemap_dates ): int {
		global $wpdb;

		$recent_date = end( $sitemap_dates );
		if ( ! $recent_date ) {
			return 0;
		}

		$post_id = $this->repository->find_by_date( $recent_date );
		if ( ! $post_id ) {
			return 0;
		}

		$xml_content = get_post_meta( $post_id, 'msm_sitemap_xml', true );
		if ( ! $xml_content ) {
			return 0;
		}

		// Parse XML to extract URLs
		libxml_use_internal_errors( true );
		$xml = simplexml_load_string( $xml_content );
		libxml_clear_errors();

		if ( ! $xml || ! isset( $xml->url ) ) {
			return 0;
		}

		$total_age  = 0;
		$post_count = 0;

		foreach ( $xml->url as $url_entry ) {
			$url = (string) $url_entry->loc;
			if ( $url ) {
				$post_id_from_url = url_to_postid( $url );
				if ( $post_id_from_url ) {
					$post = get_post( $post_id_from_url );
					if ( $post && 'post' === $post->post_type ) {
						$post_date    = strtotime( $post->post_date );
						$current_time = time();
						$age_days     = floor( ( $current_time - $post_date ) / DAY_IN_SECONDS );
						$total_age   += $age_days;
						++$post_count;
					}
				}
			}
		}

		return $post_count > 0 ? (int) round( $total_age / $post_count ) : 0;
	}

	/**
	 * Count duplicate URLs across sitemaps.
	 *
	 * @param array $sitemap_dates Array of sitemap dates.
	 * @return int Number of duplicate URLs.
	 */
	private function count_duplicate_urls( array $sitemap_dates ): int {
		$all_urls   = array();
		$duplicates = 0;

		foreach ( $sitemap_dates as $date ) {
			$post_id = $this->repository->find_by_date( $date );
			if ( $post_id ) {
				$xml_content = get_post_meta( $post_id, 'msm_sitemap_xml', true );
				if ( $xml_content ) {
					// Parse XML to extract URLs
					libxml_use_internal_errors( true );
					$xml = simplexml_load_string( $xml_content );
					libxml_clear_errors();
					
					if ( $xml && isset( $xml->url ) ) {
						foreach ( $xml->url as $url_entry ) {
							$url = (string) $url_entry->loc;
							if ( $url ) {
								if ( isset( $all_urls[ $url ] ) ) {
									++$duplicates;
								} else {
									$all_urls[ $url ] = true;
								}
							}
						}
					}
				}
			}
		}

		return $duplicates;
	}
}
