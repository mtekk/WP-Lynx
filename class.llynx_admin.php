<?php
/*  
	Copyright 2007-2022  John Havlik  (email : john.havlik@mtekk.us)

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
//Do a PHP version check, require 5.6 or newer
if(version_compare(phpversion(), '5.6.0', '<'))
{
	//Only purpose of this function is to echo out the PHP version error
	function llynx_phpold()
	{
		printf('<div class="notice notice-error"><p>' . __('Your PHP version is too old, please upgrade to a newer version. Your version is %s, this plugin requires %s', 'wp-lynx') . '</p></div>', phpversion(), '5.6.0');
	}
	//If we are in the admin, let's print a warning then return
	if(is_admin())
	{
		add_action('admin_notices', 'llynx_phpold');
	}
	return;
}
//Include admin base class
if(!class_exists('\mtekk\adminKit\adminKit'))
{
	require_once(dirname(__FILE__) . '/includes/adminKit/class-mtekk_adminkit.php');
}
use mtekk\adminKit\{adminKit, form, message, setting};
/**
 * The administrative interface class 
 * 
 */
class llynx_admin extends adminKit
{
	const version = '1.3.0';
	protected $full_name = 'WP Lynx Settings';
	protected $short_name = 'WP Lynx';
	protected $access_level = 'manage_options';
	protected $identifier = 'wp-lynx';
	protected $unique_prefix = 'llynx';
	protected $plugin_basename = null;
	protected $support_url = 'http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-';
	protected $template_tags = null;
	/**
	 * Administrative interface class default constructor
	 * @param bcn_breadcrumb_trail $breadcrumb_trail a breadcrumb trail object
	 * @param string $basename The basename of the plugin
	 */
	function __construct(array &$opts, $basename, $template_tags, array &$settings)
	{
		$this->plugin_basename = $basename;
		//Grab default options that were passed in
		$this->settings =& $settings;
		$this->opt =& $opts;
		$this->template_tags = $template_tags;
		add_action('admin_print_styles-post.php', array($this, 'admin_edit_page_styles'));
		add_action('admin_print_styles-post-new.php', array($this, 'admin_edit_page_styles'));
		//We're going to make sure we load the parent's constructor
		parent::__construct();
	}
	/**
	 * Loads opts array values into the local settings array
	 *
	 * @param array $opts The opts array
	 */
	function setting_merge($opts)
	{
		$unknown = array();
		foreach($opts as $key => $value)
		{
			if(isset($this->settings[$key]) && $this->settings[$key] instanceof setting\setting)
			{
				$this->settings[$key]->set_value($this->settings[$key]->validate($value));
			}
			else if(isset($this->settings[$key]) && is_array($this->settings[$key]) && is_array($value))
			{
				foreach($value as $subkey => $subvalue)
				{
					if(isset($this->settings[$key][$subkey]) && $this->settings[$key][$subkey]instanceof setting\setting)
					{
						$this->settings[$key][$subkey]->set_value($this->settings[$key][$subkey]->validate($subvalue));
					}
				}
			}
			else
			{
				$unknown[] = $key;
			}
		}
		//Add a message if we found some unknown settings while merging
		if(count($unknown) > 0)
		{
			$this->messages[] = new message(
					sprintf(__('Found %u unknown legacy settings: %s','wp-lynx'), count($unknown), implode(', ', $unknown)),
					'warning',
					true,
					'llyn_unkonwn_legacy_settings');
		}
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
		add_action('media_buttons', array($this, 'media_buttons'));
		//We're going to make sure we run the parent's version of this function as well
		parent::init();
		$this->setting_merge($this->opt);
	}
	function admin_edit_page_styles()
	{
		//Find the url for the image, use nice functions
		$imgSrc = plugins_url('wp-lynx/llynx.png');
		printf('<style type="text/css" media="screen">
		.wp-lynx-media-button {
			background: url(%s) 0 3px no-repeat;
			background-size: 12px 12px;
		}
		</style>', $imgSrc);
	}
	function wp_loaded()
	{
		parent::wp_loaded();
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
	 * media_buttons
	 * 
	 * Adds a nice link button next to the Upload/Insert buttons in the edit pannel
	 * 
	 */
	function media_buttons($context)
	{
		printf('<button class="button add_lynx_print" data-editor="%1$s" type="button"><span class="wp-lynx-media-button wp-media-buttons-icon"></span>%2$s</button>', $context, __('Insert Lynx Print', 'wp-lynx'));
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
			//Upgrading to 1.0.0 
			if(version_compare($version, '1.0.0', '<'))
			{
				$opts['acurl_max_redirects'] = 3;
			}
		}
		//Save the passed in opts to the object's option array
		$this->opt = $opts;
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
			$general_tab = '<p>' . __('Tips for the settings are located below select options.', 'wp-lynx') .
				'</p><h5>' . __('Resources', 'wp-lynx') . '</h5><ul><li>' .
				sprintf(__("%sTutorials and How Tos%s: There are several guides, tutorials, and how tos available on the author's website.", 'wp-lynx'),'<a title="' . __('Go to the WP Lynx tag archive.', 'wp-lynx') . '" href="http://mtekk.us/archives/tag/wp-lynx">', '</a>') . '</li><li>' .
				sprintf(__('%sOnline Documentation%s: Check out the documentation for more indepth technical information.', 'wp-lynx'), '<a title="' . __('Go to the WP Lynx online documentation', 'wp-lynx') . '" href="http://mtekk.us/code/wp-lynx/wp-lynx-doc/">', '</a>') . '</li><li>' .
				sprintf(__('%sReport a Bug%s: If you think you have found a bug, please include your WordPress version and details on how to reproduce the bug.', 'wp-lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp-lynx') . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-' . linksLynx::version . '/#respond">', '</a>') . '</li></ul>' . 
				'<h5>' . __('Giving Back', 'wp-lynx') . '</h5><ul><li>' .
				sprintf(__('%sDonate%s: Love WP Lynx and want to help development? Consider buying the author a beer.', 'wp-lynx'),'<a title="' . __('Go to PayPal to give a donation to WP Lynx.', 'wp-lynx') . '" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=FD5XEU783BR8U&lc=US&item_name=WP%20Lynx%20Donation&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted">', '</a>') . '</li><li>' .
				sprintf(__('%sTranslate%s: Is your language not available? Contact John Havlik to get translating.', 'wp-lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp-lynx') . '" href="http://translate.mtekk.us">', '</a>') . '</li></ul>';
			
			$screen->add_help_tab(
				array(
				'id' => $this->identifier . '-base',
				'title' => __('General', 'wp-lynx'),
				'content' => $general_tab
				));
			$quickstart_tab = '<p>' . __('Using WP Lynx is quite simple. Just start writing a new post (or edit an existing post) and click on the paw print next to the WordPress add media icon. After clicking the paw print button, the Add Lynx Print dialog will popup in a lightbox, just like the add media lightbox.', 'wp-lynx') . '</p><p>' . 
			__('The Add Lynx Print dialog is simple to use. Just enter the URL to the website or page that you want to link to in to the text area. You can enter more than one link at a time, just place a space, or start a newline between each link. Then press the "Get" button. After the pages have been retrieved you should have something similar to the picture above. The pictures are changeable, just use the arrows to thumb through the available pictures. The same goes for the text field, which you may manually edit or thumb through some preselected paragraphs from the linked site.', 'wp-lynx') . '</p><p>' .
			__('When you are ready to insert a Link Print, just click the "Insert into Post" button (or the "Insert All" button at the bottom to insert multiple Link Prints simultaneously). If you go to the HTML tab in the editor you\'ll see that WP Lynx generates pure HTML. This gives the user full control over their Lynx Prints.', 'wp-lynx') . '</p>';
			$screen->add_help_tab(
				array(
				'id' => $this->identifier . '-quick-start',
				'title' => __('Quick Start', 'wp-lynx'),
				'content' => $quickstart_tab
				));
			$styling_tab = '<p>' . __('Using the default lynx print template, the following CSS can be used as base for styling your lynx prints.', 'wp-lynx') . '</p>' .
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
				'title' => __('Styling', 'wp-lynx'),
				'content' => $styling_tab
				));
			$screen->add_help_tab(
				array(
				'id' => $this->identifier . '-import-export-reset',
				'title' => __('Import/Export/Reset', 'wp-lynx'),
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
			'mtad_import' => __('Import', 'wp-lynx'),
			'mtad_export' => __('Export', 'wp-lynx'),
			'mtad_reset' => __('Reset', 'wp-lynx'),
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
		do_action($this->unique_prefix . '_settings_pre_messages', $this->opt);
		//Display our messages
		$this->messages();
		$uploadDir = wp_upload_dir();
		if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
		{
			//Let the user know their directory is not writable
			$this->message['error'][] = __('WordPress uploads directory is not writable, thumbnails will be disabled.', 'wp-lynx');
			//Too late to use normal hook, directly display the message
			$this->message();
		}
		?>
		<div class="wrap"><h2><?php _e('WP Lynx Settings', 'wp-lynx'); ?></h2>
		<?php
		//We exit after the version check if there is an action the user needs to take before saving settings
		if(!$this->version_check(get_option($this->unique_prefix . '_version')))
		{
			return;
		}
		?>	
		<form action="<?php echo $this->admin_url(); ?>" method="post" id="llynx-options">
			<?php settings_fields('llynx_options');?>
			<div id="hasadmintabs">
			<fieldset id="general" class="llynx_options">
				<legend class="screen-reader-text" data-title="<?php _e('A collection of settings most likely to be modified are located under this tab.', 'wp-lynx');?>"><?php _e('General', 'wp-lynx'); ?></legend>
				<h3><?php _e('General', 'wp-lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->form->input_check($this->settings['bog_only'], __("For sites with Open Graph metadata, only fetch that data.", 'wp-lynx'));
						$this->form->input_check($this->settings['bshort_url'], __('Shorten URL using a URL shortening service such as tinyurl.com.', 'wp-lynx'));
						$this->form->input_check($this->settings['bglobal_style'], __('Enable the default Lynx Prints styling on your blog.', 'wp-lynx'));
					?>
				</table>
			</fieldset>
			<fieldset id="content" class="llynx_options">
				<legend class="screen-reader-text" data-title="<?php _e('Settings related to how Lynx Prints will display.', 'wp-lynx');?>"><?php _e('Content', 'wp-lynx'); ?></legend>
				<h3><?php _e('Content', 'wp-lynx'); ?></h3>
				<table class="form-table">
				<?php
					$this->form->textbox($this->settings['Htemplate'], 3, false, __('Available tags: ', 'wp-lynx') . implode(', ', $this->template_tags));
				?>
				</table>
				<h3><?php _e('Images', 'wp-lynx'); ?></h3>
				<table class="form-table">
				<?php
					$this->form->input_text($this->settings['acache_max_x'], 'small-text', false, __('Maximum cached image width in pixels.', 'wp-lynx'));
					$this->form->input_text($this->settings['acache_max_y'], 'small-text', false, __('Maximum cached image height in pixels.', 'wp-lynx'));
					$this->form->input_check($this->settings['bcache_crop'], __('Crop images in the cache to the above dimensions.', 'wp-lynx'));
				?>
					<tr valign="top">
						<th scope="row">
							<?php _e('Cached Image Format', 'wp-lynx'); ?>
						</th>
						<td>
							<?php
								$this->form->input_radio($this->settings['Scache_type'], 'original', __('Same as source format', 'wp-lynx'));
								$this->form->input_radio($this->settings['Scache_type'], 'png', __('PNG'));
								$this->form->input_radio($this->settings['Scache_type'], 'jpeg', __('JPEG'));
								$this->form->input_radio($this->settings['Scache_type'], 'gif', __('GIF'));
							?>
							<span class="setting-description"><?php _e('The image format to use in the local image cache.', 'wp-lynx'); ?></span>
						</td>
					</tr>
					<?php
					$this->form->input_text($this->settings['acache_quality'], 'small-text', false, __('Image quality when cached images are saved as JPEG.', 'wp-lynx'));
					?>
				</table>
			</fieldset>
			<fieldset id="advanced" class="llynx_options">
				<legend class="screen-reader-text" data-title="<?php _e('Advanced settings for the WP Lynx content scraping engine.', 'wp-lynx');?>"><?php _e('Advanced', 'wp-lynx'); ?></legend>
				<h3><?php _e('Advanced', 'wp-lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->form->input_text($this->settings['acurl_timeout'], 'small-text', false, __('Maximum time for scrape execution in seconds.', 'wp-lynx'));
						$this->form->input_text($this->settings['acurl_max_redirects'], 'small-text', false, __('Maximum number of redirects to follow while scraping a URL.', 'wp-lynx'));						
						$this->form->input_text($this->settings['Scurl_agent'], 'large-text', $this->opt['bcurl_embrowser'], __('Useragent to use during scrape execution.', 'wp-lynx'));
						$this->form->input_check($this->settings['bcurl_embrowser'], __("Useragent will be exactly as the users's browser.", 'wp-lynx'));
					?>
				</table>
				<h3><?php _e('Thumbnails', 'wp-lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->form->input_check($this->settings['bwthumbs_enable'], __('Enable generation of Website Thumbails via a 3rd party provider (snapito.com)', 'wp-lynx'));
						$this->form->input_text($this->settings['swthumbs_key'], 'large-text', false, __('Your API key for the 3rd party thumbnail provider (snapito.com)', 'wp-lynx'));
					?>
				</table>
				<h3><?php _e('Images', 'wp-lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->form->input_text($this->settings['aimg_min_x'], 'small-text', false, __('Minimum width of images to scrape in pixels.', 'wp-lynx'));
						$this->form->input_text($this->settings['aimg_min_y'], 'small-text', false, __('Minimum hieght of images to scrape in pixels.', 'wp-lynx'));
						$this->form->input_text($this->settings['aimg_max_count'], 'small-text', false, __('Maximum number of images to scrape.', 'wp-lynx'));
						$this->form->input_text($this->settings['aimg_max_range'], 'small-text', false, __('Maximum number of bytes to download when determining the dimensions of JPEG images.', 'wp-lynx'));
					?>
				</table>
				<h3><?php _e('Text', 'wp-lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->form->input_text($this->settings['ap_min_length'], 'small-text', false, __('Minimum paragraph length to be scraped (in characters).', 'wp-lynx'));
						$this->form->input_text($this->settings['ap_max_length'], 'small-text', false, __('Maximum paragraph length before it is cutt off (in characters).', 'wp-lynx'));
						$this->form->input_text($this->settings['ap_max_count'], 'small-text', false, __('Maximum number of paragraphs to scrape.', 'wp-lynx'));
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