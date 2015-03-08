var attr = DS.attr,
	hasMany = DS.hasMany,
	belongsTo = DS.belongsTo,
	Model = DS.Model;

Timez.State = Model.extend({
	pid:	attr('number'),
	idle:	attr('number'),
	exec:	attr('string'),
	name:	attr('string'),
	cwd:	attr('string'),
	task:	belongsTo('task', { async: true })
});
