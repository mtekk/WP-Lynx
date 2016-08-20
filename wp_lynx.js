var llynx = llynx || {};

( function( $ ) {
	//TODO: need to ensure ajaxurl is always available (when we move out of wp-admin)
	llynx.ajaxurl = ajaxurl;
	llynx.l10n = llynx_l10n;
	llynx.send_to_editor = function(html) {
		var editor, node,
			hasTinymce = typeof tinymce !== 'undefined';
		if(hasTinymce)
		{
			editor = tinymce.get(wpActiveEditor);
			//If we find ourselves in a llynx div, append to end of it to prevent llynx printception
			if(editor && (node = editor.dom.getParent(editor.selection.getNode(), 'div.llynx_print')))
			{
				$(node).after(html);
				return 0;
			}
		}
		//Fall back to the normal WordPress method
		window.send_to_editor(html);
	};
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
			'change input' : 'changeTitle',
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
			$('.spinner', this.$el).css('visibility', 'visible');
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
				"html").fail(this.responseBad);
		},
		responseBad : function() {
			console.log(objectL10n.wp_lynx_request_error_msg);
			llynx.messages.create({type: 'error', message: objectL10n.wp_lynx_request_error_msg});
		},
		sendToPost : function(data) {
			//In the future this may be more intellegent, but for now the server gives us ready to use HTML
			var htmlContent = data;
			llynx.send_to_editor(htmlContent);
			llynx.messages.create({type: 'notice', message: llynx.l10n.insertSuccessMsg});
			//All done, remove the view/model
			this.del();
		},
		changeTitle : function(e) {
			var tempTitle = e.target.value.trim();
			this.model.set({title: tempTitle});
		},
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
	//Our toolbar
	llynx.media.view.llynxPrintInsert = Backbone.View.extend({
		className: 'llynx-print-insert-toolbar',
		template:  _.template($('#tmpl-llynx-print-insert').html()),
		initialize : function(){
			this.listenTo(llynx.sites, 'all', this.render);
			this.listenTo(llynx.media._frame.states, 'activate', this.render);
			_.bindAll(this, 'render', 'insertPrints');
		},
		events: {
			'click .llynx-print-insert-all' : 'insertPrints'
		},
		insertPre : function(site) {
			//TODO: Enable nonces
			$.post(llynx.ajaxurl, {
				action: 'wp_lynx_fetch_print',
				title: site.attributes.title,
				url: site.attributes.url,
				image: site.attributes.images[site.attributes.image],
				description: site.attributes.descriptions[site.attributes.description],
				nonce: '1234'
				},
				this.sendToPost,
				"html").fail(this.responseBad);
		},
		responseBad : function() {
			console.log(objectL10n.wp_lynx_request_error_msg);
			llynx.messages.create({type: 'error', message: objectL10n.wp_lynx_request_error_msg});
		},
		sendToPost : function(data) {
			//In the future this may be more intellegent, but for now the server gives us ready to use HTML
			var htmlContent = data;
			llynx.send_to_editor(htmlContent);
		},
		insertPrints : function(e) {
			if($(e.target).attr('disabled') != undefined) {
				return 0;
			}
			//Insert the prints
			llynx.sites.each(this.insertPre, this);
			//Cleanup
			_.invoke(llynx.sites.toArray(), 'destroy');
			//Close the frame
			llynx.media._frame.close();
		},
		render : function() {
			var lengthTemp;
			if(llynx.media._frame._state == 'llynx-print-add-state') {
				lengthTemp = llynx.sites.length;
			}
			else {
				lengthTemp = 0;
			}
			this.$el.html(this.template({length : lengthTemp}));
			return this;
		}
	});
	//lynxPrintAdd
	media.view.llynxPrintAdd = Backbone.View.extend({
		spinnerQueue: 0,
		className: 'llynx-print-add-frame',
		template:  _.template($('#tmpl-llynx-print-add').html()),
		initialize : function(){
			this.listenTo(llynx.sites, 'reset', this.addAll);
			this.listenTo(llynx.sites, 'add', this.addSite);
			this.listenTo(llynx.messages, 'add', this.addMessage);
			_.bindAll(this, 'keyup', 'save', 'response', 'addSite', 'addMessage', 'addAll');
		},
		events: {
			'keyup #llynx_url' : 'keyup',
			'click .llynx_save' : 'save'
		},
		keyup : function(e) {
			if(e.keyCode === 13)
			{
				this.save(e);
			}
		},
		save : function(e) {
			$('.embed-url .spinner').css('visibility', 'visible');
			//Clear messages before running again
			_.invoke(llynx.messages.toArray(), 'destroy');
			var urls = $('input[name=llynx_url]').val().split(' ');
			urls.forEach(function(url){
				this.spinnerQueue++;
				//TODO: Enable nonces
				$.post(llynx.ajaxurl, {
					action: 'wp_lynx_fetch_url',
					url: url,
					nonce: '1234'
					},
					this.response,
					"json").fail(this.responseBad);
			}, this);
		},
		response : function(data) {
			this.manageSpinner();
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
		responseBad : function() {
			this.manageSpinner();
			console.log(objectL10n.wp_lynx_request_error_msg);
			llynx.messages.create({type: 'error', message: objectL10n.wp_lynx_request_error_msg});
		},
		manageSpinner : function() {
			if(--this.spinnerQueue == 0)
			{
				$('.embed-url .spinner').css('visibility', 'hidden');
			}
		},
		addSite : function(site) {
			var view = new llynx.view.lynxPrint({model: site});
			this.$('#llynx_sites').append(view.render().el);
			this.$('input[name=llynx_url]').val('');
		},
		addMessage : function(message) {
			var view = new llynx.view.message({model: message});
			this.$('#llynx_sites').append(view.render().el);
			this.$('input[name=llynx_url]').val('');
		},
		addAll : function() {
			//Clear our html before adding in everything
			this.$('#llynx_sites').html('');
			llynx.sites.each(this.addSite, this);
			llynx.messages.each(this.addMessage, this);
		},
		render : function() {
			this.$el.html(this.template({}));
			this.addAll();
			return this;
		}
	});

	media.controller.llynxPrintAdd = wp.media.controller.State.extend({
		defaults: {
			id:       'llynx-print-add-state',
			menu:     'default',
			content:  'llynx_print_add_state'
		}
	});
	
	media.view.llynxHelp = wp.media.View.extend({
		className: 'llynx-help-frame',
		template:  wp.media.template( 'llynx-help' )
	});

	media.controller.llynxHelp = wp.media.controller.State.extend({
		defaults: {
			id:       'llynx-help-state',
			menu:     'default',
			content:  'llynx_help_state'
		}
	});

	_.extend( media, {
		frame: function() {
			if ( this._frame )
				return this._frame;

			var states = [
				new media.controller.llynxPrintAdd( {
					title: 'Add Lynx Print',
					id: 'llynx-print-add-state',
					priority: 10,
					toolbar: 'llynx_print_insert'
				} ),
				new media.controller.llynxHelp( {
					title: 'Help',
					id: 'llynx-help-state',
					priority: 20
				} )
			];

			this._frame = wp.media( {
				className: 'media-frame no-sidebar',
				state: 'llynx-print-add-state',
				states: states
			} );

			this._frame.on( 'content:create:llynx_print_add_state', function() {
				var view = new llynx.media.view.llynxPrintAdd( {
					controller: media.frame(),
					model:      media.frame().state()
				} );
				var toolbar = new llynx.media.view.llynxPrintInsert( {
					controller: media.frame(),
					model:      media.frame().state()
				});
				media.frame().content.set( view );
				media.frame().toolbar.set( toolbar );
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
		},

		close: function() {
			$( '.media-modal' ).removeClass( 'smaller' );
		},

		menuRender: function( view ) {
			
		},

		init: function() {
			$( '.add_lynx_print' ).on( 'click', function( e ) {
				e.preventDefault();
				media.frame().open();
			});
		}
	} );

	$( media.init );
} )( jQuery );