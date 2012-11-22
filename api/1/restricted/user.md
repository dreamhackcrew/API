##1/user/get/[uid]
Fetches a single user from a uid (user id). If no uid is provided the authorized users data is returned.

###Parameters
fields - uid, username, firstname, lastname, profile_picture, badge_picture

###Example
<http://api.crew.dreamhack.se/1/user/get/635?fields=uid,username>

##1/user/list
Fetches all users that is a member of the same events as the authorized user.

###Parameters
* fields - uid, username, firstname, lastname
* event - limit search to a specific event

###Example
* <http://api.crew.dreamhack.se/1/user/list?fields=uid,username>
* <http://api.crew.dreamhack.se/1/user/list?event=10178>

##1/user/search/[search text]
Searches for a specific username and returns a list, almost like the list method.

###Parameters
* fields - uid, username, firstname, lastname
* event - limit search to a specific event

###Example
* <http://api.crew.dreamhack.se/1/user/search/jonaz>
