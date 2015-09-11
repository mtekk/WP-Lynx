<?php
/*
Plugin Name: WP Lynx
Plugin URI: http://mtekk.us/code/wp-lynx/
Description: Adds Facebook-esq extended link information to your WordPress pages and posts. For details on how to use this plugin visit <a href="http://mtekk.us/code/wp-lynx/">WP Lynx</a>. 
Version: 1.0.0
Author: John Havlik
Author URI: http://mtekk.us/
License: GPL2
Text Domain: wp_lynx
Domain Path: /languages/
*/
/*  
	Copyright 2010-2014  John Havlik  (email : john.havlik@mtekk.us)

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
//Do a PHP version check, require 5.3 or newer
if(version_compare(PHP_VERSION, '5.3.0', '<'))
{
	//Silently deactivate plugin, keeps admin usable
	deactivate_plugins(plugin_basename(__FILE__), true);
	//Spit out die messages
	wp_die(sprintf(__('Your PHP version is too old, please upgrade to a newer version. Your version is %s, this plugin requires %s', 'wp_lynx'), phpversion(), '5.3.0'));
}
if(!function_exists('mb_strlen'))
{
	require_once(dirname(__FILE__) . '/includes/multibyte_supplicant.php');
}
//Include admin base class
if(!class_exists('mtekk_adminKit'))
{
	require_once(dirname(__FILE__) . '/includes/class.mtekk_adminkit.php');
}
//Include llynxScrape class
if(!class_exists('llynxScrape'))
{
	require_once(dirname(__FILE__) . '/class.llynx_scrape.php');
}
/**
 * The administrative interface class 
 */
class linksLynx
{
	const version = '1.0.0';
	protected $name = 'WP Lynx';
	protected $identifier = 'wp_lynx';
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
					'Scache_type' => 'original',
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
			$this->admin = new llynx_admin($this->opt, $this->plugin_basename, $this->template_tags);
		}
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
	}
	/**
	 * admin initialisation callback function
	 * 
	 * is bound to wpordpress action 'admin_init' on instantiation
	 * 
	 * @return void
	 */
	function init()
	{
		//We're going to make sure we run the parent's version of this function as well
		parent::init();
		$this->llynx_scrape->opt = $this->opt;
		add_action('media_upload_wp_lynx', array($this, 'media_upload'));
		$this->allowed_html = wp_kses_allowed_html('post');
		wp_enqueue_script('llynx_javascript', plugins_url('/wp_lynx.js', dirname(__FILE__) . '/wp_lynx.js'), array('jquery'));
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
		//If we are not in the admin, load up our style (if told to)
		if(!is_admin())
		{
			//Sync our options
			$this->opt = mtekk_adminKit::parse_args(get_option('llynx_options'), $this->opt);
			$this->llynx_scrape->opt = $this->opt;
			//Only print if enabled
			if($this->opt['bglobal_style'])
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
		$this->opt = mtekk_adminKit::parse_args(get_option('llynx_options'), $this->opt);
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
		if((count($this->llynx_scrape->images) < 1 && $this->llynx_scrape->title == '' && count($this->llynx_scrape->text) < 1))
		{
			echo json_encode(array(
				'error' => 'scrape',
				'error_msg' => $this->llynx_scrape->error
				));
			die();
		}
		$uploadDir = wp_upload_dir();
		if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
		{
			$allow_images = false;
		}
		else
		{
			$allow_images = true;
		}
		if(!$allow_images)
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
		//Sync our options
		$this->opt = mtekk_adminKit::parse_args(get_option('llynx_options'), $this->opt);
		$this->llynx_scrape->opt = $this->opt;
		//Grab the nonce and check
		//$nonce = intval($_POST['nonce']);
		//Clean up the URL
		$url = esc_url_raw($_POST['url']);
		//Clean the title
		$title = esc_attr($_POST['title']);
		//Clean the image
		$image = esc_url_raw($_POST['image']);
		//Clean the description
		$description = wp_kses($_POST['description'], wp_kses_allowed_html('post'));
		//Assemble the lynx print and echo
		echo $this->url_insert_handler($url, $title, $description, $image, false);
		die();
	}
//TODO Old junk need to refactor	
	/**
	 * resize_image
	 * 
	 * Resizes the given image
	 * 
	 * @param bitstream $data
	 * @param int $nW
	 * @param int $nH
	 * @return GDImage 
	 */
	function resize_image($data, &$nW, &$nH)
	{
		//Time to resize the image
		$imgRaw = imagecreatefromstring($data);
		//Get the image dimensions and aspect ratio
		$w = imagesx($imgRaw);
		$h = imagesy($imgRaw);
		$r = $w/$h;
		//If we will be cropping the image we need to do some calculations
		if($this->opt['bcache_crop'])
		{
			//If we are wider, hight is more important
			if($w > $h)
			{
				$w = ceil($w - ($w * ($r - $this->opt['acache_max_x'] / $this->opt['acache_max_y'])));
			}
			//If we are taller, width is more important
			else
			{
				$h = ceil($h - ($h * ($r - $this->opt['acache_max_x'] / $this->opt['acache_max_y'])));
			}
			//Out new height and widths are simple as we are cropping
			$nH = $this->opt['acache_max_y'];
			$nW = $this->opt['acache_max_x'];
		}
		//Otherwise we're just resizing
		else
		{
			//If the destination ration is wider than the source we need to adjust accordingly
			if($this->opt['acache_max_x']/$this->opt['acache_max_y'] > $r)
			{
				//We are height limited, maintain aspect ratio
				$nW = $this->opt['acache_max_y'] * $r;
				$nH = $this->opt['acache_max_y'];
			}
			else
			{
				//We are width limited, maintain aspect ratio
				$nW = $this->opt['acache_max_x'];
				$nH = $this->opt['acache_max_x'] / $r;
			}
		}
		//Create the destination image
		$imgThumb = imagecreatetruecolor($nW, $nH);
		//Do the resizing/cropping
		imagecopyresampled($imgThumb, $imgRaw, 0, 0, 0, 0, $nW, $nH, $w, $h);
		//Return the thumbnail
		return $imgThumb;
	}
	/**
	 * url_insert_handler
	 * 
	 * Handles inserting a link print into the current post editor screen
	 * @param string $url
	 * @param string $title
	 * @param string $description
	 * @param string $image
	 * @param bool $no_image
	 * @return string compiled HTML
	 */
	function url_insert_handler($url, $title, $description, $image = NULL, $no_image = false)
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
		else if($values['short_url'] == '')
		{
			$values['short_url'] = $values['url'];
		}
		//Built the image component, if needed
		if(!$no_image && $image !== NULL)
		{
			//Get the upload location
			$uploadDir = wp_upload_dir();
			//Grab the image (raw data), use a referrer to avoid issues with anti-hotlinking scripts
			//If we recieved an error, then we have no image
			if(isset($uploadDir['path']) && $uploadDir['url'] != NULL && $imgData = $this->llynx_scrape->getContent($image, $url))
			{
				//We need to manipulate the url to get the image name
				$imgName = explode('/', $image);
				$imgName = end($imgName);
				$imgParts = explode('.',$imgName);
				$imgName = $imgParts[0];
				if($imgName == '')
				{
					$imgName = 'llynx-site-thumb';
				}
				//The extension should be the stuff after the last '.', make sure its lower case
				$imgExt = strtolower(end($imgParts));
				if($this->llynx_scrape->is_PNG($imgData))
				{
					$imgExt = 'png';
				}
				else if($this->llynx_scrape->is_JPEG($imgData))
				{
					$imgExt = 'jpg';
				}
				else if($this->llynx_scrape->is_GIF($imgData))
				{
					$imgExt = 'gif';
				}
				//Generate the thumbnail
				$nH = 0;
				$nW = 0;
				$imgThumb = $this->resize_image($imgData, $nW, $nH);
				$saved = false;
				//If we will be saving as jpeg
				if($this->opt['Scache_type'] == 'jpeg' || ($this->opt['Scache_type'] == 'original' && ($imgExt == 'jpg' || $imgExt == 'jpeg')))
				{
					//Make sure we use a unique filename
					$fileName = wp_unique_filename($uploadDir['path'], $imgName . '.jpg');
					//Compile our image location and image URL
					$imgLoc = $uploadDir['path'] . '/' . $fileName;
					//Save as JPEG
					$saved = imagejpeg($imgThumb, $imgLoc, $this->opt['acache_quality']);
				}
				//If we will be saving as png
				else if($this->opt['Scache_type'] == 'png' || ($this->opt['Scache_type'] == 'original' && $imgExt == 'png'))
				{
					//Make sure we use a unique filename
					$fileName = wp_unique_filename($uploadDir['path'], $imgName . '.png');
					//Compile our image location and image URL
					$imgLoc = $uploadDir['path'] . '/' . $fileName;
					//Save as PNG
					$saved = imagepng($imgThumb, $imgLoc);
				}
				//If we will be saving as gif
				else
				{
					//Make sure we use a unique filename
					$fileName = wp_unique_filename($uploadDir['path'], $imgName . '.gif');
					//Compile our image location and image URL
					$imgLoc = $uploadDir['path'] . '/' . $fileName;
					//Save as GIF
					$saved = imagegif($imgThumb, $imgLoc);
				}
				$imgURL = $uploadDir['url'] . '/' . $fileName;
				//If the image was saved, we'll allow the image tag to be replaced
				if($saved)
				{
					//Verify we have the correct permissions of new file
					$stat = @stat(dirname($imgLoc));
					$perms = $stat['mode'] & 0007777;
					$perms = $perms & 0000666;
					@chmod($imgLoc, $perms);
					//Assemble the image and link it, if it exists
					$values['image'] = sprintf('<a title="Go to %1$s" href="%2$s"><img alt="%1$s" src="%3$s" width="%4$s" height="%5$s" /></a>', esc_attr($values['title']), $values['short_url'], $imgURL, $nW, $nH);
				}
			}
		}
		//Replace the template tags with values
		return str_replace($this->template_tags, $values, $this->opt['Htemplate']);
	}
}
//Let's make an instance of our object takes care of everything
$linksLynx = new linksLynx;