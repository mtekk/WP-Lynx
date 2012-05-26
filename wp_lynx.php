<?php
/*
Plugin Name: WP Lynx
Plugin URI: http://mtekk.us/code/wp-lynx/
Description: Adds Facebook-esq extended link information to your WordPress pages and posts. For details on how to use this plugin visit <a href="http://mtekk.us/code/wp-lynx/">WP Lynx</a>. 
Version: 0.4.50
Author: John Havlik
Author URI: http://mtekk.us/
License: GPL2
TextDomain: wp_lynx
DomainPath: /languages/
*/
/*  
	Copyright 2010-2011  John Havlik  (email : mtekkmonkey@gmail.com)

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
//Do a PHP version check, require 5.2 or newer
if(version_compare(PHP_VERSION, '5.2.0', '<'))
{
	//Silently deactivate plugin, keeps admin usable
	deactivate_plugins(plugin_basename(__FILE__), true);
	//Spit out die messages
	wp_die(sprintf(__('Your PHP version is too old, please upgrade to a newer version. Your version is %s, this plugin requires %s', 'wp_lynx'), phpversion(), '5.2.0'));
}
if(!function_exists('mb_strlen'))
{
	require_once(dirname(__FILE__) . '/includes/multibyte_supplicant.php');
}
//Include admin base class
if(!class_exists('mtekk_adminKit'))
{
	require_once(dirname(__FILE__) . '/includes/mtekk_adminkit.php');
}
//Include llynxScrape class
if(!class_exists('llynxScrape'))
{
	require_once(dirname(__FILE__) . '/llynx_scrape.php');
}
/**
 * The administrative interface class 
 */
class linksLynx extends mtekk_adminKit
{
	protected $version = '0.4.50';
	protected $full_name = 'WP Lynx Settings';
	protected $short_name = 'WP Lynx';
	protected $access_level = 'manage_options';
	protected $identifier = 'wp_lynx';
	protected $unique_prefix = 'llynx';
	protected $plugin_basename = 'wp-lynx/wp_lynx.php';
	protected $support_url = 'http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-';
	protected $llynx_scrape;
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
					'Scache_type' => 'original',
					'acache_quality' => 80,
					'acache_max_x' => 100,
					'acache_max_y' => 100,
					'bcache_crop' => false,
					'bshort_url' => false,
					'Htemplate' => '<div class="llynx_print">%image%<div class="llynx_text"><a title="Go to %title%" href="%url%">%title%</a><small>%url%</small><span>%description%</span></div></div>',
					'Himage_template' => '',
					'bog_only' => false);
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
	function linksLynx()
	{
		$this->llynx_scrape = new llynxScrape(null);
		//We set the plugin basename here, could manually set it, but this is for demonstration purposes
		$this->plugin_basename = plugin_basename(__FILE__);
		//We're going to make sure we load the parent's constructor
		parent::__construct();
		add_action('init', array($this, 'wp_init'));
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
		//If we are emulating the user's browser, we should update our user agent accordingly
		if($this->opt['bcurl_embrowser'])
		{
			$this->llynx_scrape->opt['Scurl_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		add_action('media_buttons_context', array($this, 'media_buttons_context'));
		add_action('media_upload_wp_lynx', array($this, 'media_upload'));
		add_filter('tiny_mce_before_init', array($this, 'add_editor_style'));
	}
	function wp_init()
	{
		//Register CSS for tabs
		wp_register_style('llynx_style', plugins_url('/wp_lynx_style.css', dirname(__FILE__) . '/wp_lynx_style.css'));
		//If we are not in the admin, load up our style (if told to)
		if(!is_admin())
		{
			//Sync our options
			$this->opt = $this->parse_args(get_option('llynx_options'), $this->opt);
			$this->llynx_scrape->opt = $this->opt;
			//Only print if enabled
			if($this->opt['bglobal_style'])
			{
				wp_enqueue_style('llynx_style');
			}
		}
	}
	/**
	 * security
	 * 
	 * Makes sure the current user can manage options to proceed
	 */
	function security()
	{
		//If the user can not manage options we will die on them
		if(!current_user_can($this->access_level))
		{
			wp_die(__('Insufficient privileges to proceed.', 'wp_lynx'));
		}
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
		if($version !== $this->version)
		{
			//Upgrading from 0.2.x
			if(version_compare($version, '0.3.0', '<'))
			{
				$opts['short_url'] = false;
				$opts['template'] = '<div class="llynx_print">%image%<div class="llynx_text"><a title="Go to %title%" href="%url%">%title%</a><small>%url%</small><span>%description%</span></div></div>';
			}
			//Upgrading from 3.0.x
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
		$context .= sprintf('<a title="%s" href="%s&amp;type=wp_lynx&amp;TB_iframe=true" id="add_link_print" class="thickbox"><img src="%s" alt="%s"/></a>', $title, $url, $imgSrc, $this->short_name);
		return $context;
	}
	/**
	 * media_upload
	 * 
	 * Handles all of the special media iframe stuff
	 * 
	 * @param object $mode [optional]
	 * @return 
	 */
	function media_upload($mode = 'default')
	{
		//We have to manually enqueue all dependency styles as wp doens't do them in the correct order see bug #12415
		wp_enqueue_style('global');
		wp_enqueue_style('wp-admin');
		//The style we're actually after
		wp_enqueue_style('media');
		//We're going to override some WP styles in this
		add_action('admin_head', array($this, 'admin_head_style'));
		//We need this to do the nice sorting and other things
		wp_enqueue_script('admin-gallery');
		wp_enqueue_script('llynx_javascript', plugins_url('/wp_lynx.js', dirname(__FILE__) . '/wp_lynx.js'), array('jquery'));
		add_action('wp_lynx_media_upload_header', 'media_upload_header');
		wp_iframe(array($this, 'url_tab'));
	}
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
	 * @param array $data array of $_POST data
	 * @return string compiled HTML
	 */
	function url_insert_handler($data)
	{
		$values = array('url' => $data['url'],
					'short_url' => '',
					'image' => '',
					'title' => stripslashes($data['title']),
					'description' => stripslashes($data['content']));
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
		if(!isset($data['nothumb']) && $data['img'] !== NULL)
		{
			//Get the upload location
			$uploadDir = wp_upload_dir();
			//Grab the image (raw data), use a referrer to avoid issues with anti-hotlinking scripts
			//If we recieved an error, then we have no image
			if(isset($uploadDir['path']) && $uploadDir['url'] != NULL && $imgData = $this->llynx_scrape->getContent($data['img'], $data['url']))
			{
				//We need to manipulate the url to get the image name
				$imgName = explode('/', $data['img']);
				$imgName = end($imgName);
				$imgExt = explode('.',$imgName);
				//The extension should be the stuff after the last '.', make sure its lower case
				$imgExt = strtolower(end($imgExt));
				//Make sure we use a unique filename
				$fileName = wp_unique_filename($uploadDir['path'], $imgName);
				//Compile our image location and image URL
				$imgLoc = $uploadDir['path'] . "/$fileName";
				$imgURL = $uploadDir['url'] . "/$fileName";
				//Generate the thumbnail
				$nH = 0;
				$nW = 0;
				$imgThumb = $this->resize_image($imgData, $nW, $nH);
				$saved = false;
				//If we will be saving as jpeg
				if($this->opt['Scache_type'] == 'jpeg' || ($this->opt['Scache_type'] == 'original' && ($imgExt == 'jpg' || $imgExt == 'jpeg')))
				{
					//Save as JPEG
					$saved = imagejpeg($imgThumb, $imgLoc, $this->opt['acache_quality']);
				}
				//If we will be saving as png
				else if($this->opt['Scache_type'] == 'png' || ($this->opt['Scache_type'] == 'original' && $imgExt == 'png'))
				{
					//Save as PNG
					$saved = imagepng($imgThumb, $imgLoc);
				}
				//If we will be saving as gif
				else
				{
					//Save as GIF
					$saved = imagegif($imgThumb, $imgLoc);
				}
				//If the image was saved, we'll allow the image tag to be replaced
				if($saved)
				{
					//Verify we have the correct permissions of new file
					$stat = @stat(dirname($imgLoc));
					$perms = $stat['mode'] & 0007777;
					$perms = $perms & 0000666;
					@chmod($imgLoc, $perms);
					//Remove %image% tag from image template, if there is one
					//$this->opt['Himg_template'] = str_replace('%image%', '', $this->opt['Himg_template']);
					//Assemble the image and link it, if it exists
					$values['image'] = sprintf('<a title="Go to %s" href="%s"><img alt="%s" src="%s" width="%s" height="%s" /></a>', stripslashes($data['title']), $values['short_url'], stripslashes($data['title']), $imgURL, $nW, $nH);
				}
			}
		}
		//Replace the template tags with values
		return str_replace($this->template_tags, $values, $this->opt['Htemplate']);
	}
	function url_tab()
	{
		global $post_ID, $temp_ID;
		//We may be in a temporary post, so we can't rely on post_ID
		$curID = (int) (0 == $post_ID) ? $temp_ID : $post_ID;
		//Assemble the link to our special uploader
		$formUrl = 'media-upload.php?post_id=' . $curID;
		//Handle the case when we want to insert only one lynx print
		if(isset($_POST['prints_send']))
		{
			//Grab the keys of llynx send
			$keys = array_keys($_POST['prints_send']);
			//Grab the first, and probably only id
			$key = (int) array_shift($keys);
			//Grab our dirty data badges[$key][post_content]
			$data = $_POST['prints'][$key];
			//Use the WP function to send this html to the editor
			media_send_to_editor($this->url_insert_handler($data));
		}
		//Handle the case when we want to insert all of the lynx prints
		if(isset($_POST['prints_send_all']))
		{
			$html = '';
			//Loop through all of our prints, adding them to our compiled html
			foreach($_POST['prints'] as $data)
			{
				//Grab the compiled data
				$html .= $this->url_insert_handler($data);
			}
			//Use the WP function to send this html to the editor
			media_send_to_editor($html);
		}
		//We want to keep the urls we've grabbed in the get field
		$urlString = '';
		if(isset($_POST['llynx_get_url']['url']))
		{
			$urlString = $_POST['llynx_get_url']['url'];
		}
		?>
		<script type="text/javascript">
		llynx_imgs = new Array();
		llynx_cimgs = new Array();
		llynx_cont = new Array();
		llynx_ccont = new Array();
		<!--
		jQuery(function($){
			var preloaded = $(".media-item.preloaded");
			if ( preloaded.length > 0 ) {
				preloaded.each(function(){prepareMediaItem({id:this.id.replace(/[^0-9]/g, '')},'');});
				updateMediaForm();
			}
		});
		-->
		</script>
		<div id="media-upload-header"><ul id="sidemenu"><li id="tab-type"><a href="<?php echo $formUrl; ?>&amp;type=wp_lynx&amp;TB_iframe=true" <?php if(!isset($_GET['ltab'])){echo 'class="current"';} ?>>Add Lynx Print</a></li><li><a href="<?php echo $formUrl; ?>&amp;type=wp_lynx&amp;TB_iframe=true&amp;ltab=help" <?php if(isset($_GET['ltab']) && $_GET['ltab'] == 'help'){echo 'class="current"';} ?>>Help</a></li></ul></div>
		<?php if(isset($_GET['ltab']) && $_GET['ltab'] == 'help')
		{?>
			<div style="margin:1em;">
			<h3 class="media-title"><?php _e('WP Lynx Help','wp_lynx'); ?></h3>
			<p>
			<?php _e('The Add Lynx Print dialog is simple to use. Just enter the URL to the website or page that you want to link to in to the text area. You can enter more than one link at a time, just place a space, or start a newline between each link. Then press the "Get" button. After the pages have been retrieved you should have something similar to the picture above. The pictures are changeable, just use the arrows to thumb through the available pictures. The same goes for the text field, which you may manually edit or thumb through some preselected paragraphs from the linked site.', 'wp_lynx');?>
			</p>
			<p>
			<?php _e('When you are ready to insert a Link Print, just click the "Insert into Post" button (or the "Insert All" button at the bottom to insert multiple Link Prints simultaneously). If you go to the HTML tab in the editor you\'ll see that WP Lynx generates pure HTML. This gives the user full control over their Lynx Prints.', 'wp_lynx');?>
			</p>
			<p>
			<?php printf(__('If you think you have found a bug, please include your WordPress version and details on how to reporduce the bug when you %sreport the issue%s.', 'wp_lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp_lynx') . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-' . $this->version . '/#respond">', '</a>');?>
			</p>
			</div>
		<?php }
		else
		{?>
		<form action="<?php echo $formUrl; ?>&amp;type=wp_lynx&amp;TB_iframe=true" method="post" id="llynx_get_url" class="media-upload-form type-form validate">
			<?php wp_nonce_field('llynx_get_url');?>
			<h3 class="media-title"><?php _e('Add a Lynx Print','wp_lynx'); ?></h3>
			<div class="media-blank">
				<table class="describe">
					<tbody>
						<tr>
							<th class="label" valign="top" scope="row">
								<span class="alignleft"><?php _e('URL(s):')?></span>
							</th>
							<td class="field">
								<textarea id="llynx_get_url[url]" aria-required="true" name="llynx_get_url[url]" style="height:3.5em;"><?php echo $urlString; ?></textarea>
								<input class="button" type="submit" value="<?php _e('Get','wp_lynx');?>" name="llynx_get_url_button"/>
							</td>
						</tr>
					</tbody>
				</table>	
			</div>
		</form>
		<?php
		  $uploadDir = wp_upload_dir();
		  if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
		  {
			  //Let the user know their directory is not writable
			  $this->message['error'][] = __('WordPress uploads directory is not writable, thumbnails will be disabled.', 'wp_lynx');
			  //Too late to use normal hook, directly display the message
			  $this->message();
		  }
		}
		if(isset($_POST['llynx_get_url']))
		{
			//Get urls if any were sent
			$urls = preg_split('/\s+/',$_POST['llynx_get_url']['url']);
		?>
		<div class="hide-if-no-js" id="sort-buttons">
			<span><?php _e('All Prints:'); ?><a id="showall" href="#" style="display: inline;"><?php _e('Show'); ?></a><a style="display: none;" id="hideall" href="#"> <?php _e('Hide'); ?></a></span>
			<?php _e('Sort Order:'); ?><a id="asc" href="#"><?php _e('Ascending');?></a> | <a id="desc" href="#"><?php _e('Descending'); ?></a> | <a id="clear" href="#"><?php _e('Clear'); ?></a>
		</div>
		<form action="<?php echo $formUrl; ?>&amp;type=wp_lynx&amp;TB_iframe=true" method="post" id="llynx_insert_print" class="media-upload-form type-form validate">
			<?php wp_nonce_field('llynx_insert_print');?>
			<table cellspacing="0" class="widefat">
				<thead>
					<tr>
						<th><?php _e('Website'); ?></th>
						<th class="order-head"><?php _e('Order'); ?></th>
						<th class="actions-head"><?php _e('Actions'); ?></th>
					</tr>
				</thead>
			</table>
			<div id="media-items" class="ui-sortable">
				<?php
				$uploadDir = wp_upload_dir();
				if(!isset($uploadDir['path']) || !is_writable($uploadDir['path']))
				{
					$allow_images = false;
				}
				else
				{
					$allow_images = true;
				}
				foreach($urls as $key => $url)
				{
					//If we recieve a blank URL, skip to next iteration
					if($url == NULL)
					{
						continue;
					}
					//Let's clean up the url before using it
					$url = html_entity_decode(esc_url($url), ENT_COMPAT, 'UTF-8');
					//Let's get some data from that url
					$this->llynx_scrape->scrapeContent($url);
					//If we didn't get anything, throw error, continue on our way
					if(count($this->llynx_scrape->images) < 1 && $this->llynx_scrape->title == '' && count($this->llynx_scrape->text) < 1)
					{
						?>
						<div class="media-item child-of-<?php echo $curID; ?> preloaded" id="media-item-<?php echo $key; ?>">
							<?php printf(__('Error while retrieving %s', 'wp_lynx'), $url);?>
							<blockquote>
								<?php
									if(is_array($this->llynx_scrape->error))
									{
										if(count($this->llynx_scrape->error) > 0)
										{
											var_dump($this->llynx_scrape->error);
										}
										else
										{
											_e('cURL error, please check that you have php5-curl and libcurl installed.', 'wp_lynx');
										}
									}
									else
									{
										echo $this->llynx_scrape->error;
									}?>
							</blockquote>
						</div>
						<?php
						continue;
					}
					if(!$allow_images)
					{
						$this->llynx_scrape->images = array();
					}
					?>
				<div class="media-item child-of-<?php echo $curID; ?> preloaded" id="media-item-<?php echo $key; ?>">
					<input type="hidden" value="image" id="type-of-<?php echo $key; ?>">
					<input type="hidden" value="<?php echo $this->llynx_scrape->images[0]; ?>" id="prints<?php echo $key; ?>img" name="prints[<?php echo $key; ?>][img]">
					<input type="hidden" value="<?php echo $url; ?>" id="prints[<?php echo $key;?>][url]" name="prints[<?php echo $key;?>][url]">
					<a href="#" class="toggle describe-toggle-on"><?php _e('Show'); ?></a>
					<a href="#" class="toggle describe-toggle-off"><?php _e('Hide'); ?></a>
					<div class="menu_order"> <input type="text" value="0" name="prints[<?php echo $key; ?>][menu_order]" id="prints[<?php echo $key; ?>][menu_order]" class="menu_order_input"></div>
					<div class="filename new"><span class="title"><?php echo $this->llynx_scrape->title; ?></span></div>
					<table class="slidetoggle describe startclosed" style="display: none;">
						<thead id="media-head-llynx<?php echo $key; ?>" class="media-item-info">
						<tr valign="top">
							<td id="thumbnail-head-llynx-<?php echo $key; ?>" class="A1B1">
								<p class="llynx_thumb"><img style="margin-top: 3px;" alt="" src="<?php if($allow_images && count($this->llynx_scrape->images) > 0){echo $this->llynx_scrape->images[0];} ?>" class="thumbnail"></p>
									<script type="text/javascript">
										llynx_imgs[<?php echo $key; ?>] = new Array();
										llynx_cimgs[<?php echo $key; ?>] = 0;
									<?php
									//Since our keys are not continuous, we need to keep track of them ourselves
									$kId = 0;
									//Now output all of the images, hide all of them
									if($allow_images && count($this->llynx_scrape->images) > 0)
									{
										foreach($this->llynx_scrape->images as $image)
										{
											?>llynx_imgs[<?php echo $key; ?>][<?php echo $kId; ?>] = '<?php echo $image;?>';<?php
											echo "\n";
											$kId++;
										}
									}
									?></script>
								<p>
									<input type="button" value="&lt;" class="button disabled" disabled="disabled" onclick="prev_thumb(<?php echo $key; ?>)" id="imgprev-btn-<?php echo $key; ?>" />
									<input type="button" value="&gt;" <?php if(!$allow_images || count($this->llynx_scrape->images) <= 1){echo 'disabled="disabled" class="disabled button"';}else{echo 'class="button"';}?> onclick="next_thumb(<?php echo $key; ?>)" id="imgnext-btn-<?php echo $key; ?>" />
									<span id="icount-<?php echo $key; ?>"><?php if(!$allow_images || count($this->llynx_scrape->images) < 1){echo '0';}else{echo '1';}?> / <?php echo count($this->llynx_scrape->images); ?></span>
								</p>
								<p>
									<?php 
									if(count($this->llynx_scrape->images) < 1)
									{
										?><input type="hidden" value="true" id="prints[<?php echo $key; ?>][nothumb]" name="prints[<?php echo $key; ?>][nothumb]" /><?php
									}
									?>
									<input type="checkbox" <?php if(!$allow_images || count($this->llynx_scrape->images) < 1){echo 'checked="checked" disabled="disabled" class="disabled"';}?> onclick="img_toggle(<?php echo $key; ?>)" value="none" id="prints[<?php echo $key; ?>][nothumb]" name="prints[<?php echo $key; ?>][nothumb]" /><label for="prints[<?php echo $key; ?>][nothumb]"><?php _e('No Thumbnail', 'wp_lynx'); ?></label>
								</p>
							</td>
							<td>
								<p><input type="text" aria-required="true" value="<?php echo $this->llynx_scrape->title; ?>" name="prints[<?php echo $key; ?>][title]" id="prints[<?php echo $key; ?>][title]" class="text"><br />
								<small><?php echo $url; ?></small></p>
								<p><textarea name="prints[<?php echo $key; ?>][content]" id="prints<?php echo $key; ?>content" type="text"><?php echo $this->llynx_scrape->text[0];?></textarea></p>
								<p>
									<input type="button" value="&lt;" class="button disabled" disabled="disabled" onclick="prev_content(<?php echo $key; ?>)" id="contprev-btn-<?php echo $key; ?>">
									<input type="button" value="&gt;" <?php if(count($this->llynx_scrape->text) <= 1){echo 'disabled="disabled" class="disabled button"';}else{echo 'class="button"';}?> onclick="next_content(<?php echo $key; ?>)" id="contnext-btn-<?php echo $key; ?>">
									<span id="ccount-<?php echo $key; ?>"><?php if(count($this->llynx_scrape->text) < 1){echo '0';}else{echo '1';}?> / <?php echo count($this->llynx_scrape->text); ?></span>
								</p>
								<script type="text/javascript">
									llynx_cont[<?php echo $key; ?>] = new Array();
									llynx_ccont[<?php echo $key; ?>] = 0;
								<?php
									//Since our keys are not continuous, we need to keep track of them ourselves
									$kId = 0;
									//Now output all of the text, hide all of them
									foreach($this->llynx_scrape->text as $text)
									{
										printf("llynx_cont[%s][%s] = '%s';\n", $key, $kId, addslashes(html_entity_decode($text, ENT_COMPAT, 'UTF-8')));
										$kId++;
									}
								?></script>
							</td>
						</tr>
						</thead>
						<tbody>
						<tr class="submit">
							<td></td>
							<td class="savesend">
								<input type="submit" value="<?php _e('Insert into Post');?>" name="prints_send[<?php echo $key; ?>]" class="button">  <a onclick="document.getElementById('media-items').removeChild(document.getElementById('media-item-<?php echo $key; ?>'));return false;" class="del-link" href="#"><?php _e('Delete'); ?></a>
							</td>
						</tr>
					</tbody>
					</table>
				</div>
				<?php } ?>
			</div>
			<div>
				<p class="ml-submit">
					<input type="submit" value="<?php _e('Insert All'); ?>" id="prints_send_all" name="prints_send_all" class="button">
				</p>
			</div>
		</form><?php
		}
	}
	function admin_head_style()
	{
		?>
<style type="text/css">
/*WP Lynx Admin Styles*/
.describe td{vertical-align:top;}
.describe textarea{height:5em;}
.A1B1{width:128px;float:left;}
.llynx_thumb{height:138px;overflow:hidden;border-bottom:1px solid #dfdfdf;margin-bottom:5px;}
</style>
		<?php
	}
	function help()
	{
		$screen = get_current_screen();
		//Add contextual help on current screen
		if($screen->id == 'settings_page_' . $this->identifier)
		{
			$general_tab = '<p>' . __('Tips for the settings are located below select options.', 'wp_lynx') .
				'</p><h5>' . __('Resources', 'wp_lynx') . '</h5><ul><li>' .
				sprintf(__("%sTutorials and How Tos%s: There are several guides, tutorials, and how tos available on the author's website.", 'wp_lynx'),'<a title="' . __('Go to the WP Lynx tag archive.', 'breadcrumb_navxt') . '" href="http://mtekk.us/archives/tag/wp-lynx">', '</a>') . '</li><li>' .
				sprintf(__('%sOnline Documentation%s: Check out the documentation for more indepth technical information.', 'breadcrumb_navxt'), '<a title="' . __('Go to the WP Lynx online documentation', 'breadcrumb_navxt') . '" href="http://mtekk.us/code/wp-lynx/wp-lynx-doc/">', '</a>') . '</li><li>' .
				sprintf(__('%sReport a Bug%s: If you think you have found a bug, please include your WordPress version and details on how to reproduce the bug.', 'wp_lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp_lynx') . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-' . $this->version . '/#respond">', '</a>') . '</li></ul>' . 
				'<h5>' . __('Giving Back', 'wp_lynx') . '</h5><ul><li>' .
				sprintf(__('%sDonate%s: Love WP Lynx and want to help development? Consider buying the author a beer.', 'wp_lynx'),'<a title="' . __('Go to PayPal to give a donation to WP Lynx.', 'wp_lynx') . '" href="https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=FD5XEU783BR8U&lc=US&item_name=Breadcrumb%20NavXT%20Donation&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donateCC_LG%2egif%3aNonHosted">', '</a>') . '</li><li>' .
				sprintf(__('%sTranslate%s: Is your language not available? Contact John Havlik to get translating.', 'wp_lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp_lynx') . '" href="">', '</a>') . '</li></ul>';
			
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
		//Let's call the parent version of the page, will handle our setting stuff
		parent::admin_page();
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
				<h3><?php _e('General', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_check(__('Open Graph Only Mode', 'wp_lynx'), 'bog_only', __("For sites with Open Graph metadata, only fetch that data.", 'wp_lynx'));
						$this->input_check(__('Shorten URL', 'wp_lynx'), 'bshort_url', __('Shorten URL using a URL shortening service such as tinyurl.com.', 'wp_lynx'));
						$this->input_check(__('Default Style', 'wp_lynx'), 'bglobal_style', __('Enable the default Lynx Prints styling on your blog.', 'wp_lynx'));
						$this->input_text(__('Maximum Image Width', 'wp_lynx'), 'acache_max_x', '10', false, __('Maximum cached image width in pixels.', 'wp_lynx'));
						$this->input_text(__('Maximum Image Height', 'wp_lynx'), 'acache_max_y', '10', false, __('Maximum cached image height in pixels.', 'wp_lynx'));
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
						$this->input_text(__('Cache Image Quality', 'wp_lynx'), 'acache_quality', '10', false, __('Image quality when cached images are saved as JPEG.', 'wp_lynx'));
					?>
				</table>
			</fieldset>
			<fieldset id="images" class="llynx_options">
				<h3><?php _e('Images', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Minimum Image Width', 'wp_lynx'), 'aimg_min_x', '10', false, __('Minimum width of images to scrape in pixels.', 'wp_lynx'));
						$this->input_text(__('Minimum Image Height', 'wp_lynx'), 'aimg_min_y', '10', false, __('Minimum hieght of images to scrape in pixels.', 'wp_lynx'));
						$this->input_text(__('Maximum Image Count', 'wp_lynx'), 'aimg_max_count', '10', false, __('Maximum number of images to scrape.', 'wp_lynx'));
						$this->input_text(__('Maximum Image Scrape Size', 'wp_lynx'), 'aimg_max_range', '10', false, __('Maximum number of bytes to download when determining the dimensions of JPEG images.', 'wp_lynx'));
					?>
				</table>
			</fieldset>
			<fieldset id="text" class="llynx_options">
				<h3><?php _e('Text', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->input_text(__('Minimum Paragraph Length', 'wp_lynx'), 'ap_min_length', '10', false, __('Minimum paragraph length to be scraped (in characters).', 'wp_lynx'));
						$this->input_text(__('Maximum Paragraph Length', 'wp_lynx'), 'ap_max_length', '10', false, __('Maximum paragraph length before it is cutt off (in characters).', 'wp_lynx'));
						$this->input_text(__('Minimum Paragraph Count', 'wp_lynx'), 'ap_max_count', '10', false, __('Maximum number of paragraphs to scrape.', 'wp_lynx'));
					?>
				</table>
			</fieldset>
			<fieldset id="advanced" class="llynx_options">
				<h3><?php _e('Advanced', 'wp_lynx'); ?></h3>
				<table class="form-table">
					<?php
						$this->textbox(__('Lynx Print Template', 'wp_lynx'), 'Htemplate', 3, false, __('Available tags: ', 'wp_lynx') . implode(', ', $this->template_tags));
						$this->input_text(__('Timeout', 'wp_lynx'), 'acurl_timeout', '10', false, __('Maximum time for scrape execution in seconds.', 'wp_lynx'));
						$this->input_text(__('Useragent', 'wp_lynx'), 'Scurl_agent', '32', $this->opt['bcurl_embrowser'], __('Useragent to use during scrape execution.', 'wp_lynx'));
						$this->input_check(__('Emulate Browser', 'wp_lynx'), 'bcurl_embrowser', __("Useragent will be exactly as the users's browser.", 'wp_lynx'));
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
//Let's make an instance of our object takes care of everything
$linksLynx = new linksLynx;