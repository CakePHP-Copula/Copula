![Copula logo](http://img.photobucket.com/albums/v295/Tenebrous/Copula/copula-logo_zps9cf00474.jpg)
#Copula - bringing it all together

Copula is a CakePHP plugin for interacting with remote APIs like Facebook and Google. It takes care of all the boring details: you don't have to worry about connecting to the service or storing access tokens.

> *The word copula derives from the Latin noun for a "link" or "tie" that connects two different things.*

Copula is, in itself, not very useful, and primarily aimed at developers.

##Features

* Supports both OAuth and OAuth v2
* Access tokens can be stored in the session, or persisted in your database
* Integrates with CakePHP's built-in Authorization features

In addition, Copula is well-tested, and working towards 100% code coverage.

##Installation

* Clone or download Copula to `app/Plugin/Copula`
* Follow the [Integration Guide](https://github.com/CakePHP-Copula/Copula/blob/master/Integration.md)

##Implementations

Copula has recently been rewritten and is necessarily incompatible with any plugins using previous versions. The code has been tested to work with Twitter (OAuth v1) and Google Cloud Print (OAuth v2). You may expect this section to be expanded shortly.

##License

Copula is &copy; Patrick Leahy and Dean Sofer. HttpSocketOAuth is &copy; Dean Sofer and Neil Crookes. All code is dual-licensed under both the MIT license and the GNU GPL version 2 or later.
