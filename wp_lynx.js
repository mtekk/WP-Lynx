var ds = ds || {};

/**
 * Demo 5
 */
( function( $ ) {
	var media;

	ds.media = media = {};

	_.extend( media, { view: {}, controller: {} } );

	media.view.llynxPrintAdd = wp.media.View.extend( {
		className: 'llynx-print-add-frame',
		regions: ['menu', 'title', 'content', 'router'],
		template:  wp.media.template( 'llynx-print-add' ) // <script type="text/html" id="tmpl-hello-world">
	} );

	media.controller.llynxPrintAdd = wp.media.controller.State.extend( {
		defaults: {
			id:       'llynx-print-add-state',
			menu:     'default',
			//toolbar:  'select',
			//router:   'browse',
			content:  'llynx_print_add_state'
		}
	} );
	
	media.view.llynxHelp = wp.media.View.extend( {
		className: 'llynx-help-frame',
		regions: ['menu', 'title', 'content', 'router'],
		template:  wp.media.template( 'llynx-help' ) // <script type="text/html" id="tmpl-hello-world">
	} );

	media.controller.llynxHelp = wp.media.controller.State.extend( {
		defaults: {
			id:       'llynx-help-state',
			menu:     'default',
			//toolbar:  'select',
			//router:   'browse',
			content:  'llynx_help_state'
		}
	} );
	media.buttonId = '#add_link_print',

	_.extend( media, {
		frame: function() {
			if ( this._frame )
				return this._frame;

			var states = [
				new media.controller.llynxPrintAdd( {
					title:    'Add Lynx Print',
					id:       'llynx-print-add-state',
					priority: 10
				} ),
				new media.controller.llynxHelp( {
					title:    'Help',
					id:       'llynx-help-state',
					priority: 20
				} )
			];

			this._frame = wp.media( {
				className: 'media-frame no-sidebar',
				state: 'llynx-print-add-state',
				states: states//,
				//multiple: false
				//frame: 'post'
			} );

			this._frame.on( 'content:create:llynx_print_add_state', function() {
				var view = new ds.media.view.llynxPrintAdd( {
					controller: media.frame(),
					model:      media.frame().state()
				} );
				media.frame().content.set( view );
			} );
			
			this._frame.on( 'content:create:llynx_help_state', function() {
				var view = new ds.media.view.llynxHelp( {
					controller: media.frame(),
					model:      media.frame().state()
				} );
				media.frame().content.set( view );
			} );
			
			this._frame.on( 'open', this.open );

			this._frame.on( 'ready', this.ready );

			this._frame.on( 'close', this.close );

			this._frame.on( 'menu:render:default', this.menuRender );

			this._frame.state( 'library' ).on( 'select', this.select );
			this._frame.state( 'image' ).on( 'select', this.select );

			return this._frame;
		},

		open: function() {
			$( '.media-modal' ).addClass( 'smaller' );
		},

		ready: function() {
			console.log( 'Frame ready' );
		},

		close: function() {
			$( '.media-modal' ).removeClass( 'smaller' );
		},

		menuRender: function( view ) {
			
			/*view.unset( 'library-separator' );
			view.unset( 'embed' );
			view.unset( 'gallery' );
			*/
		},

		select: function() {
			var settings = wp.media.view.settings,
				selection = this.get( 'selection' );

			$( '.added' ).remove();
			selection.map( media.showAttachmentDetails );
		},

		showAttachmentDetails: function( attachment ) {
			var details_tmpl = $( '#attachment-details-tmpl' ),
				details = details_tmpl.clone();

			details.addClass( 'added' );

			$( 'input', details ).each( function() {
				var key = $( this ).attr( 'id' ).replace( 'attachment-', '' );
				$( this ).val( attachment.get( key ) );
			} );

			details.attr( 'id', 'attachment-details-' + attachment.get( 'id' ) );

			var sizes = attachment.get( 'sizes' );
			$( 'img', details ).attr( 'src', sizes.thumbnail.url );

			$( 'textarea', details ).val( JSON.stringify( attachment, null, 2 ) );

			details_tmpl.after( details );
		},

		init: function() {
			$( media.buttonId ).on( 'click', function( e ) {
				e.preventDefault();
				media.frame().open();
			});
		}
	} );

	$( media.init );
} )( jQuery );

function next_thumb(id)
{
	jQuery("#imgprev-btn-" + id).removeAttr("disabled");
	jQuery("#imgprev-btn-" + id).removeClass("disabled");
	llynx_cimgs[id]++;
	if(llynx_cimgs[id] == llynx_imgs[id].length - 1)
	{
		jQuery("#imgnext-btn-" + id).attr("disabled","disabled");
		jQuery("#imgnext-btn-" + id).addClass("disabled");
	}
	jQuery("#icount-" + id).text((llynx_cimgs[id] + 1) + " / " + llynx_imgs[id].length);
	jQuery("#prints" + id + "img").val(llynx_imgs[id][llynx_cimgs[id]]);
	jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").attr("src", llynx_imgs[id][llynx_cimgs[id]]);
}
function prev_thumb(id)
{
	jQuery("#imgnext-btn-" + id).removeAttr("disabled");
	jQuery("#imgnext-btn-" + id).removeClass("disabled");
	llynx_cimgs[id]--;
	if(llynx_cimgs[id] == 0)
	{
		jQuery("#imgprev-btn-" + id).attr("disabled","disabled");
		jQuery("#imgprev-btn-" + id).addClass("disabled");
	}
	jQuery("#icount-" + id).text((llynx_cimgs[id] + 1) + " / " + llynx_imgs[id].length);
	jQuery("#prints" + id + "img").val(llynx_imgs[id][llynx_cimgs[id]]);
	jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").attr("src", llynx_imgs[id][llynx_cimgs[id]]);
}
function img_toggle(id)
{
	if(jQuery("#thumbnail-head-llynx-" + id + " > p > input:checked").length > 0)
	{
		jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").fadeOut();
		jQuery("#imgprev-btn-" + id).attr("disabled","disabled");
		jQuery("#imgprev-btn-" + id).addClass("disabled");
		jQuery("#imgnext-btn-" + id).attr("disabled","disabled");
		jQuery("#imgnext-btn-" + id).addClass("disabled");
	}
	else
	{
		jQuery("#thumbnail-head-llynx-" + id + " > p > .thumbnail").fadeIn();
		if(llynx_cimgs[id] == 0)
		{
			jQuery("#imgprev-btn-" + id).attr("disabled","disabled");
			jQuery("#imgprev-btn-" + id).addClass("disabled");
		}
		else
		{
			jQuery("#imgprev-btn-" + id).removeAttr("disabled");
			jQuery("#imgprev-btn-" + id).removeClass("disabled");
		}
		if(llynx_cimgs[id] == llynx_imgs[id].length - 1)
		{
			jQuery("#imgnext-btn-" + id).attr("disabled","disabled");
			jQuery("#imgnext-btn-" + id).addClass("disabled");
		}
		else
		{
			jQuery("#imgnext-btn-" + id).removeAttr("disabled");
			jQuery("#imgnext-btn-" + id).removeClass("disabled");
		}
	}
}
function next_content(id)
{
	jQuery("#contprev-btn-" + id).removeAttr("disabled");
	jQuery("#contprev-btn-" + id).removeClass("disabled");
	llynx_ccont[id]++;
	if(llynx_ccont[id] == llynx_cont[id].length - 1)
	{
		jQuery("#contnext-btn-" + id).attr("disabled","disabled");
		jQuery("#contnext-btn-" + id).addClass("disabled");
	}
	jQuery("#ccount-" + id).text((llynx_ccont[id] + 1) + " / " + llynx_cont[id].length);
	jQuery("#prints" + id + "content").text(llynx_cont[id][llynx_ccont[id]]);
}
function prev_content(id)
{
	jQuery("#contnext-btn-" + id).removeAttr("disabled");
	jQuery("#contnext-btn-" + id).removeClass("disabled");
	llynx_ccont[id]--;
	if(llynx_ccont[id] == 0)
	{
		jQuery("#contprev-btn-" + id).attr("disabled","disabled");
		jQuery("#contprev-btn-" + id).addClass("disabled");
	}
	jQuery("#ccount-" + id).text((llynx_ccont[id] + 1) + " / " + llynx_cont[id].length);
	jQuery("#prints" + id + "content").text(llynx_cont[id][llynx_ccont[id]]);
}