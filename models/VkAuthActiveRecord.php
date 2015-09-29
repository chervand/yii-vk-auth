<?php

class VkAuthActiveRecord extends CActiveRecord
{
	const STATUS_DELETED = -1;
	const STATUS_DISABLED = 0;
	const STATUS_ACTIVE = 1;

	public function disable()
	{
		return $this->saveAttributes([
			'updated_at' => time(),
			'status' => self::STATUS_DISABLED
		]);
	}

	public function delete($hard = false)
	{
		if ($hard === true) {
			return parent::delete();
		}

		return $this->saveAttributes([
			'updated_at' => time(),
			'status' => self::STATUS_DELETED
		]);
	}

	public function scopes()
	{
		return [
			'deleted' => ['condition' => $this->tableAlias . '.status=' . self::STATUS_DELETED],
			'disabled' => ['condition' => $this->tableAlias . '.status=' . self::STATUS_DISABLED],
			'active' => ['condition' => $this->tableAlias . '.status=' . self::STATUS_ACTIVE],
		];
	}

	protected function beforeSave()
	{
		if (parent::beforeSave()) {
			if ($this->isNewRecord) {
				$this->created_at = time();
				$this->status = self::STATUS_ACTIVE;
			}
			$this->updated_at = time();
			return true;
		}
		return false;
	}

	protected function retrieveClientIp()
	{
		$headers = [
			'HTTP_X_REAL_IP',
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR'
		];

		foreach ($headers as $header) {
			if (isset($_SERVER[$header]) && !empty($_SERVER[$header])) {
				return $_SERVER[$header];
			}
		}

		return null;
	}

}