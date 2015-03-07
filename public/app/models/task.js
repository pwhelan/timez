var attr = DS.attr,
	hasMany = DS.hasMany,
	belongsTo = DS.belongsTo,
	Model = DS.Model;

Timez.Task = Model.extend({
	name: DS.attr('string'),
	active: DS.attr('boolean'),
	start: DS.attr('date'),
	stop: DS.attr('date')
	//_id: DS.attr('string')
	// states: array ...
	// id: ..
});
