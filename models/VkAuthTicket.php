<?php

/**
 * Class VkAuthTicket
 * @property int $id
 * @property string $ticket
 * @property string $ip retrieved by server
 * @property int $created_at
 * @property int $updated_at
 * @property int $status
 * @property bool $isValid
 */
class VkAuthTicket extends VkAuthActiveRecord
{
	const LIFETIME = 300;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'vk_auth_ticket';
	}

	public function rules()
	{
		return [
			['register', 'default', 'value' => false]
		];
	}

	public function relations()
	{
		return [
			'token' => [self::HAS_ONE, 'VkAuthToken', 'ticket_id']
		];
	}

	public function scopes()
	{
		return array_merge(parent::scopes(), [
			'valid' => ['condition' => $this->tableAlias . '.created_at>=' . time() - self::LIFETIME],
		]);
	}

	public function getIsValid()
	{
		return $this->created_at >= time() - self::LIFETIME;
	}

	protected function beforeSave()
	{
		if (parent::beforeSave()) {
			if ($this->isNewRecord) {
				$this->ticket = $this->createTicket();
				$this->ip = $this->retrieveClientIp();
			}
			return true;
		}
		return false;
	}

	protected function createTicket()
	{
		$ticket = sha1(rand() . time());

		$exists = $this->exists([
			'scopes' => ['active', 'valid'],
			'condition' => 't.ticket=:ticket',
			'params' => [':ticket' => $ticket]
		]);

		if ($exists == true) {
			$ticket = $this->createTicket();
		}

		return $ticket;
	}
}