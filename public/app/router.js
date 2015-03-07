Timez.Router.map(function() {
	this.resource('task', { path: '/web' });
});

Timez.Router.reopen({
	location: 'history'
});

Timez.TaskRoute = Ember.Route.extend({
	
	model: function() {
		return this.store.find('task', 0);
	}
	
});
