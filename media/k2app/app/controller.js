define(['underscore', 'backbone', 'marionette', 'dispatcher', 'session'], function(_, Backbone, Marionette, K2Dispatcher, K2Session) {
	'use strict';
	var K2Controller = Marionette.Controller.extend({

		// The available resources for request. Any other request returns a 404 error.
		resources : ['items', 'categories', 'tags', 'comments', 'users', 'extrafieldsgroups', 'extrafields', 'usergroups', 'media', 'information', 'settings', 'utilities'],

		// Holds the current resource type.
		resource : 'items',

		// Holds the current model instance.
		model : null,

		// Holds the current collection instance.
		collection : null,

		// Holds the modal flag
		isModal : false,

		// Holds the messages queue
		messages : [],

		// Initialize function
		initialize : function() {

			// Listener for add event.
			K2Dispatcher.on('app:controller:add', function() {
				this.edit();
			}, this);

			// Listener for edit event.
			K2Dispatcher.on('app:controller:edit', function(id) {
				if (this.isModal) {
					this.selectRow(id);
				} else {
					this.edit(id);
				}
			}, this);

			// Listener for save events.
			K2Dispatcher.on('app:controller:save', function(redirect, callback) {
				this.save(redirect, callback);
			}, this);

			// Listener for close event.
			K2Dispatcher.on('app:controller:close', function() {
				this.close();
			}, this);

			// Listener for list event.
			K2Dispatcher.on('app:controller:list', function(id) {
				this.list();
			}, this);

			// Listener for toggle state event.
			K2Dispatcher.on('app:controller:toggleState', function(id, state, model) {
				if (!this.isModal) {
					this.toggleState(id, state, model);
				}
			}, this);

			// Listener for filter event.
			K2Dispatcher.on('app:controller:filter', function(state, value, mode) {
				this.filter(state, value, mode);
			}, this);

			// Listener for delete event.
			K2Dispatcher.on('app:controller:batchDelete', function(rows) {
				this.batchDelete(rows);
			}, this);

			// Listener for batch set state event.
			K2Dispatcher.on('app:controller:batchSetState', function(rows, value, state) {
				this.batchSetState(rows, value, state);
			}, this);

			// Listener for batch set multiple states event.
			K2Dispatcher.on('app:controller:batchSetMultipleStates', function(rows, states, mode) {
				this.batchSetMultipleStates(rows, states, mode);
			}, this);

			// Listener for save ordering
			K2Dispatcher.on('app:controller:saveOrder', function(keys, values, column, reset) {
				this.saveOrder(keys, values, column, reset);
			}, this);

			// Listener for updating the collection states
			K2Dispatcher.on('app:controller:setCollectionState', function(state, value) {
				this.collection.setState(state, value);
			}, this);

			// Listener for browse server event
			K2Dispatcher.on('app:controller:browseServer', function(options) {
				this.browseServer(options);
			}, this);

			// Listener for items layout event
			K2Dispatcher.on('app:items:layout', function(layout) {
				this.view.trigger('setLayout', layout);
			}, this);

			// Listener for sidebar search event
			K2Dispatcher.on('app:controller:search', function(search) {
				this.search(search);
			}, this);
			
			// Listener for setting the resource to the header
			K2Dispatcher.on('app:controller:get:resource', function() {
				K2Dispatcher.trigger('app:header:set:resource', this.resource);
			}, this);
		},

		// Executes the request based on the URL.
		execute : function(url) {
			if (!url) {
				this.list(1);
			} else {
				var parts = url.split('/');
				var first = _.first(parts);
				if (first == 'modal') {
					this.isModal = true;
					jQuery('[data-application="k2"]').addClass('jw--modalwrap');
					parts = _.rest(parts);
				} else {
					jQuery('[data-application="k2"]').removeClass('jw--modalwrap');
				}
				this.resource = _.first(parts);
				if (_.indexOf(this.resources, this.resource) === -1) {

					// Show the 404 view
					require(['views/404'], _.bind(function(View) {
						var view = new View();
						view.render();
						jQuery('[data-application="k2"]').html(view.el);
					}, this));

				} else {
					if (parts.length === 1) {
						this.list(1);
					} else if (parts.length === 2 && parts[1] === 'add') {
						this.edit();
					} else if (parts.length === 3 && parts[1] === 'edit') {
						this.edit(_.last(parts));
					} else if (parts.length === 3 && parts[1] === 'page') {
						this.list(_.last(parts));
					} else {
						this.enqueueMessage('error', l('K2_NOT_FOUND'));
					}
				}
			}
		},

		// Proxy function for triggering the app:redirect event
		redirect : function(url, trigger) {
			K2Dispatcher.trigger('app:redirect', url, trigger);
		},

		// Proxy function triggering the app:redirect event
		enqueueMessage : function(type, text) {
			K2Dispatcher.trigger('app:messages:add', type, text);
		},

		// Displays a listing page depending on the requested resource type
		list : function(page) {

			K2Dispatcher.trigger('app:menu:active', this.resource);

			// Load the required files
			require(['collections/' + this.resource, 'views/' + this.resource + '/list', 'views/pagination', 'views/list'], _.bind(function(Collection, View, Pagination, Layout) {

				// Determine the page from the previous request
				if (!page && this.collection) {
					page = this.collection.getState('page');
				}

				// Ensure that we have a page number
				if (!page) {
					page = 1;
				}

				// Create the collection
				this.collection = new Collection();

				// Set the page
				this.collection.setState('page', page);

				// Fetch data from server
				this.collection.fetch({

					// Success callback
					success : _.bind(function(collection, response, options) {

						// Create view
						this.view = new View({
							collection : this.collection,
							isModal : this.isModal
						});

						// Get the pagination model
						var paginationModel = this.collection.getPagination();

						// Pass some data to the pagination model
						paginationModel.set('label', this.resource);
						paginationModel.set('link', this.resource);

						// Create the pagination view
						this.pagination = new Pagination({
							model : paginationModel
						});

						// Create the layout
						var layout = new Layout({
							collection : this.collection
						});

						// Show messages
						_.each(response.messages, _.bind(function(message) {
							this.messages.push(message);
						}, this));
						K2Dispatcher.trigger('app:messages:reset', this);

						// Render the layout to the page
						K2Dispatcher.trigger('app:region:show', layout, 'content', this.resource + '-list');

						// Render views to the layout
						layout.grid.show(this.view);
						layout.pagination.show(this.pagination);

						// Update the URL without triggering the router function
						this.redirect(this.resource + '/page/' + this.collection.getState('page'), false);

					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});

			}, this));
		},

		// Displays a form page depending on the requested resource type
		edit : function(id) {

			// Load the required files
			require(['models/' + this.resource, 'views/' + this.resource + '/edit'], _.bind(function(Model, View) {

				// Create the model
				this.model = new Model();

				// If an id is provided use it
				if (id) {
					this.model.set('id', id);
				} else {
					var catid = jQuery('select#category').val();
				}

				// Fetch the data from server
				this.model.fetch({
					success : _.bind(function(model, response, options) {
						// Create the view
						this.view = new View({
							model : this.model
						});

						// Show messages
						_.each(response.messages, _.bind(function(message) {
							this.messages.push(message);
						}, this));
						K2Dispatcher.trigger('app:messages:reset', this);

						// Render the view
						K2Dispatcher.trigger('app:region:show', this.view, 'content', this.resource + '-form');
						
						// In case the #content contains some embedded scripts, trigger these. These may be editors.
						var editor = jQuery('#content .jw--pane__content > .jw--editor--tab > script').toArray();
						var index, len;
						for (index = 0, len = editor.length; index < len; index++){
							eval (editor[index].text);
						}
						
						// We may have a catid set in case a new article is created. Copy it to the category selector in the article editor
						if (catid) {
							jQuery('select#catid').val(catid);
						}

						// Determine the new URL
						var suffix = (id) ? '/edit/' + id : '/add';

						// Update the URL without triggering the router function
						this.redirect(this.resource + suffix, false);
					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});

			}, this));
		},

		// Save function. Saves the model and redirects properly.
		save : function(redirect, callback) {

			// Reset messages
			K2Dispatcher.trigger('app:messages:reset', this);

			// Trigger the onBeforeSave event if available
			var onBeforeSave = true;
			if ( typeof (this.view.onBeforeSave) === 'function') {
				onBeforeSave = this.view.onBeforeSave();
			}

			if (onBeforeSave) {
				
				// Keep tabs status to session if it's a save operation ( not save and close or save and new )
				if(redirect == 'edit') {
					var tabsState = [];
					var tabs = this.view.$('[data-role="tabs-navigation"]');
					_.each(tabs, function(tab) {
						var item  = jQuery(tab).find('a.active');
						var list = jQuery(tab).find('a');
						tabsState.push(list.index(item));
					});
					if(tabsState.length) {
						K2Session.set('k2.tabs.state', JSON.stringify(tabsState));
					}					
				}

				// Get the form variables
				var input = this.view.$('form').serializeArray();

				// Save
				this.model.save(null, {
					data : input,
					silent : true,
					success : _.bind(function(model, response) {

						this.messages = response.messages;

						if (redirect === 'list') {
							this.list();
						} else if (redirect === 'add') {
							this.edit();
						} else if (redirect === 'edit') {
							this.edit(this.model.get('id'));
						} else if (redirect === 'custom' && callback) {
							this[callback]();
						}
					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});
			}

		},

		// Close function. Checks in the row and redirects to list.
		close : function() {
			if (this.model.isNew() || !this.model.has('checked_out')) {
				this.model.cleanUp(this.resource);
				this.list();
			} else {
				this.model.checkin({
					success : _.bind(function() {
						this.list();
					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});
			}
		},

		// Toggle state function.
		toggleState : function(id, state, model) {
			if ( typeof (model) == 'undefined') {
				model = this.collection.get(id);
			}
			model.toggleState(state, {
				success : _.bind(function() {
					this.list();
				}, this),
				error : _.bind(function(model, xhr, options) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		saveOrder : function(keys, values, column, reset) {
			this.collection.batch(keys, values, column, {
				success : _.bind(function(response) {
					if (reset) {
						this.resetCollection();
					}
					K2Dispatcher.trigger('app:messages:set', response);
				}, this),
				error : _.bind(function(xhr) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		// Destroy function. Deletes an array of rows and renders again the list.
		batchDelete : function(rows) {
			this.collection.destroy(rows, {
				success : _.bind(function(response) {
					this.list();
					K2Dispatcher.trigger('app:messages:set', response);
				}, this),
				error : _.bind(function(xhr) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		// Filter function. Updates the collection states depending on the filters and renders the list again.
		filter : function(state, value, mode) {
			this.collection.setState(state, value);
			// Go to page 1 for new states except sorting and limit
			if (state !== 'sorting' && state !== 'limit' && state !== 'page') {
				this.collection.setState('page', 1);
			}
			if (mode == 'merge') {
				this.mergeCollection();
			} else {
				this.resetCollection();
			}
		},

		// Reset collection.
		resetCollection : function() {
			this.collection.fetch({
				reset : true,
				success : _.bind(function() {
					this.redirect(this.resource + '/page/' + this.collection.getState('page'), false);
				}, this),
				error : _.bind(function(collection, xhr, options) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		// Reset collection.
		mergeCollection : function() {
			this.collection.fetch({
				reset : false,
				remove : false,
				error : _.bind(function(collection, xhr, options) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		// Search
		search : function(search) {
			this.searchCollection = this.collection.clone();
			this.searchCollection.setState('search', search);
			this.searchCollection.fetch({
				reset : false,
				parse : true,
				silent : true,
				success : function(collection, xhr, options) {
					K2Dispatcher.trigger('app:sidebar:search', collection);
				},
				error : _.bind(function(collection, xhr, options) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		// Batch function. Updates the collection states depending on the filters and renders the list again.
		batchSetState : function(rows, value, state) {
			var keys = [];
			var values = [];
			_.each(rows, function(row) {
				keys.push(row.value);
				values.push(value);
			});
			this.collection.batch(keys, values, state, {
				success : _.bind(function(response) {
					this.list();
					K2Dispatcher.trigger('app:messages:set', response);
				}, this),
				error : _.bind(function(xhr) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		// Batch function. Updates the collection states depending on the batch actions and renders the list again.
		batchSetMultipleStates : function(rows, states, mode) {
			var keys = [];
			_.each(rows, function(row) {
				keys.push(row.value);
			});
			this.collection.multibatch(keys, states, mode, {
				success : _.bind(function(response) {
					this.list();
					K2Dispatcher.trigger('app:messages:set', response);
				}, this),
				error : _.bind(function(xhr) {
					this.enqueueMessage('error', xhr.responseText);
				}, this)
			});
		},

		settings : function() {

			// Load the required files
			require(['models/settings', 'views/settings/edit'], _.bind(function(Model, View) {

				// Create the model
				this.model = new Model();

				// Fetch the data from server
				this.model.fetch({

					// Success callback
					success : _.bind(function() {

						// Create the view
						this.view = new View({
							model : this.model
						});

						// Render the view
						K2Dispatcher.trigger('app:region:show', this.view, 'content', 'settings');

					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});

			}, this));
		},

		information : function() {
			
			// Load the required files
			require(['models/information', 'views/information'], _.bind(function(Model, View) {

				// Create the model
				this.model = new Model();

				// Fetch the data from server
				this.model.fetch({

					// Success callback
					success : _.bind(function() {

						// Create the view
						this.view = new View({
							model : this.model
						});

						// Render the view
						K2Dispatcher.trigger('app:region:show', this.view, 'content', 'information');

					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});

			}, this));
		},

		media : function() {
			
			// Load the required files
			require(['models/media', 'views/media/manager'], _.bind(function(Model, View) {

				// Create the model
				this.model = new Model();

				// Fetch the data from server
				this.model.fetch({

					// Success callback
					success : _.bind(function() {

						// Create the view
						this.view = new View({
							model : this.model
						});

						// Render the view
						K2Dispatcher.trigger('app:region:show', this.view, 'content', 'media');

					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});

			}, this));
		},

		utilities : function() {
						
			// Load the required files
			require(['models/utilities', 'views/utilities'], _.bind(function(Model, View) {

				// Create the model
				this.model = new Model();

				// Fetch the data from server
				this.model.fetch({

					// Success callback
					success : _.bind(function() {

						// Create the view
						this.view = new View({
							model : this.model
						});

						// Render the view
						K2Dispatcher.trigger('app:region:show', this.view, 'content', 'utilities');

					}, this),
					error : _.bind(function(model, xhr, options) {
						this.enqueueMessage('error', xhr.responseText);
					}, this)
				});

			}, this));
		},

		browseServer : function(options) {
			require(['views/media/manager'], _.bind(function(K2ViewMediaManager) {
				var view = new K2ViewMediaManager(options);
				K2Dispatcher.trigger('app:region:show', view, 'modal');
			}, this));
		},

		selectRow : function(id) {
			var row = this.collection.get(id);
			parent.K2SelectRow(row);
		}
	});

	return K2Controller;
});
