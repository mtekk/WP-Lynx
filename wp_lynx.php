<?php
/*
Plugin Name: WP Lynx
Plugin URI: http://mtekk.us/code/wp-lynx/
Description: Adds Facebook-esq extended link information to your WordPress pages and posts. For details on how to use this plugin visit <a href="http://mtekk.us/code/wp-lynx/">WP Lynx</a>. 
Version: 0.9.50
Author: John Havlik
Author URI: http://mtekk.us/
License: GPL2
TextDomain: wp_lynx
DomainPath: /languages/
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
	protected $version = '0.9.50';
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
		//If we are emulating the user's browser, we should update our user agent accordingly
		if($this->opt['bcurl_embrowser'])
		{
			$this->llynx_scrape->opt['Scurl_agent'] = $_SERVER['HTTP_USER_AGENT'];
		}
		
		add_action('media_upload_wp_lynx', array($this, 'media_upload'));
		$this->allowed_html = wp_kses_allowed_html('post');
		wp_enqueue_script('llynx_javascript', plugins_url('/wp_lynx.js', dirname(__FILE__) . '/wp_lynx.js'), array('jquery'));
	}
	function wp_init()
	{
		//Register CSS for tabs
		wp_register_style('llynx_style', plugins_url('/wp_lynx_style.css', dirname(__FILE__) . '/wp_lynx_style.css'));
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
		add_action( 'print_media_templates', array( $this, 'print_media_templates' ) );
	}
	function enqueue_scripts()
	{
		//TODO: ensure we load only at the correct times
		wp_enqueue_script('llynx_javascript', plugins_url('/wp_lynx.js', dirname(__FILE__) . '/wp_lynx.js'), array( 'media-views' ));
	}
	/**
	 * Adds a new template for the HelloWorld view.
	 */
	function print_media_templates() {
		?>
<script type="text/html" id="tmpl-llynx-print-add">
<div class="media-embed"><label class="embed-url"><input id="llynx_url" type="text" name="llynx_url" placeholder="Enter URL"><span class="spinner"></span> <button id="llynx_go" name="llynx_go">Get</button></label>
	<div class="embed-link-settings">
		
	</div>
</div>
</script>
<script type="text/html" id="tmpl-llynx-help">
<div class="media-embed llynx-text" style="margin:1em;">
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
</script>
		<?php
	}
	
	
	
//TODO Old junk need to either remove or refactor	
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
		//add_action('wp_lynx_media_upload_header', 'media_upload_header');
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
								<p><textarea name="prints[<?php echo $key; ?>][content]" id="prints<?php echo $key; ?>content" type="text"><?php if(isset($this->llynx_scrape->text[0])){echo $this->llynx_scrape->text[0];}?></textarea>
								</p>
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
}
//Let's make an instance of our object takes care of everything
$linksLynx = new linksLynx;