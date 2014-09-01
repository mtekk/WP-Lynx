var llynx = llynx || {};

( function( $ ) {
	//TODO: need to ensure ajaxurl is always available (when we move out of wp-admin)
	llynx.ajaxurl = ajaxurl;
	//Begin with our model and collection definitions
	//LynxPrint stuff
	var lynxPrint = Backbone.Model.extend({
		urlRoot: '/wplynx',
		defaults: {
			url: '',
			title: '',
			descriptions: '',
			description: 0,
			images: '',
			image: 0
		},
		//We don't usse the underscores syncing since REST doesn't make sense for this app
		sync : function () {
			return false;
		}
	});
	var lynxTrack = Backbone.Collection.extend({
		model: lynxPrint	
	});
	llynx.sites = new lynxTrack();
	//Messages stuff
	var lynxMessage = Backbone.Model.extend({
		urlRoot: '/wplynx',
		defaults: {
			type: '',
			message: ''
		},
		//We don't usse the underscores syncing since REST doesn't make sense for this app
		sync : function () {
			return false;
		}
	});
	var lynxMessages = Backbone.Collection.extend({
		model: lynxMessage
	});
	llynx.messages = new lynxMessages();
	//WP Media Modal stuff
	var media;
	llynx.media = media = {};
	_.extend( media, { view: {}, controller: {} } );
	
	//Our Views
	llynx.view = {};
	//LynxPrint view
	llynx.view.lynxPrint = Backbone.View.extend({
		className: 'llynx-print',
		//Have to use underscore rather than WP version as we're too generic
		template: _.template($('#tmpl-llynx-print').html()),
		events: {
			'click .llynx_img_prev' : 'prevImg',
			'click .llynx_img_next' : 'nextImg',
			'click .llynx_desc_prev' : 'prevDesc',
			'click .llynx_desc_next' : 'nextDesc',
			'click .llynx_del' : 'del',
			'click .llynx_insert' : 'insertPre',
			'keyup textarea' : 'keyupDescription'
		},
		initialize : function() {
			this.listenTo(this.model, 'change', this.render);
			this.listenTo(this.model, 'destroy', this.remove);
			_.bindAll(this, 'render', 'nextImg', 'prevImg', 'sendToPost', 'del', 'keyupDescription');
		},
		render : function() {
			this.$el.html(this.template(this.model.attributes));
			return this;
		},
		nextImg : function() {
			curImage = this.model.attributes.image
			if(++curImage < this.model.attributes.images.length)
			{
				this.model.set({image: curImage});
			}
		},
		prevImg : function() {
			curImage = this.model.attributes.image
			if(--curImage >= 0)
			{
				this.model.set({image: curImage});
			}
		},
		nextDesc : function() {
			curDesc = this.model.attributes.description
			if(++curDesc < this.model.attributes.descriptions.length)
			{
				this.model.set({description: curDesc});
			}
		},
		prevDesc : function() {
			curDesc = this.model.attributes.description
			if(--curDesc >= 0)
			{
				this.model.set({description: curDesc});
			}
		},
		del : function() {
			this.model.destroy();
		},
		insertPre : function() {
			var tempTitle = $('.llynx_title', this.$el).val().trim();
			this.model.set({title: tempTitle});
			$('.spinner', this.$el).show();
			//TODO: Enable nonces
			$.post(llynx.ajaxurl, {
				action: 'wp_lynx_fetch_print',
				title: this.model.attributes.title,
				url: this.model.attributes.url,
				image: this.model.attributes.images[this.model.attributes.image],
				description: this.model.attributes.descriptions[this.model.attributes.description],
				nonce: '1234'
				},
				this.sendToPost,
				"html");
		},
		sendToPost : function(data) {
			//In the future this may be more intellegent, but for now the server gives us ready to use HTML
			var htmlContent = data;
			window.send_to_editor(htmlContent);
			//All done, remove the view/model
			this.del();
		},
		/*keyupTitle : function(e) {
			var tempTitle = e.target.value.trim();
			this.model.set({title: tempTitle});
		},*/
		keyupDescription : function(e) {
			//Retrieve our descriptions, make temporary copy
			var tempDesc = this.model.attributes.descriptions;
			//Update our temporary copy
			tempDesc[this.model.attributes.description] = e.target.value.trim();
			//Update the model
			this.model.set({descriptions: tempDesc});
		}
	});
	//LynxMessage
	llynx.view.message = Backbone.View.extend({
		className: 'llynx-message',
		//Have to use underscore rather than WP version as we're too generic
		template: _.template($('#tmpl-llynx-message').html()),
		events: {
			'click .llynx_message_close' : 'del'
		},
		initialize : function() {
			this.listenTo(this.model, 'change', this.render);
			this.listenTo(this.model, 'destroy', this.remove);
			_.bindAll(this, 'render', 'del');
		},
		render : function() {
			this.$el.html(this.template(this.model.attributes));
			return this;
		},
		del : function() {
			this.model.destroy();
		}
	});
	//lynxPrintAdd
	media.view.llynxPrintAdd = wp.media.View.extend({
		className: 'llynx-print-add-frame',
		regions: ['menu', 'title', 'content', 'router', 'navigation'],
		template:  wp.media.template( 'llynx-print-add' ),
		initialize : function(){
			this.llynxSites = this.$('.llynx_sites');
			this.listenTo(llynx.sites, 'add', this.addSite);
			this.listenTo(llynx.messages, 'add', this.addMessage);
			_.bindAll(this, 'keyup', 'save', 'response', 'addSite', 'addMessage');
		},
		events: {
			"keyup #llynx_url" : "keyup"
		},
		keyup : function(e) {
			if(e.keyCode === 13)
			{
				this.save(e);
			}
		},
		save : function(e) {
			$('.embed-url .spinner').show();
			//Clear messages before running again
			llynx.messages.each(function(message){
				message.destroy();
			});
			//TODO: Enable nonces
			$.post(llynx.ajaxurl, {
				action: 'wp_lynx_fetch_url',
				url: $('input[name=llynx_url]').val(),
				nonce: '1234'
				},
				this.response,
				"json");
		},
		response : function(data) {
			$('.embed-url .spinner').hide();
			if(data.hasOwnProperty('error'))
			{
				console.log(data.error_msg);
				llynx.messages.create({type: 'error', message: data.error_msg});
			}
			else
			{
				llynx.sites.create({url: data.url, title: data.title, descriptions: data.descriptions, images: data.images});
			}
		},
		addSite : function(site) {
			var view = new llynx.view.lynxPrint({model: site});
			$('#llynx_sites').append(view.render().el);
			$('input[name=llynx_url]').val('');
		},
		addMessage : function(message) {
			var view = new llynx.view.message({model: message});
			$('#llynx_sites').append(view.render().el);
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