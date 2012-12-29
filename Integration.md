#Integration Guide for Copula v0.1

Introduction by way of Disclaimer:

This is unfortunately not a drop-in, set-it-and-forget-it plugin. This is a way to integrate remote API features into your CakePHP application with a minimum of effort.

As with most plugins, it is not intended for users to edit the plugin code or configuration directly. Functionality changes should be implemented in *your* code. One design goal with this project has been to make such changes easy.

This document does not address all possible configurations, only the most common scenarios. 

This code is provided ‘as-is’ with no warranty express or implied. Use at your own risk.

---

1. [Config](#config)
    1. [Bootstrap](#bootstrap)
    2. [Database Config](#database-config)  
        1. [Api Datasource](#api-datasource)  
        2. [Token Datasource](#token-datasource)  
    3. [Paths](#paths)
    4. [Routes](#routes)

2. [The Controller Layer](#the-controller-layer)
    1. [Component Functions](#component-functions)

3. [The Model Layer](#the-model-layer)
    1. [Using APIs in Your Model](#using-apis-in-your-model)

4. [The Datasource Layer](#the-datasource-layer)
    1. [Extending the Datasource](#extending-the-datasource)

5. [Further Integration](#further-integration)

---

<a id="config"><h2>1. Config</h2></a>

Copula’s configuration follows the normal CakePHP conventions for the most part. The equivalent of a database schema is our paths file, which contains information about where to locate remote resources and how to query them. This is the complicated part of integration; the other sections are much shorter.

<br />
<a id="bootstrap"><h3>1.1 Bootstrap</h3></a>

Copula should be loaded as normal in your app’s `bootstrap.php`, using `CakePlugin::load()`. It does not require any routes or a bootstrap file of its own to be loaded.

Your code on the other hand will probably need to load the configuration file containing your paths in the bootstrap file using `Configure::load()`. Copula will not load the paths automatically for you; the way the Configure class is written makes this phenomenally hard to test.

<br />
<a id="database"><h3>1.2 Database</h3></a>

Copula provides a schema file for persistent storage of access tokens. Storing tokens in the database is optional but recommended, and is assumed to be the default method.

Additionally, each OAuth API is split into two datasources, both of which must be configured in your app’s Config/database.php file.

An example is given below:

```php

	var $cloudprint = array(
		'datasource' => 'Cloudprint.CloudprintSource',
		'login' => 'username',
		'password' => 'secretPassword',
		'authMethod' => 'OAuthV2',
		'access_token' => '',
		'refresh_token' => ''
	);

	var $cloudprintToken = array(
		'datasource' => 'Copula.RemoteTokenSource',
		'login' => 'username',
		'password' => 'secretPassword',
		'authMethod' => 'OAuthV2',
		'scheme' => 'https',
		'authorize' => 'o/oauth2/auth',
		'access' => 'o/oauth2/token',
		'host' => 'accounts.google.com',
		'scope' => 'https://www.googleapis.com/auth/cloudprint',
		'callback' => 'https://mysite.com/oauth2callback'
	);

```

The second configuration block is used to retrieve access and request tokens from the OAuth provider, and the first one is used for the actual API operations. Keys common to both blocks are:

<dl>
<dt><b><code>login</code></b></dt>
<dd>This must equal the <code>consumer_key</code> (OAuth) or <code>client_id</code> (OAuth 2.0)</dd>
<dt><b><code>password</code></b></dt>
<dd>This must equal the <code>consumer_secret</code> (OAuth) or <code>client_secret</code> (OAuth 2.0)</dd>
<dt><b><code>authMethod</code></b></dt>
<dd>This must equal either <code>'OAuth'</code> or <code>'OAuthV2'</code>. Those are the only allowed values.</dd>
<dt><b><code>scheme</code></b></dt>
<dd>Valid values: <code>'http'</code> or <code>'https'</code>. Many, if not most, APIs prefer you to use HTTPS.</dd>
</dl>

Also common to both configurations is the `'datasource'` key, which is required by CakePHP. By default the API datasource should be set to `'Copula.ApisSource'` and the Token datasource should be set to `'Copula.RemoteTokenSource'`. You may use any value for this key provided that the referenced class extends the default one.
<br />
<a id="api-datasource"><h4>1.2.1 API Datasource</h4></a>

No additional configuration beyond the above is required, but if your app will only ever use one set of credentials, you may manually set them in the API Datasource config using the following keys:

<dl>
<dt><b><code>access_token</code></b></dt>
<dd>This must equal to the access token given to you by the OAuth provider for both OAuth and OAuth 2.0 datasources.</dd>

<dt><b><code>token_secret</code></b></dt>
<dd><i>(optional)</i> If using OAuth, this must be equal to the access token secret given by your OAuth provider. If using OAuth 2.0, omit this field.</dd>

<dt><b><code>refresh_token</code></b></dt>
<dd><i>(optional)</i> If using OAuth 2.0, this field must be equal to the refresh token given by your OAuth provider. If not using OAuth 2.0, omit this field.</dd>
</dl>

At the time of this writing, actually using static OAuth 2.0 credentials is probably a bad idea. Not only that, but supporting that use case is not high on the feature list. For a more thorough discussion, consult [Section 4. Extending the Datasource.](#extending-the-datasource)
<br />
<a id="token-datasource"><h4>1.2.2 Token Datasource</h4></a>

In addition to the aforementioned keys belonging to both configs, the following keys are used in the Token datasource config. **Note:** there is a naming convention here: the name of the Token database config needs to be the same as the matching API config plus the string `'Token'`. Future versions may combine the two configs and dynamically generate the proper info. If you're reading this, that hasn't happened.

<dl>
<dt><b><code>host</code></b></dt>
<dd>This must be equal to the hostname of the OAuth provider's credential server. This may or may not differ from the host used to provide the actual API services.</dd>
<dt><b><code>authorize</code></b></dt>
<dd>This contains the relative path for the authorization endpoint of the OAuth provider's credential server, <i>without</i> a prefixed slash.</dd>
<dt><b><code>access</code></b></dt>
<dd>This contains the relative path for the access token endpoint of the OAuth provider's credential server, <i>without</i> a prefixed slash.</dd>
<dt><b><code>request</code></b></dt>
<dd><i>(optional)</i> This contains the relative path for the request endpoint of the OAuth provider's credential server, <i>without</i> a prefixed slash. Not used in OAuth 2.0</dd>
<dt><b><code>scope</code></b></dt>
<dd><i>(optional)</i> Some API providers require this field; consult your API documentation for more details.</dd>
<dt><b><code>callback</code></b></dt>
<dd>This must contain the address on your server to which the user will be redirected after authorization.</dd>
</dl>

For more Detail on the OAuth authorization process, consult the following links:

 * [OAuth v1 information](http://developer.yahoo.com/oauth/guide/oauth-auth-flow.html)
 * [OAuth v2 information](https://developers.google.com/accounts/docs/OAuth2#webserver)

For the details on how these methods have been implemented, see [Section 2.1 Component Functions](#component-functions).

<br />
<a id="paths"><h3>1.3 Paths</h3></a>

Paths are configured in whichever config file you want. For consistency I would recommend using `Config/paths.php`
You are responsible for loading this file (using `Configure::load()`) before the ApisSource datasource needs it. As previously mentioned, `bootstrap.php` is a convenient place for that.

API paths must be ordered from most specific conditions to least (or none). This is because the map is iterated through
until the first path which has all of its required conditions met is found. If a path has no required conditions, it will
be used. Optional conditions aren't checked, but are added when building the request.

```php

	$config['Copula']['MyPlugin']['path']['host'] = 'www.example.com/api';
	$config['Copula']['MyPlugin']['read'] = array(
	// section
	'people' => array(
		// api url
		'people/id=' => array(
			// required conditions
			'id',
		),
		'people/url=' => array(
			'url',
		),
		'people/~' => array(),
	),
	'people-search' => array(
		'people-search' => array(
		// optional conditions the api call can take
			'optional' => array(
				'keywords',
			),
		),
	),
	);
	$config['Copula']['MyPlugin']['write'] = array(
	);
	$config['Copula']['MyPlugin']['update'] = array(
	);
	$config['Copula']['MyPlugin']['delete'] = array(
	);

```

You can specify the section by passing a `'section'` key in `find()` requests, otherwise it will default to the value of `$useTable` for the model making the request.

<br />
<a id="routes"><h3>1.4 Routes</h3></a>

Route configuration is done in the normal `Config/routes.php` file. Configuration is limited to whatever is necessary to make your OAuth callback address work.

Example:  
`Router::connect('/oauth2callback', array('plugin' => 'myplugin', 'controller' => 'widgets', 'action' => 'callback'));`

---

<br />
<a id="the-controller-layer"><h2>2. The Controller Layer</h2></a>

The controller-level features of Copula are easy to integrate. The Authorization functions are implemented as a normal CakePHP Authorize object. There are actually some limitations to this by-the-book approach, but it also makes it easy to develop a replacement. For more detail on the subject, see [Section 5. Going Further](#going-further).

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

The CakePHP manual goes into great detail about how to attach both [auth objects](http://book.cakephp.org/2.0/en/core-libraries/components/authentication.html#configuring-authorization-handlers) and [components](http://book.cakephp.org/2.0/en/controllers/components.html#configuring-components), so that will not be covered here, except to note that neither class has any configurable settings (so far).

<br />
<a id="component-functions"><h3>2.1 Component Functions</h3></a>

If you are using the provided authorization object, there is no need to explicitly direct the user to an authorization action; they will be redirected to the OAuth provider after an authorization failure. You must, however, implement an action somewhere in your application to handle the callback from the OAuth provider. For those that missed the links on what actually happens in this process, now is a good time to review that information:

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

`$useTable` should refer to a section in your path configuration, as defined in [Paths](#paths).

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

Earlier in this document you may have noticed a suggestion that using static OAuth 2.0 credentials was a bad idea. If you've been following along, you may also have noticed that expired tokens are refreshed when they are retrieved from storage. The ApisSource datasource provides no methods for detecting token expiration. Additionally, it is highly unlikely that any methods could be implemented, as the behavior of the API at that point is not defined. Most APIs will return a 403 error, but this might also be returned for other reasons.

When using static OAuth 2.0 credentials, it is highly likely that you will end up using an expired token, whereas most other implementations should be able to avoid this. It will therefore be imperative that you detect this error and recover from it.

This guide can only offer a general guideline to how token refreshing might be implemented. Do not use the example; write your own version. That goes double if you're using static OAuth 2.0 credentials. 

```php
<?php
	#filename: Plugin/Example/Model/Datasource/ExampleSource.php

	class ExampleSource extends ApisSource {
		public function afterRequest(Model $model, HttpSocketResponse $response){
			if($response->code == 403 && !(strpos($response->body, 'Expired Token') === false)){
				$id = AuthComponent::user('id');
				$model->authorize($model, $id);
				return $this->request($model);
			}
			return parent::afterRequest($model, $response);
	}
```
The other big reason for extending the APIs datasource is to implement the `beforeRequest()` callback. Changing the behavior of `decode()` and the logging functions may also be advisable.

```php

	#filename: Plugin/Example/Model/Datasource/ExampleSource.php

	<?php
	App::uses('ApisSource', 'Copula.Model/Datasource');
	class ExampleSource extends ApisSource {
		// Key => Values substitutions in the uri-path right before the request is made. Scans uri-path for :keyname
		public $tokens = array();
		// Last minute tweaks
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