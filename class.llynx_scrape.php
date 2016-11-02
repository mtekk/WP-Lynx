<?php
/*  
	Copyright 2010-2016  John Havlik  (email : john.havlik@mtekk.us)

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
//Include pdf_helpers class
if(!class_exists('pdf_helpers'))
{
	require_once(dirname(__FILE__) . '/class.pdf_helpers.php');
}
class llynxScrape
{
	const version = '1.1.2';
	public $opt = array(
		'ap_max_count' => 5,
		'ap_min_length' => 120,
		'ap_max_length' => 180,
		'aimg_max_count' => 20,
		'aimg_min_x' => 50, 
		'aimg_min_y' => 50,
		'aimg_max_range' => 256,
		'Scurl_agent' => 'WP Links Bot',
		'acurl_timeout' => 2,
		'bog_only' => false,
		'acurl_max_redirects' => 3
	);
	public $error = array();
	public $images = array();
	public $title = '';
	public $text = array();
	function __construct($url = null)
	{
		if($url !== null)
		{
			//Get our content
			$this->scrapeContent($url);
		}
	}
	function getContent($url, $referer = null, $range = null, $encoding = '')
	{
		if(function_exists('curl_init'))
		{
			$curlOpt = array(
				CURLOPT_RETURNTRANSFER	=> true,		// Return web page
				CURLOPT_HEADER			=> false,		// Don't return headers
				CURLOPT_FOLLOWLOCATION	=> !ini_get('safe_mode'),		// Follow redirects, if not in safemode
				CURLOPT_ENCODING		=> $encoding,			// Handle all encodings
				CURLOPT_USERAGENT		=> $this->opt['Scurl_agent'],		// Useragent
				CURLOPT_AUTOREFERER		=> true,		// Set referer on redirect
				CURLOPT_FAILONERROR		=> true,		// Fail silently on HTTP error
				CURLOPT_CONNECTTIMEOUT	=> $this->opt['acurl_timeout'],	// Timeout on connect
				CURLOPT_TIMEOUT			=> $this->opt['acurl_timeout'],	// Timeout on response
				CURLOPT_MAXREDIRS		=> $this->opt['acurl_max_redirects'],	// Stop after x redirects
				CURLOPT_SSL_VERIFYHOST	=> 0            // Don't verify ssl
			);
			//Conditionally set range, if passed in
			if($range !== null)
			{
				$curlOpt[CURLOPT_RANGE] = $range;
			}
			//Conditionally set referer, if passed in
			if($referer !== null)
			{
				$curlOpt[CURLOPT_REFERER] = $referer;
			}
			//Instantiate a CURL context
			$context = curl_init($url);
			//Set our options
			curl_setopt_array($context, $curlOpt); 
			//Get our content from CURL
			$content = curl_exec($context);
			//Get any errors from CURL
			$this->error = curl_error($context);
			//Close the CURL context
			curl_close($context);
			//Deal with CURL errors
			if(empty($content))
			{
				return false;
			}
			return $content;
		}
	}
	function scrapeContent($url)
	{
		//Get our content
		if($content = $this->getContent($url))
		{
			//Reset images and text variables
			$this->images = array();
			$this->text = array();
			//If this is PDF we must do other things
			if($this->is_PDF($content))
			{
				//TODO: eventually we could use a 3rd party library to attempt to extract the title and maybe some text
				$this->title = '';
				$this->text = array('');
				$this->images[] = pdf_helpers::pdf_image_preview($content);
			}
			else
			{
				//Convert to UTF-8
				$content =  mb_convert_encoding($content, "UTF-8", $this->findEncoding($content));
				//Strip any script tags
				$content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', ' ', $content);
				$content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', ' ', $content);
				//Find our Open Graph content
				$this->findOGtags($content);
				if(!$this->opt['bog_only'] || (count($this->images) < 1 && count($this->text) < 1))
				{
					//Extract images on the page
					$this->findImages($content, $url);
					//Extract a few paragraphs from the page
					$this->findText($content);
				}
				//Extract the page title
				$this->findTitle($content);
			}
		}
	}
	function parseTag($tag, $content)
	{
		//Match all tags, yeah we're a little greedy, but we won't get any javascript tags
		preg_match_all('~<' . $tag . '\s+([^>]+)>~i', $content, $matches);
		$raw = array();
		//We want the width, height, and src
		foreach($matches[1] as $str)
		{
			preg_match_all('~([a-z]([a-z0-9]*)?)=("|\')(.*?)("|\')~is', $str, $pairs);
			if(count($pairs[1]) > 0)
			{
				$raw[] = array_combine($pairs[1],$pairs[4]);
			}
		}
		return $raw;
	}

	function findOGtags($content)
	{
		$ogKeyMap = array('image' => 'og:image', 'text' => 'og:description');
		$meta = $this->parseTag('meta', $content);
		//Loop around the meta tags we grabbed
		foreach($meta as $entry)
		{
			//If the meta tag has the property keyword, keep digging
			if(isset($entry['property']))
			{
				//Loop around the known keys
				foreach($ogKeyMap as $key=>$ogKey)
				{
					//If we have a match, do something
					if($entry['property'] === $ogKey)
					{
						//Deal with the site image
						if($key === 'image')
						{
							$this->images[] = esc_url($entry['content']);
						}
						//Deal with the site description
						else if($key === 'text')
						{
							$this->text[] = strip_tags($entry['content']);
						}
						//No need to keep looping
						break;
					}
				}
			}
		}
	}
	function findEncoding($content)
	{
		//Find the charset meta attribute
		preg_match_all('~charset\=.*?(\'|\"|\s)~i', $content, $matches);
		//Trim out everything we don't need
		$matches = preg_replace('/(charset|\=|\'|\"|\s)/', '', $matches[0]);
		//Return the charset in uppercase so that mb_convert_encoding can work it's magic
		if(!isset($matches[0]) || strtoupper($matches[0]) == '')
		{
			return 'auto';
		}
		else
		{
			return strtoupper($matches[0]);
		}
	}
	function findImages($content, $baseURL)
	{
		//Pars the image tags
		$rawImages = $this->parseTag('img', $content);
		foreach($rawImages as $image)
		{
			//If we've gotten our specified fill, exit early
			if(count($this->images) >= $this->opt['aimg_max_count'])
			{
				return null;
			}
			//If someone did the invalid thing of having no src for an img tag, ignore that tag
			if(!isset($image['src']))
			{
				continue;
			}
			if(isset($image['style']) && strpos('width', $image['style']) !== false)
			{
				preg_match_all('~(width|height):(.*?)px~is', $image['style'], $pairs);
				$tempDims = array_combine($pairs[1], $pairs[2]);
				if($tempDims['height'])
				{
					$image['width'] = ltrim($tempDims['width']);
					$image['height'] = ltrim($tempDims['height']);
				}
			}
			//Make sure we fix any relative URLs
			$fixedURL = $this->urlFix($baseURL, $image['src']);
			$size = array('0','0');
			//Find the extension
			$extension = explode('.', $image['src']);
			//The extension should be the stuff after the last '.', make sure its lower case
			$extension = strtolower(end($extension));
			//If the HTML told us the height, great, ignore for gif as it may be used in a html/CSS hack
			if(isset($image['width']) && isset($image['height']) && $image['width'] > 0 && $image['height'] > 0 && $extension != 'gif')
			{
				$size[0] = $image['width'];
				$size[1] = $image['height'];
			}
			//If not, let's try to find it manually
			else
			{	
				$range = '0-';
				//If it is PNG we can transfer less
				if($extension == 'png')
				{
					//Only need 24Bytes for x and y info in png
					$range .= '24';
				}
				//If it is GIF we need even less data
				else if($extension == 'gif')
				{
					//Only need 10Bytes for x and y info in gif
					$range .= '10';
				}
				//Otherwise try just grabbing a good sized chunk
				else
				{
					//Need to get to a frame header for JPEG, default is 256 as all cleaned up JPEGS need this at max
					$range .= $this->opt['aimg_max_range'];
				}
				//We only want appropriately sized images, have to specify encoding of identity to prevent poorly configured servers from sending double compressed images
				if($data = $this->getContent($fixedURL, $baseURL, $range, 'identity'))
				{
					if(strlen($data) >= 10 && $tempSize = $this->getGIFImageXY($data))
					{
						$size = $tempSize;
					}
					else if(strlen($data) >= 24 && $tempSize = $this->getPNGImageXY($data))
					{
						$size = $tempSize;
					}
					else if($tempSize = $this->getJPEGImageXY($data))
					{
						$size = $tempSize;
					}
				}
				else
				{
					//We don't know the size, skip this image
					continue;
				}
			}
			//Check the sizes
			if($fixedURL && $size[0] >= $this->opt['aimg_min_x'] && $size[1] >= $this->opt['aimg_min_y'])
			{
				$this->images[] = esc_url($fixedURL);
			}
		}
	}
	function findText($content, $tag = 'p')
	{
		//Match all p tags, not super greedy now
		$expression = sprintf('/<%s.*?<\/%s>+/si', $tag, $tag);
		preg_match_all($expression, $content, $media);
		$stuff = $media[0];
		$i = 0;
		//For each paragraph we want to strip HTML tags
		foreach($stuff as $paragraph)
		{
			//Remove excess whitespace up here, otherwise we will get bad results
			if($i < $this->opt['ap_max_count'] && strlen($save = preg_replace('/(\s\s+|\n)/', ' ',strip_tags($paragraph))) > $this->opt['ap_min_length'])
			{
				//Keep under max lenght
				$this->text[] = $this->trimText(trim($save), $this->opt['ap_max_length']);
				$i++;
			}
		}
		if($i < $this->opt['ap_max_count'] && $tag == 'p')
		{
			$this->findText($content, 'div');
		}
	}
	function findTitle($content)
	{
		//Match the title tag
		preg_match('/<title>([^>]*)<\/title>/i', $content, $title);
		//Clean up the title, remove excess whitespace and such
		$this->title = trim(preg_replace('/(\s\s+|\n)/', ' ',strip_tags($title[0])));
	}
	/**
	 * titleTrim
	 * 
	 * This function will intelligently trim the input text to the value passed in through $maxLength.
	 * 
	 * @param string $text the text to trim.
	 * @param int $maxLength of the input text.
	 * @return string trimmed text
	 */
	function trimText($text, $maxLength)
	{
		//Make sure that we are not making it longer with that ellipse
		if((mb_strlen($text) + 3) > $maxLength)
		{
			//Trim the text
			$text = mb_substr($text, 0, $maxLength - 1);
			//Make sure we can split at a space, but we want to limit to cutting at max an additional 25%
			if(mb_strpos($text, ' ', .75 * $maxLength) > 0)
			{
				//Don't split mid word
				while(mb_substr($text,-1) != ' ')
				{
					$text = mb_substr($text, 0, -1);
				}
			}
			//Remove the whitespace at the end and add the hellip
			$text = rtrim($text) . '&hellip;';
		}
		return $text;
	}
	/**
	 * urlFix
	 * 
	 * This function adds in the url in $baseURL if the input of $url looks to be a relative URL
	 * 
	 * @param string $baseURL
	 * @param string $url
	 * @return the fixed url
	 */
	function urlFix($baseURL, $url)
	{
		return WP_Http::make_absolute_url($url, $baseURL);
	}
	function is_PNG($data)
	{
		//The identity for a PNG is 8Bytes (64bits) long
		$ident = unpack('Nupper/Nlower', $data);
		//Make sure we get PNG
		if($ident['upper'] === 0x89504E47 && $ident['lower'] === 0x0D0A1A0A)
		{
			return true;
		}
		return false;
	}
	function is_GIF($data)
	{
		//The identity for a GIF is 6bytes (48Bits) long
		$ident = unpack('nupper/nmiddle/nlower', $data);
		//Make sure we get GIF 87a or 89a
		if($ident['upper'] === 0x4749 && $ident['middle'] === 0x4638 && ($ident['lower'] === 0x3761 || $ident['lower'] === 0x3961))
		{
			return false;
		}
	}
	function is_JPEG($data)
	{
		$ident = unpack('nmagic/nmarker', $data);
		//Make sure we're a JPEG
		if($ident['magic'] === 0xFFD8)
		{
			return true;
		}
		return false;
	}
	function is_PDF($data)
	{
		//The identity for a PDF is 4Bytes (32bits) long
		$ident = unpack('Nupper', $data);
		//Make sure we get %PDF
		if($ident['upper'] === 0x25504446)
		{
			return true;
		}
		return false;
	}
	/**
	 * getJPEGImageXY
	 * 
	 * geoff's getJPEGImageXY from http://us.php.net/manual/en/function.getimagesize.php
	 * only needs the headder data of the JPEG, saving a ton of bandwidth
	 * 
	 * @param bitstream $data
	 * @return 
	 */
	function getJPEGImageXY($data)
	{
		$soi = unpack('nmagic/nmarker', $data);
		//Make sure we're a JPEG
		if($soi['magic'] != 0xFFD8)
		{
			return false;
		}
		$marker = $soi['marker'];
		$data = substr($data, 4);
		$done = false;
		//Loop until we find the first frame
		while(1)
		{
			if(strlen($data) === 0)
			{
				return false;
			}
			switch($marker)
			{
				case 0xFFC0:
					$info = unpack('nlength/Cprecision/nY/nX', $data);
					return array($info['X'], $info['Y']);
					break;
				default:
					$info   = unpack('nlength', $data);
					$data   = substr($data, $info['length']);
					//If we run out of data, return dimensions just larger than min (most JPEGs will be large enough)
					if(strlen($data) <= 1)
					{
						return array($this->opt['aimg_min_x'] + 1, $this->opt['aimg_min_y'] + 1);
					}
					$info   = unpack('nmarker', $data);
					$marker = $info['marker'];
					$data   = substr($data, 2);
					break;
			}
		}
	}
	/**
	 * getPNGImageXY
	 * 
	 * Requires first 24 Bytes of a PNG
	 * 
	 * @param bitstream $data
	 * @return 
	 */
	function getPNGImageXY($data)
	{
		//The identity for a PNG is 8Bytes (64bits)long
		$ident = unpack('Nupper/Nlower', $data);
		//Make sure we get PNG
		if($ident['upper'] !== 0x89504E47 || $ident['lower'] !== 0x0D0A1A0A)
		{
			return false;
		}
		//Get rid of the first 8 bytes that we processed
		$data = substr($data, 8);
		//Grab the first chunk tag, should be IHDR
		$chunk = unpack('Nlength/Ntype', $data);
		//IHDR must come first, if not we return false
		if($chunk['type'] === 0x49484452)
		{
			//Get rid of the 8 bytes we just processed
			$data = substr($data, 8);
			//Grab our x and y
			$info = unpack('NX/NY', $data);
			//Return in common format
			return array($info['X'], $info['Y']);
		}
		else
		{
			return false;
		}
	}
	/**
	 * getGIFImageXY
	 * 
	 * Requires first 10 Bytes of a GIF
	 * 
	 * @param bitstream $data
	 * @return 
	 */
	function getGIFImageXY($data)
	{
		//The identity for a GIF is 6bytes (48Bits)long
		$ident = unpack('nupper/nmiddle/nlower', $data);
		//Make sure we get GIF 87a or 89a
		if($ident['upper'] !== 0x4749 || $ident['middle'] !== 0x4638 || ($ident['lower'] !== 0x3761 && $ident['lower'] !== 0x3961))
		{
			return false;
		}
		//Get rid of the first 6 bytes that we processed
		$data = substr($data, 6);
		//Grab our x and y, GIF is little endian for width and length
		$info = unpack('vX/vY', $data);
		//Return in common format
		return array($info['X'], $info['Y']);
	}
}