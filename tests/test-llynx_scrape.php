<?php
/**
 * This file contains tests for the llynx_scrape class
 *
 * @group llynx_scrape
 * @group llynx_core
 */
class ScrapeTest extends WP_UnitTestCase {
	public $scrape;
	function setUp() {
		parent::setUp();
		$this->scrape = new llynxScrape();
	}
	public function tearDown() {
		parent::tearDown();
	}
	function test_url_fix() {
		$full_url = 'http://mtekk.us/code/breadcrumb-navxt';
		$base_url = 'http://mtekk.us';
		//First with an absolute URL
		$url = $this->scrape->urlFix($base_url, $full_url);
		$this->assertSame($full_url, $url);
		//Now a relative URL
		$url2 = $this->scrape->urlFix($base_url, '/code/breadcrumb-navxt');
		$this->assertSame($full_url, $url2);
	}
}