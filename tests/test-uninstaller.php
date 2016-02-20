<?php
/**
 * This file contains tests for the adminKit uninstaller
 *
 * @group uninstall
 */
class UninstallerTests extends WP_Plugin_Uninstall_UnitTestCase
{
	public function setUp()
	{
		$this->plugin_file = dirname( dirname( __FILE__ ) ) . '/wp-lynx.php';
		parent::setUp();
		global $current_user;
		// This code will run before each test!
		$current_user = new WP_User(1);
		$current_user->set_role('administrator');
		require dirname( dirname( __FILE__ ) ) . '/wp_lynx.php';
		require dirname( dirname( __FILE__ ) ) . '/class.llynx_admin.php';
		$llynx_admin = new llynx_admin(
			array('Sfoo' => 'bar'),
			plugin_basename($this->plugin_file),
			array('%foo%', '%bar%')
		);
		$llynx_admin->install();
	}
	
	public function tearDown()
	{
		parent::tearDown();
		// This code will run after each test
	}
	function test_uninstall()
	{
		global $plugin, $current_screen;
		//Ensure we're actually installed
		$this->assertNotEquals( false, get_option('llynx_version') );
		$this->assertNotEquals( false, get_option('llynx_options') );
		$this->assertNotEquals( false, get_option('llynx_options_bk') );
		
		$plugin = 'wp_lynx.php';
		
		//We need to trigger is_admin()
		$screen = WP_Screen::get( 'admin_init' );
		$current_screen = $screen;
		
		//No go on and uninstall
		$this->uninstall();
		
		//Ensure we're actually uninstalled
		$this->assertEquals( false, get_option('llynx_version') );
		$this->assertEquals( false, get_option('llynx_options') );
		$this->assertEquals( false, get_option('llynx_options_bk') );
	}
}

