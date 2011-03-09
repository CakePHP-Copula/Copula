# Apis Plugin

Since I started going through several restful apis things started to become repitive. I decided to layout my code in a more 'proper ' fashion then.

## Installation

1. Clone or download to `plugins/apis`

3. Add your configuration to `database.php` and set it to the model

<pre><code>
:: database.php ::
var $myapi = array(
	'datasource' => 'Apis.Apis',
	'driver' => 'ApiPluginName.ApiPluginName' // Example: 'Github.Github'
	
	// These are only required for authenticated requests (write-access)
	'login' => '--Your API Key--',
	'password' => '--Your API Secret--',
);

:: my_model.php ::
var $useDbConfig = 'myapi';
</code></pre>

### Follow the api-specific instructions found in the respective plugin