<?php
/**
 * OauthToken Model for storing OauthToken Data. Depending on the API you are using, you may need
 * to add new fields to suit your needs.
 * @author Paul Marshall <www.protelligence.com>
 *
 */
class OauthToken extends ApisAppModel {

    public $belongsTo = array(
            'User' => array('className' => 'User'
            ));

    public function getRefreshTokenByAccessToken($accessToken = null) {
        $result = $this->field($config['Apis']['OauthTokenModel']['refresh_token'], array('conditions' => array($config['Apis']['OauthTokenModel']['access_token'] => $accessToken)));
        if (isset($result[$config['Apis']['OauthTokenModel']][$config['Apis']['OauthTokenModel']['refresh_token']])) {
            return $result[$config['Apis']['OauthTokenModel']][$config['Apis']['OauthTokenModel']['refresh_token']];
        }
        return false;
    }
}