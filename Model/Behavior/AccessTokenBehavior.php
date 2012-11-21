<?php

App::uses('HttpSocket', 'Network/Http');

/**
 * Description of AccessToken
 *
 * @package cake
 * @subpackage cloudprint
 */
class AccessTokenBehavior extends ModelBehavior {

    var $config = array();

    function setup(\Model $Model, $config = array()) {
        if (!isset($this->config[$Model->alias])) {
            $this->config[$Model->alias] = array(
                'expires' => '3600'
            );
        }
        $this->config[$Model->alias] = array_merge(
                $this->config[$Model->alias], (array) $config);
        Configure::load($this->config[$Model->alias]['Api'] . '.' . $this->config[$Model->alias]['Api'] . "Source");
        $oauth = Configure::read('Apis.' . $this->config[$Model->alias]['Api']);
        if (!empty($oauth)) {
            $path = $oauth['oauth']['scheme'] . '//' . $oauth['hosts']['oauth'] . '/' . $oauth['oauth']['access'];
            $Model->Socket = new HttpSocket($path);
        } else {
            throw new CakeException('API not configured.');
        }
    }
    
/**
 * Used to get Oauth Tokens for the configured API.
 * @param \Model $model
 * @param string $oAuthCode
 * @param string $grant_type
 * @return array array containing access token values
 * @throws CakeException
 */
    function getAccessToken(\Model $model, $oAuthCode, $grant_type) {
        Configure::load($this->config[$model->alias]['Api'] . '.' . $this->config[$model->alias]['Api'] . "Source");
        $credentials = $this->getCredentials($model, $this->config[$model->alias]['Api']);
        $config = Configure::read('Apis.' . $this->config[$model->alias]['Api']);
        $request = array(
            'method' => 'POST');
        $body = array(
            'client_id' => $credentials['key'],
            'client_secret' => $credentials['secret'],
            'grant_type' => $grant_type
        );
        if ($grant_type == "refresh_token") {
            $body['refresh_token'] = $oAuthCode;
        } elseif ($grant_type == "authorization_code") {
            $body['code'] = $oAuthCode;
        }
        $body = substr(Router::queryString($body), 1);
        if ($grant_type == "authorization_code") {
            //append redirect URI to body.
            //it should not be encoded
            $body .= "&redirect_uri=" . $config['callback'];
        }
        $request['body'] = $body;
        $response = $model->Socket->request($request);
        if ($response->isOk()) {
            return json_decode($response->body, true);
        } else {
            // @todo write pretty error handlers. Because someone may eventually care.
            // @todo make exceptions happen at the datasource layer
            throw new CakeException($response->body, $response->code);
        }
    }

    /**
     * Returns oauth credentials for the API
     * @return array containing oauth credentials for the datasource
     */
    function getCredentials(\Model $model) {
        $ds = ConnectionManager::getDataSource(strtolower($this->config[$model->alias]['Api']));
        $credentials = array('key' => $ds->config['login'], 'secret' => $ds->config['password']);       
            return $credentials;
    }

    /**
     *
     * @param array $access_token
     * @return array $access_token refreshed access token
     */
    function getRefreshAccess(\Model $model, $access_token) {
        $refresh = $this->getAccessToken($model, $access_token['refresh_token'], 'refresh_token');
        if ($refresh) {
            $model->id = $access_token['id'];
            $model->saveField('access_token', $refresh['access_token']);
            $access_token['modified'] = $model->field('modified'); #not strictly necessary, adds a db call.
            $access_token['access_token'] = $refresh['access_token'];
            return $access_token;
        }
    }

    /**
     * As written this is more of a "best-guess". The only way we can really be sure that a token is expired is to try to use it.
     * @param array $token array containing an OAuth2 token
     * @return boolean
     */
    function isExpired(\Model $model, $token) {
        $expires = $this->config[$model->alias]['expires'];
        $now = strtotime('now');
        $modified = strtotime($token['modified']);
        $interval = $now -$modified;
        return ($interval > $expires) ? true : false;
    }
    
/**
 * Checks for existing models before saving. Also checks whether Api is set.
 * @param \Model $model
 * @param array $options
 * @return boolean
 */
    function beforeSave(\Model $model, $options = array()) {
        $existing = $model->find('all', array(
           'conditions' => array( 'user_id' => $model->data[$model->alias]['user_id']),
            'callbacks' => 'before'
            ));
        if (!empty($existing[0][$model->alias])) {
            $model->id = $existing[0][$model->alias]['id'];
        }
        if(empty($model->data[$model->alias]['api'])){
            $this->data[$model->alias]['api'] = $this->config[$model->alias]['Api'];
        }
        return true;
    }

    /**
     *  This ensures that only one result is ever returned. Not strictly necessary, but I'm having trouble seeing where you'd ever want more than one at a time.
     * Call it a security measure. Also it makes the afterFind() easier.
     * @param \Model $model
     * @param array $query
     * @return array modified query
     */
    function beforeFind(\Model $model, $query) {
        if ($model->findQueryType != 'first') {
            $model->findQueryType = "first";
        }
        if(empty($query['conditions']['api'])){
            $query['conditions']['api'] = $this->config[$model->alias]['Api'];
        }
        return $query;
    }

    /**
     * Verifies token validity.
     * @param \Model $model
     * @param array $results
     * @param boolean $primary
     */
    function afterFind(\Model $model, $results, $primary = false) {
        if ($primary && $model->findQueryType == "first" && !empty($results['0'][$model->alias]['access_token'])) {
            $token = $results['0'][$model->alias];
            if ($this->isExpired($model, $token)) {
                $refresh = $this->getRefreshAccess($model, $token);
                if ($refresh) {
                    $results['0'][$model->alias]['access_token'] = $refresh['access_token'];
                    $results['0'][$model->alias]['modified'] = $refresh['modified'];
                }
            }
        }
        return $results;
    }

}

?>