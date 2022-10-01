<?php
/*
Plugin Name: WP Lynx
Plugin URI: http://mtekk.us/code/wp-lynx/
Description: Adds Facebook-esq extended link information to your WordPress pages and posts. For details on how to use this plugin visit <a href="http://mtekk.us/code/wp-lynx/">WP Lynx</a>. 
Version: 1.2.99
Author: John Havlik
Author URI: http://mtekk.us/
License: GPL2
Text Domain: wp-lynx
Domain Path: /languages/
*/
/*  
	Copyright 2010-2021  John Havlik  (email : john.havlik@mtekk.us)

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
//Do a PHP version check, require 5.6 or newer
//Do a PHP version check, require 5.6 or newer
if(version_compare(phpversion(), '5.6.0', '<'))
{
	//Only purpose of this function is to echo out the PHP version error
	function llynx_phpold()
	{
		printf('<div class="notice notice-error"><p>' . esc_html__('Your PHP version is too old, please upgrade to a newer version. Your version is %1$s, WP Lynx requires %2$s', 'wp-lynx') . '</p></div>', phpversion(), '5.6.0');
	}
	//If we are in the admin, let's print a warning then return
	if(is_admin())
	{
		add_action('admin_notices', 'llynx_phpold');
	}
	return;
}
if(!function_exists('mb_strlen'))
{
	require_once(dirname(__FILE__) . '/includes/multibyte_supplicant.php');
}
//Include admin base class
if(!class_exists('\mtekk\adminKit\adminKit'))
{
	require_once(dirname(__FILE__) . '/includes/adminKit/class-mtekk_adminkit.php');
}
//Include llynxScrape class
if(!class_exists('llynxScrape'))
{
	require_once(dirname(__FILE__) . '/class.llynx_scrape.php');
}
//Include pdf_helpers class
if(!class_exists('pdf_helpers'))
{
	require_once(dirname(__FILE__) . '/class.pdf_helpers.php');
}
use mtekk\adminKit\{adminKit, setting};
/**
 * The administrative interface class 
 */
class linksLynx
{
	const version = '1.2.99';
	protected $name = 'WP Lynx';
	protected $identifier = 'wp-lynx';
	protected $unique_prefix = 'llynx';
	protected $plugin_basename = null;
	protected $admin = null;
	protected $llynx_scrape = null;
	protected $opt = array(
					'bglobal_style' => true,
					'ap_max_count' => 5,
					'ap_min_length' => 120,
					'ap_max_length' => 180,
					'aimg_max_count' => 20,
					'aimg_min_x' => 50, 
					'aimg_min_y' => 50,
					'aimg_max_range' => 256,
					'Scurl_agent' => 'WP Links Bot',
					'bcurl_embrowser' => false,
					'acurl_timeout' => 3,
					'acurl_max_redirects' => 3,
					'Ecache_type' => 'original',
					'acache_quality' => 80,
					'acache_max_x' => 100,
					'acache_max_y' => 100,
					'bcache_crop' => false,
					'bshort_url' => false,
					'Htemplate' => '<div class="llynx_print">%image%<div class="llynx_text"><a title="Go to %title%" href="%url%">%title%</a><small>%url%</small><span>%description%</span></div></div>',
					'Himage_template' => '',
					'bog_only' => false,
					'bwthumbs_enable' => false,
					'swthumbs_key' => ''
					);
	protected $settings = array();
	protected $template_tags = array(
					'%url%',
					'%short_url%',
					'%image%',
					'%title%',
					'%description%'
					);
	/**
	 * linksLynx
	 * 
	 * Class default constructor
	 */
	function __construct()
	{
		$this->llynx_scrape = new llynxScrape(null);
		//We set the plugin basename here, could manually set it, but this is for demonstration purposes
		$this->plugin_basename = plugin_basename(__FILE__);
		add_action('init', array($this, 'wp_init'));
		//Load our main admin if in the dashboard
		if(is_admin())
		{
			require_once(dirname(__FILE__) . '/class.llynx_admin.php');
			//Instantiate our new admin object
			$this->admin = new llynx_admin($this->opt, $this->plugin_basename, $this->template_tags, $this->settings);
		}
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
	}
	function wp_init()
	{
		if(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG)
		{
			$suffix = '';
		}
		else
		{
			$suffix = '.min';
		}
		//Register our styles and scripts, pick the correct one depending on if we have script debug enabled
		wp_register_style('llynx_style', plugins_url('/wp_lynx_style' . $suffix . '.css', dirname(__FILE__) . '/wp_lynx_style' . $suffix . '.css'));
		wp_register_style('llynx_media', plugins_url('/llynx_media' . $suffix . '.css', dirname(__FILE__) . '/llynx_media' . $suffix . '.css'));
		wp_register_script('llynx_javascript', plugins_url('/wp_lynx' . $suffix . '.js', dirname(__FILE__) . '/wp_lynx' . $suffix . '.js'), array( 'media-views' ), $this::version, true);
		linksLynx::setup_setting_defaults($this->settings);
		//If we are not in the admin, load up our style (if told to)
		if(!is_admin())
		{
			$this->get_settings();
			//Only print if enabled
			if($this->settings['bglobal_style']->get_value())
			{
				wp_enqueue_style('llynx_style');
			}
		}
		add_action('print_media_templates', array($this, 'print_media_templates'));
		add_action('wp_ajax_wp_lynx_fetch_url', array($this, 'fetch_url'));
		add_action('wp_ajax_wp_lynx_fetch_print', array($this, 'fetch_print'));
	}
	/**
	 * Checks if the current resource is in the dashboard and a post*.php page
	 */
	function is_admin_edit()
	{
		global $pagenow;
		if(!is_admin())
		{
			return false;
		}
		if($pagenow == 'post-new.php' || $pagenow == 'post.php')
		{
			return true;
		}
		return false;
	}
	/**
	 * Enqueue our scripts
	 */
	function enqueue_scripts()
	{
		//Only do things if this is an "edit" opage
		if($this->is_admin_edit())
		{
			wp_enqueue_script('llynx_javascript');
			wp_localize_script('llynx_javascript', 'llynx_l10n', array(
				'insertSuccessMsg' => __('Lynx Print inserted into post successfully', 'wp-lynx')
				));
			wp_enqueue_style('llynx_media');
			//Load the translations for the tabs
			wp_localize_script('llynx_javascript', 'objectL10n', array(
				'wp_lynx_request_error_msg' => __('It appears your server timed out while processing this request.', 'wp-lynx')
			));
		}
	}
	/**
	 * Adds a new template for the HelloWorld view.
	 */
	function print_media_templates()
	{
		include_once('template.llynx_views.php');
	}
	function fetch_url()
	{
		//Sync our options
		$this->opt = adminKit::parse_args(get_option('llynx_options'), $this->opt);
		//If we are emulating the user's browser, we should update our user agent accordingly
		if($this->opt['bcurl_embrowser'])
		{
			$this->llynx_scrape->opt['Scurl_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		$this->llynx_scrape->opt = $this->opt;
		error_reporting(E_ALL);
		//Grab the nonce and check
		//$nonce = intval($_POST['nonce']);
		//Clean up the URL
		$url = esc_url_raw($_POST['url']);
		//Don't bother to scrape if the URL isn't valid
		if($url == null)
		{
			echo json_encode(array(
				'error' => 'url',
				'error_msg' => __('Invalid URL', 'wp-lynx')
				));
			die();
		}
		$this->llynx_scrape->scrapeContent($url);
		//Check if llynx_scrape found errors
		if(count($this->llynx_scrape->images) < 1 && $this->llynx_scrape->title == '' && count($this->llynx_scrape->text) < 1)
		{
			echo json_encode(array(
				'error' => 'scrape',
				'error_msg' => $this->llynx_scrape->error
				));
			die();
		}
		$uploadDir = wp_upload_dir();
		//If the upload directory isn't set or writeable, we can't upload thumbnails, so disable them
		if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
		{
			$this->llynx_scrape->images = array();
		}
		else if($this->opt['bwthumbs_enable'])
		{
			//Backup our array of images
			$temp_array = $this->llynx_scrape->images;
			//Clear our array of images
			$this->llynx_scrape->images = array();
			//Keep the #0 slot the same (respect open graph)
			$this->llynx_scrape->images[0] = array_shift($temp_array);
			//Splice in the screen cap of the site
			$this->llynx_scrape->images[1] = sprintf('http://api.snapito.com/web/%s/mc?url=%s', $this->opt['swthumbs_key'], $url);
			//Place the rest at the end
			$this->llynx_scrape->images = array_merge($this->llynx_scrape->images, $temp_array);
		}
		//Echo out our results
		echo $this->json_encode($url);
		//Nothing left to do but die
		die();
	}
	function json_encode($url)
	{
		$descriptions = array();
		foreach($this->llynx_scrape->text as $text)
		{
			$descriptions[] = html_entity_decode($text, ENT_COMPAT, 'UTF-8');
		}
		return json_encode(array(
			'title' => $this->llynx_scrape->title,
			'url' => $url,
			'descriptions' => $descriptions,
			'images' => $this->llynx_scrape->images
			));
	}
	function fetch_print()
	{
		$this->get_settings();
		//Grab the nonce and check
		//$nonce = intval($_POST['nonce']);
		//Clean up the URL
		$url = esc_url_raw($_POST['url']);
		//Clean the title
		$title = esc_attr($_POST['title']);
		//Clean the image
		$is_pdf = pdf_helpers::is_image_data($_POST['image']);
		$image = esc_url_raw($_POST['image']);
		//Clean the description
		$description = wp_kses($_POST['description'], wp_kses_allowed_html('post'));
		//Assemble the lynx print and echo
		echo $this->url_insert_handler($url, $title, $description, $image, false, $is_pdf);
		die();
	}
	/**
	 * Handles saving the image thumbnails
	 * 
	 * @param resource $thumbnail The thumbnail image to save, may be GD or Imagick resource
	 * @param string $name The name of the image to save
	 * @param string $extension The image thumbnail extensions
	 * @param string $directory The directory to save in
	 * @param string &$file_name The passed by reference file name for the saved image
	 * 
	 * @return bool whether or not the image thumbnail was sucessfully saved
	 */
	function save_thumbnail($thumbnail, $name, $extension, $directory, &$file_name)
	{
		//PDFs are special
		if($extension == 'pdf')
		{
			//Make sure we use a unique filename
			$file_name = wp_unique_filename($directory['path'], $name . '.jpg');
			//Compile our image location and image URL
			$save_location = $directory['path'] . '/' . $file_name;
			return $thumbnail->writeImage($save_location);
		}
		//If we will be saving as jpeg
		else if($this->settings['Ecache_type']->get_value() == 'jpeg' || ($this->settings['Ecache_type']->get_value() == 'original' && ($extension == 'jpg' || $extension == 'jpeg')))
		{
			//Make sure we use a unique filename
			$file_name = wp_unique_filename($directory['path'], $name . '.jpg');
			//Compile our image location and image URL
			$save_location = $directory['path'] . '/' . $file_name;
			//Save as JPEG
			return imagejpeg($thumbnail, $save_location, $this->opt['acache_quality']);
		}
		//If we will be saving as png
		else if($this->settings['Ecache_type']->get_value() == 'png' || ($this->settings['Ecache_type']->get_value() == 'original' && $extension == 'png'))
		{
			//Make sure we use a unique filename
			$file_name = wp_unique_filename($directory['path'], $name . '.png');
			//Compile our image location and image URL
			$save_location = $directory['path'] . '/' . $file_name;
			//Save as PNG
			return imagepng($thumbnail, $save_location);
		}
		//If we will be saving as gif
		else
		{
			//Make sure we use a unique filename
			$file_name = wp_unique_filename($directory['path'], $name . '.gif');
			//Compile our image location and image URL
			$save_location = $directory['path'] . '/' . $file_name;
			//Save as GIF
			return imagegif($thumbnail, $save_location);
		}
	}
	/**
	 * Extracts the image extension from the available data
	 * 
	 * @param resource $data The image data
	 * @param string $url The URL for the image
	 * 
	 * @return array An associative array with the name and extension of the image
	 */
	function get_image_name($data, $url)
	{
		//We need to manipulate the url to get the image name
		$image_name = explode('/', $url);
		$image_name = end($image_name);
		$image_parts = explode('.', $image_name);
		$image_name = $image_parts[0];
		if($image_name == '')
		{
			$image_name = 'llynx-site-thumb';
		}
		//The extension should be the stuff after the last '.', make sure its lower case
		$extension = strtolower(end($image_parts));
		if($data instanceof Imagick)
		{
			$extension = 'pdf';
		}
		else if($this->llynx_scrape->is_PNG($data))
		{
			$extension = 'png';
		}
		else if($this->llynx_scrape->is_JPEG($data))
		{
			$extension = 'jpg';
		}
		else if($this->llynx_scrape->is_GIF($data))
		{
			$extension = 'gif';
		}
		return array('name' => $image_name, 'extension' => $extension);
	}
	/**
	 * Calculates new height and width numbers based off of the settings for thumbnail scaling/cropping
	 * 
	 * @param int &$width The original width of the image
	 * @param int &$height The original height of the image
	 * @param int &$new_width The scaled width of the image
	 * @param int &$new_height The scaled height of the image
	 */
	function get_scaled_dimensions(&$width, &$height, &$new_width, &$new_height)
	{
		$aspect_ratio = $width/$height;
		//If we will be cropping the image we need to do some calculations
		if($this->settings['bcache_crop']->get_value())
		{
			//If we are wider, hight is more important
			if($width > $height)
			{
				$width = ceil($width - ($width * ($aspect_ratio - $this->settings['acache_max_x']->get_value() / $this->settings['acache_max_y']->get_value())));
			}
			//If we are taller, width is more important
			else
			{
				$height = ceil($height - ($height * ($aspect_ratio - $this->settings['acache_max_x']->get_value() / $this->settings['acache_max_y']->get_value())));
			}
			//Out new height and widths are simple as we are cropping
			$new_height = $this->settings['acache_max_y']->get_value();
			$new_width = $this->settings['acache_max_x']->get_value();
		}
		//Otherwise we're just resizing
		else
		{
			//If the destination ration is wider than the source we need to adjust accordingly
			if($this->settings['acache_max_x']->get_value() / $this->settings['acache_max_y']->get_value() > $aspect_ratio)
			{
				//We are height limited, maintain aspect ratio
				$new_width = $this->settings['acache_max_y']->get_value() * $aspect_ratio;
				$new_height = $this->settings['acache_max_y']->get_value();
			}
			else
			{
				//We are width limited, maintain aspect ratio
				$new_width = $this->settings['acache_max_x']->get_value();
				$new_height = $this->settings['acache_max_x']->get_value() / $aspect_ratio;
			}
		}
	}
	/**
	 * Resizes and possibly cropps the given image
	 * 
	 * @param bitstream $data The raw bistream of the image to resize
	 * @param int &$new_width The new width of the resized image
	 * @param int &$new_height The new height of the resized image
	 * @return GDImage The scaled/cropped image
	 */
	function resize_image($data, &$new_width, &$new_height)
	{
		//Time to resize the image
		$imgRaw = imagecreatefromstring($data);
		//Get the image dimensions and aspect ratio
		$width = imagesx($imgRaw);
		$height = imagesy($imgRaw);
		$this->get_scaled_dimensions($width, $height, $new_width, $new_height);
		//Create the destination image
		$imgThumb = imagecreatetruecolor($new_width, $new_height);
		//Do the resizing/cropping
		imagecopyresampled($imgThumb, $imgRaw, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
		//Return the thumbnail
		return $imgThumb;
	}
//TODO Old junk need to refactor
	/**
	 * url_insert_handler
	 * 
	 * Handles inserting a link print into the current post editor screen
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param string $image
	 * @param bool $no_image
	 * @param bool $is_pdf
	 * @return string compiled HTML
	 */
	function url_insert_handler($url, $title, $description, $image_source_url = NULL, $no_image = false, $is_pdf = false)
	{
		$values = array('url' => $url,
					'short_url' => '',
					'image' => '',
					'title' => stripslashes($title),
					'description' => stripslashes($description));
		//If the user has enabled short_urls
		if($this->opt['bshort_url'])
		{
			//Assemble our request URL, in the future more short url services may be supported
			$url = 'http://tinyurl.com/api-create.php?url=' . urlencode($data['url']);
			//Use WordPress HTTP API for the request
			$response = wp_remote_get($url);
			//If we didn't get an error, replace the short_url value with what we found
			if(!is_wp_error($response))
			{
				//For tinyurl, it will just be the response body
				$values['short_url'] = esc_url($response['body']);
			}
		}
		//Make sure short_url is never blank
		if($values['short_url'] == '')
		{
			$values['short_url'] = $values['url'];
		}
		//Build the image component, if needed
		if(!$no_image && ($image_source_url !== NULL || $is_pdf))
		{
			//Get the upload location
			$uploadDir = wp_upload_dir();
			//Grab the image (raw data), use a referrer to avoid issues with anti-hotlinking scripts
			//If we recieved an error, then we have no image
			if(isset($uploadDir['path']) && $uploadDir['url'] != NULL)
			{
				$imgData = false;
				$new_height = 0;
				$new_width = 0;
				//If we have a PDF we have some special work to perform
				if($is_pdf)
				{
					$image_name = explode('/', $url);
					$image_name = end($image_name);
					if($content = $this->llynx_scrape->getContent($url))
					{
						$imgData = pdf_helpers::create_pdf_image($content, $image_name, $this->opt['acache_quality']);
						//Still need to get the dimensions so we have the scaled data
						$width = $imgData->getImageWidth();
						$height = $imgData->getImageHeight();
						$this->get_scaled_dimensions($width, $height, $new_width, $new_height);
						//Crop the thumbnail if we need to, otherwise just scale
						if($this->opt['bcache_crop'])
						{
							$imgData->cropThumbnailImage($this->opt['acache_max_x'], $this->opt['acache_max_y']);
						}
						else
						{
							$imgData->thumbnailImage($new_width, $new_height, false);
						}
						$imgThumb = $imgData;
					}
				}
				//All other images are ehandled as before
				else
				{
					$imgData = $this->llynx_scrape->getContent($image_source_url, $url);
					//Resize the image
					$imgThumb = $this->resize_image($imgData, $new_width, $new_height);
				}
				if($imgData !== false)
				{
					//Get the image name and extension from the image data and URL
					$image = $this->get_image_name($imgData, $image_source_url);
					//If the image was saved, we'll allow the image tag to be replaced
					if($this->save_thumbnail($imgThumb, $image['name'], $image['extension'], $uploadDir, $fileName))
					{
						$imgURL = $uploadDir['url'] . '/' . $fileName;
						//Verify we have the correct permissions of new file
						$stat = @stat(dirname($imgLoc));
						if(is_array($stat) && isset($stat['mode']))
						{
							$perms = $stat['mode'] & 0007777;
							//Should only be writable by owner, read by owner, group, and everyone
							$perms = $perms & 0000644;
							@chmod($imgLoc, $perms);
						}
						//Assemble the image and link it, if it exists
						$values['image'] = sprintf('<a title="Go to %1$s" href="%2$s"><img alt="%1$s" src="%3$s" width="%4$s" height="%5$s" /></a>', esc_attr($values['title']), $values['short_url'], $imgURL, $new_width, $new_height);
					}
				}
			}
		}
		//Replace the template tags with values
		return str_replace($this->template_tags, $values, $this->opt['Htemplate']);
	}
	public function uninstall()
	{
		$this->admin->uninstall();
	}
	static function setup_setting_defaults(array &$settings)
	{
		//Hook for letting other plugins add in their default settings (has to go first to prevent other from overriding base settings)
		$settings = apply_filters('llynx_settings_init', $settings);
		//Now on to our settings
		$settings['bglobal_style'] = new setting\setting_bool(
				'global_style',
				true,
				__('Default Style', 'wp-lynx'));
		$settings['bshort_url'] = new setting\setting_bool(
				'short_url',
				false,
				__('Shorten URL', 'wp-lynx'));
		$settings['bog_only'] = new setting\setting_bool(
				'og_only',
				false,
				__('Open Graph Only Mode', 'wp-lynx'));
		$settings['Htemplate'] = new setting\setting_html(
				'template',
				'<div class="llynx_print">%image%<div class="llynx_text"><a title="Go to %title%" href="%url%">%title%</a><small>%url%</small><span>%description%</span></div></div>',
				__('Lynx Print Template', 'wp-lynx'));
		$settings['acache_max_x'] = new setting\setting_absint(
				'cache_max_x',
				100,
				__('Maximum Image Width', 'wp-lynx'));
		$settings['acache_max_y'] = new setting\setting_absint(
				'cache_max_y',
				100,
				__('Maximum Image Height', 'wp-lynx'));
		$settings['acache_quality'] = new setting\setting_absint(
				'cache_quality',
				80,
				__('Cache Image Quality', 'wp-lynx'));
		$settings['bcache_crop'] = new setting\setting_bool(
				'cache_crop',
				false,
				__('Crop Image', 'wp-lynx'));
		$settings['Ecache_type'] = new setting\setting_enum(
				'cache_type',
				'original',
				__('Cached Image Format', 'wp-lynx'),
				false,
				false,
				array('original', 'png', 'jpeg', 'gif'));
		$settings['acurl_timeout'] = new setting\setting_absint(
				'curl_timeout',
				3,
				__('Timeout', 'wp-lynx'));
		$settings['acurl_max_redirects'] = new setting\setting_absint(
				'curl_max_redirects',
				3,
				__('Max Redirects', 'wp-lynx'));
		$settings['bcurl_embrowser'] = new setting\setting_bool(
				'curl_embrowser',
				false,
				__('Emulate Browser', 'wp-lynx'));
		$settings['Scurl_agent'] = new setting\setting_string(
				'curl_agent',
				'WP Links Bot',
				__('Useragent', 'wp-lynx'));
		$settings['bcurl_embrowser'] = new setting\setting_bool(
				'curl_embrowser',
				false,
				__('Emulate Browser', 'wp-lynx'));
		$settings['swthumbs_key'] = new setting\setting_string(
				'wthumbs_key',
				'',
				__('API Key', 'wp-lynx'),
				true);
		$settings['bwthumbs_enable'] = new setting\setting_bool(
				'wthumbs_enable',
				false,
				__('Enable Website Thumbnails', 'wp-lynx'));
		$settings['aimg_min_x'] = new setting\setting_absint(
				'img_min_x',
				50,
				__('Minimum Image Width', 'wp-lynx'));
		$settings['aimg_min_y'] = new setting\setting_absint(
				'img_min_y',
				50,
				__('Minimum Image Height', 'wp-lynx'));
		$settings['aimg_max_count'] = new setting\setting_absint(
				'img_max_count',
				20,
				__('Maximum Image Count', 'wp-lynx'));
		$settings['aimg_max_range'] = new setting\setting_absint(
				'img_max_range',
				256,
				__('Maximum Image Scrape Size', 'wp-lynx'));
		$settings['ap_min_length'] = new setting\setting_absint(
				'p_min_length',
				120,
				__('Minimum Paragraph Length', 'wp-lynx'));
		$settings['ap_max_length'] = new setting\setting_absint(
				'p_max_length',
				180,
				__('Maximum Paragraph Length', 'wp-lynx'));
		$settings['ap_max_count'] = new setting\setting_absint(
				'p_max_count',
				5,
				__('Minimum Paragraph Count', 'wp-lynx'));
	}
	/**
	 * Function updates the breadcrumb_trail options array from the database in a semi intellegent manner
	 *
	 * @since  1.3.0
	 */
	private function get_settings()
	{
		//Convert our settings to opts
		$opts = adminKit::settings_to_opts($this->settings);
		//Grab the current settings for the current local site from the db
		$this->llynx_scrape->opt = adminKit::parse_args(get_option('llynx_options'), $opts);
		//FIXME: Temporary until we move to saving as settings instead of opts in adminKit 3
		foreach($this->llynx_scrape->opt as $key => $value)
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
	}
}
//Let's make an instance of our object takes care of everything
$linksLynx = new linksLynx;