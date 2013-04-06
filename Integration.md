#Integration Guide for Copula v0.1

Introduction by way of Disclaimer:

This is unfortunately not a drop-in, set-it-and-forget-it plugin. This is a way to integrate remote API features into your CakePHP application with a minimum of effort.

As with most plugins, it is not intended for users to edit the plugin code or configuration directly. Functionality changes should be implemented in *your* code. One design goal with this project has been to make such changes easy.

This document does not address all possible configurations, only the most common scenarios. 

This code is provided ‘as-is’ with no warranty express or implied. Use at your own risk.

---

1. [Config](#1-config)
    1. [Bootstrap](#11-bootstrap)
    2. [Database](#12-database)  
    3. [Hosts](#13-hosts) 
    4. [Paths](#14-paths)
    5. [Routes](#15-routes)

2. [The Controller Layer](#2-the-controller-layer)
    1. [Component Functions](#21-component-functions)

3. [The Model Layer](#3-the-model-layer)
    1. [Using APIs in Your Model](#31-using-apis-in-your-model)

4. [The Datasource Layer](#4-the-datasource-layer)
    1. [Extending the Datasource](#41-extending-the-datasource)

5. [Further Integration](#5-further-integration)

---

<a id="config"><h2>1. Config</h2></a>

Copula’s configuration follows the normal CakePHP conventions for the most part. The equivalent of a database schema is our paths file, which contains information about where to locate remote resources and how to query them. 

<br />
<a id="bootstrap"><h3>1.1 Bootstrap</h3></a>

Copula should be loaded as normal in your app’s `bootstrap.php`, using `CakePlugin::load()`. It does not require any routes or a bootstrap file of its own to be loaded. If you're creating a plugin, same thing: load Copula in your bootstrap file.

Your code will probably also need to load two configuration files: one containing your paths, the other containing authorization configuration. A good place to load the files is in the bootstrap file. Copula will not load the paths automatically for you; the way the Configure class is written makes this phenomenally hard to test.

```php

	<?php
	Configure::load('MyPlugin.paths');
	Configure::load('MyPlugin.hosts');
```



<br />
<a id="database"><h3>1.2 Database</h3></a>

Copula provides a schema file for persistent storage of access tokens. Storing tokens in the database is optional but recommended, and is the default method.

Additionally, you must configure a datasource for your plugin.

An example is given below:

```php

	var $cloudprint = array(
		'datasource' => 'MyApi.ApisSource',
		'login' => 'username',
		'password' => 'secretPassword',
	);
```

####Database Array Keys

<dl>
<dt><b><code>datasource</code></b></dt>
<dd>This must be set to either <code>'Copula.ApisSource'</code> or some other class that extends ApisSource</dd>
<dt><b><code>login</code></b></dt>
<dd>For OAuth v1, this must be equal to the <code>oauth_consumer_key</code> given by your OAuth provider. For OAuth v2, this field must be equal to the <code>client_id</code> given by your OAuth provider.</dd>
<dt><b><code>password</code></b></dt>
<dd>For OAuth v1, this must be equal to the <code>oauth_consumer_secret</code> given by your OAuth provider. For OAuth v2, this field must be equal to the <code>client_secret</code> given by your OAuth provider.</dd>
</dl>

<br />
<a id="hosts"><h3>1.3 Hosts</h3></a>

For the purposes of this document, the hosts configuration file is assumed to be present at `YourPlugin/Config/hosts.php`. It follows the normal pattern for CakePHP configuration files. An example is shown below:

```php

	<?php
	$config['Copula']['cloudprint']['Auth'] = array(
		'authMethod' => 'OAuthV2',
		'scheme' => 'https',
		'authorize' => 'o/oauth2/auth',
		'access' => 'o/oauth2/token',
		'host' => 'accounts.google.com',
		'scope' => 'https://www.googleapis.com/auth/cloudprint',
		'callback' => 'https://example.com/oauth2callback/'
	);

	$config['Copula']['cloudprint']['Api'] = array(
		'host' => 'www.google.com/cloudprint',
		'authMethod' => 'OAuthV2'
	);

 ```

This example is for a plugin that implements Google's Cloud Print API. Settings here will override any default configuration options set in the datasource layer.

Internally Copula uses two different datasources in its operation: the first one is only used to retrieve request tokens (OAuth v1) and access tokens (OAuth v1, v2). You will probably not ever need to modify it or use it directly.

####Hosts Array Keys
<dl>
<dt><b><code>scheme</code></b></dt>
<dd><i>(optional)</i> Valid values are <code>'http'</code> or <code>'https'</code>. This sets the transfer protocol for the datasource. This key may be used in either section.</dd>
<dt><b><code>authMethod</code></b></dt>
<dd>Valid values are <code>'OAuth'</code> for OAuth v1, or <code>'OAuthV2'</code> for OAuth v2. This field <b>MUST</b> be set in both sections, and <b>MUST</b> be set to the same value in both places.</dd>
<dt><b><code>host</code></b></dt>
<dd>This must be equal to the hostname of the OAuth provider's credential server. This may or may not differ from the host used to provide the actual API services. <b>MUST</b> be set in both sections.</dd>
<dt><b><code>authorize</code></b></dt>
<dd>This contains the relative path for the authorization endpoint of the OAuth provider's credential server, <i>without</i> a prefixed slash. Required for <code>'Auth'</code> section. Not used in <code>'Api'</code> section.</dd>
<dt><b><code>access</code></b></dt>
<dd>This contains the relative path for the access token endpoint of the OAuth provider's credential server, <i>without</i> a prefixed slash. Required for <code>'Auth'</code> section. Not used in <code>'Api'</code> section.</dd>
<dt><b><code>request</code></b></dt>
<dd><i>(optional)</i> This contains the relative path for the request endpoint of the OAuth provider's credential server, <i>without</i> a prefixed slash. Not used in OAuth v2. Required for <code>'Auth'</code> section. Not used in <code>'Api'</code> section.</dd>
<dt><b><code>scope</code></b></dt>
<dd><i>(optional)</i> Some API providers require this field; consult your API documentation for more details. Required for <code>'Auth'</code> section. Not used in <code>'Api'</code> section.</dd>
<dt><b><code>callback</code></b></dt>
<dd>This must contain the address on your server to which the user will be redirected after authorization. Required for <code>'Auth'</code> section. Not used in <code>'Api'</code> section.</dd>
</dl>

>For more Detail on the OAuth authorization process, consult the following links:
>
> * [OAuth v1 information](http://developer.yahoo.com/oauth/guide/oauth-auth-flow.html)
> * [OAuth v2 information](https://developers.google.com/accounts/docs/OAuth2#webserver)
>
>For the details on how these methods have been implemented, see [Section 2.1 Component Functions](#21-component-functions).

<br />
<a id="paths"><h3>1.4 Paths</h3></a>

Paths are configured in whichever config file you want. For consistency it is recommended to use `YourPlugin/Config/paths.php`
You are responsible for loading this file (using `Configure::load()`) before the ApisSource datasource needs it. As previously mentioned, `bootstrap.php` is a convenient place for that.

Optional conditions aren't checked, but are added when building the request.

```php

	$config['Copula']['MyPlugin']['read'] = array(
	//endpoint, selected by Model::$useTable;
	'people' => array(
		'path' => 'people/id',
		'required' => array('id'),
		'optional' => array()
		),
	'url' => array(
		'path' => 'people/url=',
		'required' => array('url')	# 'optional' key omitted
		),
	'home' => array(
		'path' => 'people/~'	# 'path' is the only required key
		),
	'people-search' => array(
		'path' => 'people-search',
		'optional' => array('keywords')
		)
	);
	$config['Copula']['MyPlugin']['write'] = array(
	);
	$config['Copula']['MyPlugin']['update'] = array(
	);
	$config['Copula']['MyPlugin']['delete'] = array(
	);

```

Endpoints are selected by the value of `Model::$useTable` for the model making the request. If a specified endpoint does not exist in the paths configuration, an exception will be thrown. As the example above shows, empty arrays do not need to be present.

In the table above, endpoints would be selected by setting `Model::$useTable` to e.g. `'home'` or `'people-search'`. Optionally, you may create arbitrarily deeply nested maps, and use a dot-delimited syntax to select endpoints.

```php

	$config['Copula']['MyPlugin']['read'] = array(
		'people' => array(
			'searches' => array(
				'people-search' => array(
					'path' => 'people-search',
					'optional' => array('keywords')
				)
			)
		)
	);

```

The above would be selected by setting `$this->useTable = 'people.searches.people-search';` in your Model file. This can aid in organization for complex API maps.
<br />
<a id="routes"><h3>1.5 Routes</h3></a>

Route configuration is done in the normal `Config/routes.php` file. Configuration is limited to whatever is necessary to make your OAuth callback address work.

Example:  
`Router::connect('/oauth2callback', array('plugin' => 'myplugin', 'controller' => 'widgets', 'action' => 'callback'));`

---

<br />
<a id="the-controller-layer"><h2>2. The Controller Layer</h2></a>

The controller-level features of Copula are easy to integrate. The Authorization functions are implemented as a normal CakePHP Authorize object. There are actually some limitations to this by-the-book approach, but it also makes it easy to develop a replacement. For more detail on the subject, see [Section 5. Going Further](#5-going-further).

Where were we? So you have an Authorize object, which just exists to provide a yes/no answer to "Does this person have some sort of access token?" and a Component that implements all the OAuth functions related to initially getting those tokens. Both of these expect the controller they're attached to to have a public property `$Apis`.

```php

	class MyController extends AppController{
		public $Apis = array('twitter', 'facebook');
	}

```

The name of the API here must be an exact match for a database configuration variable. You can also specify where the access tokens are stored:

```php

		public $Apis = array('github' => array('store' => 'Session'));
```

Valid values for the `'store'` key are (currently) `'Db'` or `'Session'`. Support for storing tokens in persistent cookies may be added in the future.

The CakePHP manual goes into great detail about how to attach both [auth objects](http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#configuring-authorization-handlers) and [components](http://book.cakephp.org/2.0/en/controllers/components.html#configuring-components), so that will not be covered here.

The OauthComponent has one configuration option, which may be specified when attaching it to a controller.

```php

	public $components = array('Oauth' => array('autoAuth' => false));
```

The `'autoAuth'` key determines whether the plugin will handle OAuth authorization failures automatically; the default value is `true`. Your application may require some custom handling of authorization requests, in which case you would set `'autoAuth'` to `false`. 

<br />
<a id="component-functions"><h3>2.1 Component Functions</h3></a>

If you are using the provided authorization object, there is no need to explicitly direct the user to an authorization action; they will be redirected to the OAuth provider after an OAuth-related authorization failure. You must, however, implement an action somewhere in your application to handle the callback from the OAuth provider. For those that missed the links on what actually happens in this process, now is a good time to review that information:

 * [OAuth v1 information](http://developer.yahoo.com/oauth/guide/oauth-auth-flow.html)
 * [OAuth v2 information](https://developers.google.com/accounts/docs/OAuth2#webserver)

If you do not need any special behavior for any step in this process, define a callback method as follows:

```php
<?php
	class MyApiController extends AppController {
		function callback(){
			$this->Oauth->callback('myApi');
		}
	}
```

The Oauth component uses a Session variable `'Oauth.redirect'` to determine if users should be redirected elsewhere after a successful authorization. It sets this variable to the value of `$this->controller->request->here` when it begins to handle an Authorization failure, so hopefully after the user gets done authorizing your application's use of the Remote API on their behalf, they end up doing what they originally wanted to do on your site. If `'Oauth.redirect'` is not set, the callback function will return an array containing the new access token.

The OauthComponent exposes a method for each stage of the OAuth authorization process, for both OAuth v1 and OAuth v2.

For OAuth v1, the functions are:

`OauthComponent::getOauthRequestToken()` : This obtains the request token to be used in the authorization request.

`OauthComponent::authorize()` : This returns the query string that the user should be redirected to in order to authorize your application.

`OauthComponent::getAccessToken()` : After the user authorizes your application, this method exchanges the authorization code for an access token used for making API requests.

For OAuth v2, the functions are:

`OauthComponent::authorizeV2()` : This returns the query string that the user should be redirected to in order to authorize your application.

`OauthComponent::getAccessTokenV2()` : This exchanges the authorization code returned by the OAuth server for an access token used for making API requests.

Most users will not need to customize any functionality.

---

<br />
<a id="the-model-layer"><h2>3. The Model Layer</h2></a>

Copula provides a very simple interface at the Model layer, in the form of the OAuthConsumer Behavior. This is attached as normal for CakePHP using the `$actsAs` property. 

```php

	<?php
	class ApiModel extends AppModel{
		public $actsAs = array('Copula.OAuthConsumer' => array('autoFetch' => false));
	}
```

The `'autoFetch'` key determines whether or not an access token is automatically fetched from the local database. If you set it to `false`, you must manually call `authorize()` before using any API functions. **Note:** If you are not storing access tokens in the database, you must set `'autoFetch'` to `false`. An example of this is shown below:

```php

	<?php
	class ApiModel extends AppModel{
		protected function _authorize(){
			$TokenStore = ClassRegistry::init('Copula.TokenStoreSession');
			$user_id = AuthComponent::user('id');
			return $this->authorize($user_id, $TokenStore, 'myApi');
		}
	}
```

The authorize() function retrieves tokens from storage, if they exist, and throws an exception if they do not exist. OAuth 2.0 tokens have their expiration checked when retrieved from the database. This will probably fail hard if your time zone is not set correctly. If the token is determined to be expired, it will be refreshed automatically. If a token is expired and cannot be refreshed, an exception will be thrown. The token is then merged with the model's datasource configuration.

The OAuthConsumer Behavior also provides a method for models to switch datasources. If you don't understand how that might be useful, you probably don't need to use it.

<br />
<a id="using-apis-in-your-model"><h3>3.1 Using APIs In Your Model</h3></a>

The simplest model configuration would probably look something like the following:

```php

	<?php
	class Widget extends AppModel {
		public $useDbConfig = 'myapi';
		public $useTable = 'job';
		public $actsAs = array('Copula.OAuthConsumer');
	}

```

`$useTable` should refer to a section in your path configuration, as defined in [Paths](#14-paths).

You should be able to use `find()` and `save()` as normal. Updating records *might* work, and deleting records is probably going to take a lot of hacking on the part of the Copula developers, due to the way those things are implemented in CakePHP. Also, they may not even make sense in the context of a given API, so you may be on your own there. Feedback on this subject would be nice.

---

<br />
<a id="the-datasource-layer"><h2>4. The Datasource Layer</h2></a>

Nothing here is really meant to be interacted with directly. If you really must do so, create a request array as detailed [here](http://book.cakephp.org/2.0/en/core-utility-libraries/httpsocket.html#HttpSocket::request), attach it to a Model as so:
`$model->request = $request;`
Then instantiate ApisSource and call `ApisSource::request($model);`

If those instructions were not clear, you probably shouldn't attempt them.

<br />
<a id="extending-the-datasource"><h3>4.1 Extending the Datasource</h3></a>

####Oauth v2 Token Expiration

If you've been following along, you may also have noticed that expired tokens are refreshed when they are retrieved from storage. This should not be a problem under most circumstances -- it can be expected to cause problems, however, if a token is valid when retrieved but expires before it is used. Generally the elapsed time between retrieval and use is a matter of milliseconds, which makes this error fairly unlikely, but given enough users and requests, someone will be unlucky.

The ApisSource datasource provides no methods for detecting token expiration. Additionally, it is highly unlikely that any methods could be implemented, as the behavior of the API at that point is not defined. Most APIs will return a 403 error, but this might also be returned for other reasons.

This document can only offer a general guideline to how token refreshing might be implemented. **Do not use this example; write your own version.**

```php

	<?php
	#filename: Plugin/Example/Model/Datasource/ExampleSource.php

	App::uses('ApisSource', 'Copula.Model/Datasource');

	class ExampleSource extends ApisSource {
		public function afterRequest(Model $model, HttpSocketResponse $response){
			if($response->code == 403 && (strpos($response->body, 'Expired Token') !== false)){
				$id = AuthComponent::user('id');
				$model->authorize($model, $id);
				return $this->request($model);
			}
			return parent::afterRequest($model, $response);
	}
```

In the event that you are not able to write a function to refresh the token, any subsequent requests should detect the expired token and handle it as normal, so if all else fails pop up a message asking the user to retry the action, and it should work.

#### beforeRequest, afterRequest

The other big reason for extending the APIs datasource is to implement the `beforeRequest()` callback. Changing the behavior of `decode()` and the logging functions may also be advisable.

```php

	<?php
	#filename: Plugin/Example/Model/Datasource/ExampleSource.php

	App::uses('ApisSource', 'Copula.Model/Datasource');

	class ExampleSource extends ApisSource {
		public function beforeRequest(Model $model) {
			$model->request['header']['x-li-format'] = $this->config['format'];
			return $model->request;
		}
	}
```

It should be noted that the `beforeRequest()` callback should return the request array. Similarly, `afterRequest()` should return the (possibly processed) results.

---

<br />
<a id="going-further"><h2>5. Going Further</h2></a>

We made it! That wasn't so bad, was it? So now that we've told you all the basic steps for integration, there are a couple things that could be improved upon. Since this code is distributed as a plugin rather than a full application, we rely on you to bring your own user model. Unhappily, the only thing we can really rely on to be present is an 'id' field. We can't even rely on telling you to edit your user model, because that degrades the ability for this plugin to be used in other plugins, which is kind of the whole point.

Ideally though, the oauth tokens would be retrieved at login, and stored in the Session with the rest of the user data. This would allow lots of the code to be much simpler. If you do this, please let the Copula developers know about it; it would be nice to be able to point people in that direction.

That's it. Happy Coding.