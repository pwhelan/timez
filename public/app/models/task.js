var attr = DS.attr,
	hasMany = DS.hasMany,
	belongsTo = DS.belongsTo,
	Model = DS.Model;

Timez.Task = Model.extend({
	name:	attr('string'),
	active:	attr('boolean'),
	start:	attr('date'),
	stop:	attr('date'),
	states:	hasMany('state', { async: true }),
	notes:	hasMany('note', { async: true })
});
