<?php
/*  Copyright 2007-2014  John Havlik  (email : john.havlik@mtekk.us)

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
require_once(dirname(__FILE__) . '/includes/block_direct_access.php');
//Do a PHP version check, require 5.2 or newer
if(version_compare(phpversion(), '5.2.0', '<'))
{
	//Only purpose of this function is to echo out the PHP version error
	function llynx_phpold()
	{
		printf('<div class="error"><p>' . __('Your PHP version is too old, please upgrade to a newer version. Your version is %s, this plugin requires %s', 'llynx') . '</p></div>', phpversion(), '5.2.0');
	}
	//If we are in the admin, let's print a warning then return
	if(is_admin())
	{
		add_action('admin_notices', 'llynx_phpold');
	}
	return;
}
//Include admin base class
if(!class_exists('mtekk_adminKit'))
{
	require_once(dirname(__FILE__) . '/includes/class.mtekk_adminkit.php');
}
/**
 * The administrative interface class 
 * 
 */
class llynx_admin extends mtekk_adminKit
{
	const version = '0.9.50';
	protected $full_name = 'WP Lynx Settings';
	protected $short_name = 'WP Lynx';
	protected $access_level = 'manage_options';
	protected $identifier = 'wp_lynx';
	protected $unique_prefix = 'llynx';
	protected $plugin_basename = null;
	protected $support_url = 'http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-';
	protected $template_tags = null;
	/**
	 * Administrative interface class default constructor
	 * @param bcn_breadcrumb_trail $breadcrumb_trail a breadcrumb trail object
	 * @param string $basename The basename of the plugin
	 */
	function __construct($opts, $basename, $template_tags)
	{
		$this->plugin_basename = $basename;
		//Grab default options that were passed in
		$this->opt = $opts;
		$this->template_tags = $template_tags;
		//We're going to make sure we load the parent's constructor
		parent::__construct();
	}
	/**
	 * admin initialization callback function
	 * 
	 * is bound to wpordpress action 'admin_init' on instantiation
	 * 
	 * @since  3.2.0
	 * @return void
	 */
	function init()
	{
		//Add tiny mce style
		add_filter('tiny_mce_before_init', array($this, 'add_editor_style'));
		//Add in our media button
		add_action('media_buttons_context', array($this, 'media_buttons_context'));
		//We're going to make sure we run the parent's version of this function as well
		parent::init();
	}
	function wp_loaded()
	{
		parent::wp_loaded();
	}
	/**
	 * Makes sure the current user can manage options to proceed
	 */
	function security()
	{
		//If the user can not manage options we will die on them
		if(!current_user_can($this->access_level))
		{
			wp_die(__('Insufficient privileges to proceed.', 'wp-lynx'));
		}
	}
	/**
	 * Adds a style to tiny mce for Link Prints
	 */
	function add_editor_style($init)
	{
		//build out style link, needs to be http accessible
		$style = plugins_url('/wp_lynx_style.css', dirname(__FILE__) . '/wp_lynx_style.css');
		if(array_key_exists('content_css',$init))
		{
			$init['content_css'] .= ',' . $style;
		}
		else
		{
			$init['content_css'] = $style;
		}
		return $init;
	}
	/**
	 * media_buttons_context
	 * 
	 * Adds a nice link button next to the Upload/Insert buttons in the edit pannel
	 * 
	 * @return 
	 */
	function media_buttons_context($context)
	{
		global $post_ID, $temp_ID;
		//We may be in a temporary post, so we can't rely on post_ID
		$curID = (int) (0 == $post_ID) ? $temp_ID : $post_ID;
		//Assemble the link to our special uploader
		$url = 'media-upload.php?post_id=' . $curID;
		//Find the url for the image, use nice functions
		$imgSrc = plugins_url('wp-lynx/llynx.png');
		//The hyperlink title
		$title = __('Add a Lynx Print', 'wp_lynx');
		//Append our link to the current context
		//%s&amp;type=wp_lynx&amp;TB_iframe=true
		$context .= sprintf('<a title="%s" href="#" id="add_link_print" class="button"><img src="%s" alt="%s"/> Add Lynx Print</a>', $title, $imgSrc, $this->short_name);
		return $context;
	}
	/**
	 * Upgrades input options array, sets to $this->opt
	 * 
	 * @param array $opts
	 * @param string $version the version of the passed in options
	 */
	function opts_upgrade($opts, $version)
	{
		//If our version is not the same as in the db, time to update
		if(version_compare($version, $this::version, '<'))
		{
			//Upgrading from 0.2.x
			if(version_compare($version, '0.3.0', '<'))
			{
				$opts['short_url'] = false;
				$opts['template'] = '<div class="llynx_print">%image%<div class="llynx_text"><a title="Go to %title%" href="%url%">%title%</a><small>%url%</small><span>%description%</span></div></div>';
			}
			//Upgrading from 0.4.x
			if(version_compare($version, '0.4.0', '<'))
			{
				$old = $opts;
				//Only migrate if we haven't migrated yet
				if(isset($old['global_style']))
				{
					$opts = array(
						'bglobal_style' => $old['global_style'],
						'ap_max_count' => $old['p_max_count'],
						'ap_min_length' => $old['p_min_length'],
						'ap_max_length' => $old['p_max_length'],
						'aimg_max_count' => $old['img_max_count'],
						'aimg_min_x' => $old['img_min_x'], 
						'aimg_min_y' => $old['img_min_y'],
						'aimg_max_range' => $old['img_max_range'],
						'Scurl_agent' => $old['curl_agent'],
						'bcurl_embrowser' => $old['curl_embrowser'],
						'acurl_timeout' => $old['curl_timeout'],
						'Scache_type' => $old['cache_type'],
						'acache_quality' => $old['cache_quality'],
						'acache_max_x' => $old['cache_max_x'],
						'acache_max_y' => $old['cache_max_y'],
						'bcache_crop' => $old['cache_crop'],
						'bshort_url' => $old['short_url'],
						'Htemplate' => $old['template'],
						'Himage_template' => $old['image_template']
						);
				}
			}
			//Save the passed in opts to the object's option array
			$this->opt = $opts;
		}
	}
	/**
	 * help action hook function
	 * 
	 * @return string
	 * 
	 */
	function help()
	{
		$screen = get_current_screen();
		//Add contextual help on current screen
		if($screen->id == 'settings_page_' . $this->identifier)
		{
			$general_tab = '<p>' . __('Tips for the settings are located below select options.', 'wp_lynx') .
				'</p><h5>' . __('Resources', 'wp_lynx') . '</h5><ul><li>' .
				sprintf(__("%sTutorials and How Tos%s: There are several guides, tutorials, and how tos available on the author's website.", 'wp_lynx'),'<a title="' . __('Go to the WP Lynx tag archive.', 'wp_lynx') . '" href="http://mtekk.us/archives/tag/wp-lynx">', '</a>') . '</li><li>' .
				sprintf(__('%sOnline Documentation%s: Check out the documentation for more indepth technical information.', 'wp_lynx'), '<a title="' . __('Go to the WP Lynx online documentation', 'wp_lynx') . '" href="http://mtekk.us/code/wp-lynx/wp-lynx-doc/">', '</a>') . '</li><li>' .
				sprintf(__('%sReport a Bug%s: If you think you have found a bug, please include your WordPress version and details on how to reproduce the bug.', 'wp_lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp_lynx') . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-' . $this->version . '/#respond">', '</a>') . '</li></ul>' . 
				'<h5>' . __('Giving Back', 'wp_lynx') . '</h5><ul><li>' .
				sprintf(__('%sDonate%s: Love WP Lynx and want to help development? Consider buying the author a beer.', 'wp_lynx'),'<a title="' . __('Go to PayPal to give a donation to WP Lynx.', 'wp_lynx') . '" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=FD5XEU783BR8U&lc=US&item_name=WP%20Lynx%20Donation&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted">', '</a>') . '</li><li>' .
				sprintf(__('%sTranslate%s: Is your language not available? Contact John Havlik to get translating.', 'wp_lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp_lynx') . '" href="http://translate.mtekk.us">', '</a>') . '</li></ul>';
			
			$screen->add_help_tab(
				array(
				'id' => $this->identifier . '-base',
				'title' => __('General', 'wp_lynx'),
				'content' => $general_tab
				));
			$quickstart_tab = '<p>' . __('Using WP Lynx is quite simple. Just start writing a new post (or edit an existing post) and click on the paw print next to the WordPress add media icon. After clicking the paw print button, the Add Lynx Print dialog will popup in a lightbox, just like the add media lightbox.', 'wp_lynx') . '</p><p>' . 
			__('The Add Lynx Print dialog is simple to use. Just enter the URL to the website or page that you want to link to in to the text area. You can enter more than one link at a time, just place a space, or start a newline between each link. Then press the "Get" button. After the pages have been retrieved you should have something similar to the picture above. The pictures are changeable, just use the arrows to thumb through the available pictures. The same goes for the text field, which you may manually edit or thumb through some preselected paragraphs from the linked site.', 'wp_lynx') . '</p><p>' .
			__('When you are ready to insert a Link Print, just click the "Insert into Post" button (or the "Insert All" button at the bottom to insert multiple Link Prints simultaneously). If you go to the HTML tab in the editor you\'ll see that WP Lynx generates pure HTML. This gives the user full control over their Lynx Prints.', 'wp_lynx') . '</p>';
			$screen->add_help_tab(
				array(
				'id' => $this->identifier . '-quick-start',
				'title' => __('Quick Start', 'wp_lynx'),
				'content' => $quickstart_tab
				));
			$styling_tab = '<p>' . __('Using the default lynx print template, the following CSS can be used as base for styling your lynx prints.', 'wp_lynx') . '</p>' .
				'<pre><code>.llynx_print
{
	margin:10px;
	padding:5px;
	display:block;
	float:left;
	border:1px solid #999;
}
.llynx_print img
{
	padding:5px;
	border:none;
	max-width:20%;
	float:left;
}
.llynx_text
{
	float:right;
	width:70%;
}
.llynx_text a
{
	text-decoration:none;
	font-weight:bolder;
	font-size:1em;
	float:left;
	width:100%;
}
.llynx_text small
{
	padding:3px 0;
	float:left;
	width:100%;
}
.llynx_text span
{
	float:left;
	width:100%;
}</code></pre>';
			$screen->add_help_tab(
				array(
				'id' => $this->identifier . '-styling',
				'title' => __('Styling', 'wp_lynx'),
				'content' => $styling_tab
				));
			$screen->add_help_tab(
				array(
				'id' => $this->identifier . '-import-export-reset',
				'title' => __('Import/Export/Reset', 'wp_lynx'),
				'content' => $this->import_form()
				));
		}	
	}
	/**
	 * enqueue's the tab style sheet on the settings page
	 */
	function admin_styles()
	{
		wp_enqueue_style('mtekk_adminkit_tabs');
	}
	/**
	 * enqueue's the tab js and translation js on the settings page
	 */
	function admin_scripts()
	{
		//Enqueue ui-tabs
		wp_enqueue_script('jquery-ui-tabs');
		//Enqueue the admin tabs javascript
		wp_enqueue_script('mtekk_adminkit_tabs');
		//Load the translations for the tabs
		wp_localize_script('mtekk_adminkit_tabs', 'objectL10n', array(
			'mtad_uid' => 'llynx',
			'mtad_import' => __('Import', $this->identifier),
			'mtad_export' => __('Export', $this->identifier),
			'mtad_reset' => __('Reset', $this->identifier),
		));
	}
	/**
	 * admin_page
	 * 
	 * The administrative page for Links Lynx
	 * 
	 */
	function admin_page()
	{
		global $wp_taxonomies;
		$this->security();
		$uploadDir = wp_upload_dir();
		if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
		{
			//Let the user know their directory is not writable
			$this->message['error'][] = __('WordPress uploads directory is not writable, thumbnails will be disabled.', 'wp_lynx');
			//Too late to use normal hook, directly display the message
			$this->message();
		}
		?>
		<div class="wrap"><h2><?php _e('WP Lynx Settings', 'wp_lynx'); ?></h2>
		<?php
		//We exit after the version check if there is an action the user needs to take before saving settings
		if(!$this->version_check(get_option($this->unique_prefix . '_version')))
		{
			return;
		}
		?>	
		<form action="options-general.php?page=wp_lynx" method="post" id="llynx-options">
			<?php settings_fields('llynx_options');?>
			<div id="hasadmintabs">
			<fieldset id="general" class="llynx_options">
				<h3 class="tab-title" title="<?php _e('A collection of settings most likely to be modified are located under this tab.', 'wp_lynx');?>"><?php _e('General', 'wp_lynx'); ?></h3>
				<h3><?php _e('General', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_check(__('Open Graph Only Mode', 'wp_lynx'), 'bog_only', __("For sites with Open Graph metadata, only fetch that data.", 'wp_lynx'));
						$this->input_check(__('Shorten URL', 'wp_lynx'), 'bshort_url', __('Shorten URL using a URL shortening service such as tinyurl.com.', 'wp_lynx'));
						$this->input_check(__('Default Style', 'wp_lynx'), 'bglobal_style', __('Enable the default Lynx Prints styling on your blog.', 'wp_lynx'));
					?>
				</table>
			</fieldset>
			<fieldset id="content" class="llynx_options">
				<h3 class="tab-title" title="<?php _e('Settings related to how Lynx Prints will display.', 'wp_lynx');?>"><?php _e('Content', 'wp_lynx'); ?></h3>
				<h3><?php _e('Content', 'wp_lynx'); ?></h3>
				<table class="form-table">
				<?php
					$this->textbox(__('Lynx Print Template', 'wp_lynx'), 'Htemplate', 3, false, __('Available tags: ', 'wp_lynx') . implode(', ', $this->template_tags));
				?>
				</table>
				<h3><?php _e('Images', 'wp_lynx'); ?></h3>
				<table class="form-table">
				<?php
					$this->input_text(__('Maximum Image Width', 'wp_lynx'), 'acache_max_x', 'small-text', false, __('Maximum cached image width in pixels.', 'wp_lynx'));
					$this->input_text(__('Maximum Image Height', 'wp_lynx'), 'acache_max_y', 'small-text', false, __('Maximum cached image height in pixels.', 'wp_lynx'));
					$this->input_check(__('Crop Image', 'wp_lynx'), 'bcache_crop', __('Crop images in the cache to the above dimensions.', 'wp_lynx'));
				?>
					<tr valign="top">
						<th scope="row">
							<?php _e('Cached Image Format', 'wp_lynx'); ?>
						</th>
						<td>
							<?php
								$this->input_radio('Scache_type', 'original', __('Same as source format', 'wp_lynx'));
								$this->input_radio('Scache_type', 'png', __('PNG'));
								$this->input_radio('Scache_type', 'jpeg', __('JPEG'));
								$this->input_radio('Scache_type', 'gif', __('GIF'));
							?>
							<span class="setting-description"><?php _e('The image format to use in the local image cache.', 'wp_lynx'); ?></span>
						</td>
					</tr>
					<?php
						$this->input_text(__('Cache Image Quality', 'wp_lynx'), 'acache_quality', 'small-text', false, __('Image quality when cached images are saved as JPEG.', 'wp_lynx'));
					?>
				</table>
			</fieldset>
			<fieldset id="advanced" class="llynx_options">
				<h3 class="tab-title" title="<?php _e('Advanced settings for the WP Lynx content scraping engine.', 'wp_lynx');?>"><?php _e('Advanced', 'wp_lynx'); ?></h3>
				<h3><?php _e('Advanced', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Timeout', 'wp_lynx'), 'acurl_timeout', 'small-text', false, __('Maximum time for scrape execution in seconds.', 'wp_lynx'));
						$this->input_text(__('Max Redirects', 'wp_lynx'), 'acurl_max_redirects', 'small-text', false, __('Maximum number of redirects to follow while scraping a URL.', 'wp_lynx'));						
						$this->input_text(__('Useragent', 'wp_lynx'), 'Scurl_agent', 'large-text', $this->opt['bcurl_embrowser'], __('Useragent to use during scrape execution.', 'wp_lynx'));
						$this->input_check(__('Emulate Browser', 'wp_lynx'), 'bcurl_embrowser', __("Useragent will be exactly as the users's browser.", 'wp_lynx'));
					?>
				</table>
				<h3><?php _e('Thumbnails', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_check(__('Enable Website Thumbnails', 'wp_lynx'), 'bwthumbs_enable', __('Enable generation of Website Thumbails via a 3rd party provider (snapito.com)', 'wp_lynx'));
						$this->input_text(__('API Key', 'wp_lynx'), 'swthumbs_key', 'large-text', false, __('Your API key for the 3rd party thumbnail provider (snapito.com)', 'wp_lynx'));
					?>
				</table>
				<h3><?php _e('Images', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Minimum Image Width', 'wp_lynx'), 'aimg_min_x', 'small-text', false, __('Minimum width of images to scrape in pixels.', 'wp_lynx'));
						$this->input_text(__('Minimum Image Height', 'wp_lynx'), 'aimg_min_y', 'small-text', false, __('Minimum hieght of images to scrape in pixels.', 'wp_lynx'));
						$this->input_text(__('Maximum Image Count', 'wp_lynx'), 'aimg_max_count', 'small-text', false, __('Maximum number of images to scrape.', 'wp_lynx'));
						$this->input_text(__('Maximum Image Scrape Size', 'wp_lynx'), 'aimg_max_range', 'small-text', false, __('Maximum number of bytes to download when determining the dimensions of JPEG images.', 'wp_lynx'));
					?>
				</table>
				<h3><?php _e('Text', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Minimum Paragraph Length', 'wp_lynx'), 'ap_min_length', 'small-text', false, __('Minimum paragraph length to be scraped (in characters).', 'wp_lynx'));
						$this->input_text(__('Maximum Paragraph Length', 'wp_lynx'), 'ap_max_length', 'small-text', false, __('Maximum paragraph length before it is cutt off (in characters).', 'wp_lynx'));
						$this->input_text(__('Minimum Paragraph Count', 'wp_lynx'), 'ap_max_count', 'small-text', false, __('Maximum number of paragraphs to scrape.', 'wp_lynx'));
					?>
				</table>
			</fieldset>
			</div>
			<p class="submit"><input type="submit" class="button-primary" name="llynx_admin_options" value="<?php esc_attr_e('Save Changes') ?>" /></p>
		</form>
		</div>
		<?php
	}
}