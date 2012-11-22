#Features

* Secure - Users are not required to share their passwords with 3rd party applications, increasing account security.
* Standard - A wealth of client libraries and example code are compatible.

##API v1.0a's Authentication Model
The goal is to get an __access token__. The access token is a pair of keys that is permanent (without timeout). The access token keys is used to authorize a request to the restricted section of the API.

1. Step 1 - The request token

    __http://api.crew.dreamhack.se/oauth/request\_token__

1. Step 2 - Send the user to the sign in page

    __http://api.crew.dreamhack.se/oauth/authorize__

1. Step 3 - Upgrade the token to an access_token

    __http://api.crew.dreamhack.se/oauth/access\_token__


![](http://oauth.net/core/diagram.png)

## OAuth signature
This is the hard part. The secret parameter is never sent between server and client. So to prevent man-in-the-middle attacts the OAuth protocol has a parameter called signature. This signature is created by the client and checked by the server. So every time you are sending a request to a OAuth provider you need to sign the request.

The first thing you need to do is collect some parameters. The string that you are going to sign is: <HTTP METHOD>&<Base URL>&<All request parameters(GET,POST and OAuth parameters)>

So for this example request:

    POST /1/user/get?include_entities=true HTTP/1.1
    Accept: */*
    Connection: close
    User-Agent: foobar
    Content-Type: application/x-www-form-urlencoded
    Content-Length: 76
    Host: api.crew.dreamhack.se
    Authorization: 
            OAuth oauth_consumer_key=xvz1evFS4wEEPTGEFPHBog&
                  oauth_nonce=kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg&
                  oauth_signature_method=HMAC-SHA&
                  oauth_timestamp=1318622958&
                  oauth_token=370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb&
                  oauth_version=1.0

    status=Hello%20Ladies%20%2b%20Gentlemen%2c%20a%20signed%20OAuth%20request%21

1. To make the parameter string you need to collect all parameters (POST, GET and OAuth parameters)
1. URL encode both key and value
1. Sort all parameters alphabetically by encoded key
1. Combine the parameters to a URL-encoded query string

The parameter string will be

    include_entities=true&oauth_consumer_key=xvz1evFS4wEEPTGEFPHBog&oauth_nonce=kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg&oauth_signature_method=HMAC-SHA1&oauth_timestamp=1318622958&oauth_token=370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb&oauth_version=1.0&status=Hello%20Ladies%20%2B%20Gentlemen%2C%20a%20signed%20OAuth%20request%21

The next step is to use this string and sign it with the signing key. The signing key is <Consumer secret>&<Token secret>.


## Step 1 - The request token
The __request token__ is a key pair that is limited to a 5 minute use after it is issued. You need the request token to unlock the next step.

    POST /oauth/request_token HTTP/1.1
    User-Agent: foobar
    Host: api.crew.dreamhack.se
    Accept: */*
    Authorization: 
            OAuth oauth_callback="http://mycallback-url",
                  oauth_consumer_key="cChZNFj6T5R0TigYB9yd1w",      (you as a developer obtain this key thru the "Request API key"-form at api.crew.dreamhack.se)
                  oauth_nonce="ea9ec8429b68d6b77cd5600adbbb0456",   (this is a uniqe number that is only used once)
                  oauth_signature="F1Li3tvehgcraF8DMJ7OyxO4w9Y%3D", (this is a string hased with your Customer secret, so the server knows that its from your app)
                  oauth_signature_method="HMAC-SHA1",               (the signature hashing method, for now is only HMAC-SHA1 available)
                  oauth_timestamp="1318467427",                     (the current unix timestamp)
                  oauth_version="1.0"                               (what oauth api version that is used)

Your application should examine the HTTP status of the response. Any value other than 200 indicates a failure. The body of the response will contain the oauth_token, oauth_token_secret, and oauth_callback_confirmed parameters. Your application should verify that oauth_callback_confirmed is true and store the other two values for the next steps.

The response is __JSON encoded__ as standard, so if you want it in an other format (say URL encoded) then change the request url to /oauth/request_token.url

    {oauth_token:"NPcudxy0yU5T3tBzho7iCotZ3cnetKwcTIRlX0iwRl0",oauth_token_secret:"veNRnAWe6inFuo8o2u8SLLZLjolYDmDP7SzL0YfYI",oauth_callback_confirmed:"true"}

Now save the oauth_token and oauth_token_secret and continue to the next step.

## Step 2 - Send the user to the sign in page
Now that you have the __request token__ you can send the user to the authenticate url (/oauth/authenticate). Mobile and desktop apps should open a new browser window or direct to the URL via an embedded web view.

The __request token__ must be included in the request. Ether sent as a Authorization-header or a querystring. 

    <?php
        header("Location: http://api.crew.dreamhack.se/oauth/authorize?oauth_token=NPcudxy0yU5T3tBzho7iCotZ3cnetKwcTIRlX0iwRl0");
    ?>

When the user has authenticated it will be redirected to the __callback url__ with a new __oauth\_token__ and __oauth\_verifier__

    http://mycallback-url?oauth_token=NPcudxy0yU5T3tBzho7iCotZ3cnetKwcTIRlX0iwRl0&oauth_verifier=uw7NjWHT6OJ1MpJOXsHfNxoAhPKpgI8BlYDhxEjIBY

## Step 3 - Upgrade the token to an access_token
This step works exactly like step 1. But you use the token you got from step 2 instead and send the verifier as a post variable.

    POST /oauth/access_token HTTP/1.1
    User-Agent: foobar
    Host: api.crew.dreamhack.se
    Accept: */*
    Authorization: 
            OAuth oauth_consumer_key="cChZNFj6T5R0TigYB9yd1w",      (you as a developer obtain this key thru the "Request API key"-form at api.crew.dreamhack.se)
                  oauth_nonce="ea9ec8429b68d6b77cd5600adbbb0456",   (this is a uniqe number that is only used once)
                  oauth_signature="F1Li3tvehgcraF8DMJ7OyxO4w9Y%3D", (this is a string hased with your Customer secret, so the server knows that its from your app)
                  oauth_signature_method="HMAC-SHA1",               (the signature hashing method, for now is only HMAC-SHA1 available)
                  oauth_timestamp="1318467427",                     (the current unix timestamp)
                  oauth_token="NPcudxy0yU5T3tBzho7iCotZ3cnetKwcTIRlX0iwRl0", (the token you got from step 2)
                  oauth_version="1.0"                               (what oauth api version that is used)
    Content-Length: 57
    Content-Type: application/x-www-form-urlencoded

    oauth_verifier=uw7NjWHT6OJ1MpJOXsHfNxoAhPKpgI8BlYDhxEjIBY
    
The response will be something like

    {oauth_token:"7588892-kagSNqWge8gB1WwE3plnFsJHAZVfxWD7Vb57p0b4",oauth_token_secret:"PbKfYqSryyeKDWz4ebtY3o5ogNLG11WJuZBc9fQrQo"}

Now youre done! The token and secret you got from step 3 can be used to gain access to the restricted area of the API. 
