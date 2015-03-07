window.Timez = Ember.Application.create({
	rootElement: '#content'
});

DS.RESTAdapter.extend({
	serializer: DS.RESTSerializer.extend({
		primaryKey: '_id'
	})
});
