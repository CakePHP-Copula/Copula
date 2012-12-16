<?php

interface TokenStoreInterface {
	public function getToken($userId, $apiName);
	public function saveToken(array $access_token, $apiName, $userId, $version);
	public function checkToken($userId, $apiName);
}

?>