<?php

App::uses('Token', 'Cloudprint.Model');
App::uses('AccessTokenBehavior', 'Cloudprint.Model/Behavior');
App::uses('HttpSocket', 'Network/Http');
App::uses('HttpResponse', 'Network/Http');

class TokenTestModel extends CakeTestModel {

    public $name = "Token";
    public $useTable = false;
    public $actsAs = array(
        'Cloudprint.AccessToken' => array(
            'expires' => '3600',
            'Api' => 'Cloudprint'
        )
    );
    protected $_schema = array(
        'id' => array(
            'type' => 'integer',
            'key' => 'primary',
            'length' => '11'
        ),
        'user_id' => array(
            'type' => 'integer',
            'key' => 'index',
            'null' => false,
            'length' => '11'
        ),
        'access_token' => array(
            'type' => 'string',
            'null' => false
        ),
        'refresh_token' => array(
            'type' => 'string',
            'null' => false
        ),
        'modified' => 'dateTime',
        'api' => array(
            'type' => 'string',
            'null' => false
        )
    );

}

/**
 * Tests functionality of AccessToken behavior
 * @property Token $Token
 */
class AccessTokenBehaviorTestCase extends CakeTestCase {

    var $fixtures = array('plugin.cloudprint.token');

    function setUp() {
        parent::setUp();
        $this->Token = new TokenTestModel();
    }

    function tearDown() {
        unset($this->Token);
        parent::tearDown();
    }

    function testSetup() {
        $this->assertTrue(isset($this->Token->Behaviors->AccessToken->config['Token']));
        $this->assertTrue(is_object($this->Token->Socket));
    }

    function testGetCredentials() {
        $results = $this->Token->getCredentials();
        $this->assertTrue(!empty($results['key']) && !empty($results['secret']));
    }

    function testGetAccessToken() {
        unset($this->Token->Socket);
        $response = new HttpResponse();
        $response->body = json_encode(array(
            'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
            'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk'
                ));
        $response->code = '200';
        $this->getMock('HttpSocket', array('request'), array(), 'MockHttpSocket');
        $this->Token->Socket = new MockHttpSocket();
        $this->Token->Socket->expects($this->once())
                ->method('request')
                ->will($this->returnValue($response));
        $result = $this->Token->getAccessToken('code', 'authorization_code');
        $expected = array(
            'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
            'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk'
        );
        $this->assertEquals($expected, $result);
    }

    function testIsExpired() {
        $newer['modified'] = date('Y-m-d H:i:s', strtotime('-5 min'));
        $older['modified'] = '2012-11-07 23:10:18';
        $this->assertTrue($this->Token->isExpired($older));
        $this->assertFalse($this->Token->isExpired($newer));
    }

    function testGetRefreshAccess() {
        $this->getMock('AccessTokenBehavior', array('getAccessToken'), array(), 'MockAccess');
        $this->Access = new MockAccess();
        $token = array(
            'access_token' => 'test1',
            'refresh_token' => 'replace'
        );
        $this->Access->expects($this->once())
                ->method('getAccessToken')
                ->will($this->returnValue($token));
        $this->getMock('TokenTestModel', array('field', 'saveField'), array(), 'MockModel');
        $this->Model = new MockModel();
        $this->Model->expects($this->once())->method('saveField');
        $this->Model->expects($this->once())
                ->method('field')
                ->will($this->returnValue('test3'));
        $results =$this->Access->getRefreshAccess($this->Model, array('access_token' => 'replace', 'refresh_token' => 'replace', 'id' => '1'));
        $expected = array(
            'access_token' => 'test1',
            'refresh_token' => 'replace',
            'modified' => 'test3',
            'id' => '1'
        );
        $this->assertEquals('1', $this->Model->id);
        $this->assertEquals($expected,$results);
    }

    function testAfterFind() {
        $token = $this->Token->find('all', array(
            'conditions' => array('user_id' => '1'),
            'callbacks' => 'before'
        ));
        $this->getMock('AccessTokenBehavior', array('isExpired', 'getRefreshAccess'), array(), 'MockAccess');
        $this->Access = new MockAccess();
        $this->Access->expects($this->any())
                ->method('isExpired')
                ->will($this->onConsecutiveCalls(false, true));
        $expected = array(
            'access_token' => 'ya29.AHES6ZTopEd2PaRCaLZDd0B9TKNqdt857DYrlC-Welo9d84LaElzAg',
            'refresh_token' => '1/jr6xd0f83uXDh-sBE3eO_lo8qMr11pOQXalzfTAYXGk'
        );
        $this->Access->expects($this->once())
                ->method('getRefreshAccess')
                ->will($this->returnValue($expected));
        $results = $this->Access->afterFind($this->Token, $token, true);
        $this->assertEquals($results[0]['Token']['access_token'], $expected['access_token']);
        $this->assertEquals($results[0]['Token']['refresh_token'], $expected['refresh_token']);
    }

    function testBeforeSave() {
        $result = $this->Token->find('all', array(
            'conditions' => array('user_id' => '1'),
            'callbacks' => 'before'
                ));
        unset($result[0]['Token']['api']);
        $this->assertEquals($result[0], $this->Token->save($result[0]));
        $id = $result[0]['Token']['id'];
        $this->Token->id = '';
        $result[0]['Token']['id'] = '';
        $result[0]['Token']['user_id'] = '99';
        $this->Token->save($result[0]);
        $this->assertNotEquals($id, $this->Token->id);
    }

    function testBeforeFind() {
        $result = $this->Token->find('all', array(
            'conditions' => array('user_id' => '1'),
            'callbacks' => 'before'
                ));
        $this->assertEquals('1', count($result['0']));
    }

}

?>