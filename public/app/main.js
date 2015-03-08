window.Timez = Ember.Application.create({
	rootElement: '#content'
});

Ember.Handlebars.registerBoundHelper('date-format', function(value, format) {
	return moment(value).format(format);
});

Timez.TasksController = Ember.Controller.extend({
	// initial value
	isExpanded: false,
	actions: {
		expand: function() {
			this.set('isExpanded', true);
		},
		contract: function() {
			this.set('isExpanded', false);
		},
		toggle: function() {
			this.set('isExpanded', !this.get('isExpanded'));
		},
		summarize: function() {
			
		}
	}
});
