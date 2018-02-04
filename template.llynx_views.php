<?php
/*  Copyright 2007-2017  John Havlik  (email : john.havlik@mtekk.us)

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
?>
<script type="text/html" id="tmpl-llynx-print-add">
<div class="media-embed">
	<label class="embed-url">
		<input id="llynx_url" type="text" name="llynx_url" placeholder="<?php _e('Enter URL', 'wp-lynx');?>">
		<button class="llynx_save" title="<?php _e("Fetch the URL's Lynx Print", 'wp-lynx');?>"><?php _e('Get', 'wp-lynx');?></button>
		<span class="spinner"></span>
	</label>
	<div id="llynx_sites">
		
	</div>
</div>
</script>
<script type="text/html" id="tmpl-llynx-print">
<div class="llynx_col_img">
	<div class="llynx_thumb">
		<img src="<%= images[image] %>" draggable="false" />
	</div>
	<button class="llynx_img_prev" title="<?php _e('Previous Image', 'wp-lynx');?>" <%= (image < 1) ? 'disabled="disabled"' : '' %>>&lt;</button>
	<button class="llynx_img_next" title="<?php _e('Next Image', 'wp-lynx');?>"<%= (image+1 >= images.length) ? 'disabled="disabled"' : '' %>>&gt;</button>
	<span class="llynx-img-count"><%= image+1 %>/<%= images.length %></span>
</div>
<div class="llynx_main">
	<input class="llynx_title" type="text" name="llynx_title" placeholder="<?php _e('Enter Site Title', 'wp-lynx');?>" value="<%= title %>">
	<small><%= url %></small>
	<textarea class="llynx_description"><%= descriptions[description] %></textarea>
	<button class="llynx_desc_prev" title="<?php _e('Previous Description', 'wp-lynx');?>" <%= (description < 1) ? 'disabled="disabled"' : '' %>>&lt;</button>
	<button class="llynx_desc_next" title="<?php _e('Next Description', 'wp-lynx');?>"<%= (description+1 >= descriptions.length) ? 'disabled="disabled"' : '' %>>&gt;</button>
	<span class="llynx-desc-count"><%= description+1 %>/<%= descriptions.length %></span>
	<p>
		<button class="llynx_insert" title="<?php _e('Insert this Lynx Print into the post', 'wp-lynx');?>"><?php _e('Insert Into Post', 'wp-lynx');?></button>
		<button class="llynx_del" title="<?php _e('Delete this Lynx Print', 'wp-lynx');?>"><?php _e('Delete', 'wp-lynx');?></button>
		<span class="spinner"></span>
	</p>
</div>
</script>
<script type="text/html" id="tmpl-llynx-message">
	<div class="<%= type %>">
		<span><%= message %></span>
		<a class="llynx_message_close" href="#" title="<?php _e('Dismiss Message','wp-lynx')?>">
			<span class="llynx_close_icon">
				<span class="screen-reader-text"><?php _e('Dismiss Message', 'wp-lynx');?></span>
			</span>
		</a>
	</div>
</script>
<script type="text/html" id="tmpl-llynx-print-insert">
	<div class="media-toolbar-primary search-form">
		<a title="<?php _e('Insert all of the Lynx Prints into the post.', 'wp-lynx');?>" <%= (length < 1 ) ? 'disabled="disabled"' : ''%> class="button media-button button-primary button-large media-button-insert llynx-print-insert-all" href="#"><?php _e('Insert into post', 'wp-lynx');?></a>
	</div>
</script>
<script type="text/html" id="tmpl-llynx-help">
<div class="media-embed llynx-text" style="margin:1em;">
	<p>
		<?php _e('The Add Lynx Print dialog is simple to use. Just enter the URL to the website or page that you want to link to in to the text area. You can enter more than one link at a time, just place a space, or start a newline between each link. Then press the "Get" button. After the pages have been retrieved you should have something similar to the picture above. The pictures are changeable, just use the arrows to thumb through the available pictures. The same goes for the text field, which you may manually edit or thumb through some preselected paragraphs from the linked site.', 'wp-lynx');?>
	</p>
	<p>
		<?php _e('When you are ready to insert a Link Print, just click the "Insert into Post" button (or the "Insert All" button at the bottom to insert multiple Link Prints simultaneously). If you go to the HTML tab in the editor you\'ll see that WP Lynx generates pure HTML. This gives the user full control over their Lynx Prints.', 'wp-lynx');?>
	</p>
	<p>
		<?php printf(__('If you think you have found a bug, please include your WordPress version and details on how to reporduce the bug when you %sreport the issue%s.', 'wp-lynx'),'<a title="' . __('Go to the WP Lynx support post for your version.', 'wp-lynx') . '" href="http://mtekk.us/archives/wordpress/plugins-wordpress/wp-lynx-' . linksLynx::version . '/#respond">', '</a>');?>
	</p>
</div>
</script>