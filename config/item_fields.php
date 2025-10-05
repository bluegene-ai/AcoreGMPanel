<?php
/**
 * File: config/item_fields.php
 * Purpose: Provides functionality for the config module.
 */

return [
	'base' => [
		'label' => 'lang:app.item.config.groups.base.label',
		'fields' => [
			['name'=>'name','label'=>'lang:app.item.config.groups.base.fields.name.label','type'=>'text'],
			['name'=>'quality','label'=>'lang:app.item.config.groups.base.fields.quality.label','type'=>'select','enum'=>'qualities'],
			['name'=>'class','label'=>'lang:app.item.config.groups.base.fields.class.label','type'=>'select','enum'=>'classes'],
			['name'=>'subclass','label'=>'lang:app.item.config.groups.base.fields.subclass.label','type'=>'select','enum'=>'subclasses','depends'=>'class'],
			['name'=>'itemlevel','label'=>'lang:app.item.config.groups.base.fields.itemlevel.label','type'=>'number'],
			['name'=>'requiredlevel','label'=>'lang:app.item.config.groups.base.fields.requiredlevel.label','type'=>'number'],
			['name'=>'stackable','label'=>'lang:app.item.config.groups.base.fields.stackable.label','type'=>'number'],
			['name'=>'maxcount','label'=>'lang:app.item.config.groups.base.fields.maxcount.label','type'=>'number'],
			['name'=>'containerslots','label'=>'lang:app.item.config.groups.base.fields.containerslots.label','type'=>'number'],
			['name'=>'inventorytype','label'=>'lang:app.item.config.groups.base.fields.inventorytype.label','type'=>'number'],
			['name'=>'allowableclass','label'=>'lang:app.item.config.groups.base.fields.allowableclass.label','type'=>'number'],
			['name'=>'allowablerace','label'=>'lang:app.item.config.groups.base.fields.allowablerace.label','type'=>'number'],
		]
	],
	'combat' => [
		'label'=>'lang:app.item.config.groups.combat.label',
		'fields'=>[
			['name'=>'dmg_min1','label'=>'lang:app.item.config.groups.combat.fields.dmg_min1.label','type'=>'number'],
			['name'=>'dmg_max1','label'=>'lang:app.item.config.groups.combat.fields.dmg_max1.label','type'=>'number'],
			['name'=>'delay','label'=>'lang:app.item.config.groups.combat.fields.delay.label','type'=>'number'],
			['name'=>'ammo_type','label'=>'lang:app.item.config.groups.combat.fields.ammo_type.label','type'=>'number'],
			['name'=>'range_mod','label'=>'lang:app.item.config.groups.combat.fields.range_mod.label','type'=>'number'],
		]
	],
	'resist' => [
		'label'=>'lang:app.item.config.groups.resist.label',
		'fields'=>[
			['name'=>'armor','label'=>'lang:app.item.config.groups.resist.fields.armor.label','type'=>'number'],
			['name'=>'holy_res','label'=>'lang:app.item.config.groups.resist.fields.holy_res.label','type'=>'number'],
			['name'=>'fire_res','label'=>'lang:app.item.config.groups.resist.fields.fire_res.label','type'=>'number'],
			['name'=>'nature_res','label'=>'lang:app.item.config.groups.resist.fields.nature_res.label','type'=>'number'],
			['name'=>'frost_res','label'=>'lang:app.item.config.groups.resist.fields.frost_res.label','type'=>'number'],
			['name'=>'shadow_res','label'=>'lang:app.item.config.groups.resist.fields.shadow_res.label','type'=>'number'],
			['name'=>'arcane_res','label'=>'lang:app.item.config.groups.resist.fields.arcane_res.label','type'=>'number'],
		]
	],
	'req' => [
		'label'=>'lang:app.item.config.groups.req.label',
		'fields'=>[
			['name'=>'requiredskill','label'=>'lang:app.item.config.groups.req.fields.requiredskill.label','type'=>'number'],
			['name'=>'requiredskillrank','label'=>'lang:app.item.config.groups.req.fields.requiredskillrank.label','type'=>'number'],
			['name'=>'requiredspell','label'=>'lang:app.item.config.groups.req.fields.requiredspell.label','type'=>'number'],
			['name'=>'requiredreputationfaction','label'=>'lang:app.item.config.groups.req.fields.requiredreputationfaction.label','type'=>'number'],
			['name'=>'requiredreputationrank','label'=>'lang:app.item.config.groups.req.fields.requiredreputationrank.label','type'=>'number'],
			['name'=>'bonding','label'=>'lang:app.item.config.groups.req.fields.bonding.label','type'=>'number'],
			['name'=>'startquest','label'=>'lang:app.item.config.groups.req.fields.startquest.label','type'=>'number'],
		]
	],
	'socket' => [
		'label'=>'lang:app.item.config.groups.socket.label',
		'fields'=>[
			['name'=>'socketbonus','label'=>'lang:app.item.config.groups.socket.fields.socketbonus.label','type'=>'number'],
			['name'=>'gemproperties','label'=>'lang:app.item.config.groups.socket.fields.gemproperties.label','type'=>'number'],
		]
	],
	'economy' => [
		'label'=>'lang:app.item.config.groups.economy.label',
		'fields'=>[
			['name'=>'buyprice','label'=>'lang:app.item.config.groups.economy.fields.buyprice.label','type'=>'number'],
			['name'=>'sellprice','label'=>'lang:app.item.config.groups.economy.fields.sellprice.label','type'=>'number'],
			['name'=>'minMoneyLoot','label'=>'lang:app.item.config.groups.economy.fields.minMoneyLoot.label','type'=>'number'],
			['name'=>'maxMoneyLoot','label'=>'lang:app.item.config.groups.economy.fields.maxMoneyLoot.label','type'=>'number'],
			['name'=>'duration','label'=>'lang:app.item.config.groups.economy.fields.duration.label','type'=>'number'],
			['name'=>'randomproperty','label'=>'lang:app.item.config.groups.economy.fields.randomproperty.label','type'=>'number'],
			['name'=>'randomsuffix','label'=>'lang:app.item.config.groups.economy.fields.randomsuffix.label','type'=>'number'],
			['name'=>'material','label'=>'lang:app.item.config.groups.economy.fields.material.label','type'=>'number'],
			['name'=>'sheath','label'=>'lang:app.item.config.groups.economy.fields.sheath.label','type'=>'number'],
			['name'=>'bagfamily','label'=>'lang:app.item.config.groups.economy.fields.bagfamily.label','type'=>'number'],
		]
	],
	'stats' => [
		'label' => 'lang:app.item.config.groups.stats.label',
		'repeat' => [
			'count' => 5,
			'pattern' => [
				['name'=>'stat_type{n}','label'=>'lang:app.item.config.groups.stats.repeat.pattern.stat_type.label','type'=>'number'],
				['name'=>'stat_value{n}','label'=>'lang:app.item.config.groups.stats.repeat.pattern.stat_value.label','type'=>'number']
			],
			'trailing' => [
				['name'=>'scalingstatdistribution','label'=>'lang:app.item.config.groups.stats.repeat.trailing.scalingstatdistribution.label','type'=>'number'],
				['name'=>'scalingstatvalue','label'=>'lang:app.item.config.groups.stats.repeat.trailing.scalingstatvalue.label','type'=>'number']
			]
		]
	],
	'spells' => [
		'label' => 'lang:app.item.config.groups.spells.label',
		'repeat' => [
			'count' => 3,
			'pattern' => [
				['name'=>'spellid_{n}','label'=>'lang:app.item.config.groups.spells.repeat.pattern.spellid.label','type'=>'number'],
				['name'=>'spelltrigger_{n}','label'=>'lang:app.item.config.groups.spells.repeat.pattern.spelltrigger.label','type'=>'number'],
				['name'=>'spellcharges_{n}','label'=>'lang:app.item.config.groups.spells.repeat.pattern.spellcharges.label','type'=>'number']
			]
		]
	],
];

