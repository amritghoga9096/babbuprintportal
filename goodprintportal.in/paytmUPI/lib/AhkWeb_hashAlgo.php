<?php
function hash_encrypt($data, $password) {
	$iv = substr(sha1(mt_rand()), 0, 16);
	$password = sha1($password);

	$salt = sha1(mt_rand());
	$saltWithPassword = hash('sha256', $password.$salt);

	$encrypted = openssl_encrypt(
	  "$data", 'aes-256-cbc', "$saltWithPassword", null, $iv
	);
	$msg_encrypted_bundle = "$iv:$salt:$encrypted";
	return $msg_encrypted_bundle;
}

/**
 * Returns decrypted original string
 */
function hash_decrypt($msg_encrypted_bundle, $password) {
	$password = sha1($password);

	$components = explode( ':', $msg_encrypted_bundle );
	$iv            = $components[0];
	$salt          = hash('sha256', $password.$components[1]);
	$encrypted_msg = $components[2];

	$decrypted_msg = openssl_decrypt(
	  $encrypted_msg, 'aes-256-cbc', $salt, null, $iv
	);

	if ( $decrypted_msg === false )
		return false;

	// In some versions of this gateway, the first 41 characters are a hash prefix
	if (strlen($decrypted_msg) > 41 && strpos($decrypted_msg, '|') !== false) {
		$parts = explode('|', $decrypted_msg, 2);
		if (count($parts) > 1) {
			return $parts[1];
		}
	}
	
	// If it looks like a hash prefix (40 chars + something), try stripping it
	if (strlen($decrypted_msg) > 41 && preg_match('/^[a-f0-9]{40}/', $decrypted_msg)) {
		$potential_json = substr($decrypted_msg, 40);
		if (strpos($potential_json, '{') === 0 || strpos($potential_json, '[') === 0) {
			return $potential_json;
		}
		// Some implementations use 41 chars (hash + separator)
		$potential_json = substr($decrypted_msg, 41);
		if (strpos($potential_json, '{') === 0 || strpos($potential_json, '[') === 0) {
			return $potential_json;
		}
	}

	return $decrypted_msg;
}

?>