<?php
/*  
	Copyright 2016  John Havlik  (email : john.havlik@mtekk.us)

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

//Include our url fixing functions
if(!function_exists('url_to_absolute'))
{
	require_once(dirname(__FILE__) . '/includes/url_to_absolute.php');
}
class pdf_helpers
{
	function __construct()
	{
		
	}
	/**
	 * Creates a single page image for the passed in PDF
	 * 
	 * @param string $content The raw content of the PDF returned by getContent
	 * @param string $name The name of the PDF
	 * @param int $quality The quality factor for JPEG
	 * 
	 * @return Imagick the image representing the specified PDF
	 */
	static public function create_pdf_image($content, $name, $quality = 75)
	{
		if(class_exists('Imagick'))
		{
			//Create our thumbnail
			$image = new Imagick();
			$image->readImageBlob($content, $name . '[0]');
			$image->setIteratorIndex(0);
			$image->setImageFormat('jpeg');
			$image->setImageCompressionQuality($quality);
			return $image;
		}
		return false;
	}
	/**
	 * This function is meant to be called to return the content of an image for a specified PDF
	 * 
	 * @param string $content The raw content of the PDF returned by getContent
	 * 
	 * @return bool|string If Imagick is available, the base64 encoded string for the image is presented, otherwise returns false
	 */
	static public function pdf_image_preview($content)
	{
		if(class_exists('Imagick'))
		{
			$thumbnail = pdf_helpers::create_pdf_image($content, 'preview.pdf');
			if($thumbnail !== false)
			{
				$thumbnail->resizeimage(250, 500, Imagick::FILTER_CUBIC, 0.5);
				return 'data:image/' . $thumbnail->getImageFormat() . ';base64,' . base64_encode($thumbnail->getImageBlob());
			}
		}
		return false;
	}
	/*
	 * This function reads the first few bytes of the input string to see if it is image data or a url
	 * 
	 * @param string $content The raw content to determine if it is a URL or data
	 * 
	 * @return bool Wheather or not the content is image data
	 */
	static public function is_image_data($content)
	{
		return (stripos($content, 'data:image') === 0 && stripos($content, ';base64,') !== false);
	}
}
