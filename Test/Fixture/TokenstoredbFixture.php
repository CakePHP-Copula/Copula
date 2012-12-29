<?php

class TokenstoredbFixture extends CakeTestFixture {

	var $name = "Tokenstoredb";
	var $table = 'tokens';
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
			'null' => true
		),
		'modified' => 'datetime',
		'api' => array(
			'type' => 'string',
			'null' => false
		),
		'expires_in' => array('type' => 'string', 'null' => true),
		'token_secret' => array('type' => 'string', 'null' => true)
	);
	var $records = array(
		array(
			'id' => '2',
			'user_id' => '1',
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'modified' => '2012-11-07 23:10:18',
			'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk',
			'api' => 'testapi',
			'expires_in' => '3600',
			'token_secret' => null
		),
		array(
			'id' => '3',
			'user_id' => '2',
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'modified' => '2012-11-07 23:10:18',
			'token_secret' => 'Ed2PaR3eO_lo8qMDd0B9TK',
			'api' => 'testapi',
			'refresh_token' => null,
			'expires_in' => null
		),
		array(
			'id' => '4',
			'user_id' => '3',
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'modified' => '2012-11-07 23:10:18',
			'refresh_token' => '',
			'api' => 'cloudprint',
			'expires_in' => '3600',
			'token_secret' => null
		)
	);

	public function init() {
		$this->records[] = array(
			'id' => '5',
			'user_id' => '6',
			'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
			'modified' => (string) date('Y-m-d H:i:s'),
			'refresh_token' =>'',
			'api' => 'cloudprint',
			'expires_in' => '3600',
			'token_secret' => null
		);
	}

}

?>