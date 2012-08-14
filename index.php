<?php

// php-cdn
// dynamic file caching pseudo cdn
/////////////////////////////////////////////////////////////////////////
// cdn root path   : http://cdn.com/
// cdn example url : http://cdn.com/path/to/resource.css?d=12345
// maps the uri    : /path/to/resource.css?d=12345
// to the origin   : http://yoursite.com/path/to/resource.css?d=12345
// caches file to  : ./cache/[base64-encoded-uri].css
// returns local cached copy or issues 304 not modified
/////////////////////////////////////////////////////////////////////////
// error_reporting(E_ERROR | E_PARSE);

// cache for N seconds (default 1 day)
$f_expires = 86400;

// the source that we intend to mirror
$f_origin = 'http://cdn.com';

// encode as filename-safe base64
$f_name = strtr(base64_encode($_SERVER['REQUEST_URI']), '+/=', '-_,');

// parse the file extension
$f_ext = strrchr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '.');

// assign the correct mime type
switch ($f_ext) {
	// images
	case '.gif'  : $f_type = 'image/gif';                break;
	case '.jpg'  : $f_type = 'image/jpeg';               break;
	case '.png'  : $f_type = 'image/png';                break;
	case '.ico'  : $f_type = 'image/x-icon';             break;
	// documents
	case '.js'   : $f_type = 'application/x-javascript'; break;
	case '.css'  : $f_type = 'text/css';                 break;
	case '.xml'  : $f_type = 'text/xml';                 break;
	case '.json' : $f_type = 'application/json';         break;
	// no match
	default      :
		// extension is not supported, issue *404*
		header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		header('Cache-Control: private');
		exit;
}

// construct usable file path
$f_path = "./cache/{$f_name}{$f_ext}";

// check the local cache
if (file_exists($f_path)) {
	// get last modified time
	$f_modified = filemtime($f_path);
	
	// validate the client cache
	if (isset(    $_SERVER['HTTP_IF_MODIFIED_SINCE']) && 
	   (strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) == $f_modified)
	) {
		// client has a valid cache, issue *304*
		header($_SERVER['SERVER_PROTOCOL'] . ' 304 Not Modified');
	} else {
		// send all requisite cache-me-please! headers
		header('Pragma: public');
		header("Cache-Control: max-age={$f_expires}");
		header("Content-type: {$f_type}");
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s \G\M\T', $f_modified));
		header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + $f_expires));
		
		// stream the file
		readfile($f_path);
	}
} else {
	// http *HEAD* request 
	// verify that the image exists
	$ch = curl_init();
	curl_setopt_array($ch, 	array(
		CURLOPT_URL            => $f_origin . $_SERVER['REQUEST_URI'],
		CURLOPT_TIMEOUT        => 15,
		CURLOPT_CONNECTTIMEOUT => 15,
		CURLOPT_FAILONERROR    => 1,
		CURLOPT_RETURNTRANSFER => 1,
		CURLOPT_BINARYTRANSFER => 1,
		CURLOPT_HEADER         => 0,
		CURLOPT_NOBODY         => 1,
		// CURLOPT_FOLLOWLOCATION => 1, 
	));
	
	// we have located the remote file
	if (curl_exec($ch) !== false) {
		$fp = fopen($f_path, 'a+b');
		if(flock($fp, LOCK_EX | LOCK_NB)) {
			// empty *possible* contents
			ftruncate($fp, 0);
			rewind($fp);

			// http *GET* request
			// and write directly to the file
			$ch2 = curl_init();
			curl_setopt_array($ch2, 	array(
				CURLOPT_URL            => $f_origin . $_SERVER['REQUEST_URI'],
				CURLOPT_TIMEOUT        => 15,
				CURLOPT_CONNECTTIMEOUT => 15,
				CURLOPT_FAILONERROR    => 1,
				CURLOPT_RETURNTRANSFER => 1,
				CURLOPT_BINARYTRANSFER => 1,
				CURLOPT_HEADER         => 0,
				CURLOPT_FILE           => $fp
				// CURLOPT_FOLLOWLOCATION => 1, 
			));
				
			// did the transfer complete?
			if (curl_exec($ch2) === false) {
				// something went wrong, null 
				// the file just in case >.>
				ftruncate($fp, 0); 
			}
				
			// 1) flush output to the file
			// 2) release the file lock
			// 3) release the curl socket
			fflush($fp);
			flock($fp, LOCK_UN);
			curl_close($ch2);
		}
				
		// close the file
		fclose($fp);
			
		// issue *302* for *this* request
		header('Location: ' . $f_origin . $_SERVER['REQUEST_URI']);
	} else {
		// the file doesn't exist, issue *404*
		header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
		header('Cache-Control: private');
	}
	
	// finished
	curl_close($ch);
}

?>