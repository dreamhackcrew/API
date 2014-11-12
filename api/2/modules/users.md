# Users

* <http://api.crew.dreamhack.se/2/users> -- gets all users
* <http://api.crew.dreamhack.se/2/users/256&event=10194> -- gets user by userid with permissions
* <http://api.crew.dreamhack.se/2/users/self&event=10194> -- gets your own user with permissions
* <http://api.crew.dreamhack.se/2/users/relations/$userid> -- returns a tree of the users relations. If user is TA, the entire Team is returned as multidim array. If user is GA, the group the GA is in gets returned as multidim array, if the user is just user it returns the team name and self.
* <http://api.crew.dreamhack.se/2/users/phone/_max_> -- returns the usernames phonenr if they have one added.
