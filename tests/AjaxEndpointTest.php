<?php
/**
 * Tests for the Metro Sitemap AJAX endpoint and data retrieval logic.
 *
 * @package Metro_Sitemap/unit_tests
 */

namespace Automattic\MSM_Sitemap\Tests;

use Metro_Sitemap;

class AjaxEndpointTest extends TestCase {
	protected $admin_id;

	public function setUp(): void {
		parent::setUp();
		$this->admin_id = $this->factory->user->create(['role' => 'administrator']);
	}

	public function test_get_sitemap_counts_data_returns_expected_keys() {
		$data = Metro_Sitemap::get_sitemap_counts_data(5);
		$this->assertIsArray($data);
		$this->assertArrayHasKey('total_indexed_urls', $data);
		$this->assertArrayHasKey('total_sitemaps', $data);
		$this->assertArrayHasKey('sitemap_indexed_urls', $data);
		$this->assertIsArray($data['sitemap_indexed_urls']);
		$this->assertCount(5, $data['sitemap_indexed_urls']);
	}
} 
