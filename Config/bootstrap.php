<?php

//The model you used for your users.
Configure::write('Apis.UserModel','User');

//The model used to store Oauth Tokens.
Configure::write('Apis'.'OauthTokenModel','OauthToken');

//Oauth Token fields.
Configure::write('Apis.OauthTokenModel.access_token','access_token');
Configure::write('Apis.OauthTokenModel.refresh_token','refresh_token');
