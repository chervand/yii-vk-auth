<?php

class VkAuthTest extends \Codeception\TestCase\Test
{
	use \Codeception\Specify;

	/**
	 * @var \UnitTester
	 */
	protected $tester;

	/**
	 * @var string test user access_token
	 */
	protected $access_token = '1fa948c7aa4bf75874ee9abe12084a912d2692f2d061178efff92faed80f5d477aeb9c415f2798c7c6bfe';

	/**
	 * @var string test user ip
	 */
	protected $ip = '213.160.153.2';

	/**
	 * @var int test user id
	 */
	protected $user_id = 11057642;

	/**
	 * @var VkAuth internal object storage
	 */
	protected $vk;

	/**
	 * @var VkAuthToken internal object storage
	 */
	protected $token;

	/**
	 * before every test
	 */
	protected function _before()
	{
		if (isset(Yii::app()->vk)) {
			$this->vk = Yii::app()->vk;
		}
	}

	/**
	 * after every test
	 */
	protected function _after()
	{
		if ($this->token instanceof VkAuthToken) {
			$this->deleteToken($this->token);
		}
	}

	/**
	 * tests DB tables for existence
	 */
	public function testDbTablesExist()
	{
		$this->tester->dontSeeInDatabase('vk_auth_ticket', ['id' => -1]);
		$this->tester->dontSeeInDatabase('vk_auth_token', ['id' => -1]);
	}

	/**
	 * tests classes and objects for existence
	 */
	public function testClassesExist()
	{
		$this->assertTrue(class_exists('VkAuthActiveRecord'));
		$this->assertTrue(class_exists('VkAuthTicket'));
		$this->assertTrue(class_exists('VkAuthToken'));
		$this->assertTrue($this->vk instanceof VkAuth);
	}

	/**
	 * tests ticket CRUD operations
	 */
	public function testTicketCreateDisableDelete()
	{
		$model = new VkAuthTicket();

		// create
		$this->assertTrue($model->save());
		$this->tester->seeInDatabase('vk_auth_ticket', [
			'ticket' => $model->ticket
		]);

		// disable
		$this->assertTrue($model->disable());
		$this->tester->seeInDatabase('vk_auth_ticket', [
			'ticket' => $model->ticket,
			'status' => VkAuthTicket::STATUS_DISABLED
		]);

		// delete soft
		$this->assertTrue($model->delete());
		$this->tester->seeInDatabase('vk_auth_ticket', [
			'ticket' => $model->ticket,
			'status' => VkAuthTicket::STATUS_DELETED
		]);

		// delete hard
		$this->assertTrue($model->delete(true));
		$this->tester->dontSeeInDatabase('vk_auth_ticket', [
			'id' => $model->id
		]);
	}

	/**
	 * tests ticket lifetime (valid and expired)
	 */
	public function testTicketLifetime()
	{
		$model = new VkAuthTicket();

		// fresh ticket is valid
		$model->created_at = time();
		$this->assertTrue($model->isValid);

		// is valid within lifetime
		$model->created_at = time() - (VkAuthTicket::LIFETIME - 1);
		$this->assertTrue($model->isValid);

		// expired
		$model->created_at = time() - (VkAuthTicket::LIFETIME + 1);
		$this->assertFalse($model->isValid);
	}

	/**
	 * tests token CRUD operations
	 * @throws CDbException
	 */
	public function testTokenCreateDisableDelete()
	{
		$model = new VkAuthToken();

		// no attributes
		$this->assertFalse($model->save());

		// ticket not found
		$model->attributes = [
			'auth_ticket' => -1,
			'ip' => '127.0.0.1',
			'access_token' => sha1(rand()),
			'expires_in' => time() + 3600,
			'user_id' => 1,
		];
		$this->assertFalse($model->save());

		// create
		$ticket = new VkAuthTicket();
		$ticket->save();
		$ticket->saveAttributes(['ip' => '127.0.0.1']);
		$model->attributes = [
			'auth_ticket' => $ticket->ticket,
			'ip' => '127.0.0.1',
			'access_token' => sha1(rand()),
			'expires_in' => time() + 3600,
			'user_id' => 1,
		];
		$this->assertTrue($model->save());
		$this->tester->seeInDatabase('vk_auth_token', [
			'ticket_id' => $model->ticket_id,
			'access_token' => $model->access_token
		]);

		// ticket relation
		$this->assertTrue($model->ticket instanceof VkAuthTicket);

		// disable
		$this->assertTrue($model->disable());
		$this->tester->seeInDatabase('vk_auth_token', [
			'ticket_id' => $model->ticket_id,
			'access_token' => $model->access_token,
			'status' => VkAuthToken::STATUS_DISABLED
		]);

		// delete soft
		$this->assertTrue($model->delete());
		$this->tester->seeInDatabase('vk_auth_token', [
			'ticket_id' => $model->ticket_id,
			'access_token' => $model->access_token,
			'status' => VkAuthToken::STATUS_DELETED
		]);

		// delete hard
		$this->assertTrue($model->delete(true));
		$ticket->delete(true);
		$this->tester->dontSeeInDatabase('vk_auth_token', [
			'id' => $model->id
		]);
	}

	/**
	 * tests token verification
	 */
	public function testTokenVerify()
	{
		$this->token = $this->createToken();

		$this->specifyConfig()->cloneOnly('token');

		$this->specify('vk component configured', function () {
			$this->assertInternalType('string', $this->vk->clientId);
			$this->assertInternalType('string', $this->vk->clientSecret);
			$this->assertInternalType('string', $this->vk->accessToken);
		});

		$this->specify('check token', function () {
			$this->assertTrue($this->vk->checkToken($this->token));
		});

		$this->specify('token is vk-valid', function () {
			$this->assertTrue($this->token->verify());
			$this->tester->seeInDatabase('vk_auth_token', [
				'id' => $this->token->id,
				'status' => VkAuthToken::STATUS_VERIFIED
			]);
		});
	}

	/**
	 * tests vk user profile retrieval
	 */
	public function testGetVkUserProfile()
	{
		$this->token = $this->createToken();

		$response = $this->token->request('users.get', [
			'query' => [
				'fields' => 'sex,contacts,photo_200,schools'
			]
		]);

		$this->assertTrue($response instanceof \GuzzleHttp\Psr7\Response);
		$this->assertEquals(200, $response->getStatusCode());

		$body = json_decode((string)$response->getBody());

		$this->assertEquals($this->user_id, $body->response[0]->uid);
		$this->assertObjectHasAttribute('uid', $body->response[0]);
		$this->assertObjectHasAttribute('first_name', $body->response[0]);
		$this->assertObjectHasAttribute('last_name', $body->response[0]);
		$this->assertObjectHasAttribute('sex', $body->response[0]);
		$this->assertObjectHasAttribute('mobile_phone', $body->response[0]);
		$this->assertObjectHasAttribute('photo_200', $body->response[0]);
		$this->assertObjectHasAttribute('schools', $body->response[0]);
	}

	/**
	 * creates a token (with ticket)
	 * @return VkAuthToken
	 * @throws CDbException
	 */
	protected function createToken()
	{
		// create ticket
		$ticket = new VkAuthTicket();
		$this->assertTrue($ticket->save(), json_encode($ticket->errors));
		$ticket->saveAttributes(['ip' => $this->ip]);

		// create token
		$token = new VkAuthToken();
		$token->attributes = [
			'auth_ticket' => $ticket->ticket,
			'ip' => $this->ip,
			'access_token' => $this->access_token,
			'expires_in' => 0,
			'user_id' => $this->user_id
		];
		$this->assertTrue($token->save(), json_encode($token->errors));
		return $token;
	}

	/**
	 * removes a token (with ticket)
	 * @param VkAuthToken $token
	 * @param bool|false $hard
	 * @return bool
	 */
	protected function deleteToken(VkAuthToken $token, $hard = false)
	{
		if ($token->ticket instanceof VkAuthTicket) {
			return $token->ticket->delete($hard);
		}
		return false;
	}
}