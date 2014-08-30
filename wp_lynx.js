var llynx = llynx || {};

( function( $ ) {
	var lynxPrint = Backbone.Model.extend({
		urlRoot: '/wplynx',
		defaults: {
			url: '',
			title: '',
			descriptions: '',
			images: ''
		},
		//We don't usse the underscores syncing since REST doesn't make sense for this app
		sync : function () {
			return false;
		}
	});
	var media;
	var lynxTrack = Backbone.Collection.extend({
		model: lynxPrint	
	});
	llynx.sites = new lynxTrack();
	llynx.media = media = {};
	_.extend( media, { view: {}, controller: {} } );
	llynx.view = {};
	llynx.view.lynxPrint = Backbone.View.extend({
		className: 'llynx-print',
		//Have to use underscore rather than WP version as we're too generic
		template: _.template($('#tmpl-llynx-print').html()),
		initialize: function() {
			this.listenTo(this.model, 'change', this.render);
			_.bindAll(this, 'render');
		},
		render : function() {
			console.log(this.model.attributes);
			this.$el.html( this.template( this.model.attributes ));
			return this;
		}
	});
	media.view.llynxPrintAdd = wp.media.View.extend({
		className: 'llynx-print-add-frame',
		regions: ['menu', 'title', 'content', 'router', 'navigation'],
		template:  wp.media.template( 'llynx-print-add' ),
		initialize : function(){
			this.llynxSites = this.$('.llynx_sites');
			this.listenTo(llynx.sites, 'add', this.addSite)
			_.bindAll(this, 'keyup', 'save', 'response', 'addSite');
		},
		events: {
			"keyup #llynx_url" : "keyup"
		},
		keyup : function(e) {
			if(e.keyCode === 13)
			{
				console.log('caught enter');
				this.save(e);
			}
		},
		save : function(e) {
			$('.spinner').show();
			//TODO: need to ensure ajaxurl is always available (when we move out of wp-admin)
			$.post(ajaxurl, {
				action: 'wp_lynx_fetch_url',
				url: $('input[name=llynx_url]').val(),
				nonce: '1234'
				},
				this.response,
				"json");
		},
		response : function(data) {
			$('.spinner').hide();
			llynx.sites.create({url: data.url, title: data.title, descriptions: data.descriptions, images: data.images});
		},
		addSite : function( site ) {
			var view = new llynx.view.lynxPrint({model: site});
			$('#llynx_sites').append( view.render().el );
			$('input[name=llynx_url]').val('');
		}
	});

	media.controller.llynxPrintAdd = wp.media.controller.State.extend({
		defaults: {
			id:       'llynx-print-add-state',
			menu:     'default',
			toolbar:  'insert',
			//router:   'browse',
			content:  'llynx_print_add_state'
		}
	});
	
	media.view.llynxHelp = wp.media.View.extend({
		className: 'llynx-help-frame',
		regions: ['menu', 'title', 'content', 'router'],
		template:  wp.media.template( 'llynx-help' )
	});

	media.controller.llynxHelp = wp.media.controller.State.extend({
		defaults: {
			id:       'llynx-help-state',
			menu:     'default',
			content:  'llynx_help_state'
		}
	});
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
				var view = new llynx.media.view.llynxPrintAdd( {
					controller: media.frame(),
					model:      media.frame().state()
				} );
				media.frame().content.set( view );
			} );
			
			this._frame.on( 'content:create:llynx_help_state', function() {
				var view = new llynx.media.view.llynxHelp( {
					controller: media.frame(),
					model:      media.frame().state()
				} );
				media.frame().content.set( view );
			} );
			
			this._frame.on( 'open', this.open );

			this._frame.on( 'ready', this.ready );

			this._frame.on( 'close', this.close );

			this._frame.on( 'menu:render:default', this.menuRender );
			
			return this._frame;
		},

		open: function() {
			$( '.media-modal' ).addClass( 'smaller' );
		},
		
		events: {
			
		},
		
		ready: function() {
			console.log( 'Frame ready' );
		},

		close: function() {
			$( '.media-modal' ).removeClass( 'smaller' );
		},

		menuRender: function( view ) {
			
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