<?php

/**
 * Class VkAuth implements an application component for working with vk.com API.
 * todo: access_token retrieval, not implemented >> expire: 0 == never
 * @author chervand <chervand@gmail.com>
 */
class VkAuth extends CApplicationComponent
{
	/**
	 * success response code
	 */
	const STATUS_SUCCESS = 1;

	/**
	 * @var string vk.com API version
	 */
	public $v = '5.37';

	/**
	 * @var string app ID
	 * @link http://vk.com/dev/secure_how_to
	 */
	public $clientId;

	/**
	 * @var string app secret key
	 * @link http://vk.com/dev/secure_how_to
	 */
	public $clientSecret;

	/**
	 * @var string vk.com client app access_token to call server methods
	 * @link http://vk.com/dev/secure_how_to
	 */
	public $accessToken;

	/**
	 * @var \GuzzleHttp\Client
	 */
	protected $guzzleClient;

	public function init()
	{
		parent::init();
		$this->guzzleClient = new \GuzzleHttp\Client(['base_uri' => 'https://api.vk.com/method/']);
	}

	/**
	 * Checks token via vk.com.
	 * @link http://vk.com/dev/secure.checkToken
	 * @param VkAuthToken $token
	 * @return bool
	 */
	public function checkToken(VkAuthToken &$token)
	{
		$response = $this->request('secure.checkToken', [
			'query' => [
				'v' => $this->v,
				'client_id' => $this->clientId,
				'client_secret' => $this->clientSecret,
				'access_token' => $this->accessToken,
				'ip' => $token->ip,
				'token' => $token->access_token,
			],
		]);

		if ($response->getStatusCode() == 200) {

			$body = json_decode((string)$response->getBody());

			if (
				is_object($body)
				&& isset($body->response, $body->response->success)
				&& $body->response->success == self::STATUS_SUCCESS
				&& isset ($body->response, $body->response->user_id)
				&& $body->response->user_id == $token->user_id
			) {
				return true;
			}

		}

		return false;
	}

	public function request($uri, $options = [], $method = 'GET')
	{
		return $this->guzzleClient->request($method, $uri, $options);
	}
}