<?php

App::uses('BaseAuthorize', 'Controller/Component/Auth');

class OauthAuthorize extends BaseAuthorize {

    function authorize($user, \CakeRequest $request) {
        $plugin = (empty($request->params['plugin'])) ? null : '.' . $request->params['plugin'];
        if (!empty($this->controller()->Apis)) {
            $apiNames[] = $this->controller()->Apis;
        } else {
            $apiNames[] = $request->params['controller'];
        }
        foreach ($apiNames as $apiName) {
            $className = $apiName . "Token";
            App::uses($className, $plugin . 'Model');
            if (!class_exists($className)) {
                throw new NotFoundException("Token model for Api $apiName does not exist");
            }
            $this->{$className} = ClassRegistry::init($className);
            $token = $this->{$className}->getTokenDb($user['id']);
            if (!empty($token['access_token'])) {
                $this->setAccessToken(strtolower($apiName), $token['access_token']);
            } else {
                return false;
            }
        }
        return true;
    }

    function setAccessToken($datasource, $token) {
        $ds = ConnectionManager::getDataSource($datasource);
        $ds->config['access_token'] = $token;
    }

}

?>