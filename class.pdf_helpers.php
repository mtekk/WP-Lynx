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
	 * @param int $width The width of the image, defaults to 250 px
	 * 
	 * @return Imagick the image representing the specified PDF
	 */
	static public function create_pdf_image($content, $name, $width = 250)
	{
		if(class_exists('Imagick'))
		{
			//Create our thumbnail
			$image = new Imagick();
			$image->readImageBlob($content, $name . '[0]');
			$image->setIteratorIndex(0);
			$image->setImageFormat('jpeg');
			$image->resizeimage($width, 2* $width, Imagick::FILTER_CUBIC, 0.5);
			$image->setImageCompressionQuality($this->opt['acache_quality']);
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
				return 'data:image/' . $thumbnail->getImageFormat() . ';base64,' . base64_encode($thumbnail->getImageBlob());
			}
		}
		return false;
	}
}
