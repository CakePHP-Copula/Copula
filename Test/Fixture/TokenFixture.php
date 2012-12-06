<?php

class TokenFixture extends CakeTestFixture {

	var $name = "Token";
	var $fields = array(
		'id' => array(
			'type' => 'integer',
			'key' => 'primary'
		),
		'user_id' => array(
			'type' => 'integer',
			'key' => 'index',
			'null' => false
		),
		'access_token' => array(
			'type' => 'string',
			'null' => false
		),
		'refresh_token' => array(
			'type' => 'string',
			'null' => false
		),
		'modified' => 'datetime',
		'api' => array(
			'type' => 'string',
			'null' => false
		)
	);
	var $records = array(
		array(
			'id' => '2',
			'user_id' => '1',
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'modified' => '2012-11-07 23:10:18',
			'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk',
			'api' => 'testapi'
		),
		array(
			'id' => '3',
			'user_id' => '2',
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'modified' => '2012-11-07 23:10:18',
			'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk',
			'api' => 'testapi'
		)
	);

}

?>