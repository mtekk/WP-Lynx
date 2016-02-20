<?php
/*  Copyright 2015  John Havlik  (email : john.havlik@mtekk.us)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
/**
 * WP Lynx - uninstall script
 *
 * uninstall script based on WordPress Uninstall Plugin API
 */

require_once(dirname(__FILE__) . '/includes/class.mtekk_adminkit_uninstaller.php');

/**
 * WP Lynx uninstaller class
 * 
 */
class llynx_uninstaller extends mtekk_adminKit_uninstaller
{
	protected $unique_prefix = 'llynx';
	protected $plugin_basename = null;
	
	public function __construct()
	{
		$this->plugin_basename = plugin_basename('/wp_lynx.php');
		parent::__construct();
	}
	/**
	 * Options uninstallation function for legacy
	 */
	private function uninstall_legacy()
	{
		delete_option($this->unique_prefix . '_options');
		delete_option($this->unique_prefix . '_options_bk');
		delete_option($this->unique_prefix . '_version');
		delete_site_option($this->unique_prefix . '_options');
		delete_site_option($this->unique_prefix . '_options_bk');
		delete_site_option($this->unique_prefix . '_version');
	}
	/**
	 * uninstall breadcrumb navxt admin plugin
	 * 
	 * @return bool
	 */
	private function uninstall_options()
	{
		if(version_compare(phpversion(), '5.3.0', '<'))
		{
			return $this->uninstall_legacy();
		}
		//Grab our global linksLynx object
		global $linksLynx;
		//Load dependencies if applicable
		if(!class_exists('linksLynx'))
		{
			require_once($this->_get_plugin_path());
		}
		$linksLynx = new linksLynx();
		//Uninstall
		return $linksLynx->uninstall();
	}	
	
	/**
	 * uninstall method
	 * 
	 * @return bool wether or not uninstall did run successfull.
	 */
	public function uninstall()
	{
		//Only bother to do things 
		if($this->is_installed())
		{
			return $this->uninstall_options();
		}	
	}
	
}

/*
 * main
 */
new llynx_uninstaller();