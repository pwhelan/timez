var attr = DS.attr,
	hasMany = DS.hasMany,
	belongsTo = DS.belongsTo,
	Model = DS.Model;

Timez.Note = Model.extend({
	date:	attr('date'),
	text:	attr('string'),
	task:	belongsTo('task', { async: true })
});
