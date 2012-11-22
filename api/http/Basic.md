#Basic access authentication
If an HTTP receives an anonymous request for a protected resource it can force the use of Basic authentication by rejecting the request with a 401 (Access Denied) status code and setting the WWW-Authenticate __response header__ as shown below:

    HTTP/1.1 401 Unauthorized
    WWW-Authenticate: Basic realm="Sign in with your Crew Corner account"

The word Basic in the WWW-Authenticate selects the authentication mechanism that the HTTP client must use to access the resource. The realm string can be set to any value to identify the secure area and may used by HTTP clients to manage passwords.

Most web browsers will display a login dialog when this response is received, allowing the user to enter a username and password. This information is then used to retry the request with an Authorization __request header__:

    GET /1/user/get/635 HTTP/1.1
    Host: api.crew.dreamhack.se
    Authorization: Basic dXNlcm5hbWU6cGFzc3dvcmQ=

The Authorization specifies the authentication mechanism (in this case Basic) followed by the username and password. Although, the string dXNlcm5hbWU6cGFzc3dvcmQ= may look encrypted it is simply a base64 encoded version of <username>:<password>. In this example, the un-encoded string "username:password" was used and would be readily available to anyone who could intercept the HTTP request.

## Using curl

    curl --user name:password "http://api.crew.dreamhack.se/1/user/get/635?fields=uid,username"
