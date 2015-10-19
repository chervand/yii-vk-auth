<?php

/**
 * Class VkAuthToken
 * @property int $id
 * @property int $ticket_id
 * @property string $ip provided by client
 * @property string $access_token
 * @property int $expires_in
 * @property int $user_id
 * @property int $created_at
 * @property int $updated_at
 * @property int $status
 * @property VkAuthTicket $ticket
 */
class VkAuthToken extends VkAuthActiveRecord
{
	const STATUS_VERIFIED = 10;

	public $auth_ticket;

	public static function model($className = __CLASS__)
	{
		return parent::model($className);
	}

	public function tableName()
	{
		return 'vk_auth_token';
	}

	public function rules()
	{
		return [
			['auth_ticket, access_token, expires_in, user_id', 'required'],
			['auth_ticket', 'vTicket'],
			['ip', 'default', 'value' => $this->retrieveClientIp()]
		];
	}

	public function vTicket()
	{
		$ticket = VkAuthTicket::model()->find([
			'scopes' => ['active', 'valid'],
			'condition' => 't.ticket=:ticket AND t.ip=:ip',
			'params' => [
				':ticket' => $this->auth_ticket,
				':ip' => $this->ip
			]
		]);
		if ($ticket instanceof VkAuthTicket) {
			$this->ticket_id = $ticket->id;
		} else {
			$this->ticket_id = null;
			$this->addError('auth_ticket', 'Ticket does not exist or is not valid.');
		}
	}

	public function relations()
	{
		return [
			'ticket' => [self::BELONGS_TO, 'VkAuthTicket', 'ticket_id']
		];
	}

	public function verify()
	{
		/** @var VkAuth $vk */
		$vk = Yii::app()->vk;

		if ($vk->checkToken($this)) {
			$this->saveAttributes([
				'updated_at' => time(),
				'status' => self::STATUS_VERIFIED
			]);
			return true;
		}

		return false;
	}

	public function request($uri, $options = [], $method = 'GET')
	{
		/** @var VkAuth $vk */
		$vk = Yii::app()->vk;

		$options['query']['access_token'] = $this->access_token;
		return $vk->request($uri, $options, $method);
	}
}