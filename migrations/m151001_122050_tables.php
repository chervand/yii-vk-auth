<?php

class m151001_122050_tables extends CDbMigration
{
	public function createTable($table, $columns)
	{
		parent::createTable($table, array_merge($columns, [
			'created_at' => 'integer null',
			'updated_at' => 'integer null',
			'status' => 'bool not null default true',
			'key (status)'
		]), 'ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci');
	}

	public function safeUp()
	{
		$this->createTable('vk_auth_ticket', [
			'id' => 'pk',
			'ticket' => 'string not null',
			'ip' => 'string null',
			'register' => 'bool not null default false'
		]);

		$this->createTable('vk_auth_token', [
			'id' => 'pk',
			'ticket_id' => 'integer not null',
			'ip' => 'string not null',
			'access_token' => 'string not null',
			'expires_in' => 'integer not null',
			'user_id' => 'integer not null',
			'foreign key (ticket_id) references vk_auth_ticket (id) on delete cascade on update cascade',
		]);
	}

	public function safeDown()
	{
		$this->dropTable('vk_auth_token');
		$this->dropTable('vk_auth_ticket');
		$this->dropTable('vk_auth_client');
	}
}