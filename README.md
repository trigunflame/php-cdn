php-cdn
=======



`dynamic` `file` `caching` `pseudo` `cdn`
 
 
* cdn root path   : http://cdn.com/
* cdn example url : http://cdn.com/path/to/resource.css?d=12345
* maps the uri    : /path/to/resource.css?d=12345
* to the origin   : http://yoursite.com/path/to/resource.css?d=12345
* caches file to  : ./cache/[base64-encoded-uri].css
* returns local cached copy or issues 304 not modified