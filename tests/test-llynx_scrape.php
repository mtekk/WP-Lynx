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
	function test_findTitle(){
		$text1 = '<html>
	<head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
        <title>Aptana  | Download Aptana Studio 3.6.1 </title>
	</head>';
		$text2 = '<html>
	<head>
        <meta http-equiv="content-type" content="text/html; charset=utf-8" />
	</head>';
		$this->scrape->findTitle($text1);
		$this->assertSame('Aptana | Download Aptana Studio 3.6.1', $this->scrape->title);
		$this->scrape->title = '';
		$this->scrape->findTitle($text2);
		$this->assertSame('', $this->scrape->title);
	}
	function test_trimText(){
		
	}
}