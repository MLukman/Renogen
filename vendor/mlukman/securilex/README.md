# Securilex

[![Packagist Version](https://img.shields.io/packagist/v/mlukman/securilex.svg)](https://packagist.org/packages/mlukman/securilex) [![Build Status](https://travis-ci.org/MLukman/Securilex.svg?branch=master)](https://travis-ci.org/MLukman/Securilex)

Securilex is a simplified security provider for Silex.

The main class for Securilex is `\Securilex\SecurityServiceProvider`.

Usage:

	// $app is instance of \Silex\Application
	$app->register(new \Securilex\ServiceProvider());

    // securilex is now a service
    $app['securilex']-> ...

## Authentication
Authentication deals with identifying user identity via login mechanism. The main class for authentication is `\Securilex\Firewall`.

To define a firewall, you need to define four items:

1. **The path(s) to secure**: whether the whole site (/) or just specific area (/admin/).
2. **The path to login page** = optional, if none specific it will use browser login popup (i.e. Basic HTTP Authentication).
3. **The authentication factory** = method of authentication, e.g. plaintext password validation, encoded password validation, LDAP, OAuth etc.
4. **The user provider** = source to get the list of users, e.g. hard-coded, database etc.

The first two items are required when constructing a Firewall:

	$firewall = new \Securilex\Firewall('/secure/', '/login/');

You can define multiple secure paths to be under the same firewall:

	$firewall = new \Securilex\Firewall(array('/secure/', '/admin/'), '/login/');

The next two items are to be passed to `addAuthenticationFactory` method:

	$firewall->addAuthenticationFactory($authFactory, $userProvider);

Register the firewall to securilex using `addFirewall()` method:

	$app['securilex']->addFirewall($firewall);

### Authentication Factory

Authentication Factory defines the method of authentication of user credentials. Securilex comes with a few ready-to-use authentication factories:

 - **PlaintextPasswordAuthenticationFactory** - simply compares the entered password with the stored plaintext password.
 - **LdapBindAuthenticationFactory** - authenticates login using external LDAP service.
 - **InitializableAuthenticationFactory** - provides authentication mechanism that remembers the first password that users enter after registration/resetting the password. Requires a user provider that implements `MutableUserProviderInterface` (will be further explained in the next section).
 - **SimpleAuthenticationFactory** - delegates authentication to another class that implements `SimpleAuthenticatorInterface`.

You can create additional factories by:
 1. implementing `AuthenticationFactoryInterface` (`LdapBindAuthenticationFactory` does this), or 
 2. extending `SimpleAuthenticationFactory` while implementing `SimpleAuthenticatorInterface` (`PlaintextPasswordAuthenticationFactory` and `InitializableAuthenticationFactory` do this).

### User Provider

User Provider provides the list of users who have access to the corresponding firewall. Securilex supports all Symfony's existing user providers while it also provides additional ones:

- **NoPasswordUserProvider** - when the application cannot know the passwords of the users, for example when using `LdapBindAuthenticationFactory`.
- **UserProviderSegmentalizer** - when a list of users needs to be segmentalized to be authenticated by different authentication factories. Instead of defining two user providers with slightly different parameters and potentially causing redundant calls to database or external source, using segmentalizer will cache the user data while it is being authenticated by multiple authentication factories.

## Authorization
Authorization deals with allowing or denying access to page or resource.

// TODO: Work In Progress